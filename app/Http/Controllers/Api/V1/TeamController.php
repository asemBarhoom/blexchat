<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Team\AcceptTeamInvitationRequest;
use App\Http\Requests\Api\Team\InviteTeammateRequest;
use App\Mail\TeamInvitationMail;
use App\Models\Conversation;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Team', properties: [
    new OA\Property(property: 'members', type: 'array', description: 'The owner first, then the teammates: who a conversation can be assigned to', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'role', type: 'string', enum: ['owner', 'agent'], description: 'agent: the inbox and contacts only'),
    ])),
    new OA\Property(property: 'invitations', type: 'array', description: 'Open invitations; the owner only, [] for a teammate', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
    ])),
    new OA\Property(property: 'seats', properties: [
        new OA\Property(property: 'used', type: 'integer', example: 2, description: 'The owner, the teammates and the open invitations'),
        new OA\Property(property: 'limit', type: 'integer', example: 3, description: 'The plan’s inbox teammates, the owner included'),
    ]),
    new OA\Property(property: 'assignment', type: 'boolean', description: 'The plan includes team roles and assignment, so conversations can be assigned'),
    new OA\Property(property: 'role', type: 'string', enum: ['owner', 'agent'], description: 'The signed-in user’s role'),
])]
class TeamController extends Controller
{
    #[OA\Get(
        path: '/api/v1/team',
        summary: 'Settings · Team: the team, open invitations and seats',
        description: 'A teammate (`role: agent`) signs in with their own account and works in the owner’s inbox and contacts; every other page answers 403 `code: owner_only` for them.',
        security: [['sanctum' => []]],
        tags: ['Team'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Team')])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        return $this->team($request->user());
    }

    #[OA\Post(
        path: '/api/v1/team/invitations',
        summary: 'Invite a teammate by email',
        description: 'Emails a link to the frontend’s `/team/join?token=…`, valid 7 days. Inviting the same email again sends a fresh link.',
        security: [['sanctum' => []]],
        tags: ['Team'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email'], properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Invited; the team as `GET /team`', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Team')])),
            new OA\Response(response: 403, description: '`code: seat_limit` when every seat is in use (show Upgrade)'),
            new OA\Response(response: 422, description: 'Validation error: not an email, your own, or already on the team', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function invite(InviteTeammateRequest $request): JsonResponse
    {
        $owner = $request->user();
        $email = Str::lower($request->validated('email'));
        $seats = $owner->seatsUsage();
        $isResend = $owner->teamInvitations()->where('email', $email)->where('expires_at', '>', now())->exists();

        if (! $isResend && $seats['used'] >= $seats['limit']) {
            return response()->json(['message' => __('team.seat_limit', ['limit' => $seats['limit']]), 'code' => 'seat_limit', 'seats' => $seats], 403);
        }

        [$invitation, $token] = TeamInvitation::issue($owner, $email);
        Mail::to($email)->send((new TeamInvitationMail($invitation, $token))->locale(app()->getLocale()));

        return $this->team($owner)->setStatusCode(201);
    }

    #[OA\Delete(
        path: '/api/v1/team/invitations/{id}',
        summary: 'Withdraw an invitation',
        security: [['sanctum' => []]],
        tags: ['Team'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Withdrawn; the team as `GET /team`'),
            new OA\Response(response: 404, description: 'Not one of your invitations'),
        ],
    )]
    public function withdraw(Request $request, TeamInvitation $invitation): JsonResponse
    {
        abort_unless($invitation->owner_id === $request->user()->id, 404);
        $invitation->delete();

        return $this->team($request->user());
    }

    #[OA\Post(
        path: '/api/v1/team/invitations/accept',
        summary: 'Join a team from the invitation link',
        description: 'The join page signs the person in (the usual email link or Google), then sends the token. They must be signed in with the invited email, and their account must have no channels or team of its own.',
        security: [['sanctum' => []]],
        tags: ['Team'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['token'], properties: [
            new OA\Property(property: 'token', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Joined; the team as `GET /team`'),
            new OA\Response(response: 403, description: '`code`: `other_email` (signed in with another email; `email` names the invited one) or `has_own_account`'),
            new OA\Response(response: 404, description: '`code: invitation_invalid`: expired, withdrawn or unknown'),
        ],
    )]
    public function accept(AcceptTeamInvitationRequest $request): JsonResponse
    {
        $user = $request->user();
        $invitation = TeamInvitation::findByToken($request->validated('token'));

        if ($invitation === null) {
            return response()->json(['message' => __('team.invitation_invalid'), 'code' => 'invitation_invalid'], 404);
        }

        if ($invitation->email !== Str::lower($user->email)) {
            return response()->json(['message' => __('team.invitation_other_email', ['email' => $invitation->email]), 'code' => 'other_email', 'email' => $invitation->email], 403);
        }

        if ($invitation->owner_id === $user->id || $user->isTeammate() || $user->channels()->exists() || $user->teammates()->exists()) {
            return response()->json(['message' => __('team.has_own_account'), 'code' => 'has_own_account'], 403);
        }

        DB::transaction(function () use ($user, $invitation): void {
            $user->forceFill(['team_owner_id' => $invitation->owner_id])->save();
            $invitation->delete();
        });

        return $this->team($user->refresh());
    }

    #[OA\Delete(
        path: '/api/v1/team/members/{id}',
        summary: 'Remove a teammate',
        description: 'They keep their sign-in but lose the inbox; the conversations assigned to them are unassigned.',
        security: [['sanctum' => []]],
        tags: ['Team'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Removed; the team as `GET /team`'),
            new OA\Response(response: 404, description: 'Not on your team'),
        ],
    )]
    public function remove(Request $request, User $member): JsonResponse
    {
        abort_unless($member->team_owner_id === $request->user()->id, 404);
        $this->leaveTeam($member);

        return $this->team($request->user());
    }

    #[OA\Post(
        path: '/api/v1/team/leave',
        summary: 'Leave the team (a teammate)',
        security: [['sanctum' => []]],
        tags: ['Team'],
        responses: [
            new OA\Response(response: 204, description: 'Left'),
            new OA\Response(response: 404, description: 'Not on a team'),
        ],
    )]
    public function leave(Request $request): JsonResponse
    {
        abort_unless($request->user()->isTeammate(), 404);
        $this->leaveTeam($request->user());

        return response()->json(null, 204);
    }

    private function leaveTeam(User $member): void
    {
        DB::transaction(function () use ($member): void {
            Conversation::whereIn('channel_id', $member->teamOwner->channels()->select('id'))->where('assigned_to', $member->id)->update(['assigned_to' => null]);
            $member->forceFill(['team_owner_id' => null])->save();
        });
    }

    private function team(User $user): JsonResponse
    {
        $owner = $user->account();
        $member = fn (User $person, string $role): array => ['id' => $person->id, 'name' => $person->name, 'email' => $person->email, 'role' => $role];

        return response()->json(['data' => [
            'members' => [$member($owner, 'owner'), ...$owner->teammates()->orderBy('id')->get()->map(fn (User $teammate): array => $member($teammate, 'agent'))],
            'invitations' => $user->isTeammate() ? [] : $owner->teamInvitations()->where('expires_at', '>', now())->orderBy('id')->get(['id', 'email', 'expires_at'])
                ->map(fn (TeamInvitation $invitation): array => ['id' => $invitation->id, 'email' => $invitation->email, 'expires_at' => $invitation->expires_at->toIso8601String()])
                ->all(),
            'seats' => $owner->seatsUsage(),
            'assignment' => (bool) $owner->currentPlan()->has_assignment,
            'role' => $user->isTeammate() ? 'agent' : 'owner',
        ]]);
    }
}

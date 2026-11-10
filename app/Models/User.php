<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'google_id', 'password', 'business_name', 'business_info', 'inbox_accepting_new', 'is_admin', 'stripe_customer_id'])]
#[Hidden(['password', 'remember_token', 'google_id', 'stripe_customer_id'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * A teammate joined without a workspace works in the owner's first.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->team_owner_id !== null && $user->workspace_id === null && ($owner = self::find($user->team_owner_id)) !== null) {
                $user->workspace_id = Workspace::defaultFor($owner)->id;
            }
        });
    }

    /**
     * The language the frontend last asked for, else the app default. Mail to the user goes out in it.
     */
    public function preferredLocale(): string
    {
        return $this->locale ?? config('app.locale');
    }

    /** The workspace picked for this request; see workspace(). */
    private ?Workspace $currentWorkspace = null;

    protected $attributes = [
        'inbox_accepting_new' => true,
    ];

    /**
     * Shop owners sign up through the API; only staff marked as admins may use the panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /**
     * The account for an address proven by an emailed link or Google, created on first sign-in.
     * Either proof verifies the email.
     *
     * @return array{0: self, 1: bool} The user, and whether the account was just created.
     */
    public static function signInWithEmail(string $email, ?string $name = null): array
    {
        $user = self::firstOrNew(['email' => Str::lower($email)]);
        $isNew = ! $user->exists;

        $user->name ??= $name;
        $user->email_verified_at ??= now();
        $user->save();

        return [$user, $isNew];
    }

    /**
     * The owner whose inbox and contacts this teammate works in.
     *
     * @return BelongsTo<User, $this>
     */
    public function teamOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_owner_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function teammates(): HasMany
    {
        return $this->hasMany(User::class, 'team_owner_id');
    }

    /**
     * @return HasMany<TeamInvitation, $this>
     */
    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class, 'owner_id');
    }

    /**
     * The businesses this owner runs (BE-11).
     *
     * @return HasMany<Workspace, $this>
     */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * The workspace a teammate was invited to.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function teamWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Picks the workspace this request works in: one of the owner's, by the X-Workspace header.
     * Returns false when the owner has no such workspace, or a teammate names another one.
     */
    public function useWorkspace(?string $workspaceId): bool
    {
        $workspace = match (true) {
            $this->isTeammate() => $this->teamWorkspace ?? Workspace::defaultFor($this->teamOwner),
            blank($workspaceId) => Workspace::defaultFor($this),
            default => $this->workspaces()->find($workspaceId),
        };

        if ($workspace === null || (filled($workspaceId) && (string) $workspace->id !== (string) $workspaceId)) {
            return false;
        }

        $this->currentWorkspace = $workspace;

        return true;
    }

    /**
     * The workspace this user works in now: the one picked for the request, else their first.
     */
    public function workspace(): Workspace
    {
        if ($this->currentWorkspace === null) {
            $this->useWorkspace(null);
        }

        return $this->currentWorkspace;
    }

    public function isTeammate(): bool
    {
        return $this->team_owner_id !== null;
    }

    /**
     * Whose data this user works with: the owner for a teammate, else themself.
     */
    public function account(): self
    {
        return $this->isTeammate() ? $this->teamOwner : $this;
    }

    /**
     * Inbox seats: the owner, the teammates and the invitations still open, against the plan's.
     *
     * @return array{used: int, limit: int}
     */
    public function seatsUsage(): array
    {
        $open = $this->teamInvitations()->where('expires_at', '>', now())->count();

        return ['used' => 1 + $this->teammates()->count() + $open, 'limit' => $this->currentPlan()->teammates_limit];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'survey_completed_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'inbox_accepting_new' => 'boolean',
        ];
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    public function automationFolders(): HasMany
    {
        return $this->hasMany(AutomationFolder::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * The tier in force today: the subscription's (Free again once a trial ends), or Free with none.
     */
    public function currentPlan(): Plan
    {
        return $this->subscription?->effectivePlan() ?? Plan::free();
    }

    /**
     * Why an automation on this channel cannot go live now, as the wizard's "Couldn't go live" notice
     * names it: the channel is no longer connected, or the plan's live automations are all in use.
     * Null when it can. `$automation` is the one going live, left out of the count.
     *
     * @return array{code: 'channel_disconnected'|'live_limit', message: string, channel?: string, limit?: int}|null
     */
    public function goLiveRefusal(Channel $channel, ?Automation $automation = null): ?array
    {
        if ($channel->status !== 'active') {
            $name = Contact::PLATFORMS[$channel->platform] ?? ucfirst($channel->platform);

            return ['code' => 'channel_disconnected', 'message' => __('automation.channel_disconnected', ['channel' => $name]), 'channel' => $channel->platform];
        }

        $limit = ($this->subscription?->effectivePlan() ?? Plan::firstWhere('slug', Plan::FREE_SLUG))?->live_automations_limit;
        $live = $this->automations()->where('active', true)->when($automation, fn ($query) => $query->whereKeyNot($automation->id))->count();

        if ($limit !== null && $live >= $limit) {
            return ['code' => 'live_limit', 'message' => trans_choice('automation.live_limit', $limit, ['count' => $limit]), 'limit' => $limit];
        }

        return null;
    }

    /**
     * The sidebar's contacts meter: everyone saved against the plan's limit. People who message
     * are always saved, so an account can be over its limit, for instance after a trial ends.
     *
     * @return array{used: int, limit: int, plan: string, over_limit: bool}
     */
    public function contactsUsage(): array
    {
        $used = $this->contacts()->count();
        $plan = $this->currentPlan();

        return ['used' => $used, 'limit' => $plan->contacts_limit, 'plan' => $plan->slug, 'over_limit' => $used > $plan->contacts_limit];
    }

    /**
     * Adding someone by hand stops at the limit.
     */
    public function canAddContact(): bool
    {
        $usage = $this->contactsUsage();

        return $usage['used'] < $usage['limit'];
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    /**
     * Whether choosing this plan starts a free trial: the plan has one and this account has not
     * had its one trial, or is trying this very plan now.
     */
    public function canStartTrialOn(Plan $plan): bool
    {
        if (! $plan->hasTrial()) {
            return false;
        }

        $current = $this->subscription()->first();

        return $current?->trial_used_at === null || ($current->onTrial() && $current->plan_id === $plan->id);
    }

    public function surveyAnswers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    /**
     * The first onboarding step not done yet, in the frontend's order: connect a channel (W05–W07),
     * the survey (W08), a plan (W09). `done` sends the owner on to the first automation or the app.
     */
    public function onboardingStep(): string
    {
        return match (true) {
            ! $this->channels()->exists() => 'channel',
            $this->survey_completed_at === null => 'survey',
            ! $this->subscription()->exists() => 'plan',
            default => 'done',
        };
    }
}

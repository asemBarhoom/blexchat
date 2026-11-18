<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\Channels\MetaSignedRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Meta's two required callbacks for App Review. Meta identifies the person by the `user_id` in the
 * signed request; a channel matches when its account id is that id (Instagram Login gives the
 * Instagram account id).
 */
class MetaDataDeletionController extends Controller
{
    public function __construct(private MetaSignedRequest $signedRequest) {}

    /**
     * The person removed the app in Meta: their channels stop working and lose their token.
     */
    public function deauthorize(Request $request): JsonResponse
    {
        $payload = $this->signedRequest->parse($request->input('signed_request'));
        abort_if($payload === null, 400, 'Invalid signed_request.');

        // Through the model, so the emptied token is stored encrypted like any other.
        $this->channels($payload)->get()->each->update(['status' => 'inactive', 'access_token' => '', 'token_expires_at' => null]);

        return response()->json(['status' => 'received']);
    }

    /**
     * The person asked Meta to delete their data: their channels go, with the conversations on them.
     * Meta shows the returned url and code to the person; deletion is done before the response.
     */
    public function dataDeletion(Request $request): JsonResponse
    {
        $payload = $this->signedRequest->parse($request->input('signed_request'));
        abort_if($payload === null, 400, 'Invalid signed_request.');

        $this->channels($payload)->get()->each->delete();

        $code = Str::lower(Str::random(16));

        return response()->json([
            'url' => rtrim(config('app.frontend_url'), '/').'/data-deletion?code='.$code,
            'confirmation_code' => $code,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Builder<Channel>
     */
    private function channels(array $payload): Builder
    {
        return Channel::whereIn('platform', ['instagram', 'messenger', 'whatsapp'])->where('account_id', (string) $payload['user_id']);
    }
}

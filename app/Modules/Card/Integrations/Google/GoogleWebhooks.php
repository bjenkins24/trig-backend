<?php

namespace App\Modules\Card\Integrations\Google;

use App\Models\User;
use App\Modules\User\UserRepository;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleWebhooks
{
    public const WEBHOOK_URL = '/webhooks/google-drive';

    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function watchFiles(User $user): void
    {
        $webhookId = Str::uuid();

        $oauthConnection = $this->userRepository->getOauthConnection($user, Google::INTEGRATION_KEY);
        // If we are already watching for changes then no need to hit endpoint again
        if ($oauthConnection->properties->webhook_id) {
            return;
        }

        try {
            $expiration = Carbon::now()->addSeconds(604800)->timestamp;
            Http::post('https://www.googleapis.com/drive/v3/changes/watch', [
                'id'              => $webhookId,
                'address'         => Config::get('app.url').self::WEBHOOK_URL,
                'type'            => 'web_hook',
                'expiration'      => $expiration * 1000,
            ]);
            $oauthConnection->properties['webhook_id'] = $webhookId;
            $oauthConnection->properties['webhook_expiration'] = $expiration;
            $oauthConnection->save();
        } catch (Exception $e) {
            Log::error('Unable to watch google drive for changes for user '.$user->id.': '.$e->getMessage());
        }
    }
}

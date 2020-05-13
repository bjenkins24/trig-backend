<?php

namespace Tests\Support\Traits;

use App\Models\User;
use App\Modules\OauthConnection\OauthConnectionRepository;

trait CreateOauthConnection
{
    public static $ACCESS_TOKEN = 'ya29.a0Ae4lvC1wU4oWWGgTXbw79vJVtjstCV1Hy2Di-dmYApdjQOQomWfg4w9OZpManqJvxD1VXwEiAAvxo_fQIQwb6fumSKKiO-ViYEHaJTsxWS8uXyHIoB_d6vLGL-IxAf9tW8VFWQCHeP3Im17PU029ZtDna3ssBK12y-w';
    public static $REFRESH_TOKEN = '1//0fhpEZ1LyYyXNCgYIARAAGA8SNwF-L9IrF4-hXziVN01TUS0Gb33Xdr5o6iFpS_rtJ6c1eEQiHmnov3vKfZIinJX2_pAXoZGvm70';

    /**
     * Create fake oauth connection for testing.
     *
     * @return void
     */
    private function createOauthConnection(User $user, int $expiresIn = 3600)
    {
        app(OauthConnectionRepository::class)->create(
            $user,
            'google',
            collect([
                'access_token'  => self::$ACCESS_TOKEN,
                'refresh_token' => self::$REFRESH_TOKEN,
                'expires_in'    => $expiresIn,
            ])
        );
    }
}

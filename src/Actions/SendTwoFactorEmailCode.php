<?php

namespace NickDeKruijk\Leap\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use NickDeKruijk\Leap\Mail\TwoFactorCodeMail;

class SendTwoFactorEmailCode
{
    /**
     * Generate a new email two factor code, store it and mail it to the user.
     */
    public function __invoke(Authenticatable $user): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put(
            self::cacheKey($user),
            $code,
            now()->addMinutes((int) config('leap.auth_2fa.email.expires', 15))
        );

        Mail::to($user->email)->send(new TwoFactorCodeMail($code));
    }

    /**
     * The cache key a pending email two factor code is stored under for the given user.
     */
    public static function cacheKey(Authenticatable $user): string
    {
        return 'leap:auth_2fa:email:'.$user->getAuthIdentifier();
    }
}

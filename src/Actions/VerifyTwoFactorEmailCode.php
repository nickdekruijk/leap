<?php

namespace NickDeKruijk\Leap\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

class VerifyTwoFactorEmailCode
{
    /**
     * Verify a submitted email two factor code and consume it on success.
     */
    public function __invoke(Authenticatable $user, ?string $code): bool
    {
        $code = trim((string) $code);

        if ($code === '') {
            return false;
        }

        $key = SendTwoFactorEmailCode::cacheKey($user);
        $stored = Cache::get($key);

        if ($stored === null || ! hash_equals((string) $stored, $code)) {
            return false;
        }

        Cache::forget($key);

        return true;
    }
}

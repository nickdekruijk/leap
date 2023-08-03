<?php

namespace NickDeKruijk\Leap\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Nette\Utils\Random;
use Illuminate\Mail\SentMessage;

class Auth2FAController extends Controller
{
    public function __construct()
    {
        Auth::shouldUse(config('leap.guard'));
    }

    /**
     * Generate a random string based on leap 2fa configuration.
     *
     * @return string
     */
    public static function generateCode(): string
    {
        return Random::generate(config('leap.auth_2fa.mail.code.length'), config('leap.auth_2fa.mail.code.charlist'));
    }

    /**
     * Send a 2FA code by mail
     *
     * @param string $code
     * @return SentMessage|null
     */
    public static function mailCode(string $code): ?SentMessage
    {
        $user = Auth::user();
        return Mail::send(config('leap.auth_2fa.mail.view'), ['user' => $user, 'code' => $code], function ($m) use ($user) {
            $m->from(config('leap.auth_2fa.mail.from.address'), config('leap.auth_2fa.mail.from.name'));
            $m->to($user->email, $user->name)->subject(trans(config('leap.auth_2fa.mail.subject')));
        });
    }

    /**
     * Prepare the validation by generating a code and sending it by mail if not expired.
     *
     * @return void
     */
    public static function prepareValidation()
    {
        if (config('leap.auth_2fa.method') == 'mail') {
            // Check if user has an unexpired code in session
            if (!session('leap.auth_2fa.expires') || session('leap.auth_2fa.expires')->isPast()) {
                // If the code has expired generate a new one and send it by mail
                $code = self::generateCode();
                session(['leap.auth_2fa.code' => $code]);
                session(['leap.auth_2fa.expires' => now()->addMinutes(config('leap.auth_2fa.mail.code.expires'))]);
                self::mailCode($code);
            }
        }
    }

    /**
     * Return the validation code from session
     *
     * @return string
     */
    public static function getCode(): string
    {
        return session('leap.auth_2fa.code');
    }

    /**
     * Check if 2FA is enabled and must be validated
     *
     * @return boolean
     */
    public static function mustValidate(): bool
    {
        return (config('leap.auth_2fa.method') && !session('leap.auth_2fa.validated'));
    }

    /**
     * Attempt to validate the code
     *
     * @param string $code
     * @return boolean
     */
    public static function attempt(string $code): bool
    {
        if ($code === Auth2FAController::getCode()) {
            request()->session()->regenerateToken();
            session()->forget('leap.auth_2fa.code');
            session()->forget('leap.auth_2fa.expires');
            session()->put('leap.auth_2fa.validated', true);
            return true;
        } else {
            session()->put('leap.auth_2fa.validated', false);
            return false;
        }
    }
}

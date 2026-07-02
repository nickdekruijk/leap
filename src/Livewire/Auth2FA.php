<?php

namespace NickDeKruijk\Leap\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Computed;
use Livewire\Component;
use NickDeKruijk\Leap\Actions\SendTwoFactorEmailCode;
use NickDeKruijk\Leap\Actions\VerifyTwoFactorEmailCode;
use NickDeKruijk\Leap\Controllers\LogoutController;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Traits\CanLog;

class Auth2FA extends Component
{
    use CanLog;
    use WithRateLimiting;

    public $code;

    public $message;

    protected function rules()
    {
        return ['code' => 'required'];
    }

    /**
     * The active two factor method ('totp' or 'email') for the current user.
     */
    #[Computed]
    public function method(): ?string
    {
        return Leap::twoFactorMethod(Auth::guard(config('leap.guard'))->user());
    }

    /**
     * Validate a TOTP/email code or a one-time recovery code for the given user.
     */
    private function hasValidCode($user, string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        // Try the code as a TOTP code from an authenticator app, or as an emailed code
        $verified = Leap::twoFactorMethod($user) === 'email'
            ? app(VerifyTwoFactorEmailCode::class)($user, $code)
            : app(TwoFactorAuthenticationProvider::class)->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code
            );

        if ($verified) {
            return true;
        }

        // Otherwise try it as a single-use recovery code (TOTP only, the
        // email method has no recovery codes of its own)
        if (! $user->two_factor_recovery_codes) {
            return false;
        }

        $recoveryCode = collect($user->recoveryCodes())->first(function ($stored) use ($code) {
            return hash_equals($stored, $code);
        });

        if ($recoveryCode) {
            $user->replaceRecoveryCode($recoveryCode);

            return true;
        }

        return false;
    }

    public function submit()
    {
        $this->message = null;
        $this->validate();

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->addError('code', trans('auth.throttle', ['seconds' => $exception->secondsUntilAvailable]));

            return;
        }

        $user = Auth::guard(config('leap.guard'))->user();

        if ($user && $this->hasValidCode($user, $this->code)) {
            session(['leap.auth_2fa.validated' => true]);
            session()->regenerateToken();
            $this->log('login-2fa');

            return $this->redirectIntended(route('leap.home'));
        }

        $this->log('login-2fa-failed');
        $this->addError('code', __('leap::auth.two_factor_invalid'));
    }

    public function logout()
    {
        $logout = new LogoutController;
        $logout();
    }

    public function resend(SendTwoFactorEmailCode $send)
    {
        try {
            $this->rateLimit(1, (int) config('leap.auth_2fa.email.resend_throttle', 60));
        } catch (TooManyRequestsException $exception) {
            $this->addError('code', trans('auth.throttle', ['seconds' => $exception->secondsUntilAvailable]));

            return;
        }

        $user = Auth::guard(config('leap.guard'))->user();
        $send($user);

        $this->message = __('leap::auth.two_factor_email_resent');
        $this->log('login-2fa-resend');
    }

    public function mount()
    {
        if (! Leap::mustValidateTwoFactor()) {
            return $this->redirectIntended(route('leap.home'));
        }

        // laravel/passkeys' confirm response redirects via redirect()->intended()
        // with no fallback of its own (unlike our own redirectIntended() calls,
        // which always pass route('leap.home')). Seed a sane fallback here so a
        // passkey confirmation from this page doesn't land on '/' instead of the
        // admin panel. Leaves a genuinely captured intended URL (e.g. someone
        // hit a deep link while logged out) untouched.
        if (! session()->has('url.intended')) {
            session(['url.intended' => route('leap.home')]);
        }

        $user = Auth::guard(config('leap.guard'))->user();

        if (Leap::twoFactorMethod($user) === 'email' && Cache::missing(SendTwoFactorEmailCode::cacheKey($user))) {
            app(SendTwoFactorEmailCode::class)($user);
        }
    }

    public function render()
    {
        return view('leap::livewire.auth2fa')->layout('leap::layouts.app');
    }
}

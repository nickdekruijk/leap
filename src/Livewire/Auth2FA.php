<?php

namespace NickDeKruijk\Leap\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Livewire\Component;
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
     * Validate a TOTP code or a one-time recovery code for the given user.
     */
    private function hasValidCode($user, string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        // Try the code as a TOTP code from an authenticator app
        if (app(TwoFactorAuthenticationProvider::class)->verify(
            Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
            $code
        )) {
            return true;
        }

        // Otherwise try it as a single-use recovery code
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
        $logout = new LogoutController();
        $logout();
    }

    public function mount()
    {
        if (! Leap::mustValidateTwoFactor()) {
            return $this->redirectIntended(route('leap.home'));
        }
    }

    public function render()
    {
        return view('leap::livewire.auth2fa')->layout('leap::layouts.app');
    }
}

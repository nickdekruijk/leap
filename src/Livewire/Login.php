<?php

namespace NickDeKruijk\Leap\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Passkey;
use Livewire\Attributes\Computed;
use Livewire\Component;
use NickDeKruijk\Leap\Traits\CanLog;

class Login extends Component
{
    use CanLog;
    use WithRateLimiting;

    public $email;

    public $password;

    public $remember;

    /**
     * Whether to offer passkey login at all.
     *
     * With no passkeys registered the button cannot work for anyone: the
     * browser opens an empty picker and the resulting NotAllowedError is
     * swallowed by passkeys.js, so the click does nothing and says nothing.
     * Registration lives behind the login (Profile), so hiding the button
     * until the first passkey exists locks nobody out.
     *
     * Deliberately global rather than per-account: keying this on the typed
     * email would let anyone probe which accounts exist and which have a
     * passkey.
     */
    #[Computed]
    public function offerPasskeyLogin(): bool
    {
        return config('leap.auth_passkeys.enabled') && Passkey::exists();
    }

    protected function rules()
    {
        $rules = [];
        foreach (config('leap.credentials') as $column) {
            if ($column == 'email') {
                $rules[$column] = 'required|email:rfc,spoof,strict,filter'; // ,dns
            } else {
                $rules[$column] = 'required';
            }
        }

        return $rules;
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function submit()
    {
        $this->validate();
        $credentials = [];
        foreach (config('leap.credentials') as $column) {
            $credentials[$column] = $this->$column;
        }
        try {
            $this->rateLimit(5);
            if (Auth::guard(config('leap.guard'))->attempt($credentials, $this->remember)) {
                // Require the two factor challenge to be passed again for this login
                session()->forget('leap.auth_2fa.validated');
                $this->log('login');

                return $this->redirectIntended(route('leap.home'));
            } else {
                $this->log('login-failed', [array_key_first($credentials) => $credentials[array_key_first($credentials)]]);
                $this->addError('password', trans('auth.failed'));
            }
        } catch (TooManyRequestsException $exception) {
            $this->log('login-throttle', ['seconds' => $exception->secondsUntilAvailable, array_key_first($credentials) => $credentials[array_key_first($credentials)]]);
            $this->addError('password', trans('auth.throttle', ['seconds' => $exception->secondsUntilAvailable]));
        }
    }

    public function mount()
    {
        if (Auth::guard(config('leap.guard'))->check()) {
            return $this->redirectIntended(route('leap.home'));
        }
    }

    public function render()
    {
        return view('leap::livewire.login')->layout('leap::layouts.app');
    }
}

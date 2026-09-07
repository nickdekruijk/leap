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

    /**
     * The login fields, keyed by column as listed in config('leap.credentials').
     * An array rather than fixed $email/$password properties, so a host that
     * authenticates on another column (username, say) can name it in config
     * and the form follows.
     */
    public array $credentials = [];

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
                $rules['credentials.'.$column] = 'required|email:rfc,spoof,strict,filter'; // ,dns
            } else {
                $rules['credentials.'.$column] = 'required';
            }
        }

        return $rules;
    }

    protected function validationAttributes()
    {
        $attributes = [];
        foreach (config('leap.credentials') as $column) {
            $attributes['credentials.'.$column] = __('leap::auth.'.$column);
        }

        return $attributes;
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
            $credentials[$column] = $this->credentials[$column] ?? null;
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
                $this->addError('credentials.password', trans('auth.failed'));
            }
        } catch (TooManyRequestsException $exception) {
            $this->log('login-throttle', ['seconds' => $exception->secondsUntilAvailable, array_key_first($credentials) => $credentials[array_key_first($credentials)]]);
            $this->addError('credentials.password', trans('auth.throttle', ['seconds' => $exception->secondsUntilAvailable]));
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

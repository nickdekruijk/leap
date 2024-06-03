<?php

namespace NickDeKruijk\Leap\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NickDeKruijk\Leap\Traits\CanLog;

class Login extends Component
{
    use CanLog;
    use WithRateLimiting;

    public $email;
    public $password;
    public $remember;

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

<?php

namespace NickDeKruijk\Leap\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    use WithRateLimiting;

    public $email;
    public $password;
    public $remember;

    public function __construct()
    {
        Auth::shouldUse(config('leap.guard'));
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
            if (Auth::attempt($credentials, $this->remember)) {
                return $this->redirectIntended(route('leap.home'));
            } else {
                $this->addError('password', trans('auth.failed'));
            }
        } catch (TooManyRequestsException $exception) {
            $this->addError('password', trans('auth.throttle', ['seconds' => $exception->secondsUntilAvailable]));
        }
    }

    public function mount()
    {
        if (Auth::check()) {
            return $this->redirectIntended(route('leap.home'));
        } else {
            session()->forget('leap.role');
        }
    }

    public function render()
    {
        return view('leap::livewire.login')->layout('leap::layouts.app');
    }
}

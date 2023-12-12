<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NickDeKruijk\Leap\Models\Role;

class Login extends Component
{
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
        if (Auth::attempt($credentials, $this->remember)) {
            return $this->redirectIntended();
        } else {
            $this->addError('password', trans('auth.failed'));
        }
    }

    private function redirectIntended()
    {
        request()->session()->regenerate();
        return redirect()->intended(route('leap.home'));
    }

    public function mount()
    {
        if (Auth::check()) {
            return $this->redirectIntended();
        } else {
            session()->forget('leap.role');
        }
    }

    public function render()
    {
        return view('leap::livewire.login')->layout('leap::layouts.app');
    }
}

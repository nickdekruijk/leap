<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public $email;
    public $password;

    public function __construct()
    {
        Auth::shouldUse(config('leap.guard'));
    }

    protected function rules()
    {
        $rules = [];
        foreach (config('leap.credentials') as $column) {
            if ($column == 'email') {
                $rules[$column] = 'required|email';
            } else {
                $rules[$column] = 'required';
            }
        }
        return $rules;
    }

    public function submit()
    {
        $this->validate();
        $credentials = [];
        foreach (config('leap.credentials') as $column) {
            $credentials[$column] = $this->$column;
        }
        if (Auth::attempt($credentials)) {
            return $this->redirectIntended();
        } else {
            $this->addError('login', trans('auth.failed'));
        }
    }

    private function redirectIntended()
    {
        request()->session()->regenerate();
        return redirect()->intended(route('leap.module'));
    }

    public function mount()
    {
        if (Auth::check()) {
            return $this->redirectIntended();
        }
    }

    public function render()
    {
        return view('leap::livewire.login')->layout('leap::layouts.app');
    }
}

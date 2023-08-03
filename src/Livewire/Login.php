<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NickDeKruijk\Leap\Controllers\LogoutController;
use NickDeKruijk\Leap\Models\Role;

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

        // Check if user has a role
        if (Auth::attempt($credentials)) {
            $roles = Auth::user()->belongsToMany(Role::class, config('leap.table_prefix') . 'role_user');
            if (config('leap.organizations')) {
                $role = $roles->whereNotNull('organization_id')->first();
            } else {
                $role = $roles->whereNull('organization_id')->first();
            }

            // If user has a role goto intended route, otherwise logout and try again
            if ($role) {
                return $this->redirectIntended();
            } else {
                $this->addError('email', 'User has no role.');
                Auth::logout();
            }
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

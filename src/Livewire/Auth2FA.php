<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NickDeKruijk\Leap\Controllers\Auth2FAController;
use NickDeKruijk\Leap\Controllers\LogoutController;

class Auth2FA extends Component
{
    public $code;
    public $message;

    public function __construct()
    {
        Auth::shouldUse(config('leap.guard'));
    }

    protected function rules()
    {
        $rules = [];
        $rules['code'] = 'required';
        return $rules;
    }

    public function submit()
    {
        $this->message = null;
        $this->validate();
        if (Auth2FAController::attempt($this->code)) {
            $this->redirectIntended();
        } else {
            $this->addError('code', trans('Invalid code'));
        }
    }

    private function redirectIntended()
    {
        request()->session()->regenerate();
        return redirect()->intended(route('leap.module'));
    }

    public function logout()
    {
        $logout = new LogoutController();
        $logout();
    }

    public function mount()
    {
        if (Auth2FAController::mustValidate()) {
            Auth2FAController::prepareValidation();
        } else {
            $this->redirectIntended();
        }
    }

    public function render()
    {
        return view('leap::livewire.auth2fa')->layout('leap::layouts.app');
    }
}

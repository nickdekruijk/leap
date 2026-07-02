<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Component;
use NickDeKruijk\Leap\Traits\CanLog;

class ResetPassword extends Component
{
    use CanLog;

    public $token;
    public $email;
    public $password;
    public $password_confirmation;

    protected function rules()
    {
        return [
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
        ];
    }

    public function submit()
    {
        $this->validate();

        $status = Password::broker(config('leap.password_broker'))->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user) {
                $user->forceFill([
                    'password' => bcrypt($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->log('password-reset', ['email' => $this->email]);
            session()->flash('leap.status', __('leap::auth.password_reset_success'));

            return $this->redirect(route('leap.login'));
        }

        $this->addError('email', __($status));
    }

    public function mount(?string $token = null)
    {
        $this->token = $token;
        $this->email = request('email');
    }

    public function render()
    {
        return view('leap::livewire.reset-password')->layout('leap::layouts.app');
    }
}

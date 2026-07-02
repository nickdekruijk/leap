<?php

namespace NickDeKruijk\Leap\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Livewire\Component;
use NickDeKruijk\Leap\Traits\CanLog;

class ForgotPassword extends Component
{
    use CanLog;
    use WithRateLimiting;

    public $email;
    public $status;

    protected function rules()
    {
        return ['email' => 'required|email'];
    }

    public function submit()
    {
        $this->validate();

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->addError('email', trans('auth.throttle', ['seconds' => $exception->secondsUntilAvailable]));

            return;
        }

        // Send the reset link. We always show a generic confirmation to avoid
        // leaking whether an email address exists.
        Password::broker(config('leap.password_broker'))->sendResetLink(['email' => $this->email]);

        $this->log('password-reset-request', ['email' => $this->email]);

        $this->status = __('leap::auth.password_reset_sent');
    }

    public function mount()
    {
        if (Auth::guard(config('leap.guard'))->check()) {
            return $this->redirectIntended(route('leap.home'));
        }
    }

    public function render()
    {
        return view('leap::livewire.forgot-password')->layout('leap::layouts.app');
    }
}

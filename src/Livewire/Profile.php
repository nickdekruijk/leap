<?php

namespace NickDeKruijk\Leap\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Computed;
use NickDeKruijk\Leap\Actions\SendTwoFactorEmailCode;
use NickDeKruijk\Leap\Actions\VerifyTwoFactorEmailCode;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Module;

class Profile extends Module
{
    use WithRateLimiting;

    public $component = 'leap.profile';

    public $icon = 'fas-user-circle';

    public $slug = 'profile';

    public $priority = 1000;

    public $data;

    public $user;

    public $confirmCode;

    public $showRecoveryCodes = false;

    protected $default_permissions = [
        'read' => true,
        'update' => true,
    ];

    public function mount()
    {
        $this->log('read');
        $this->user = Auth::user();
        $this->title = $this->user->name;
        $this->data['name'] = $this->user->name;
        $this->data['email'] = $this->user->email;
    }

    /**
     * Two factor authentication is fully enabled once confirmed.
     */
    #[Computed]
    public function twoFactorEnabled(): bool
    {
        return $this->user->two_factor_secret && $this->user->two_factor_confirmed_at;
    }

    /**
     * The user has generated a secret but not yet confirmed a code.
     */
    #[Computed]
    public function twoFactorEnrolling(): bool
    {
        return $this->user->two_factor_secret && ! $this->user->two_factor_confirmed_at;
    }

    #[Computed]
    public function qrCodeSvg(): string
    {
        return $this->user->twoFactorQrCodeSvg();
    }

    #[Computed]
    public function recoveryCodes(): array
    {
        return $this->user->recoveryCodes();
    }

    /**
     * Email two factor authentication is fully enabled once confirmed.
     */
    #[Computed]
    public function twoFactorEmailEnabled(): bool
    {
        return (bool) $this->user->two_factor_email_confirmed_at;
    }

    /**
     * A code has been emailed but not yet confirmed.
     */
    #[Computed]
    public function twoFactorEmailEnrolling(): bool
    {
        return ! $this->twoFactorEmailEnabled && Cache::has(SendTwoFactorEmailCode::cacheKey($this->user));
    }

    public function enableTwoFactor(EnableTwoFactorAuthentication $enable)
    {
        Leap::validatePermission('update');

        // Only one two factor method can be active at a time
        $this->disableTwoFactorEmail(silent: true);

        $enable($this->user);

        $this->log('two-factor-enable');
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirm)
    {
        Leap::validatePermission('update');

        try {
            $confirm($this->user, $this->confirmCode);
        } catch (ValidationException $e) {
            $this->addError('confirmCode', __('leap::auth.two_factor_invalid'));

            return;
        }

        $this->confirmCode = null;
        $this->showRecoveryCodes = true;
        $this->log('two-factor-confirm');
        $this->dispatch('toast', __('leap::auth.two_factor_enabled'))->to(Toasts::class);
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate)
    {
        Leap::validatePermission('update');

        $generate($this->user);

        $this->showRecoveryCodes = true;
        $this->log('two-factor-recovery-codes');
        $this->dispatch('toast', __('leap::auth.two_factor_recovery_regenerated'))->to(Toasts::class);
    }

    public function disableTwoFactor(DisableTwoFactorAuthentication $disable)
    {
        Leap::validatePermission('update');

        $disable($this->user);

        $this->confirmCode = null;
        $this->showRecoveryCodes = false;
        $this->log('two-factor-disable');
        $this->dispatch('toast', __('leap::auth.two_factor_disabled'))->to(Toasts::class);
    }

    public function enableTwoFactorEmail(SendTwoFactorEmailCode $send)
    {
        Leap::validatePermission('update');
        abort_unless(config('leap.auth_2fa.email.enabled', true), 403);

        // Only one two factor method can be active at a time
        app(DisableTwoFactorAuthentication::class)($this->user);

        $send($this->user);

        $this->log('two-factor-email-enable');
    }

    public function confirmTwoFactorEmail(VerifyTwoFactorEmailCode $verify)
    {
        Leap::validatePermission('update');

        if (! $verify($this->user, $this->confirmCode)) {
            $this->addError('confirmCode', __('leap::auth.two_factor_invalid'));

            return;
        }

        $this->user->forceFill(['two_factor_email_confirmed_at' => now()])->save();

        $this->confirmCode = null;
        $this->log('two-factor-email-confirm');
        $this->dispatch('toast', __('leap::auth.two_factor_enabled'))->to(Toasts::class);
    }

    public function resendTwoFactorEmail(SendTwoFactorEmailCode $send)
    {
        Leap::validatePermission('update');

        try {
            $this->rateLimit(1, (int) config('leap.auth_2fa.email.resend_throttle', 60));
        } catch (TooManyRequestsException $exception) {
            $this->addError('confirmCode', trans('auth.throttle', ['seconds' => $exception->secondsUntilAvailable]));

            return;
        }

        $send($this->user);

        $this->log('two-factor-email-resend');
        $this->dispatch('toast', __('leap::auth.two_factor_email_resent'))->to(Toasts::class);
    }

    public function disableTwoFactorEmail(bool $silent = false)
    {
        if (! $silent) {
            Leap::validatePermission('update');
        }

        Cache::forget(SendTwoFactorEmailCode::cacheKey($this->user));

        if ($this->user->two_factor_email_confirmed_at) {
            $this->user->forceFill(['two_factor_email_confirmed_at' => null])->save();
        }

        if ($silent) {
            return;
        }

        $this->confirmCode = null;
        $this->log('two-factor-email-disable');
        $this->dispatch('toast', __('leap::auth.two_factor_disabled'))->to(Toasts::class);
    }

    public function getTitle(): string
    {
        return Auth::user()->name;
    }

    public function rules()
    {
        return [
            'data.name' => 'required|min:3',
            'data.email' => 'required|email:rfc,spoof,strict,filter', // ,dns
            'data.password_current' => 'nullable|current_password:'.config('leap.guard').'|required_with:data.password_new',
            'data.password_new' => ['nullable', 'different:data.password_current', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
            'data.password_new_confirmation' => 'nullable|same:data.password_new|required_with:data.password_new',
        ];
    }

    public function validationAttributes()
    {
        $attributes = [];
        foreach ($this->rules() as $field => $rule) {
            $attributes[$field] = strtolower(__('leap::auth.'.explode('.', $field, 2)[1]));
        }

        return $attributes;
    }

    public function updated($field, $value)
    {
        $this->validateOnly($field);
    }

    public function submit()
    {
        Leap::validatePermission('update');

        // Run validation
        $validator = Validator::make(['data' => $this->data], $this->rules(), [], $this->validationAttributes());
        if ($validator->fails()) {
            // Show validation errors as toasts
            foreach ($validator->messages()->keys() as $fieldKey) {
                $this->dispatch('toast-error', $validator->messages()->first($fieldKey), $fieldKey)->to(Toasts::class);
            }
            // Show validation errors
            $validator->validate();
        } else {
            // Check if name is changed
            if ($this->user->name != $this->data['name']) {
                $this->user->name = $this->data['name'];
                $this->log('update', ['name' => $this->user->name]);
                $this->dispatch('toast', ucfirst($this->validationAttributes()['data.name']).' '.__('leap::resource.updated'))->to(Toasts::class);
                // Update title and navigation to reflect name change
                $this->title = $this->user->name;
                $this->dispatch('update-navigation')->to(Navigation::class);
            }

            // Check if password is changed
            if (isset($this->data['password_new'])) {
                $this->log('update', 'new password');
                $this->user->password = bcrypt($this->data['password_new']);
                $this->dispatch('toast', __('leap::auth.password').' '.__('leap::resource.updated'))->to(Toasts::class);
            }

            // Check if anything changed
            if ($this->user->isDirty()) {
                $this->user->save();
            } else {
                $this->dispatch('toast-alert', __('leap::resource.no_changes'))->to(Toasts::class);
            }
        }
    }

    public function render()
    {
        /** @disregard P1013 Undefined method intelephense error */
        return view('leap::livewire.profile')->layout('leap::layouts.app');
    }
}

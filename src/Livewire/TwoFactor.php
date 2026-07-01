<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Computed;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Module;

class TwoFactor extends Module
{
    public $component = 'leap.two-factor';
    public $icon = 'fas-shield-halved';
    public $slug = 'two-factor';
    public $priority = 1001;
    public $title = 'leap::auth.two_factor';

    public $confirmCode;
    public $showRecoveryCodes = false;

    protected $default_permissions = [
        'read' => true,
        'update' => true,
    ];

    private function user()
    {
        return Auth::guard(config('leap.guard'))->user();
    }

    /**
     * Two factor authentication is fully enabled once confirmed.
     */
    #[Computed]
    public function enabled(): bool
    {
        $user = $this->user();

        return $user->two_factor_secret && $user->two_factor_confirmed_at;
    }

    /**
     * The user has generated a secret but not yet confirmed a code.
     */
    #[Computed]
    public function enrolling(): bool
    {
        $user = $this->user();

        return $user->two_factor_secret && ! $user->two_factor_confirmed_at;
    }

    #[Computed]
    public function qrCodeSvg(): string
    {
        return $this->user()->twoFactorQrCodeSvg();
    }

    #[Computed]
    public function recoveryCodes(): array
    {
        return $this->user()->recoveryCodes();
    }

    public function enable(EnableTwoFactorAuthentication $enable)
    {
        Leap::validatePermission('update');

        $enable($this->user());

        $this->log('two-factor-enable');
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm)
    {
        Leap::validatePermission('update');

        try {
            $confirm($this->user(), $this->confirmCode);
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

        $generate($this->user());

        $this->showRecoveryCodes = true;
        $this->log('two-factor-recovery-codes');
        $this->dispatch('toast', __('leap::auth.two_factor_recovery_regenerated'))->to(Toasts::class);
    }

    public function disable(DisableTwoFactorAuthentication $disable)
    {
        Leap::validatePermission('update');

        $disable($this->user());

        $this->confirmCode = null;
        $this->showRecoveryCodes = false;
        $this->log('two-factor-disable');
        $this->dispatch('toast', __('leap::auth.two_factor_disabled'))->to(Toasts::class);
    }

    public function render()
    {
        /** @disregard P1013 Undefined method intelephense error */
        return view('leap::livewire.two-factor')->layout('leap::layouts.app');
    }
}

<main class="leap-main leap-profile">
    <header class="leap-header">
        <h2>{{ $title }}</h2>
    </header>
    <div class="leap-editor">
        <div class="leap-buttons" role="group">
            @can('leap::update')
                <x-leap::button svg-icon="far-save" wire:click="submit" label="leap::resource.save" wire:loading.delay.shorter.attr="disabled" class="primary" type="submit" />
            @endcan
            <x-leap::button svg-icon="fas-xmark" href="{{ route('leap.home') }}" label="leap::resource.cancel" />
        </div>
        <form class="leap-form" wire:submit="submit">
            <fieldset class="leap-fieldset">
                <h3>@lang('leap::auth.profile_edit')</h3>
                <x-leap::input wire:model.blur="data.name" name="data.name" label="{{ __('leap::auth.name') }}" autocomplete="name" />
                <x-leap::input wire:model.blur="data.email" name="data.email" label="{{ __('leap::auth.email') }}" type="email" disabled />
            </fieldset>
        </form>
        <form class="leap-form" wire:submit="submit">
            <fieldset class="leap-fieldset">
                <h3>@lang('leap::auth.update_password')</h3>
                <x-leap::input wire:model.blur="data.password_current" name="data.password_current" label="{{ __('leap::auth.password_current') }}" type="password" autocomplete="current-password" />
                <x-leap::input wire:model.blur="data.password_new" name="data.password_new" label="{{ __('leap::auth.password_new') }}" type="password" autocomplete="new-password" />
                <x-leap::input wire:model.blur="data.password_new_confirmation" name="data.password_new_confirmation" label="{{ __('leap::auth.password_new_confirmation') }}" type="password" autocomplete="new-password" />
            </fieldset>
        </form>

        <form class="leap-form" wire:submit="{{ $this->twoFactorEmailEnrolling ? 'confirmTwoFactorEmail' : 'confirmTwoFactor' }}">
            <fieldset class="leap-fieldset">
                <h3>@lang('leap::auth.two_factor')</h3>

                @if ($this->twoFactorEnabled)
                    <label class="leap-label">
                        <span class="leap-label">{{ __('leap::auth.two_factor_is_enabled') }}</span>
                    </label>

                    @if ($showRecoveryCodes)
                        <div class="leap-two-factor-recovery">
                            <h3>{{ __('leap::auth.two_factor_recovery_codes') }}</h3>
                            <label class="leap-label">
                                <span class="leap-label">{{ __('leap::auth.two_factor_recovery_hint') }}</span>
                            </label>
                            <ul class="leap-recovery-codes">
                                @foreach ($this->recoveryCodes as $code)
                                    <li><code>{{ $code }}</code></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="leap-fieldset-buttons">
                        <x-leap::button type="button" svg-icon="fas-rotate" wire:click="regenerateRecoveryCodes" label="leap::auth.two_factor_regenerate" />
                        <x-leap::button type="button" svg-icon="fas-ban" wire:click="disableTwoFactor" class="danger" label="leap::auth.two_factor_disable" />
                    </div>
                @elseif ($this->twoFactorEnrolling)
                    <label class="leap-label">
                        <span class="leap-label">{{ __('leap::auth.two_factor_setup_hint') }}</span>
                    </label>

                    <div class="leap-two-factor-qr">
                        {!! $this->qrCodeSvg !!}
                    </div>

                    <div class="leap-two-factor-recovery">
                        <h3>{{ __('leap::auth.two_factor_recovery_codes') }}</h3>
                        <label class="leap-label">
                            <span class="leap-label">{{ __('leap::auth.two_factor_recovery_hint') }}</span>
                        </label>
                        <ul class="leap-recovery-codes">
                            @foreach ($this->recoveryCodes as $code)
                                <li><code>{{ $code }}</code></li>
                            @endforeach
                        </ul>
                    </div>

                    <x-leap::input wire:model.blur="confirmCode" name="confirmCode" label="{{ __('leap::auth.verification_code') }}" autocomplete="one-time-code" autofocus />

                    <div class="leap-fieldset-buttons">
                        <x-leap::button type="submit" svg-icon="fas-check" class="primary" label="leap::auth.two_factor_confirm" />
                        <x-leap::button type="button" svg-icon="fas-xmark" wire:click="disableTwoFactor" label="leap::resource.cancel" />
                    </div>
                @elseif ($this->twoFactorEmailEnabled)
                    <label class="leap-label">
                        <span class="leap-label">{{ __('leap::auth.two_factor_email_is_enabled') }}</span>
                    </label>

                    <div class="leap-fieldset-buttons">
                        <x-leap::button type="button" svg-icon="fas-ban" wire:click="disableTwoFactorEmail" class="danger" label="leap::auth.two_factor_disable" />
                    </div>
                @elseif ($this->twoFactorEmailEnrolling)
                    <label class="leap-label">
                        <span class="leap-label">{{ __('leap::auth.two_factor_email_setup_hint', ['email' => $this->user->email]) }}</span>
                    </label>

                    <x-leap::input wire:model.blur="confirmCode" name="confirmCode" label="{{ __('leap::auth.verification_code') }}" autocomplete="one-time-code" autofocus />

                    <div class="leap-fieldset-buttons">
                        <x-leap::button type="submit" svg-icon="fas-check" class="primary" label="leap::auth.two_factor_confirm" />
                        <x-leap::button type="button" svg-icon="fas-rotate" wire:click="resendTwoFactorEmail" label="leap::auth.two_factor_email_resend" />
                        <x-leap::button type="button" svg-icon="fas-xmark" wire:click="disableTwoFactorEmail" label="leap::resource.cancel" />
                    </div>
                @else
                    <label class="leap-label">
                        <span class="leap-label">{{ __('leap::auth.two_factor_intro') }}</span>
                    </label>
                    <div class="leap-fieldset-buttons">
                        <x-leap::button type="button" svg-icon="fas-shield-halved" wire:click="enableTwoFactor" class="primary" label="leap::auth.two_factor_enable" />
                        @if (config('leap.auth_2fa.email.enabled', true))
                            <x-leap::button type="button" svg-icon="fas-envelope" wire:click="enableTwoFactorEmail" label="leap::auth.two_factor_email_enable" />
                        @endif
                    </div>
                @endif
            </fieldset>
        </form>

        @if (config('leap.auth_passkeys.enabled'))
            <form class="leap-form">
                <fieldset class="leap-fieldset">
                    <h3>@lang('leap::auth.passkeys')</h3>
                    <label class="leap-label">
                        <span class="leap-label">{{ __('leap::auth.passkeys_intro') }}</span>
                    </label>

                    @if ($this->passkeys->isNotEmpty())
                        <ul class="leap-passkeys">
                            @foreach ($this->passkeys as $passkey)
                                <li>
                                    <span>{{ $passkey->name }}</span>
                                    <span>{{ $passkey->last_used_at ? $passkey->last_used_at->diffForHumans() : __('leap::auth.passkey_never_used') }}</span>
                                    <x-leap::button type="button" svg-icon="fas-trash" class="danger" label="leap::auth.delete" onclick="if (confirm('{{ __('leap::auth.passkey_delete_confirm') }}')) leapPasskeyDelete({{ $passkey->id }})" />
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="leap-fieldset-buttons">
                        <x-leap::button type="button" svg-icon="fas-key" label="leap::auth.passkey_add" onclick="var name = prompt('{{ __('leap::auth.passkey_add_prompt') }}'); if (name) leapPasskeyRegister(name)" />
                    </div>
                </fieldset>
            </form>
        @endif
    </div>
</main>

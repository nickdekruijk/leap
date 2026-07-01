<main class="leap-main leap-two-factor">
    <header class="leap-header">
        <h2>{{ __('leap::auth.two_factor') }}</h2>
    </header>
    <article class="leap-editor">
        @if ($this->enabled)
            <p class="leap-two-factor-status leap-two-factor-enabled">
                {{ __('leap::auth.two_factor_is_enabled') }}
            </p>

            @if ($showRecoveryCodes)
                <div class="leap-two-factor-recovery">
                    <h3>{{ __('leap::auth.two_factor_recovery_codes') }}</h3>
                    <p>{{ __('leap::auth.two_factor_recovery_hint') }}</p>
                    <ul class="leap-recovery-codes">
                        @foreach ($this->recoveryCodes as $code)
                            <li><code>{{ $code }}</code></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="leap-buttons" role="group">
                <x-leap::button svg-icon="fas-rotate" wire:click="regenerateRecoveryCodes" label="leap::auth.two_factor_regenerate" />
                <x-leap::button svg-icon="fas-shield-xmark" wire:click="disable" class="danger" label="leap::auth.two_factor_disable" />
            </div>
        @elseif ($this->enrolling)
            <p>{{ __('leap::auth.two_factor_setup_hint') }}</p>

            <div class="leap-two-factor-qr">
                {!! $this->qrCodeSvg !!}
            </div>

            <div class="leap-two-factor-recovery">
                <h3>{{ __('leap::auth.two_factor_recovery_codes') }}</h3>
                <p>{{ __('leap::auth.two_factor_recovery_hint') }}</p>
                <ul class="leap-recovery-codes">
                    @foreach ($this->recoveryCodes as $code)
                        <li><code>{{ $code }}</code></li>
                    @endforeach
                </ul>
            </div>

            <form class="leap-form" wire:submit="confirm">
                <fieldset class="leap-fieldset">
                    <x-leap::input wire:model.blur="confirmCode" name="confirmCode" label="{{ __('leap::auth.verification_code') }}" autocomplete="one-time-code" autofocus />
                </fieldset>
                <div class="leap-buttons" role="group">
                    <x-leap::button type="submit" svg-icon="fas-check" class="primary" label="leap::auth.two_factor_confirm" />
                    <x-leap::button svg-icon="fas-xmark" wire:click="disable" label="leap::resource.cancel" />
                </div>
            </form>
        @else
            <p>{{ __('leap::auth.two_factor_intro') }}</p>
            <div class="leap-buttons" role="group">
                <x-leap::button svg-icon="fas-shield-halved" wire:click="enable" class="primary" label="leap::auth.two_factor_enable" />
            </div>
        @endif
    </article>
</main>

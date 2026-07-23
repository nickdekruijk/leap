@props(['class' => '', 'status' => null, 'aside' => null])

{{-- Shared scaffold for the login/forgot/reset/2FA screens: the centered dialog,
     the logo and an optional status message. The form body goes in the slot. --}}
<main class="leap-login {{ $class }}">
    <dialog class="leap-login-dialog" open>
        <div>
            @include('leap::logo')

            @if ($status)
                <div class="form-message">
                    {{ $status }}
                </div>
            @endif

            {{ $slot }}
        </div>
        {{ $aside }}
    </dialog>
</main>

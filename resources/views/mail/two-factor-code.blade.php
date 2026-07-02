<p>{{ __('leap::auth.two_factor_email_body') }}</p>

<p style="font-size: 1.5em; font-weight: bold; letter-spacing: 0.1em;">{{ $code }}</p>

<p>{{ __('leap::auth.two_factor_email_expires_hint', ['minutes' => config('leap.auth_2fa.email.expires', 15)]) }}</p>

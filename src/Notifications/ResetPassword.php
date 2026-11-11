<?php

namespace NickDeKruijk\Leap\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The mail of Leap's forgot password flow: Laravel's own reset mail, with its text and
 * translations, but linking to Leap's reset page (leap.password.reset). Laravel's
 * notification builds the link with route('password.reset'), which a site with only
 * Leap does not have.
 *
 * The callbacks are declared again so they belong to this class: a host that customises
 * the reset mail of its own frontend with ResetPassword::createUrlUsing() or
 * toMailUsing() does not change this one, and the other way round.
 */
class ResetPassword extends BaseResetPassword
{
    /**
     * @var (\Closure(mixed, string): string)|null
     */
    public static $createUrlCallback;

    /**
     * @var (\Closure(mixed, string): MailMessage)|null
     */
    public static $toMailCallback;

    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return route('leap.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}

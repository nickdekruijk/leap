<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Support\Facades\Context;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Log;

trait CanLog
{
    /**
     * Add a log entry to the logs table and return the new log model
     * 
     * Also checks if the action is allowed to be logged and if the ip address should be anonymized
     *
     * @param string $action Action to log, e.g. login, create, update, delete
     * @param array|string $context Context of the action, e.g. email address when trying to login
     * @return Log|null
     */
    public function log(string $action, array|string $context = null): ?Log
    {
        $module = get_called_class();

        // If context is a string convert it to an array
        if (is_string($context)) {
            $context = [$context];
        }

        if (Context::get('leap.module') !== $module) {
            $context['module'] = $module;
            $module = Context::get('leap.module');
        }

        if (!in_array($action, config('leap.logging.skip_actions')) && !in_array($module, config('leap.logging.skip_modules'))) {
            // Anonymize IP address if needed
            $ip = config('leap.logging.ip_address_anonymized') ? preg_replace(['/\.\d*$/', '/[\da-f]*:[\da-f]*$/'], ['.xxx', 'xxxx:xxxx'], request()->ip()) : request()->ip();

            // Create log entry and return model instance
            return Log::create([
                'ip' => config('leap.logging.ip_address') ? $ip : null,
                'user_agent' => config('leap.logging.user_agent') ? request()->userAgent() : null,
                'module' => $module,
                'action' => $action,
                'context' => $context,
                'user_id' => auth()->id(),
            ]);
        } else {
            return null;
        }
    }
}

<?php

namespace NickDeKruijk\Leap\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the addresses in leap.allowed_ips may reach the panel, login screen
 * included. An empty list means no restriction. Answers 404 rather than 403,
 * the same way a module a user may not read does: the panel's existence
 * stays hidden from everyone else.
 */
class RestrictIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_values(array_filter((array) config('leap.allowed_ips')));

        if ($allowed && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(404);
        }

        return $next($request);
    }
}

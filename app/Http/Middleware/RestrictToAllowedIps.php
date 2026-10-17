<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional panel IP allow-list (FR-007b). Off when PANEL_ALLOWED_IPS is empty.
 */
class RestrictToAllowedIps
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $allowed */
        $allowed = config('vfilter.allowed_ips', []);

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(Response::HTTP_FORBIDDEN, __('panel.errors.ip_not_allowed'));
        }

        return $next($request);
    }
}

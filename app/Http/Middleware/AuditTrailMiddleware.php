<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditTrailMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldAudit($request, $response)) {
            return $response;
        }

        AuditLogger::requestEvent('request', $request->user(), [
            'route_name' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'request' => $request->except([
                'password',
                'password_confirmation',
                '_token',
                '_method',
            ]),
        ]);

        return $response;
    }

    private function shouldAudit(Request $request, Response $response): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if ($request->routeIs('login.store', 'logout')) {
            return false;
        }

        if ($request->routeIs('admin.audits.*')) {
            return false;
        }

        return $response->getStatusCode() < 500;
    }
}

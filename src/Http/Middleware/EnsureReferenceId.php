<?php

namespace Lunnar\AuditLogging\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnsureReferenceId
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->headers->has('X-Lunnar-Reference-Id')) {
            $request->headers->set('X-Lunnar-Reference-Id', (string) Str::uuid());
        }

        return $next($request);
    }
}

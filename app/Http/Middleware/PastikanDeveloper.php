<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel developer (/dev) hanya untuk akun ber-role developer.
 */
class PastikanDeveloper
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isDeveloper(), 403);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rute milik fitur per klien (config/client.php) dianggap tidak ada selama fiturnya mati.
 */
class PastikanFiturAktif
{
    public function handle(Request $request, Closure $next, string $fitur): Response
    {
        abort_unless(Feature::aktif($fitur), 404);

        return $next($request);
    }
}

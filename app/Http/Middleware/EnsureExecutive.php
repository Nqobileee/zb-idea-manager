<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Executive pages are checked on the server, not just hidden from the menu. */
class EnsureExecutive
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403, 'Executives only.');

        return $next($request);
    }
}

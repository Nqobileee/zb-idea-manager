<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The super admin portal needs its own sign-in. Off (404) until credentials are set in the environment. */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('ideas.super_admin.email') && config('ideas.super_admin.password'), 404);
        if (! $request->session()->get('super_admin')) {
            return redirect()->route('super.login');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/** Anyone signed in with the generic password is sent to the change-password page until they pick their own. */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && Schema::hasColumn('users', 'must_change_password') && $user->must_change_password) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Only the Zernio chatbot may call /api/zernio/*: it sends the shared secret in X-Zernio-Secret. */
class ZernioSecret
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('ideas.zernio_secret');
        if ($secret === '') {
            return response()->json(['ok' => false, 'message' => 'The Idea Manager is not connected yet.'], 503);
        }
        if (! hash_equals($secret, (string) $request->header('X-Zernio-Secret'))) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}

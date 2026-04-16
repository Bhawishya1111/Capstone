<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the Python scanner worker when it POSTs progress to Laravel.
 * Token must match SCANNER_TOKEN in .env (never commit real secrets).
 */
class VerifyScannerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('wave.scanner_token');
        $provided = $request->header('X-Scanner-Token', '');

        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $provided)) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid scanner token.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\AdminSetup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends every request to the first-time setup page while no administrator account exists.
 */
class RedirectToAdminSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('setup', 'setup/*', 'up') || ! AdminSetup::required()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'No administrator account exists yet. Create the first administrator account to continue.',
                'setup_url' => url('/setup'),
            ], 409);
        }

        return redirect('/setup');
    }
}

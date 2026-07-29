<?php

namespace App\Http\Middleware;

use App\Services\PagePermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffOrPageAccess
{
    /**
     * Allow admin/supply officers always, or department users with any listed page key.
     */
    public function handle(Request $request, Closure $next, string ...$pageKeys): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized.');
        }

        if ($pageKeys === []) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to perform this action.');
        }

        if (! PagePermissionService::userCanAccessAnyPage($user, $pageKeys)) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}

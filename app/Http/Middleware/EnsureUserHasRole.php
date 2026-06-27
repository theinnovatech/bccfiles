<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized.');
        }

        $allowed = collect($roles)->map(fn (string $role) => UserRole::from($role));

        if (! $allowed->contains($user->role)) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/');
        // Revision 12: reject writes while a database restore is running.
        $middleware->web(append: [
            \App\Http\Middleware\BlockWritesDuringRestore::class,
            // First-time setup: no administrator account yet -> /setup.
            \App\Http\Middleware\RedirectToAdminSetup::class,
        ]);
        // Check for first-time setup before the sign-in check, so every page goes to /setup.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\RedirectToAdminSetup::class,
        );
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'staff_or_page' => \App\Http\Middleware\EnsureStaffOrPageAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

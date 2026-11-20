<?php

namespace App\Http\Middleware;

use App\Services\DatabaseRestoreService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revision 12: while a restore is running (or after a failed restore that needs recovery),
 * every write request is rejected so no inventory transaction can interleave with the restore.
 */
class BlockWritesDuringRestore
{
    /** Paths that stay writable: authentication and the restore endpoints themselves. */
    private const ALLOWED = [
        'login',
        'logout',
        'backups/*/restore',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! DatabaseRestoreService::isLocked()) {
            return $next($request);
        }

        foreach (self::ALLOWED as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        $state = DatabaseRestoreService::lockState() ?? [];
        $message = ($state['state'] ?? 'running') === 'failed'
            ? 'A database restore failed and needs recovery. Inventory changes are blocked until an administrator restores the recovery backup '.($state['recovery_backup'] ?? '').' from Backup Files.'
            : 'A database restore is in progress. Inventory changes are temporarily blocked; please try again in a moment.';

        return response()->json([
            'message' => $message,
            'restore' => $state,
        ], 423);
    }
}

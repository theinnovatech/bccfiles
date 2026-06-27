<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    public function log(User $user, string $action, string $module, string $description): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => Request::ip(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\SoftDeleteRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeletedDataController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->string('type')->toString();
        $entities = SoftDeleteRegistry::entities();
        $records = [];

        $targets = $type !== '' && isset($entities[$type])
            ? [$type => $entities[$type]]
            : $entities;

        foreach ($targets as $entityType => $config) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $config['model'];

            $modelClass::onlyTrashed()
                ->orderByDesc('deleted_at')
                ->get()
                ->each(function (Model $model) use (&$records, $entityType, $config) {
                    $records[] = [
                        'id' => $model->getKey(),
                        'type' => $entityType,
                        'module' => $config['label'],
                        'name' => SoftDeleteRegistry::displayName($model, $config),
                        'deleted_at' => $model->deleted_at,
                    ];
                });
        }

        usort($records, fn (array $a, array $b) => strcmp((string) $b['deleted_at'], (string) $a['deleted_at']));

        return response()->json([
            'data' => $records,
            'types' => collect($entities)->map(fn (array $config, string $key) => [
                'value' => $key,
                'label' => $config['label'],
            ])->values(),
        ]);
    }

    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->resolveTrashedModel($type, $id);
        $config = SoftDeleteRegistry::resolve($type);
        $name = SoftDeleteRegistry::displayName($model, $config);

        DB::transaction(function () use ($model, $type) {
            $model->restore();

            if ($type === 'employees' && $model instanceof Employee) {
                $linkedUser = User::onlyTrashed()
                    ->where('employee_id', $model->id)
                    ->when($model->user_id, fn ($query) => $query->orWhere('id', $model->user_id))
                    ->first();

                $linkedUser?->restore();
            }
        });

        $this->activityLogService->log(
            $request->user(),
            'Restored',
            'Deleted Data',
            "Restored {$config['label']} record {$name}"
        );

        return response()->json(['message' => 'Record restored successfully.']);
    }

    public function forceDestroy(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->resolveTrashedModel($type, $id);
        $config = SoftDeleteRegistry::resolve($type);
        $name = SoftDeleteRegistry::displayName($model, $config);

        DB::transaction(function () use ($model, $type) {
            if ($type === 'employees' && $model instanceof Employee) {
                $linkedUser = User::onlyTrashed()
                    ->where('employee_id', $model->id)
                    ->when($model->user_id, fn ($query) => $query->orWhere('id', $model->user_id))
                    ->first();

                $linkedUser?->forceDelete();
            }

            $model->forceDelete();
        });

        $this->activityLogService->log(
            $request->user(),
            'Permanently Deleted',
            'Deleted Data',
            "Permanently deleted {$config['label']} record {$name}"
        );

        return response()->json(['message' => 'Record permanently deleted.']);
    }

    private function resolveTrashedModel(string $type, int $id): Model
    {
        $config = SoftDeleteRegistry::resolve($type);

        if (! $config) {
            abort(404, 'Unknown record type.');
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return $modelClass::onlyTrashed()->findOrFail($id);
    }
}

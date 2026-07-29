<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\ItemReturn;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ItemReturnController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        return response()->json(
            ItemReturn::query()
                ->with(['equipment.category', 'department', 'borrower', 'returner'])
                ->whereNotNull('equipment_id')
                ->orderByDesc('date_returned')
                ->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'exists:equipments,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'borrower_employee_id' => ['nullable', 'exists:employees,id'],
            'borrower_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
        ]);

        $borrowerEmployeeId = $data['borrower_employee_id'] ?? null;
        $borrowerName = trim((string) ($data['borrower_name'] ?? ''));

        if (! $borrowerEmployeeId && $borrowerName === '') {
            return response()->json([
                'message' => 'Please select or type the name of the person returning the equipment.',
            ], 422);
        }

        try {
            $return = DB::transaction(function () use ($data, $request, $borrowerEmployeeId, $borrowerName) {
                $equipment = Equipment::query()->lockForUpdate()->findOrFail($data['equipment_id']);

                if ($data['quantity'] < 1) {
                    throw new InvalidArgumentException('Return quantity must be at least 1.');
                }

                $equipment->increment('quantity', $data['quantity']);

                return ItemReturn::create([
                    'equipment_id' => $equipment->id,
                    'department_id' => $data['department_id'],
                    'borrower_employee_id' => $borrowerEmployeeId,
                    'borrower_name' => $borrowerEmployeeId ? null : $borrowerName,
                    'quantity' => $data['quantity'],
                    'reason' => $data['reason'] ?? null,
                    'returned_by' => $request->user()->id,
                    'date_returned' => now(),
                ]);
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogService->log(
            $request->user(),
            'Returned',
            'Returns',
            "Recorded return of {$return->quantity} unit(s) of {$return->equipment->name}"
        );

        return response()->json(
            $return->load(['equipment.category', 'department', 'borrower', 'returner']),
            201
        );
    }
}

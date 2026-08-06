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
                ->where(function ($query) {
                    $query->whereNotNull('equipment_id')
                        ->orWhereNotNull('custom_equipment_name');
                })
                ->orderByDesc('date_returned')
                ->paginate(20)
        );
    }

    public function returnedEquipments(): JsonResponse
    {
        return response()->json(
            ItemReturn::query()
                ->with(['equipment.category', 'department', 'borrower', 'returner'])
                ->where(function ($query) {
                    $query->whereNotNull('equipment_id')
                        ->orWhereNotNull('custom_equipment_name');
                })
                ->orderByDesc('date_returned')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:255'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'custom_equipment_name' => ['nullable', 'string', 'max:255'],
            'custom_property_number' => ['nullable', 'string', 'max:255'],
            'custom_inventory_number' => ['nullable', 'string', 'max:255'],
            'custom_equipment_type' => ['nullable', 'string', 'max:255'],
            'custom_equipment_category' => ['nullable', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'borrower_employee_id' => ['nullable', 'exists:employees,id'],
            'borrower_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
            'date_returned' => ['nullable', 'date'],
        ]);

        $borrowerEmployeeId = $data['borrower_employee_id'] ?? null;
        $borrowerName = trim((string) ($data['borrower_name'] ?? ''));
        $equipmentId = $data['equipment_id'] ?? null;
        $customName = trim((string) ($data['custom_equipment_name'] ?? ''));

        if (! $equipmentId && $customName === '') {
            return response()->json([
                'message' => 'Please select equipment from Supply Master or enter a custom equipment name.',
            ], 422);
        }

        if (! $borrowerEmployeeId && $borrowerName === '') {
            return response()->json([
                'message' => 'Please select or type the name of the person returning the equipment.',
            ], 422);
        }

        try {
            $return = DB::transaction(function () use ($data, $request, $borrowerEmployeeId, $borrowerName, $equipmentId, $customName) {
                if ($data['quantity'] < 1) {
                    throw new InvalidArgumentException('Return quantity must be at least 1.');
                }

                if ($equipmentId) {
                    Equipment::query()->lockForUpdate()->findOrFail($equipmentId);
                }

                // Returned equipments are considered used stock — they are
                // NOT added back to Supply Master and the source equipment's
                // quantity is left unchanged. The return record itself serves
                // as the "returned equipments" log.
                //
                // When no supply-master equipment is picked, the custom_*
                // fields describe the historical/past equipment being logged.
                $referenceNumber = trim((string) ($data['reference_number'] ?? ''));

                return ItemReturn::create([
                    'reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
                    'equipment_id' => $equipmentId,
                    'custom_equipment_name' => $equipmentId ? null : $customName,
                    'custom_property_number' => $equipmentId ? null : ($data['custom_property_number'] ?? null),
                    'custom_inventory_number' => $equipmentId ? null : ($data['custom_inventory_number'] ?? null),
                    'custom_equipment_type' => $equipmentId ? null : ($data['custom_equipment_type'] ?? null),
                    'custom_equipment_category' => $equipmentId ? null : ($data['custom_equipment_category'] ?? null),
                    'department_id' => $data['department_id'],
                    'borrower_employee_id' => $borrowerEmployeeId,
                    'borrower_name' => $borrowerEmployeeId ? null : $borrowerName,
                    'quantity' => $data['quantity'],
                    'reason' => $data['reason'] ?? null,
                    'returned_by' => $request->user()->id,
                    'date_returned' => ! empty($data['date_returned']) ? $data['date_returned'] : now(),
                ]);
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $return->load(['equipment.category', 'department', 'borrower', 'returner']);

        $equipmentLabel = $return->equipment?->name ?? $return->custom_equipment_name ?? 'custom equipment';

        $this->activityLogService->log(
            $request->user(),
            'Returned',
            'Returns',
            "Recorded return of {$return->quantity} unit(s) of {$equipmentLabel}"
        );

        return response()->json($return, 201);
    }
}

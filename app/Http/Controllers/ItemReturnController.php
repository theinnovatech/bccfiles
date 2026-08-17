<?php

namespace App\Http\Controllers;

use App\Enums\ReturnCondition;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Services\ActivityLogService;
use App\Support\ReferenceNumberGenerator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            'property_number' => ['nullable', 'string', 'max:255'],
            'inventory_number' => ['nullable', 'string', 'max:255'],
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
            'condition' => ['required', Rule::enum(ReturnCondition::class)],
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

                $condition = ReturnCondition::from($data['condition']);
                $dateReturned = ! empty($data['date_returned'])
                    ? Carbon::parse($data['date_returned'])
                    : now();
                $restocked = false;

                if ($equipmentId) {
                    $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipmentId);
                    $this->syncEquipmentNumbers($equipment, $data);

                    if (
                        $condition === ReturnCondition::Good
                        && ! $equipment->hasReachedLifespan($dateReturned)
                    ) {
                        $equipment->increment('quantity', $data['quantity']);
                        $restocked = true;
                    }
                }

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
                    'condition' => $condition,
                    'restocked' => $restocked,
                    'returned_by' => $request->user()->id,
                    'date_returned' => $dateReturned,
                ]);
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $return->load(['equipment.category', 'department', 'borrower', 'returner']);

        $equipmentLabel = $return->equipment?->name ?? $return->custom_equipment_name ?? 'custom equipment';
        $restockNote = $return->restocked ? ' and restocked for re-issue' : '';

        $this->activityLogService->log(
            $request->user(),
            'Returned',
            'Returns',
            "Recorded return of {$return->quantity} unit(s) of {$equipmentLabel}{$restockNote}"
        );

        return response()->json($return, 201);
    }

    private function syncEquipmentNumbers(Equipment $equipment, array $data): void
    {
        $payload = [];
        $propertyNumber = trim((string) ($data['property_number'] ?? ''));
        $inventoryNumber = trim((string) ($data['inventory_number'] ?? ''));

        if ($propertyNumber !== '') {
            $taken = Equipment::query()
                ->where('property_number', $propertyNumber)
                ->where('id', '!=', $equipment->id)
                ->exists();

            if ($taken) {
                throw new InvalidArgumentException("Property number {$propertyNumber} is already used by another equipment.");
            }

            if ($equipment->property_number !== $propertyNumber) {
                $payload['property_number'] = $propertyNumber;
            }
        }

        $resolvedInventory = $inventoryNumber !== ''
            ? $inventoryNumber
            : ($equipment->inventory_number ?: ReferenceNumberGenerator::forInventory());

        $this->assertInventoryNumberAvailable($resolvedInventory, $equipment->id);

        if ($equipment->inventory_number !== $resolvedInventory) {
            $payload['inventory_number'] = $resolvedInventory;
        }

        if ($payload !== []) {
            $equipment->update($payload);
        }
    }

    private function assertInventoryNumberAvailable(string $number, int $ignoreEquipmentId): void
    {
        $itemTaken = Item::query()
            ->where('inventory_number', $number)
            ->exists();

        $equipmentTaken = Equipment::query()
            ->where('inventory_number', $number)
            ->where('id', '!=', $ignoreEquipmentId)
            ->exists();

        if ($itemTaken || $equipmentTaken) {
            throw new InvalidArgumentException("Inventory number {$number} is already in use.");
        }
    }
}

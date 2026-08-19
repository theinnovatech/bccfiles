<?php

namespace App\Http\Controllers;

use App\Enums\IssuanceType;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Issuance;
use App\Models\IssuanceDetail;
use App\Models\Item;
use App\Services\ActivityLogService;
use App\Services\InventoryService;
use App\Support\ReferenceNumberGenerator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class IssuanceController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ActivityLogService $activityLogService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Issuance::query()
                ->with(['department', 'issuer', 'receiver.department', 'details.item', 'details.equipment'])
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $issuanceType = IssuanceType::tryFrom($request->input('issuance_type'));

        $rules = [
            'issuance_type' => ['required', Rule::enum(IssuanceType::class)],
            'issuance_number' => ['nullable', 'string', 'max:255', 'unique:issuances,issuance_number'],
            'department_id' => ['required', 'exists:departments,id'],
            'received_by' => ['nullable', 'exists:employees,id'],
            'received_by_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'use_custom_date' => ['sometimes', 'boolean'],
            'issued_date' => ['nullable', 'required_if:use_custom_date,true,1', 'date', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.inventory_number' => ['nullable', 'string', 'max:255'],
        ];

        if ($issuanceType === IssuanceType::Equipments) {
            $rules['items.*.equipment_id'] = ['required', 'exists:equipments,id'];
            $rules['items.*.property_number'] = ['required', 'string', 'max:255'];
            $rules['items.*.date_acquired'] = ['nullable', 'date', 'before_or_equal:today'];
        } elseif ($issuanceType === IssuanceType::Items) {
            $rules['items.*.item_id'] = ['required', 'exists:items,id'];
        }

        $data = $request->validate($rules);
        $issuanceType = IssuanceType::from($data['issuance_type']);

        $receivedBy = $data['received_by'] ?? null;
        $receivedByName = trim((string) ($data['received_by_name'] ?? ''));

        if (! $receivedBy && $receivedByName === '') {
            return response()->json([
                'message' => 'Please select an employee or type the receiver name.',
            ], 422);
        }

        $useCustomDate = filter_var($data['use_custom_date'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $issuedDate = $useCustomDate && filled($data['issued_date'] ?? null)
            ? Carbon::parse($data['issued_date'])->startOfDay()
            : now();

        $manualReference = trim((string) ($data['issuance_number'] ?? ''));

        try {
            $issuance = DB::transaction(function () use ($data, $request, $issuanceType, $receivedBy, $receivedByName, $issuedDate, $manualReference) {
                $issuance = Issuance::create([
                    'issuance_number' => $manualReference !== ''
                        ? $manualReference
                        : ReferenceNumberGenerator::forIssuance(),
                    'department_id' => $data['department_id'],
                    'issued_by' => $request->user()->id,
                    'received_by' => $receivedBy,
                    'received_by_name' => $receivedBy ? null : $receivedByName,
                    'issued_date' => $issuedDate,
                ]);

                foreach ($data['items'] as $line) {
                    if ($issuanceType === IssuanceType::Equipments) {
                        $this->issueEquipmentLine($issuance, $line);
                    } else {
                        $this->issueItemLine($issuance, $line, $request);
                    }
                }

                return $issuance;
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogService->log(
            $request->user(),
            'Issued',
            'Issuance',
            "Processed manual issuance {$issuance->issuance_number}"
        );

        return response()->json(
            $issuance->load(['department', 'issuer', 'receiver.department', 'details.item', 'details.equipment']),
            201
        );
    }

    public function outstandingEquipments(): JsonResponse
    {
        $details = IssuanceDetail::query()
            ->with(['equipment.category', 'issuance.receiver', 'issuance.department'])
            ->whereNotNull('equipment_id')
            ->whereNotNull('property_number')
            ->where('property_number', '!=', '')
            ->whereHas('issuance')
            ->whereHas('equipment')
            ->withSum('returns as returned_qty', 'quantity')
            ->orderByDesc('id')
            ->get();

        $rows = $details
            ->map(function (IssuanceDetail $detail) {
                $equipment = $detail->equipment;
                if (! $equipment) {
                    return null;
                }

                $outstanding = (int) $detail->quantity - (int) ($detail->returned_qty ?? 0);
                if ($outstanding < 1) {
                    return null;
                }

                $acquired = $detail->date_acquired ?: $equipment->date_acquired;
                $expires = $equipment->expiryFromAcquired($acquired);
                $issuedDate = $detail->issuance?->issued_date;

                return [
                    'id' => $detail->id,
                    'issuance_detail_id' => $detail->id,
                    'issuance_id' => $detail->issuance_id,
                    'issuance_number' => $detail->issuance?->issuance_number,
                    'equipment_id' => $equipment->id,
                    'name' => $equipment->name,
                    'type' => $equipment->type,
                    'category' => $equipment->category?->name,
                    'property_number' => $detail->property_number,
                    'inventory_number' => $detail->inventory_number,
                    'date_issued' => $issuedDate?->toDateString(),
                    'date_acquired' => $acquired ? Carbon::parse($acquired)->toDateString() : null,
                    'life_span_years' => $equipment->remainingLifeSpanYears(now(), $acquired)
                        ?? $equipment->life_span_years,
                    'lifespan_expires_on' => $expires?->toDateString(),
                    'specs' => $equipment->specs,
                    'details' => $equipment->description,
                    'origin' => $equipment->origin,
                    'department_id' => $detail->issuance?->department_id,
                    'received_by' => $detail->issuance?->received_by,
                    'received_by_name' => $detail->issuance?->receiver?->name
                        ?: $detail->issuance?->received_by_name,
                    'quantity_outstanding' => $outstanding,
                ];
            })
            ->filter()
            ->values();

        return response()->json($rows);
    }

    public function show(Issuance $issuance): JsonResponse
    {
        return response()->json(
            $issuance->load(['department', 'issuer', 'receiver.department', 'details.item', 'details.equipment'])
        );
    }

    private function issueItemLine(Issuance $issuance, array $line, Request $request): void
    {
        $item = Item::query()->findOrFail($line['item_id']);
        $inventoryNumber = $this->resolveInventoryNumber($line, $item, null);

        if ($item->inventory_number !== $inventoryNumber) {
            $item->update(['inventory_number' => $inventoryNumber]);
        }

        $this->inventoryService->issueStock(
            $item,
            $line['quantity'],
            $request->user(),
            "Issuance {$issuance->issuance_number}"
        );

        $issuance->details()->create([
            'item_id' => $item->id,
            'barcode' => $item->barcode ?? $item->item_number,
            'inventory_number' => $inventoryNumber,
            'quantity' => $line['quantity'],
        ]);
    }

    private function issueEquipmentLine(Issuance $issuance, array $line): void
    {
        $equipment = Equipment::query()->lockForUpdate()->findOrFail($line['equipment_id']);
        $propertyNumber = trim((string) ($line['property_number'] ?? ''));
        $inventoryNumber = $this->resolveIssuedInventoryNumber($line, $equipment);
        $dateAcquired = filled($line['date_acquired'] ?? null)
            ? Carbon::parse($line['date_acquired'])->toDateString()
            : $equipment->date_acquired?->toDateString();

        if ($propertyNumber === '') {
            throw new InvalidArgumentException("Property number is required for {$equipment->name}.");
        }

        if (! $equipment->isReturnedStock()) {
            $masterProperty = trim((string) $equipment->property_number);
            if ($masterProperty !== '' && strcasecmp($propertyNumber, $masterProperty) === 0) {
                throw new InvalidArgumentException(
                    "Please change the Property No. for {$equipment->name}. The default listing cannot be used for New (fresh) equipment."
                );
            }
        }

        if ($line['quantity'] > $equipment->quantity) {
            throw new InvalidArgumentException("Not enough available quantity for {$equipment->name}.");
        }

        $taken = Equipment::query()
            ->where('property_number', $propertyNumber)
            ->where('id', '!=', $equipment->id)
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException("Property number {$propertyNumber} is already used by another equipment.");
        }

        $issuance->details()->create([
            'equipment_id' => $equipment->id,
            'barcode' => $equipment->barcode ?? $propertyNumber,
            'property_number' => $propertyNumber,
            'inventory_number' => $inventoryNumber,
            'date_acquired' => $dateAcquired,
            'quantity' => $line['quantity'],
        ]);

        $equipment->decrement('quantity', $line['quantity']);
    }

    private function resolveIssuedInventoryNumber(array $line, Equipment $equipment): string
    {
        $manual = trim((string) ($line['inventory_number'] ?? ''));
        if ($manual === '') {
            return $equipment->inventory_number ?: ReferenceNumberGenerator::forInventory();
        }

        $this->assertInventoryNumberAvailable($manual, null, $equipment->id);

        return $manual;
    }

    private function resolveInventoryNumber(array $line, ?Item $item, ?Equipment $equipment): string
    {
        $manual = trim((string) ($line['inventory_number'] ?? ''));
        $current = $item?->inventory_number ?: $equipment?->inventory_number;
        $number = $manual !== '' ? $manual : ($current ?: ReferenceNumberGenerator::forInventory());

        $this->assertInventoryNumberAvailable($number, $item?->id, $equipment?->id);

        return $number;
    }

    private function assertInventoryNumberAvailable(string $number, ?int $ignoreItemId, ?int $ignoreEquipmentId): void
    {
        $itemTaken = Item::query()
            ->where('inventory_number', $number)
            ->when($ignoreItemId, fn ($query) => $query->where('id', '!=', $ignoreItemId))
            ->exists();

        $equipmentTaken = Equipment::query()
            ->where('inventory_number', $number)
            ->when($ignoreEquipmentId, fn ($query) => $query->where('id', '!=', $ignoreEquipmentId))
            ->exists();

        if ($itemTaken || $equipmentTaken) {
            throw new InvalidArgumentException("Inventory number {$number} is already in use.");
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\IssuanceType;
use App\Enums\ItemStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentSerialNumber;
use App\Models\Issuance;
use App\Models\IssuanceDetail;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\ActivityLogService;
use App\Services\InventoryService;
use App\Services\IssuanceRecipientResolver;
use App\Services\DocumentNumberService;
use App\Support\EquipmentStatus;
use App\Support\ReferenceNumberGenerator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class IssuanceController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ActivityLogService $activityLogService,
        private readonly IssuanceRecipientResolver $recipientResolver,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Issuance::query()
                ->with(['department', 'issuer', 'receiver.department', 'details.item', 'details.equipment.serialNumbers', 'details.serialNumbers'])
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $issuanceType = IssuanceType::tryFrom((string) $request->input('issuance_type'));

        $rules = [
            'issuance_type' => ['required', Rule::enum(IssuanceType::class)],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'channel' => ['nullable', Rule::in(['manual', 'scan'])],
            'issuance_number' => ['nullable', 'string', 'max:255', 'unique:issuances,issuance_number'],
            'recipient_source' => ['nullable', Rule::in(IssuanceRecipientResolver::SOURCES)],
            'received_by' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string'],
            'use_custom_date' => ['sometimes', 'boolean'],
            'issued_date' => ['nullable', 'required_if:use_custom_date,true,1', 'date', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.inventory_number' => ['nullable', 'string', 'max:255'],
            'items.*.date_acquired' => ['nullable', 'date', 'before_or_equal:today'],
        ];

        if ($issuanceType === IssuanceType::Equipments) {
            $rules['items.*.equipment_id'] = ['required', 'exists:equipments,id'];
            $rules['items.*.property_number'] = ['nullable', 'string', 'max:255'];
            $rules['items.*.serial_number_ids'] = ['nullable', 'array'];
            $rules['items.*.serial_number_ids.*'] = ['integer', 'exists:equipment_serial_numbers,id'];
        } elseif ($issuanceType === IssuanceType::Items) {
            $rules['items.*.item_id'] = ['required', 'exists:items,id'];
        }

        $data = $request->validate($rules);
        $issuanceType = IssuanceType::from($data['issuance_type']);
        $idempotencyKey = filled($data['idempotency_key'] ?? null) ? trim($data['idempotency_key']) : null;
        $channel = $data['channel'] ?? 'manual';

        // A repeated submission (double click, network retry, scanner re-send) returns the
        // original issuance instead of issuing stock a second time.
        if ($idempotencyKey && ($existing = $this->findByIdempotencyKey($idempotencyKey))) {
            return $this->duplicateResponse($existing);
        }

        // Received By is resolved and validated on the server; client-supplied names/units are ignored.
        $resolved = $this->recipientResolver->resolve(
            $request->user(),
            $data['recipient_source'] ?? null,
            $data['received_by'] ?? null,
        );
        $recipient = $resolved['employee'];

        $useCustomDate = filter_var($data['use_custom_date'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $issuedDate = $useCustomDate && filled($data['issued_date'] ?? null)
            ? Carbon::parse($data['issued_date'])->startOfDay()
            : now();

        $manualReference = trim((string) ($data['issuance_number'] ?? ''));

        try {
            $issuance = DB::transaction(function () use ($data, $request, $issuanceType, $recipient, $resolved, $issuedDate, $manualReference, $idempotencyKey, $channel) {
                $issuance = Issuance::create([
                    'issuance_number' => $manualReference !== ''
                        ? $manualReference
                        : ReferenceNumberGenerator::forIssuance(),
                    'idempotency_key' => $idempotencyKey,
                    'department_id' => $recipient->department_id,
                    // Snapshot of the recipient as recorded at issuance.
                    'department_name' => $recipient->department?->name,
                    'issued_by' => $request->user()->id,
                    'received_by' => $recipient->id,
                    'received_by_name' => $recipient->name,
                    'recipient_employee_number' => $recipient->employee_number,
                    'recipient_designation' => $recipient->position,
                    'recipient_source' => $resolved['source'],
                    'channel' => $channel,
                    'issued_date' => $issuedDate,
                ]);

                foreach ($data['items'] as $line) {
                    if ($issuanceType === IssuanceType::Equipments) {
                        $this->issueEquipmentLine($issuance, $line, $issuedDate, $request);
                    } else {
                        $this->issueItemLine($issuance, $line, $request, $issuedDate, $channel === 'scan');
                    }
                }

                return $issuance;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($idempotencyKey && ($existing = $this->findByIdempotencyKey($idempotencyKey))) {
                return $this->duplicateResponse($existing);
            }

            return response()->json(['message' => 'This issuance reference number is already in use.'], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogService->log(
            $request->user(),
            'Issued',
            'Issuance',
            sprintf(
                'Processed %s issuance %s to %s (%s)',
                $channel === 'scan' ? 'scan-to-issue' : 'manual',
                $issuance->issuance_number,
                $recipient->name,
                $recipient->department?->name ?? 'no unit'
            )
        );

        return response()->json(
            $issuance->load(['department', 'issuer', 'receiver.department', 'details.item', 'details.equipment.serialNumbers', 'details.serialNumbers']),
            201
        );
    }

    private function findByIdempotencyKey(string $key): ?Issuance
    {
        return Issuance::query()->where('idempotency_key', $key)->first();
    }

    private function duplicateResponse(Issuance $issuance): JsonResponse
    {
        $issuance->load(['department', 'issuer', 'receiver.department', 'details.item', 'details.equipment.serialNumbers', 'details.serialNumbers']);

        return response()->json(array_merge($issuance->toArray(), [
            'duplicate' => true,
            'message' => "This submission was already processed as {$issuance->issuance_number}. No stock was issued again.",
        ]), 200);
    }

    public function outstandingEquipments(): JsonResponse
    {
        $details = IssuanceDetail::query()
            ->with(['equipment.category', 'equipment.serialNumbers', 'issuance.receiver', 'issuance.department', 'serialNumbers'])
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
                    'brand' => $equipment->brand,
                    'model' => $equipment->model,
                    'brand_model' => $equipment->brand_model,
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
                    'department_name' => $detail->issuance?->departmentDisplayName(),
                    'received_by' => $detail->issuance?->received_by,
                    'received_by_name' => $detail->issuance?->recipientDisplayName(),
                    'quantity_issued' => (int) $detail->quantity,
                    'quantity_outstanding' => $outstanding,
                    'serial_numbers' => $detail->serialNumbers
                        ->where('status', EquipmentSerialNumber::STATUS_ISSUED)
                        ->map(fn ($serial) => [
                            'id' => $serial->id,
                            'serial_number' => $serial->serial_number,
                            'property_number' => $serial->property_number,
                            'inventory_number' => $serial->inventory_number,
                        ])
                        ->values(),
                ];
            })
            ->filter()
            ->values();

        return response()->json($rows);
    }

    public function show(Issuance $issuance): JsonResponse
    {
        return response()->json(
            $issuance->load(['department', 'issuer', 'receiver.department', 'details.item', 'details.equipment.serialNumbers', 'details.serialNumbers'])
        );
    }

    private function issueItemLine(Issuance $issuance, array $line, Request $request, Carbon $issuedDate, bool $strictAvailability): void
    {
        $item = Item::query()->findOrFail($line['item_id']);
        $inventoryNumber = $this->resolveInventoryNumber($line, $item, null);
        $dateAcquired = $this->resolveItemDateAcquired($item, $line, $issuedDate);

        if ($strictAvailability) {
            if ($item->status !== ItemStatus::Active) {
                throw new InvalidArgumentException("{$item->item_name} is {$item->status->value} and cannot be issued.");
            }

            $available = (int) Item::query()->whereKey($item->id)->value('current_stock');
            if ($available < (int) $line['quantity']) {
                throw new InvalidArgumentException(
                    $available < 1
                        ? "{$item->item_name} is out of stock and cannot be issued."
                        : "Only {$available} unit(s) of {$item->item_name} are available."
                );
            }
        }

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
            'date_acquired' => $dateAcquired,
            'quantity' => $line['quantity'],
        ]);
    }

    /**
     * Revision 9: Date Acquired is required. It is auto-filled from the latest receipt
     * (acquisition) of the item on or before the issuance date when not supplied.
     */
    private function resolveItemDateAcquired(Item $item, array $line, Carbon $issuedDate): string
    {
        $provided = filled($line['date_acquired'] ?? null)
            ? Carbon::parse($line['date_acquired'])->toDateString()
            : null;

        $resolved = $provided ?? StockMovement::query()
            ->where('item_id', $item->id)
            ->where('transaction_type', TransactionType::In->value)
            ->where('created_at', '<=', $issuedDate->copy()->endOfDay())
            ->latest('created_at')
            ->value('created_at');

        if (! $resolved) {
            throw new InvalidArgumentException(
                "Date Acquired is missing for {$item->item_name}: no receipt is on record. Enter the acquisition date before issuing."
            );
        }

        $resolved = Carbon::parse($resolved)->toDateString();
        $this->assertAcquiredBeforeIssued($resolved, $issuedDate, $item->item_name);

        return $resolved;
    }

    private function assertAcquiredBeforeIssued(string $dateAcquired, Carbon $issuedDate, string $label): void
    {
        if (Carbon::parse($dateAcquired)->startOfDay()->gt($issuedDate->copy()->startOfDay())) {
            throw new InvalidArgumentException(
                "Date Acquired for {$label} ({$dateAcquired}) cannot be later than the issuance date ({$issuedDate->toDateString()})."
            );
        }
    }

    private function issueEquipmentLine(Issuance $issuance, array $line, Carbon $issuedDate, Request $request): void
    {
        $equipment = Equipment::query()->with('serialNumbers')->lockForUpdate()->findOrFail($line['equipment_id']);
        $dateAcquired = filled($line['date_acquired'] ?? null)
            ? Carbon::parse($line['date_acquired'])->toDateString()
            : $equipment->date_acquired?->toDateString();

        // Revision 9: Date Acquired is required before the issuance can be finalised.
        if (! $dateAcquired) {
            throw new InvalidArgumentException(
                "Date Acquired is missing for {$equipment->name}. Enter it from the property records before issuing."
            );
        }

        $this->assertAcquiredBeforeIssued($dateAcquired, $issuedDate, $equipment->name);

        // Units under repair / for disposal / disposed are removed from the listing quantity,
        // so only serviceable units in Supply Office custody are counted here.
        if ($line['quantity'] > $equipment->quantity) {
            throw new InvalidArgumentException(
                $equipment->quantity < 1
                    ? "{$equipment->name} has no available unit to issue."
                    : "Not enough available quantity for {$equipment->name}."
            );
        }

        $units = $this->selectIssuedUnits($equipment, $line);
        $propertyNumber = $this->resolveLinePropertyNumber($equipment, $units, $line);
        $inventoryNumber = $this->resolveLineInventoryNumber($equipment, $units, $line);

        if (! $equipment->date_acquired) {
            // Resolve the missing acquisition date at the source record.
            $equipment->update(['date_acquired' => $dateAcquired]);
            $this->activityLogService->log(
                $request->user(),
                'Updated',
                'Equipments',
                "Resolved missing Date Acquired ({$dateAcquired}) for {$equipment->name} during issuance {$issuance->issuance_number}"
            );
        }

        $detail = $issuance->details()->create([
            'equipment_id' => $equipment->id,
            'barcode' => $equipment->barcode ?? $propertyNumber,
            'property_number' => $propertyNumber,
            'inventory_number' => $inventoryNumber,
            'date_acquired' => $dateAcquired,
            'quantity' => $line['quantity'],
        ]);

        foreach ($units as $unit) {
            // The physical unit keeps the numbers it was issued with, so later forms reuse them.
            $unit->update([
                'status' => EquipmentSerialNumber::STATUS_ISSUED,
                'issuance_detail_id' => $detail->id,
                'property_number' => $unit->property_number ?: $propertyNumber,
                'inventory_number' => $unit->inventory_number ?: $inventoryNumber,
            ]);
        }

        $equipment->decrement('quantity', $line['quantity']);
    }

    /**
     * The serialised units selected for an equipment line (validated, locked). Empty for legacy
     * records without serial numbers.
     *
     * @return \Illuminate\Support\Collection<int, EquipmentSerialNumber>
     */
    private function selectIssuedUnits(Equipment $equipment, array $line)
    {
        $inStock = $equipment->serialNumbers()->inStock()->lockForUpdate()->get();
        if ($inStock->isEmpty()) {
            return collect();
        }

        $ids = collect($line['serial_number_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->count() !== (int) $line['quantity']) {
            throw new InvalidArgumentException(
                "Select exactly {$line['quantity']} serial number(s) for {$equipment->name}."
            );
        }

        $selected = $inStock->whereIn('id', $ids->all());
        if ($selected->count() !== $ids->count()) {
            $blocked = EquipmentSerialNumber::query()
                ->whereIn('id', $ids->diff($selected->pluck('id'))->all())
                ->get()
                ->map(fn ($serial) => "{$serial->serial_number} (".EquipmentStatus::unitStatusLabel($serial->status).')')
                ->implode(', ');

            throw new InvalidArgumentException(
                "Serial number(s) not available for issuance for {$equipment->name}: {$blocked}."
            );
        }

        return $selected->values();
    }

    /**
     * The property number recorded on the issuance line, resolved on the server:
     *   1. a number already recorded on the selected unit(s) (authoritative),
     *   2. the record's own number when the record is one physical unit (authoritative),
     *   3. returned stock: the record's number unless a different one is typed,
     *   4. otherwise the number typed from the paper form (each unit of a multi-unit record
     *      needs its own number, not the record's shared number).
     * A typed value that differs from an authoritative number is rejected, never overwritten.
     */
    private function resolveLinePropertyNumber(Equipment $equipment, $units, array $line): string
    {
        $typed = $this->numbers->clean($line['property_number'] ?? null);
        $recorded = $units->pluck('property_number')->filter()->unique(fn ($v) => mb_strtolower($v))->values();

        if ($recorded->count() > 1) {
            throw new InvalidArgumentException(
                "The selected units of {$equipment->name} carry different property numbers ({$recorded->implode(', ')}). Issue them on separate lines."
            );
        }

        if ($recorded->count() === 1 && $units->every(fn ($unit) => filled($unit->property_number))) {
            $number = $recorded->first();
            if ($typed !== null && strcasecmp($typed, $number) !== 0) {
                throw new InvalidArgumentException(
                    'Unit '.$units->pluck('serial_number')->implode(', ')." of {$equipment->name} is recorded with property number {$number}. "
                    .'Leave the Property No. as recorded, or correct the unit\'s number in Supply Master > Equipment first.'
                );
            }

            return $number;
        }

        if ($equipment->isSingleUnit() && filled($equipment->property_number) && ! $equipment->isReturnedStock()) {
            if ($typed !== null && strcasecmp($typed, (string) $equipment->property_number) !== 0) {
                throw new InvalidArgumentException(
                    "{$equipment->name} is a single unit with property number {$equipment->property_number}. "
                    .'Leave the Property No. as recorded, or edit the equipment to change its official number.'
                );
            }

            return (string) $equipment->property_number;
        }

        if ($equipment->isReturnedStock()) {
            $number = $typed ?? (string) $equipment->property_number;
        } else {
            if ($typed === null) {
                throw new InvalidArgumentException("Enter the Property No. from the paper form for {$equipment->name}.");
            }
            if (strcasecmp($typed, (string) $equipment->property_number) === 0) {
                throw new InvalidArgumentException(
                    "{$equipment->name} holds several units under {$equipment->property_number}. Enter the Property No. of the unit being issued, from the paper form."
                );
            }
            $number = $typed;
        }

        // Genuine conflicts: the number is another record's official number, or is recorded on a
        // unit of another record. Units of this record may share a reference.
        if (Equipment::withTrashed()->where('property_number', $number)->whereKeyNot($equipment->id)->exists()) {
            throw new InvalidArgumentException("Property number {$number} is the official number of another equipment record.");
        }
        if (EquipmentSerialNumber::query()->where('property_number', $number)->where('equipment_id', '!=', $equipment->id)->exists()) {
            throw new InvalidArgumentException("Property number {$number} is recorded on a unit of another equipment record.");
        }

        return $number;
    }

    private function resolveLineInventoryNumber(Equipment $equipment, $units, array $line): ?string
    {
        $recorded = $units->pluck('inventory_number')->filter()->unique()->values();
        if ($recorded->count() === 1 && $units->every(fn ($unit) => filled($unit->inventory_number))) {
            return $recorded->first();
        }

        return $this->resolveIssuedInventoryNumber($line, $equipment);
    }

    private function resolveIssuedInventoryNumber(array $line, Equipment $equipment): ?string
    {
        $manual = trim((string) ($line['inventory_number'] ?? ''));
        if ($manual === '') {
            // The record's own inventory number; blank when the organization has not assigned
            // one (nothing is invented).
            return $equipment->inventory_number ?: null;
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

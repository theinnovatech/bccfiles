<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentCase;
use App\Models\EquipmentSerialNumber;
use App\Models\IssuanceDetail;
use App\Models\ItemReturn;
use App\Services\ActivityLogService;
use App\Services\DocumentNumberService;
use App\Support\NumberScopes;
use Illuminate\Validation\ValidationException;
use App\Support\EquipmentStatus;
use App\Support\PropertyClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class EquipmentController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Equipment::query()
                ->with(['category', 'sourceReturn', 'serialNumbers'])
                ->orderBy('name')
                ->get()
        );
    }

    public function findByBarcode(string $barcode): JsonResponse
    {
        $equipment =             Equipment::query()
                ->with(['category', 'sourceReturn', 'serialNumbers'])
                ->where(function ($query) use ($barcode) {
                    $query->where('barcode', $barcode)
                        ->orWhere('property_number', $barcode)
                        ->orWhere('inventory_number', $barcode);
                })
                ->first();

        return response()->json(['equipment' => $equipment]);
    }

    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['nullable', 'integer', 'exists:equipments,id'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'serial_numbers' => ['nullable', 'array'],
            'serial_numbers.*' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (empty($data['equipment_id']) && empty($data['barcode'])) {
            return response()->json([
                'message' => 'Select an equipment or provide a barcode, property number, or inventory number.',
            ], 422);
        }

        $equipment = ! empty($data['equipment_id'])
            ? Equipment::query()->with(['category', 'serialNumbers'])->findOrFail($data['equipment_id'])
            : Equipment::query()
                ->with(['category', 'serialNumbers'])
                ->where(function ($query) use ($data) {
                    $query->where('barcode', $data['barcode'])
                        ->orWhere('property_number', $data['barcode'])
                        ->orWhere('inventory_number', $data['barcode']);
                })
                ->firstOrFail();

        try {
            DB::transaction(function () use ($data, $equipment) {
                $newSerials = collect($data['serial_numbers'] ?? [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->values()
                    ->all();
                $tracksSerials = $equipment->serialNumbers()->exists();

                if ($tracksSerials && count($newSerials) !== (int) $data['quantity']) {
                    throw new InvalidArgumentException(
                        "Enter exactly {$data['quantity']} serial number(s) for the quantity received."
                    );
                }

                $equipment->increment('quantity', $data['quantity']);

                if ($tracksSerials) {
                    $equipment->appendInStockSerialNumbers($newSerials);
                }
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $equipment->refresh()->load(['category', 'serialNumbers']);

        $remarks = trim((string) ($data['remarks'] ?? ''));
        $ref = $equipment->inventory_number ?: $equipment->property_number;
        $message = "Received {$data['quantity']} unit(s) for equipment {$equipment->name} ({$ref})";
        if ($remarks !== '') {
            $message .= " — {$remarks}";
        }

        $this->activityLogService->log(
            $request->user(),
            'Received',
            'Equipments',
            $message
        );

        return response()->json([
            'equipment' => $equipment,
            'quantity_received' => $data['quantity'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['nullable', 'string', 'max:255', 'unique:equipments,barcode'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'property_number' => ['nullable', 'string', 'max:255'],
            'inventory_number' => ['nullable', 'string', 'max:255'],
            'property_card_number' => ['nullable', 'string', 'max:255'],
            'equipment_category_id' => ['required', 'exists:equipment_categories,id'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'serial_numbers' => ['required', 'array', 'min:1'],
            'serial_numbers.*' => ['required', 'string', 'max:255'],
            'life_span_years' => ['required', 'integer', 'min:1', 'max:100'],
            'specs' => ['nullable', 'string'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'property_class' => ['nullable', Rule::in(PropertyClass::keys())],
        ]);

        try {
            $equipment = DB::transaction(function () use ($data) {
                $serialNumbers = $data['serial_numbers'];
                unset($data['serial_numbers']);

                // Official numbers: typed by the user, or generated from the organization's
                // configured format (Settings > Document numbering).
                $numbers = [
                    'property_number' => $this->numbers->resolve('equipment_property_number', $data['property_number'] ?? null, NumberScopes::equipmentProperty(), null, 'property_number'),
                    'inventory_number' => $this->numbers->resolve('equipment_inventory_number', $data['inventory_number'] ?? null, NumberScopes::equipmentInventory(), null, 'inventory_number'),
                    'property_card_number' => $this->numbers->resolve('property_card_number', $data['property_card_number'] ?? null, NumberScopes::propertyCard(), null, 'property_card_number'),
                ];

                $equipment = Equipment::create([
                    ...$data,
                    ...$numbers,
                    'brand' => $this->clean($data['brand'] ?? null),
                    'model' => $this->clean($data['model'] ?? null),
                ]);
                $equipment->syncInStockSerialNumbers($serialNumbers);

                // A single-unit record: the record's numbers are the unit's numbers.
                if ((int) $equipment->quantity === 1) {
                    $equipment->serialNumbers()->update([
                        'property_number' => $equipment->property_number,
                        'inventory_number' => $equipment->inventory_number,
                    ]);
                }

                return $equipment;
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogService->log(
            $request->user(),
            'Created',
            'Equipments',
            "Created equipment {$equipment->name} ({$equipment->inventory_number})"
        );

        return response()->json($equipment->load(['category', 'serialNumbers']), 201);
    }

    public function show(Equipment $equipment): JsonResponse
    {
        return response()->json($equipment->load(['category', 'sourceReturn', 'serialNumbers']));
    }

    public function history(Equipment $equipment): JsonResponse
    {
        $equipment->load([
            'category',
            'serialNumbers',
            'sourceReturn.issuanceDetail.issuance.department',
            'sourceReturn.issuanceDetail.issuance.receiver',
            'sourceReturn.issuanceDetail.issuance.issuer',
            'sourceReturn.issuanceDetail.equipment.category',
            'sourceReturn.issuanceDetail.serialNumbers',
            'sourceReturn.department',
            'sourceReturn.borrower',
            'sourceReturn.returner',
            'sourceReturn.equipment.category',
            'sourceReturn.equipment.serialNumbers',
        ]);

        $events = collect();

        if ($equipment->isReturnedStock() && $equipment->sourceReturn) {
            $return = $equipment->sourceReturn;
            $origin = $return->equipment
                ?: $return->issuanceDetail?->equipment;

            if ($origin) {
                $events->push($this->registrationHistoryEvent($origin));
            }

            if ($return->issuanceDetail) {
                $events->push($this->issuedHistoryEvent($return->issuanceDetail, $origin ?? $return->equipment));
            }

            $events->push($this->returnedHistoryEvent($return));
            $events->push($this->restockedHistoryEvent($equipment, $return));
            $this->appendIssueReturnHistory($events, $equipment->id);
        } else {
            $events->push($this->registrationHistoryEvent($equipment));
            $this->appendIssueReturnHistory($events, $equipment->id);
        }

        $this->appendCaseHistory($events, $equipment->id);

        return response()->json([
            'equipment' => $equipment,
            'events' => $events
                ->sortBy(fn (array $event) => ($event['sort_at'] ?? '').'-'.($event['order'] ?? 0))
                ->values(),
        ]);
    }

    private function appendIssueReturnHistory(Collection $events, int $equipmentId): void
    {
        $details = IssuanceDetail::query()
            ->with([
                'issuance.department',
                'issuance.receiver',
                'issuance.issuer',
                'returns.department',
                'returns.borrower',
                'returns.returner',
                'returns.equipment.category',
                'equipment.category',
                'serialNumbers',
            ])
            ->where('equipment_id', $equipmentId)
            ->orderBy('id')
            ->get();

        foreach ($details as $detail) {
            $events->push($this->issuedHistoryEvent($detail, $detail->equipment));

            foreach ($detail->returns as $return) {
                $return->setRelation('issuanceDetail', $detail);
                $events->push($this->returnedHistoryEvent($return));
            }
        }

        $orphanReturns = ItemReturn::query()
            ->with(['department', 'borrower', 'returner', 'equipment.category'])
            ->where('equipment_id', $equipmentId)
            ->whereNull('issuance_detail_id')
            ->orderBy('id')
            ->get();

        foreach ($orphanReturns as $return) {
            $events->push($this->returnedHistoryEvent($return));
        }
    }

    /**
     * Repair / warranty / disposal transitions (Revision 7) in the equipment timeline.
     */
    private function appendCaseHistory(Collection $events, int $equipmentId): void
    {
        $cases = EquipmentCase::query()
            ->with(['events.performer', 'serialNumber'])
            ->where('equipment_id', $equipmentId)
            ->orderBy('id')
            ->get();

        foreach ($cases as $case) {
            foreach ($case->events as $event) {
                $events->push([
                    'type' => 'case',
                    'title' => $case->case_number.' — '.ucfirst(str_replace('_', ' ', $event->action)),
                    'sort_at' => optional($event->event_date)->toDateString().' '.optional($event->created_at)->format('H:i:s'),
                    'order' => 5,
                    'date' => optional($event->event_date)->toDateString(),
                    'fields' => $this->historyFields([
                        'Status' => trim(($event->from_status ? EquipmentStatus::label($event->from_status).' → ' : '').($event->to_status ? EquipmentStatus::label($event->to_status) : '')),
                        'Serial No.' => $case->serialNumber?->serial_number,
                        'Qty' => $case->quantity,
                        'Reason' => $event->reason,
                        'Reference' => $event->reference,
                        'Responsible user' => $event->performer?->name,
                    ]),
                ]);
            }
        }
    }

    private function registrationHistoryEvent(Equipment $equipment): array
    {
        $equipment->loadMissing('serialNumbers');
        $lifeSpan = $equipment->life_span_years !== null
            ? $equipment->life_span_years.' '.($equipment->life_span_years === 1 ? 'yr' : 'yrs')
            : null;

        return [
            'type' => 'registered',
            'title' => $equipment->isReturnedStock() ? 'Registered as Returned stock' : 'Registered as Fresh',
            'sort_at' => optional($equipment->created_at)->toDateTimeString(),
            'order' => 1,
            'date' => optional($equipment->created_at)->toDateTimeString(),
            'fields' => $this->historyFields([
                'Name' => $equipment->name,
                'Origin' => $equipment->isReturnedStock() ? 'Returned' : 'Fresh / New',
                'Category' => $equipment->category?->name,
                'Type' => $equipment->type,
                'Property No.' => $equipment->property_number,
                'Inventory No.' => $equipment->inventory_number,
                'Barcode' => $equipment->barcode,
                'Qty' => $equipment->quantity,
                'Serial Nos.' => $equipment->serialNumbers
                    ->pluck('serial_number')
                    ->filter()
                    ->implode(', '),
                'Life Span' => $lifeSpan,
                'Date Acquired' => optional($equipment->date_acquired)->toDateString(),
                'Description' => $equipment->description,
                'Specs' => $equipment->specs,
            ]),
        ];
    }

    private function issuedHistoryEvent(IssuanceDetail $detail, ?Equipment $equipment): array
    {
        $issuance = $detail->issuance;
        $acquired = $detail->date_acquired ?: $equipment?->date_acquired;
        $issuedAt = $issuance?->issued_date ?? $detail->created_at;
        $lifeSpan = $equipment?->formattedRemainingLifeSpan($issuedAt, $acquired);
        $receivedBy = $issuance?->recipientDisplayName();

        return [
            'type' => 'issued',
            'title' => 'Issued',
            'sort_at' => optional($issuedAt)->toDateTimeString(),
            'order' => 2,
            'date' => optional($issuedAt)->toDateTimeString(),
            'fields' => $this->historyFields([
                'Issuance No.' => $issuance?->issuance_number,
                'Issuance Date' => optional($issuedAt)->toDateTimeString(),
                'Department' => $issuance?->departmentDisplayName(),
                'Received By' => $receivedBy,
                'Issued By' => $issuance?->issuer?->name,
                'Property No.' => $detail->property_number,
                'Inventory No.' => $detail->inventory_number,
                'Qty' => $detail->quantity,
                'Serial Nos.' => $detail->serialNumbers
                    ->pluck('serial_number')
                    ->filter()
                    ->implode(', '),
                'Date Acquired' => optional($acquired)->toDateString(),
                'Issued as' => $equipment?->isReturnedStock() ? 'Returned' : 'Fresh / New',
                'Life Span when issued' => $lifeSpan,
            ]),
        ];
    }

    private function returnedHistoryEvent(ItemReturn $return): array
    {
        $equipment = $return->equipment;
        $acquired = $return->issuanceDetail?->date_acquired ?: $equipment?->date_acquired;
        $lifeSpan = $equipment?->formattedRemainingLifeSpan($return->date_returned, $acquired);

        return [
            'type' => 'returned',
            'title' => 'Returned',
            'sort_at' => optional($return->date_returned ?? $return->created_at)->toDateTimeString(),
            'order' => 3,
            'date' => optional($return->date_returned ?? $return->created_at)->toDateTimeString(),
            'fields' => $this->historyFields([
                'Reference No.' => $return->reference_number,
                'Date Returned' => optional($return->date_returned)->toDateTimeString(),
                'Department' => $return->department?->name,
                'Returned By' => $return->borrowerDisplayName(),
                'Recorded By' => $return->returner?->name,
                'Qty' => $return->quantity,
                'Condition' => $return->condition_label,
                'Life Span at return' => $lifeSpan,
                'Remarks' => $return->reason,
            ]),
        ];
    }

    private function restockedHistoryEvent(Equipment $equipment, ItemReturn $return): array
    {
        return [
            'type' => 'restocked',
            'title' => 'Added to Supply Master as Returned',
            'sort_at' => optional($return->date_returned ?? $equipment->created_at)->toDateTimeString(),
            'order' => 4,
            'date' => optional($return->date_returned ?? $equipment->created_at)->toDateTimeString(),
            'fields' => $this->historyFields([
                'Origin' => 'Returned',
                'Property No.' => $equipment->property_number,
                'Inventory No.' => $equipment->inventory_number,
                'Qty' => $equipment->quantity,
                'Life Span remaining' => $equipment->formattedRemainingLifeSpan($return->date_returned),
                'Date Returned' => optional($return->date_returned)->toDateTimeString(),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<array{label: string, value: string}>
     */
    private function historyFields(array $values): array
    {
        $fields = [];

        foreach ($values as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $fields[] = [
                'label' => $label,
                'value' => is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value,
            ];
        }

        return $fields;
    }

    public function update(Request $request, Equipment $equipment): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('equipments', 'barcode')->ignore($equipment->id)],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'property_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'inventory_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'property_card_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'equipment_category_id' => ['required', 'exists:equipment_categories,id'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'serial_numbers' => ['required', 'array', 'min:1'],
            'serial_numbers.*' => ['required', 'string', 'max:255'],
            'life_span_years' => ['required', 'integer', 'min:0', 'max:100'],
            'specs' => ['nullable', 'string'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'property_class' => ['nullable', Rule::in(PropertyClass::keys())],
        ]);

        $numberChanges = $this->numberChanges($request, $equipment, $data);

        try {
            DB::transaction(function () use ($data, $equipment, $numberChanges) {
                $serialNumbers = $data['serial_numbers'];
                unset($data['serial_numbers'], $data['property_number'], $data['inventory_number'], $data['property_card_number']);

                $previous = [
                    'property_number' => $equipment->property_number,
                    'inventory_number' => $equipment->inventory_number,
                ];

                $equipment->update([
                    ...$data,
                    ...$numberChanges,
                    'brand' => $this->clean($data['brand'] ?? null),
                    'model' => $this->clean($data['model'] ?? null),
                ]);
                $equipment->syncInStockSerialNumbers($serialNumbers);

                // Units that carried the record's old number follow the corrected number.
                foreach (['property_number', 'inventory_number'] as $field) {
                    if (array_key_exists($field, $numberChanges) && $previous[$field]) {
                        $equipment->serialNumbers()->where($field, $previous[$field])->update([$field => $numberChanges[$field]]);
                    }
                }
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Equipments',
            "Updated equipment {$equipment->name} (".($equipment->inventory_number ?: $equipment->property_number).")"
        );

        return response()->json($equipment->load(['category', 'serialNumbers']));
    }

    /**
     * Official numbers changed in an edit. An unchanged number is never re-validated (no false
     * duplicate); a changed number is checked within its real scope, excluding this record.
     *
     * @return array<string, ?string>
     */
    private function numberChanges(Request $request, Equipment $equipment, array $data): array
    {
        $changes = [];
        $checks = [
            'property_number' => ['equipment_property_number', NumberScopes::equipmentProperty($equipment->id), true],
            'inventory_number' => ['equipment_inventory_number', NumberScopes::equipmentInventory($equipment->id), false],
            'property_card_number' => ['property_card_number', NumberScopes::propertyCard($equipment->id), false],
        ];

        foreach ($checks as $field => [$type, $exists, $required]) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $this->numbers->clean($data[$field]);
            if ($value === $this->numbers->clean($equipment->{$field})) {
                continue;
            }

            if (! $request->user()->isAdmin() && ! $request->user()->isSupplyOfficer()) {
                throw ValidationException::withMessages([$field => 'Only administrators and supply officers can change official numbers.']);
            }

            if ($value === null && $required) {
                throw ValidationException::withMessages([$field => 'The property number cannot be blank.']);
            }

            if ($value !== null && $exists($value)) {
                throw ValidationException::withMessages([$field => DocumentNumberService::TYPES[$type]['label']." {$value} is already used by another record."]);
            }

            $changes[$field] = $value;
        }

        return $changes;
    }

    /**
     * Correct the numbers recorded on one physical unit (serial).
     */
    public function updateUnit(Request $request, Equipment $equipment, EquipmentSerialNumber $unit): JsonResponse
    {
        abort_unless((int) $unit->equipment_id === (int) $equipment->id, 404);

        $data = $request->validate([
            'property_number' => ['nullable', 'string', 'max:255'],
            'inventory_number' => ['nullable', 'string', 'max:255'],
        ]);

        $property = $this->numbers->clean($data['property_number'] ?? null);
        if ($property !== null && strcasecmp($property, (string) $equipment->property_number) !== 0
            && NumberScopes::equipmentProperty($equipment->id)($property)) {
            throw ValidationException::withMessages(['property_number' => "Property number {$property} is the official number of another equipment record."]);
        }

        if ($property !== null) {
            $elsewhere = EquipmentSerialNumber::query()
                ->where('property_number', $property)
                ->where('equipment_id', '!=', $equipment->id)
                ->exists();
            if ($elsewhere) {
                throw ValidationException::withMessages(['property_number' => "Property number {$property} is recorded on a unit of another equipment record."]);
            }
        }

        $unit->update([
            'property_number' => $property,
            'inventory_number' => $this->numbers->clean($data['inventory_number'] ?? null),
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Equipments',
            "Recorded numbers for unit {$unit->serial_number} of {$equipment->name}: property {$property}"
        );

        return response()->json($unit->fresh());
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function updatePropertyNumber(Request $request, Equipment $equipment): JsonResponse
    {
        $data = $request->validate([
            'property_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('equipments', 'property_number')->ignore($equipment->id),
            ],
        ]);

        $propertyNumber = trim($data['property_number']);

        $equipment->update([
            'property_number' => $propertyNumber,
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Equipments',
            "Linked hard-copy property number {$propertyNumber} to equipment {$equipment->name}"
        );

        return response()->json($equipment->fresh()->load('category'));
    }

    public function destroy(Request $request, Equipment $equipment): JsonResponse
    {
        $name = $equipment->name;
        $equipment->serialNumbers()->delete();
        $equipment->delete();

        $this->activityLogService->log(
            $request->user(),
            'Deleted',
            'Equipments',
            "Deleted equipment {$name}"
        );

        return response()->json(['message' => 'Equipment moved to deleted data.']);
    }
}

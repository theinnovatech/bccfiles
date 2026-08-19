<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\IssuanceDetail;
use App\Models\ItemReturn;
use App\Services\ActivityLogService;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EquipmentController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Equipment::query()
                ->with(['category', 'sourceReturn'])
                ->orderBy('name')
                ->get()
        );
    }

    public function findByBarcode(string $barcode): JsonResponse
    {
        $equipment =             Equipment::query()
                ->with(['category', 'sourceReturn'])
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
            'remarks' => ['nullable', 'string'],
        ]);

        if (empty($data['equipment_id']) && empty($data['barcode'])) {
            return response()->json([
                'message' => 'Select an equipment or provide a barcode, property number, or inventory number.',
            ], 422);
        }

        $equipment = ! empty($data['equipment_id'])
            ? Equipment::query()->with('category')->findOrFail($data['equipment_id'])
            : Equipment::query()
                ->with('category')
                ->where(function ($query) use ($data) {
                    $query->where('barcode', $data['barcode'])
                        ->orWhere('property_number', $data['barcode'])
                        ->orWhere('inventory_number', $data['barcode']);
                })
                ->firstOrFail();

        $equipment->increment('quantity', $data['quantity']);
        $equipment->refresh()->load('category');

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
            'equipment_category_id' => ['required', 'exists:equipment_categories,id'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'life_span_years' => ['required', 'integer', 'min:1', 'max:100'],
            'specs' => ['nullable', 'string'],
        ]);

        $equipment = DB::transaction(function () use ($data) {
            return Equipment::create([
                ...$data,
                'property_number' => ReferenceNumberGenerator::forEquipment(),
                'inventory_number' => ReferenceNumberGenerator::forInventory(),
            ]);
        });

        $this->activityLogService->log(
            $request->user(),
            'Created',
            'Equipments',
            "Created equipment {$equipment->name} ({$equipment->inventory_number})"
        );

        return response()->json($equipment->load('category'), 201);
    }

    public function show(Equipment $equipment): JsonResponse
    {
        return response()->json($equipment->load(['category', 'sourceReturn']));
    }

    public function history(Equipment $equipment): JsonResponse
    {
        $equipment->load([
            'category',
            'sourceReturn.issuanceDetail.issuance.department',
            'sourceReturn.issuanceDetail.issuance.receiver',
            'sourceReturn.issuanceDetail.issuance.issuer',
            'sourceReturn.issuanceDetail.equipment.category',
            'sourceReturn.department',
            'sourceReturn.borrower',
            'sourceReturn.returner',
            'sourceReturn.equipment.category',
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

    private function registrationHistoryEvent(Equipment $equipment): array
    {
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
        $receivedBy = $issuance?->receiver?->name ?: $issuance?->received_by_name;

        return [
            'type' => 'issued',
            'title' => 'Issued',
            'sort_at' => optional($issuedAt)->toDateTimeString(),
            'order' => 2,
            'date' => optional($issuedAt)->toDateTimeString(),
            'fields' => $this->historyFields([
                'Issuance No.' => $issuance?->issuance_number,
                'Issuance Date' => optional($issuedAt)->toDateTimeString(),
                'Department' => $issuance?->department?->name,
                'Received By' => $receivedBy,
                'Issued By' => $issuance?->issuer?->name,
                'Property No.' => $detail->property_number,
                'Inventory No.' => $detail->inventory_number,
                'Qty' => $detail->quantity,
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
            'equipment_category_id' => ['required', 'exists:equipment_categories,id'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'life_span_years' => ['required', 'integer', 'min:0', 'max:100'],
            'specs' => ['nullable', 'string'],
        ]);

        $equipment->update($data);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Equipments',
            "Updated equipment {$equipment->name} (".($equipment->inventory_number ?: $equipment->property_number).")"
        );

        return response()->json($equipment->load('category'));
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

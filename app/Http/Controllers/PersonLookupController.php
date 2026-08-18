<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Equipment;
use App\Models\Issuance;
use App\Models\Item;
use App\Models\ItemReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PersonLookupController extends Controller
{
    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json([]);
        }

        $type = strtolower(trim((string) $request->query('type', 'all')));
        if (! in_array($type, ['all', 'person', 'item', 'equipment', 'catalog'], true)) {
            $type = 'all';
        }

        $like = '%'.$query.'%';
        $results = [];

        if ($type === 'all' || $type === 'person') {
            Employee::query()
                ->with('department')
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like);
                })
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->each(function (Employee $employee) use (&$results) {
                    $subtitle = collect([
                        $employee->employee_number ? 'ID: '.$employee->employee_number : null,
                        $employee->department?->name,
                        $employee->position,
                        'Received By',
                    ])->filter()->implode(' · ');

                    $results[] = [
                        'type' => 'person',
                        'key' => 'person:'.$employee->id,
                        'employee_id' => $employee->id,
                        'name' => $employee->name,
                        'label' => $employee->name,
                        'subtitle' => $subtitle,
                    ];
                });

            $existingNames = collect($results)
                ->pluck('name')
                ->map(fn ($name) => mb_strtolower(trim((string) $name)));

            Issuance::query()
                ->whereNotNull('received_by_name')
                ->where('received_by_name', '!=', '')
                ->whereRaw('LOWER(received_by_name) LIKE ?', ['%'.mb_strtolower($query).'%'])
                ->select('received_by_name')
                ->groupBy('received_by_name')
                ->orderBy('received_by_name')
                ->limit(10)
                ->pluck('received_by_name')
                ->each(function (?string $name) use (&$results, $existingNames) {
                    $name = trim((string) $name);
                    if ($name === '' || $existingNames->contains(mb_strtolower($name))) {
                        return;
                    }

                    $results[] = [
                        'type' => 'person',
                        'key' => 'person-name:'.mb_strtolower($name),
                        'employee_id' => null,
                        'name' => $name,
                        'label' => $name,
                        'subtitle' => 'Received By · typed name',
                    ];
                    $existingNames->push(mb_strtolower($name));
                });
        }

        if ($type === 'all' || $type === 'item' || $type === 'catalog') {
            Item::query()
                ->with('unit')
                ->where(function ($q) use ($like) {
                    $q->where('item_name', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhere('item_number', 'like', $like)
                        ->orWhere('inventory_number', 'like', $like);
                })
                ->orderBy('item_name')
                ->limit(10)
                ->get()
                ->each(function (Item $item) use (&$results) {
                    $subtitle = collect([
                        $item->barcode ? 'Barcode: '.$item->barcode : null,
                        $item->unit?->abbreviation ?? $item->unit?->name,
                    ])->filter()->implode(' · ');

                    $results[] = [
                        'type' => 'item',
                        'key' => 'item:'.$item->id,
                        'item_id' => $item->id,
                        'name' => $item->item_name,
                        'label' => $item->item_name,
                        'subtitle' => $subtitle,
                    ];
                });
        }

        if ($type === 'all' || $type === 'equipment' || $type === 'catalog') {
            Equipment::query()
                ->with('category')
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('property_number', 'like', $like)
                        ->orWhere('inventory_number', 'like', $like)
                        ->orWhere('barcode', 'like', $like);
                })
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->each(function (Equipment $equipment) use (&$results) {
                    $subtitle = collect([
                        $equipment->property_number ? 'Property: '.$equipment->property_number : null,
                        $equipment->category?->name,
                        $equipment->type,
                    ])->filter()->implode(' · ');

                    $results[] = [
                        'type' => 'equipment',
                        'key' => 'equipment:'.$equipment->id,
                        'equipment_id' => $equipment->id,
                        'name' => $equipment->name,
                        'label' => $equipment->name,
                        'subtitle' => $subtitle,
                    ];
                });
        }

        return response()->json($results);
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $employeeId = $data['employee_id'] ?? null;
        $name = trim((string) ($data['name'] ?? ''));

        if (! $employeeId && $name === '') {
            return response()->json([
                'message' => 'Select a person or type the Received By name to look up.',
            ], 422);
        }

        $matchedEmployees = Employee::query()
            ->with('department')
            ->when($employeeId, fn ($query) => $query->where('id', $employeeId))
            ->when(! $employeeId && $name !== '', function ($query) use ($name) {
                $like = '%'.$name.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like);
                });
            })
            ->get();

        $employee = $employeeId
            ? ($matchedEmployees->firstWhere('id', $employeeId) ?: $matchedEmployees->first())
            : $matchedEmployees->first();

        $employeeIds = $matchedEmployees->pluck('id')->filter()->values();
        $matchNames = collect([
            $name !== '' ? $name : null,
        ])
            ->merge($matchedEmployees->pluck('name'))
            ->filter()
            ->unique()
            ->values();

        $issuances = Issuance::query()
            ->with(['department', 'receiver', 'details.item.unit', 'details.equipment'])
            ->where(function ($query) use ($employeeIds, $matchNames, $name) {
                if ($employeeIds->isNotEmpty()) {
                    $query->whereIn('received_by', $employeeIds);
                }

                foreach ($matchNames as $matchName) {
                    $query->orWhereRaw('LOWER(received_by_name) LIKE ?', ['%'.mb_strtolower($matchName).'%']);
                }

                if ($name !== '') {
                    $like = '%'.$name.'%';
                    $query->orWhereHas('receiver', function ($receiver) use ($like) {
                        $receiver->where('name', 'like', $like)
                            ->orWhere('employee_number', 'like', $like);
                    });
                }
            })
            ->orderByDesc('issued_date')
            ->get();

        $returns = ItemReturn::query()
            ->with(['department', 'equipment', 'item', 'borrower', 'issuanceDetail'])
            ->whereNotNull('equipment_id')
            ->where(function ($query) use ($employeeIds, $matchNames, $name) {
                if ($employeeIds->isNotEmpty()) {
                    $query->whereIn('borrower_employee_id', $employeeIds);
                }

                foreach ($matchNames as $matchName) {
                    $query->orWhereRaw('LOWER(borrower_name) LIKE ?', ['%'.mb_strtolower($matchName).'%']);
                }

                if ($name !== '') {
                    $like = '%'.$name.'%';
                    $query->orWhereHas('borrower', function ($borrower) use ($like) {
                        $borrower->where('name', 'like', $like)
                            ->orWhere('employee_number', 'like', $like);
                    });
                }
            })
            ->orderByDesc('date_returned')
            ->get();

        $itemsReceived = $this->mapItemLines($issuances);
        $equipmentBorrowed = $this->mapEquipmentLines($issuances);
        $equipmentReturned = $this->mapReturnLines($returns);
        $equipmentOutstanding = $this->mapOutstandingEquipment($equipmentBorrowed, $equipmentReturned);

        $displayName = $employee?->name
            ?? ($name !== '' ? $name : ($issuances->first()?->receiver?->name
                ?? $issuances->first()?->received_by_name
                ?? $returns->first()?->borrowerDisplayName()
                ?? 'Unknown'));

        return response()->json([
            'target' => [
                'type' => 'person',
                'employee_id' => $employee?->id,
                'name' => $displayName,
                'employee_number' => $employee?->employee_number,
                'department' => $employee?->department?->name
                    ?? $issuances->first()?->department?->name
                    ?? $returns->first()?->department?->name,
                'position' => $employee?->position,
            ],
            'summary' => [
                'items_received' => $itemsReceived->sum('quantity'),
                'equipment_borrowed' => $equipmentBorrowed->sum('quantity'),
                'equipment_returned' => $equipmentReturned->sum('quantity'),
                'equipment_outstanding' => $equipmentOutstanding->sum('outstanding_quantity'),
                'issuance_records' => $issuances->count(),
                'return_records' => $returns->count(),
            ],
            'items_received' => $itemsReceived->values(),
            'equipment_borrowed' => $equipmentBorrowed->values(),
            'equipment_returned' => $equipmentReturned->values(),
            'equipment_outstanding' => $equipmentOutstanding->values(),
        ]);
    }

    public function byItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $itemId = $data['item_id'] ?? null;
        $query = trim((string) ($data['q'] ?? ''));

        if (! $itemId && $query === '') {
            return response()->json([
                'message' => 'Select an item or enter a name/barcode to look up.',
            ], 422);
        }

        $item = $itemId
            ? Item::query()->with(['unit', 'category'])->findOrFail($itemId)
            : Item::query()
                ->with(['unit', 'category'])
                ->where(function ($q) use ($query) {
                    $like = '%'.$query.'%';
                    $q->where('item_name', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhere('item_number', 'like', $like)
                        ->orWhere('inventory_number', 'like', $like);
                })
                ->orderByRaw('CASE WHEN barcode = ? THEN 0 WHEN item_name = ? THEN 1 ELSE 2 END', [$query, $query])
                ->orderBy('item_name')
                ->first();

        if (! $item) {
            return response()->json([
                'message' => 'No item matched that search.',
            ], 404);
        }

        $issuances = Issuance::query()
            ->with([
                'department',
                'receiver',
                'details' => fn ($q) => $q->where('item_id', $item->id),
                'details.item.unit',
            ])
            ->whereHas('details', fn ($q) => $q->where('item_id', $item->id))
            ->orderByDesc('issued_date')
            ->get();

        $recipients = $issuances->flatMap(function (Issuance $issuance) use ($item) {
            return $issuance->details
                ->filter(fn ($detail) => (int) $detail->item_id === (int) $item->id)
                ->map(fn ($detail) => [
                    'issuance_number' => $issuance->issuance_number,
                    'issued_date' => optional($issuance->issued_date)?->toDateString(),
                    'person_name' => $issuance->receiver?->name
                        ?? $issuance->received_by_name
                        ?? '—',
                    'department' => $issuance->department?->name ?? '—',
                    'barcode' => $detail->barcode ?: ($item->barcode ?? '—'),
                    'quantity' => (int) $detail->quantity,
                    'unit' => $item->unit?->abbreviation ?? $item->unit?->name ?? '—',
                ]);
        })->values();

        return response()->json([
            'target' => [
                'type' => 'item',
                'item_id' => $item->id,
                'name' => $item->item_name,
                'barcode' => $item->barcode,
                'item_number' => $item->item_number,
                'inventory_number' => $item->inventory_number,
                'category' => $item->category?->name,
                'unit' => $item->unit?->abbreviation ?? $item->unit?->name,
                'current_stock' => (int) $item->current_stock,
            ],
            'summary' => [
                'total_quantity_issued' => (int) $recipients->sum('quantity'),
                'unique_recipients' => $recipients
                    ->pluck('person_name')
                    ->filter(fn ($n) => $n && $n !== '—')
                    ->unique()
                    ->count(),
                'issuance_records' => $issuances->count(),
            ],
            'recipients' => $recipients,
        ]);
    }

    public function byEquipment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['nullable', 'integer', 'exists:equipments,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $equipmentId = $data['equipment_id'] ?? null;
        $query = trim((string) ($data['q'] ?? ''));

        if (! $equipmentId && $query === '') {
            return response()->json([
                'message' => 'Select equipment or enter a name/property number to look up.',
            ], 422);
        }

        $equipment = $equipmentId
            ? Equipment::query()->with('category')->findOrFail($equipmentId)
            : Equipment::query()
                ->with('category')
                ->where(function ($q) use ($query) {
                    $like = '%'.$query.'%';
                    $q->where('name', 'like', $like)
                        ->orWhere('property_number', 'like', $like)
                        ->orWhere('inventory_number', 'like', $like)
                        ->orWhere('barcode', 'like', $like);
                })
                ->orderByRaw(
                    'CASE WHEN property_number = ? THEN 0 WHEN barcode = ? THEN 1 WHEN name = ? THEN 2 ELSE 3 END',
                    [$query, $query, $query]
                )
                ->orderBy('name')
                ->first();

        if (! $equipment) {
            return response()->json([
                'message' => 'No equipment matched that search.',
            ], 404);
        }

        $issuances = Issuance::query()
            ->with([
                'department',
                'receiver',
                'details' => fn ($q) => $q->where('equipment_id', $equipment->id),
            ])
            ->whereHas('details', fn ($q) => $q->where('equipment_id', $equipment->id))
            ->orderByDesc('issued_date')
            ->get();

        $borrowers = $issuances->flatMap(function (Issuance $issuance) use ($equipment) {
            return $issuance->details
                ->filter(fn ($detail) => (int) $detail->equipment_id === (int) $equipment->id)
                ->map(fn ($detail) => [
                    'issuance_number' => $issuance->issuance_number,
                    'issued_date' => optional($issuance->issued_date)?->toDateString(),
                    'person_name' => $issuance->receiver?->name
                        ?? $issuance->received_by_name
                        ?? '—',
                    'department' => $issuance->department?->name ?? '—',
                    'quantity' => (int) $detail->quantity,
                    'property_number' => $detail->property_number
                        ?: ($detail->equipment?->property_number ?? '—'),
                ]);
        })->values();

        $returnsRaw = ItemReturn::query()
            ->with(['department', 'borrower'])
            ->where('equipment_id', $equipment->id)
            ->orderByDesc('date_returned')
            ->get();

        $returns = $returnsRaw->map(fn (ItemReturn $return) => [
            'date_returned' => optional($return->date_returned)?->toDateString(),
            'person_name' => $return->borrower?->name
                ?? $return->borrower_name
                ?? '—',
            'department' => $return->department?->name ?? '—',
            'quantity' => (int) $return->quantity,
            'remarks' => $return->reason,
        ])->values();

        $issued = (int) $borrowers->sum('quantity');
        $returned = (int) $returns->sum('quantity');

        return response()->json([
            'target' => [
                'type' => 'equipment',
                'equipment_id' => $equipment->id,
                'name' => $equipment->name,
                'property_number' => $equipment->property_number,
                'inventory_number' => $equipment->inventory_number,
                'barcode' => $equipment->barcode,
                'category' => $equipment->category?->name,
                'type_label' => $equipment->type,
                'specs' => $equipment->specs,
            ],
            'summary' => [
                'total_issued' => $issued,
                'total_returned' => $returned,
                'outstanding' => max(0, $issued - $returned),
                'unique_borrowers' => $borrowers
                    ->pluck('person_name')
                    ->filter(fn ($n) => $n && $n !== '—')
                    ->unique()
                    ->count(),
            ],
            'borrowers' => $borrowers,
            'returns' => $returns,
        ]);
    }

    private function mapItemLines(Collection $issuances): Collection
    {
        return $issuances
            ->flatMap(function (Issuance $issuance) {
                return $issuance->details
                    ->filter(fn ($detail) => $detail->item_id)
                    ->map(fn ($detail) => [
                        'issuance_number' => $issuance->issuance_number,
                        'issued_date' => optional($issuance->issued_date)?->toDateString(),
                        'department' => $issuance->department?->name,
                        'item_name' => $detail->item?->item_name ?? '—',
                        'barcode' => $detail->barcode ?: ($detail->item?->barcode ?? '—'),
                        'unit' => $detail->item?->unit?->abbreviation
                            ?? $detail->item?->unit?->name
                            ?? '—',
                        'quantity' => (int) $detail->quantity,
                    ]);
            })
            ->values();
    }

    private function mapEquipmentLines(Collection $issuances): Collection
    {
        return $issuances
            ->flatMap(function (Issuance $issuance) {
                return $issuance->details
                    ->filter(fn ($detail) => $detail->equipment_id)
                    ->map(fn ($detail) => [
                        'issuance_number' => $issuance->issuance_number,
                        'issued_date' => optional($issuance->issued_date)?->toDateString(),
                        'department' => $issuance->department?->name,
                        'equipment_id' => $detail->equipment_id,
                        'equipment_name' => $detail->equipment?->name ?? '—',
                        'property_number' => $this->issuedPropertyNumber($detail),
                        'specs' => $detail->equipment?->specs,
                        'quantity' => (int) $detail->quantity,
                    ]);
            })
            ->values();
    }

    private function mapReturnLines(Collection $returns): Collection
    {
        return $returns->map(fn (ItemReturn $return) => [
            'return_id' => $return->id,
            'date_returned' => optional($return->date_returned)?->toDateString(),
            'department' => $return->department?->name,
            'equipment_id' => $return->equipment_id,
            'equipment_name' => $return->equipment?->name
                ?? $return->custom_equipment_name
                ?? '—',
            'property_number' => $this->returnedPropertyNumber($return),
            'specs' => $return->equipment?->specs,
            'quantity' => (int) $return->quantity,
            'remarks' => $return->reason,
        ])->values();
    }

    private function mapOutstandingEquipment(Collection $borrowed, Collection $returned): Collection
    {
        $issuedGroups = $borrowed->groupBy(
            fn (array $row) => ($row['equipment_id'] ?? '').'|'.($row['property_number'] ?? '')
        );
        $returnedGroups = $returned->groupBy(
            fn (array $row) => ($row['equipment_id'] ?? '').'|'.($row['property_number'] ?? '')
        );

        return $issuedGroups
            ->map(function (Collection $rows, string $key) use ($returnedGroups) {
                $issuedQty = (int) $rows->sum('quantity');
                $returnedQty = (int) ($returnedGroups->get($key)?->sum('quantity') ?? 0);
                $outstanding = max(0, $issuedQty - $returnedQty);
                $sample = $rows->first();

                return [
                    'equipment_id' => $sample['equipment_id'] ?? null,
                    'equipment_name' => $sample['equipment_name'] ?? '—',
                    'property_number' => $sample['property_number'] ?? '—',
                    'specs' => $sample['specs'] ?? null,
                    'issued_quantity' => $issuedQty,
                    'returned_quantity' => $returnedQty,
                    'outstanding_quantity' => $outstanding,
                ];
            })
            ->filter(fn (array $row) => $row['outstanding_quantity'] > 0)
            ->values();
    }

    private function issuedPropertyNumber($detail): string
    {
        $issued = trim((string) ($detail->property_number ?? ''));
        if ($issued !== '') {
            return $issued;
        }

        return trim((string) ($detail->equipment?->property_number ?? '')) ?: '—';
    }

    private function returnedPropertyNumber(ItemReturn $return): string
    {
        $issued = trim((string) ($return->issuanceDetail?->property_number ?? ''));
        if ($issued !== '') {
            return $issued;
        }

        $custom = trim((string) ($return->custom_property_number ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        return trim((string) ($return->equipment?->property_number ?? '')) ?: '—';
    }
}

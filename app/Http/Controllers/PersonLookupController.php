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

        $like = '%'.$query.'%';
        $results = [];

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
                'message' => 'Select an employee or type a name to look up.',
            ], 422);
        }

        $employee = $employeeId
            ? Employee::query()->with('department')->find($employeeId)
            : null;

        $matchNames = collect([
            $employee?->name,
            $name !== '' ? $name : null,
        ])->filter()->unique()->values();

        $issuances = Issuance::query()
            ->with(['department', 'receiver', 'details.item.unit', 'details.equipment'])
            ->where(function ($query) use ($employeeId, $matchNames, $name) {
                if ($employeeId) {
                    $query->where(function ($inner) use ($employeeId, $matchNames) {
                        $inner->where('received_by', $employeeId);

                        foreach ($matchNames as $matchName) {
                            $inner->orWhereRaw('LOWER(received_by_name) = ?', [mb_strtolower($matchName)]);
                        }
                    });

                    return;
                }

                $query->where('received_by_name', 'like', '%'.$name.'%')
                    ->orWhereHas('receiver', function ($receiver) use ($name) {
                        $receiver->where('name', 'like', '%'.$name.'%');
                    });
            })
            ->orderByDesc('issued_date')
            ->get();

        $returns = ItemReturn::query()
            ->with(['department', 'equipment', 'item', 'borrower'])
            ->whereNotNull('equipment_id')
            ->where(function ($query) use ($employeeId, $matchNames, $name) {
                if ($employeeId) {
                    $query->where(function ($inner) use ($employeeId, $matchNames) {
                        $inner->where('borrower_employee_id', $employeeId);

                        foreach ($matchNames as $matchName) {
                            $inner->orWhereRaw('LOWER(borrower_name) = ?', [mb_strtolower($matchName)]);
                        }
                    });

                    return;
                }

                $query->where('borrower_name', 'like', '%'.$name.'%')
                    ->orWhereHas('borrower', function ($borrower) use ($name) {
                        $borrower->where('name', 'like', '%'.$name.'%');
                    });
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
            'item_id' => ['required', 'integer', 'exists:items,id'],
        ]);

        $item = Item::query()->with(['unit', 'category'])->findOrFail($data['item_id']);

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
            'equipment_id' => ['required', 'integer', 'exists:equipments,id'],
        ]);

        $equipment = Equipment::query()->with('category')->findOrFail($data['equipment_id']);

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
                'name' => $equipment->name,
                'property_number' => $equipment->property_number,
                'inventory_number' => $equipment->inventory_number,
                'barcode' => $equipment->barcode,
                'category' => $equipment->category?->name,
                'type_label' => $equipment->type,
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
                        'property_number' => $detail->equipment?->property_number ?? '—',
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
            'equipment_name' => $return->equipment?->name ?? '—',
            'property_number' => $return->equipment?->property_number ?? '—',
            'quantity' => (int) $return->quantity,
            'remarks' => $return->reason,
        ])->values();
    }

    private function mapOutstandingEquipment(Collection $borrowed, Collection $returned): Collection
    {
        $issuedByEquipment = $borrowed->groupBy('equipment_id')->map(
            fn (Collection $rows) => $rows->sum('quantity')
        );

        $returnedByEquipment = $returned->groupBy('equipment_id')->map(
            fn (Collection $rows) => $rows->sum('quantity')
        );

        return $issuedByEquipment
            ->map(function (int $issuedQty, $equipmentId) use ($borrowed, $returnedByEquipment) {
                $returnedQty = (int) ($returnedByEquipment[$equipmentId] ?? 0);
                $outstanding = max(0, $issuedQty - $returnedQty);
                $sample = $borrowed->firstWhere('equipment_id', $equipmentId);

                return [
                    'equipment_id' => $equipmentId,
                    'equipment_name' => $sample['equipment_name'] ?? '—',
                    'property_number' => $sample['property_number'] ?? '—',
                    'issued_quantity' => $issuedQty,
                    'returned_quantity' => $returnedQty,
                    'outstanding_quantity' => $outstanding,
                ];
            })
            ->filter(fn (array $row) => $row['outstanding_quantity'] > 0)
            ->values();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Issuance;
use App\Models\ItemReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PersonLookupController extends Controller
{
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
            'person' => [
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

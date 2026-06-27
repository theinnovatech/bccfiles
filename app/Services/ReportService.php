<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Equipment;
use App\Models\Issuance;
use App\Models\IssuanceDetail;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class ReportService
{
    public function inventory(): Collection
    {
        return Item::query()
            ->with(['category', 'unit', 'location'])
            ->orderBy('item_name')
            ->get();
    }

    public function equipmentInventory(): Collection
    {
        return Equipment::query()
            ->with('category')
            ->orderBy('name')
            ->get();
    }

    public function stockMovements(?string $from = null, ?string $to = null): Collection
    {
        $query = StockMovement::query()->with(['item', 'performer'])->orderByDesc('created_at');

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query->get();
    }

    public function stockCard(
        int $itemId,
        ?string $from = null,
        ?string $to = null,
        ?string $transactionType = null,
    ): Collection {
        $query = StockMovement::query()
            ->with(['item.category', 'item.unit', 'item.location', 'performer'])
            ->where('item_id', $itemId)
            ->orderBy('created_at');

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($transactionType) {
            $query->where('transaction_type', $transactionType);
        }

        $movements = $query->get();

        $issuanceDepartments = Issuance::query()
            ->whereHas('details', fn ($q) => $q->where('item_id', $itemId))
            ->with('request.department')
            ->get()
            ->keyBy('issuance_number');

        $returns = ItemReturn::query()
            ->with('issuance.request.department')
            ->where('item_id', $itemId)
            ->get();

        return $movements->map(function (StockMovement $movement) use ($issuanceDepartments, $returns) {
            $movement->setAttribute(
                'department_office',
                $this->departmentOfficeForMovement($movement, $issuanceDepartments, $returns)
            );

            return $movement;
        });
    }

    public function propertyCard(
        int $equipmentId,
        ?string $from = null,
        ?string $to = null,
        ?string $movementType = null,
    ): Collection {
        $equipment = Equipment::query()->with('category')->findOrFail($equipmentId);

        $issuanceDetails = IssuanceDetail::query()
            ->where('equipment_id', $equipmentId)
            ->with(['issuance.request.department', 'issuance.receiver', 'issuance.issuer'])
            ->whereHas('issuance', function ($query) use ($from, $to) {
                if ($from) {
                    $query->whereDate('issued_date', '>=', $from);
                }

                if ($to) {
                    $query->whereDate('issued_date', '<=', $to);
                }
            })
            ->get()
            ->sortBy(fn (IssuanceDetail $detail) => $detail->issuance?->issued_date ?? $detail->created_at);

        $totalIssued = IssuanceDetail::query()
            ->where('equipment_id', $equipmentId)
            ->sum('quantity');

        $initialQty = $equipment->quantity + $totalIssued;
        $rows = collect();
        $balance = 0;

        $includeRegistration = $movementType !== 'issue';
        $registrationDate = $equipment->created_at;

        if ($includeRegistration) {
            $withinRange = true;

            if ($from && $registrationDate->lt(\Illuminate\Support\Carbon::parse($from)->startOfDay())) {
                $withinRange = false;
            }

            if ($to && $registrationDate->gt(\Illuminate\Support\Carbon::parse($to)->endOfDay())) {
                $withinRange = false;
            }

            if ($withinRange) {
                $balance = $initialQty;
                $rows->push([
                    'movement_date' => $registrationDate->toDateTimeString(),
                    'reference_number' => $equipment->property_number,
                    'receipt_qty' => $initialQty,
                    'issue_qty' => null,
                    'office_officer' => '',
                    'balance_qty' => $balance,
                    'amount' => '',
                    'remarks' => 'Registration',
                ]);
            } else {
                $balance = $initialQty;
            }
        } else {
            $balance = $initialQty;
        }

        if ($movementType !== 'receipt') {
            foreach ($issuanceDetails as $detail) {
                $balance -= $detail->quantity;
                $department = $detail->issuance?->request?->department?->name ?? '';
                $receiver = $detail->issuance?->receiver?->name ?? '';
                $officeOfficer = trim(implode(' / ', array_filter([$department, $receiver])));

                $rows->push([
                    'movement_date' => ($detail->issuance?->issued_date ?? $detail->created_at)->toDateTimeString(),
                    'reference_number' => $detail->issuance?->issuance_number ?? '',
                    'receipt_qty' => null,
                    'issue_qty' => $detail->quantity,
                    'office_officer' => $officeOfficer,
                    'balance_qty' => max($balance, 0),
                    'amount' => '',
                    'remarks' => $department ? "Issued to {$department}" : 'Issuance',
                ]);
            }
        }

        return $rows->values();
    }

    private function departmentOfficeForMovement(
        StockMovement $movement,
        Collection $issuanceDepartments,
        Collection $returns,
    ): string {
        if ($movement->transaction_type === TransactionType::Out) {
            if (preg_match('/Issuance\s+(\S+)/i', $movement->remarks ?? '', $matches)) {
                return $issuanceDepartments[$matches[1]]?->request?->department?->name ?? '—';
            }

            return '—';
        }

        if ($movement->transaction_type === TransactionType::Return) {
            $matchedReturn = $returns->first(function (ItemReturn $return) use ($movement) {
                if ($return->quantity !== abs($movement->quantity)) {
                    return false;
                }

                if (! $return->date_returned || ! $movement->created_at) {
                    return false;
                }

                return abs($return->date_returned->diffInSeconds($movement->created_at)) <= 60;
            });

            return $matchedReturn?->issuance?->request?->department?->name ?? '—';
        }

        return '—';
    }

    public function issuances(?int $departmentId = null): Collection
    {
        $query = Issuance::query()->with(['request.department', 'issuer', 'receiver', 'details.item', 'details.equipment']);

        if ($departmentId) {
            $query->whereHas('request', fn ($q) => $q->where('department_id', $departmentId));
        }

        return $query->orderByDesc('issued_date')->get();
    }

    public function returns(): Collection
    {
        return ItemReturn::query()->with(['item', 'returner', 'issuance'])->orderByDesc('date_returned')->get();
    }

    public function lowStock(): Collection
    {
        return Item::query()
            ->with(['category', 'unit', 'location'])
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->orderBy('current_stock')
            ->get();
    }

    public function physicalInventory(): Collection
    {
        return StockCountItem::query()
            ->with(['session.starter', 'item'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function monthlyConsumption(): Collection
    {
        return StockMovement::query()
            ->selectRaw('departments.name as department, SUM(stock_movements.quantity) as total')
            ->join('issuance_details', function ($join) {
                $join->on('issuance_details.item_id', '=', 'stock_movements.item_id');
            })
            ->join('issuances', 'issuances.id', '=', 'issuance_details.issuance_id')
            ->join('supply_requests', 'supply_requests.id', '=', 'issuances.request_id')
            ->join('departments', 'departments.id', '=', 'supply_requests.department_id')
            ->where('stock_movements.transaction_type', TransactionType::Out->value)
            ->whereYear('stock_movements.created_at', now()->year)
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total')
            ->get();
    }
}

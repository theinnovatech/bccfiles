<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Equipment;
use App\Models\Issuance;
use App\Models\IssuanceDetail;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Models\StockMovement;
use App\Support\StockStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function inventory(?string $stockStatus = null): Collection
    {
        return Item::query()
            ->with(['category', 'unit', 'location'])
            ->when($stockStatus, fn ($query) => $query->withStockStatus($stockStatus))
            ->orderBy('item_name')
            ->get();
    }

    public function equipmentInventory(): Collection
    {
        return Equipment::query()
            ->with(['category', 'sourceReturn'])
            ->orderBy('name')
            ->get();
    }

    public function stockMovements(?string $from = null, ?string $to = null, ?string $transactionType = null): Collection
    {
        $query = StockMovement::query()->with(['item', 'performer'])->orderByDesc('created_at');

        if ($transactionType) {
            $query->where('transaction_type', $transactionType);
        }

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
            ->with(['department', 'receiver.department'])
            ->get()
            ->keyBy('issuance_number');

        $returns = ItemReturn::query()
            ->with(['issuance.department', 'issuance.receiver.department'])
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
            ->with(['issuance.department', 'issuance.receiver', 'issuance.issuer'])
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
                $department = $detail->issuance?->departmentDisplayName() ?? '';
                $receiver = $detail->issuance?->recipientDisplayName()
                    ?? '';
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
                $issuance = $issuanceDepartments[$matches[1]] ?? null;

                return $issuance?->departmentDisplayName() ?? '—';
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

            $issuance = $matchedReturn?->issuance;

            return $issuance?->departmentDisplayName() ?? '—';
        }

        return '—';
    }

    public function issuances(?int $departmentId = null, ?string $from = null, ?string $to = null): Collection
    {
        $query = Issuance::query()->with([
            'department',
            'issuer',
            'receiver.department',
            'details.item',
            'details.equipment',
        ]);

        if ($departmentId) {
            $query->where(function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('department_id')
                        ->whereHas('receiver', fn ($receiver) => $receiver->where('department_id', $departmentId)));
            });
        }

        if ($from) {
            $query->whereDate('issued_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('issued_date', '<=', $to);
        }

        return $query->orderByDesc('issued_date')->get();
    }

    public function returns(?string $from = null, ?string $to = null, ?string $condition = null, ?int $departmentId = null): Collection
    {
        return ItemReturn::query()
            ->with(['equipment', 'department', 'borrower', 'returner', 'issuanceDetail'])
            ->whereNotNull('equipment_id')
            ->when($from, fn ($query) => $query->whereDate('date_returned', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('date_returned', '<=', $to))
            ->when($condition, fn ($query) => $query->where('condition', $condition))
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderByDesc('date_returned')
            ->get();
    }

    public function lowStock(?string $stockStatus = null): Collection
    {
        $status = in_array($stockStatus, [StockStatus::LOW, StockStatus::OUT], true)
            ? $stockStatus
            : 'alerting';

        return Item::query()
            ->with(['category', 'unit', 'location'])
            ->withStockStatus($status)
            ->orderBy('current_stock')
            ->get();
    }

    /**
     * Supplies issued per department for a year.
     *
     * Sums the supply lines of each issuance once, attributed to the unit recorded on that
     * issuance (snapshot name first, then the linked department). The previous version joined
     * every OUT stock movement to every issuance line of the same item, so each movement was
     * counted once per issuance of that item and credited to every department that ever
     * received it; legacy issuances with only a typed department name were dropped.
     */
    public function monthlyConsumption(?int $year = null): Collection
    {
        $lines = IssuanceDetail::query()
            ->join('issuances', function ($join) {
                $join->on('issuances.id', '=', 'issuance_details.issuance_id')
                    ->whereNull('issuances.deleted_at');
            })
            ->leftJoin('departments', 'departments.id', '=', 'issuances.department_id')
            ->whereNotNull('issuance_details.item_id')
            ->whereYear('issuances.issued_date', $year ?: now()->year)
            ->selectRaw("COALESCE(NULLIF(issuances.department_name, ''), departments.name, 'Unspecified') as department")
            ->addSelect('issuance_details.quantity');

        // Grouped in an outer query so strict ONLY_FULL_GROUP_BY (MySQL and MariaDB) accepts it.
        return DB::query()
            ->fromSub($lines->toBase(), 'consumption_lines')
            ->selectRaw('department, SUM(quantity) as total')
            ->groupBy('department')
            ->orderByDesc('total')
            ->orderBy('department')
            ->get();
    }
}

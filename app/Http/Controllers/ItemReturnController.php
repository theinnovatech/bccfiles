<?php

namespace App\Http\Controllers;

use App\Enums\ReturnCondition;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\IssuanceDetail;
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
                ->with(['equipment.category', 'department', 'borrower', 'returner', 'issuanceDetail.issuance'])
                ->where(function ($query) {
                    $query->whereNotNull('equipment_id')
                        ->orWhereNotNull('custom_equipment_name');
                })
                ->orderByDesc('id')
                ->get()
        );
    }

    public function returnedEquipments(): JsonResponse
    {
        return response()->json(
            ItemReturn::query()
                ->with(['equipment.category', 'department', 'borrower', 'returner', 'issuanceDetail.issuance'])
                ->where(function ($query) {
                    $query->whereNotNull('equipment_id')
                        ->orWhereNotNull('custom_equipment_name');
                })
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:255'],
            'equipment_id' => ['nullable', 'exists:equipments,id'],
            'issuance_detail_id' => ['nullable', 'exists:issuance_details,id'],
            'property_number' => ['nullable', 'string', 'max:255'],
            'inventory_number' => ['nullable', 'string', 'max:255'],
            'custom_equipment_name' => ['nullable', 'string', 'max:255'],
            'custom_property_number' => ['nullable', 'string', 'max:255'],
            'custom_inventory_number' => ['nullable', 'string', 'max:255'],
            'custom_equipment_type' => ['nullable', 'string', 'max:255'],
            'custom_equipment_category' => ['nullable', 'string', 'max:255'],
            'custom_date_issued' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:custom_date_acquired'],
            'custom_date_acquired' => ['nullable', 'date', 'before_or_equal:today'],
            'custom_specs' => ['nullable', 'string'],
            'custom_details' => ['nullable', 'string'],
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

        if (empty($data['issuance_detail_id']) && ! $equipmentId && $customName === '') {
            return response()->json([
                'message' => 'Enter a property number from issued equipment, or use Custom Equipment for past data.',
            ], 422);
        }

        if (! $borrowerEmployeeId && $borrowerName === '') {
            return response()->json([
                'message' => 'Please select or type the name of the person returning the equipment.',
            ], 422);
        }

        if ($customName === '' && empty($data['issuance_detail_id'])) {
            return response()->json([
                'message' => 'Enter a property number from issued equipment. Available Supply Master stock cannot be returned here.',
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
                $detail = null;
                $reachedLifespan = false;
                $equipment = null;

                if (! empty($data['issuance_detail_id'])) {
                    $detail = IssuanceDetail::query()
                        ->with('equipment')
                        ->findOrFail($data['issuance_detail_id']);
                    $equipmentId = $detail->equipment_id;
                }

                if ($equipmentId) {
                    $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipmentId);
                    $acquired = $detail?->date_acquired ?: $equipment->date_acquired;
                    $reachedLifespan = $equipment->hasReachedLifespan($dateReturned, $acquired);

                    if ($detail) {
                        $alreadyReturned = ItemReturn::query()
                            ->where('issuance_detail_id', $detail->id)
                            ->sum('quantity');
                        $outstanding = (int) $detail->quantity - (int) $alreadyReturned;
                        if ($data['quantity'] > $outstanding) {
                            throw new InvalidArgumentException(
                                "Only {$outstanding} outstanding unit(s) can be returned for this issuance."
                            );
                        }
                    }
                }

                $referenceNumber = trim((string) ($data['reference_number'] ?? ''));

                $return = ItemReturn::create([
                    'reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
                    'issuance_id' => $detail?->issuance_id,
                    'issuance_detail_id' => $detail?->id,
                    'equipment_id' => $equipmentId,
                    'custom_equipment_name' => $equipmentId ? null : $customName,
                    'custom_property_number' => $equipmentId ? null : ($data['custom_property_number'] ?? null),
                    'custom_inventory_number' => $equipmentId ? null : ($data['custom_inventory_number'] ?? null),
                    'custom_equipment_type' => $equipmentId ? null : ($data['custom_equipment_type'] ?? null),
                    'custom_equipment_category' => $equipmentId ? null : ($data['custom_equipment_category'] ?? null),
                    'custom_date_issued' => $equipmentId ? null : ($data['custom_date_issued'] ?? null),
                    'custom_date_acquired' => $equipmentId ? null : ($data['custom_date_acquired'] ?? null),
                    'custom_specs' => $equipmentId ? null : (filled($data['custom_specs'] ?? null) ? trim((string) $data['custom_specs']) : null),
                    'custom_details' => $equipmentId ? null : (filled($data['custom_details'] ?? null) ? trim((string) $data['custom_details']) : null),
                    'department_id' => $data['department_id'],
                    'borrower_employee_id' => $borrowerEmployeeId,
                    'borrower_name' => $borrowerEmployeeId ? null : $borrowerName,
                    'quantity' => $data['quantity'],
                    'reason' => $data['reason'] ?? null,
                    'condition' => $condition,
                    'restocked' => false,
                    'returned_by' => $request->user()->id,
                    'date_returned' => $dateReturned,
                ]);

                if ($equipmentId && $condition === ReturnCondition::Good && ! $reachedLifespan) {
                    $this->returnToSupplyMaster($equipment, $return, $detail, $data, $dateReturned);
                    $return->update(['restocked' => true]);
                    $restocked = true;
                }

                return $return->refresh();
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $return->load(['equipment.category', 'department', 'borrower', 'returner', 'issuanceDetail']);
        $return->equipment?->refresh();

        $equipmentLabel = $return->equipment?->name ?? $return->custom_equipment_name ?? 'custom equipment';
        $restockNote = $return->restocked ? ' and added to Supply Master as returned equipment' : '';
        $returnedStock = Equipment::query()->where('source_return_id', $return->id)->first();
        $asOf = $return->date_returned;
        $remainingLabel = $returnedStock?->formattedRemainingLifeSpan($asOf)
            ?? $return->equipment?->formattedRemainingLifeSpan(
                $asOf,
                $return->issuanceDetail?->date_acquired
            );
        $lifeSpanNote = $remainingLabel ? " Remaining life span: {$remainingLabel}." : '';

        $this->activityLogService->log(
            $request->user(),
            'Returned',
            'Returns',
            "Recorded return of {$return->quantity} unit(s) of {$equipmentLabel}{$restockNote}.{$lifeSpanNote}"
        );

        return response()->json($return, 201);
    }

    private function returnToSupplyMaster(
        Equipment $source,
        ItemReturn $return,
        ?IssuanceDetail $detail,
        array $data,
        Carbon $dateReturned
    ): void {
        $acquired = $detail?->date_acquired ?: $source->date_acquired;
        $expires = $source->expiryFromAcquired($acquired);
        $remaining = $source->remainingLifeSpanYears($dateReturned, $acquired) ?? $source->life_span_years ?? 0;

        if ($source->isReturnedStock()) {
            $source->reduceRemainingLifeSpan($dateReturned);
            $source->increment('quantity', $return->quantity);
            return;
        }

        $preferredProperty = trim((string) ($data['property_number'] ?? ''))
            ?: trim((string) ($detail?->property_number ?? ''))
            ?: (string) $source->property_number;
        $preferredInventory = trim((string) ($data['inventory_number'] ?? ''))
            ?: trim((string) ($detail?->inventory_number ?? ''))
            ?: (string) $source->inventory_number;

        Equipment::create([
            'name' => $source->name,
            'property_number' => $this->uniquePropertyNumber($preferredProperty, $source->id),
            'inventory_number' => $this->uniqueInventoryNumber($preferredInventory, $source->id),
            'barcode' => null,
            'equipment_category_id' => $source->equipment_category_id,
            'description' => $source->description,
            'type' => $source->type,
            'quantity' => $return->quantity,
            'life_span_years' => $remaining,
            'specs' => $source->specs,
            'date_acquired' => $acquired,
            'lifespan_expires_on' => $expires?->toDateString(),
            'source_return_id' => $return->id,
        ]);
    }

    private function uniquePropertyNumber(string $preferred, int $sourceId): string
    {
        $number = trim($preferred);
        if ($number !== '') {
            $owner = Equipment::query()->where('property_number', $number)->first();
            if (! $owner) {
                return $number;
            }
            if ((int) $owner->id !== $sourceId) {
                throw new InvalidArgumentException("Property number {$number} is already used by another equipment.");
            }
        }

        return ReferenceNumberGenerator::forEquipment();
    }

    private function uniqueInventoryNumber(string $preferred, int $sourceId): string
    {
        $number = trim($preferred);
        if ($number !== '') {
            $itemTaken = Item::query()->where('inventory_number', $number)->exists();
            $owner = Equipment::query()->where('inventory_number', $number)->first();
            if (! $itemTaken && ! $owner) {
                return $number;
            }
            if ($itemTaken || ($owner && (int) $owner->id !== $sourceId)) {
                throw new InvalidArgumentException("Inventory number {$number} is already in use.");
            }
        }

        return ReferenceNumberGenerator::forInventory();
    }
}

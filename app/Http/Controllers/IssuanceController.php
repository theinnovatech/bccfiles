<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Issuance;
use App\Models\Item;
use App\Models\SupplyRequest;
use App\Services\ActivityLogService;
use App\Services\InventoryService;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                ->with(['request.department', 'request.requester', 'issuer', 'receiver.department', 'details.item', 'details.equipment'])
                ->orderByDesc('issued_date')
                ->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $supplyRequest = SupplyRequest::query()
            ->with(['details.item', 'details.equipment', 'requester'])
            ->findOrFail($request->integer('request_id'));

        if (! in_array($supplyRequest->status, [RequestStatus::Approved, RequestStatus::PartiallyIssued], true)) {
            return response()->json(['message' => 'Request must be approved before issuance.'], 422);
        }

        $isEquipmentRequest = $supplyRequest->request_type === RequestType::Equipments;

        $rules = [
            'request_id' => ['required', 'exists:supply_requests,id'],
            'received_by' => ['nullable', 'exists:employees,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];

        if ($isEquipmentRequest) {
            $rules['items.*.equipment_id'] = ['required', 'exists:equipments,id'];
        } else {
            $rules['items.*.barcode'] = ['required', 'string'];
        }

        $data = $request->validate($rules);

        $issuance = DB::transaction(function () use ($data, $request, $supplyRequest, $isEquipmentRequest) {
            $issuance = Issuance::create([
                'issuance_number' => ReferenceNumberGenerator::forIssuance(),
                'request_id' => $supplyRequest->id,
                'issued_by' => $request->user()->id,
                'received_by' => $data['received_by'] ?? null,
                'issued_date' => now(),
            ]);

            foreach ($data['items'] as $line) {
                if ($isEquipmentRequest) {
                    $this->issueEquipmentLine($issuance, $supplyRequest, $line);
                } else {
                    $this->issueItemLine($issuance, $supplyRequest, $line, $request);
                }
            }

            $allIssued = $supplyRequest->details()->get()->every(
                fn ($detail) => $detail->quantity_issued >= $detail->quantity_requested
            );

            $supplyRequest->update([
                'status' => $allIssued ? RequestStatus::Issued : RequestStatus::PartiallyIssued,
            ]);

            return $issuance;
        });

        $requesterName = $supplyRequest->requester?->name;

        $this->activityLogService->log(
            $request->user(),
            'Issued',
            'Issuance',
            $requesterName
                ? "Processed issuance {$issuance->issuance_number} for {$requesterName}"
                : "Processed issuance {$issuance->issuance_number}"
        );

        return response()->json($issuance->load(['request.department', 'issuer', 'receiver', 'details.item', 'details.equipment']), 201);
    }

    public function show(Issuance $issuance): JsonResponse
    {
        return response()->json($issuance->load(['request.department', 'request.requester', 'issuer', 'receiver', 'details.item', 'details.equipment']));
    }

    private function issueItemLine(Issuance $issuance, SupplyRequest $supplyRequest, array $line, Request $request): void
    {
        $item = Item::query()->where('barcode', $line['barcode'])->firstOrFail();
        $detail = $supplyRequest->details()->where('item_id', $item->id)->first();

        if (! $detail) {
            throw new \InvalidArgumentException("Item {$item->item_name} is not part of this request.");
        }

        $remaining = $detail->quantity_requested - $detail->quantity_issued;
        if ($line['quantity'] > $remaining) {
            throw new \InvalidArgumentException("Issuance quantity exceeds requested amount for {$item->item_name}.");
        }

        $this->inventoryService->issueStock(
            $item,
            $line['quantity'],
            $request->user(),
            "Issuance {$issuance->issuance_number}"
        );

        $issuance->details()->create([
            'item_id' => $item->id,
            'barcode' => $item->barcode,
            'quantity' => $line['quantity'],
        ]);

        $detail->increment('quantity_issued', $line['quantity']);
    }

    private function issueEquipmentLine(Issuance $issuance, SupplyRequest $supplyRequest, array $line): void
    {
        $equipment = Equipment::query()->findOrFail($line['equipment_id']);
        $detail = $supplyRequest->details()->where('equipment_id', $equipment->id)->first();

        if (! $detail) {
            throw new \InvalidArgumentException("Equipment {$equipment->name} is not part of this request.");
        }

        $remaining = $detail->quantity_requested - $detail->quantity_issued;
        if ($line['quantity'] > $remaining) {
            throw new \InvalidArgumentException("Issuance quantity exceeds requested amount for {$equipment->name}.");
        }

        if ($line['quantity'] > $equipment->quantity) {
            throw new \InvalidArgumentException("Not enough available quantity for {$equipment->name}.");
        }

        $issuance->details()->create([
            'equipment_id' => $equipment->id,
            'barcode' => $equipment->barcode ?? $equipment->property_number,
            'quantity' => $line['quantity'],
        ]);

        $equipment->decrement('quantity', $line['quantity']);
        $detail->increment('quantity_issued', $line['quantity']);
    }
}

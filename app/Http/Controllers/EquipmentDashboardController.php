<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentCase;
use App\Services\ActivityLogService;
use App\Services\EquipmentCaseService;
use App\Services\EquipmentStatusService;
use App\Support\EquipmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Revisions 1 & 7: Equipment Dashboard counts, filtered lists and controlled
 * repair / warranty / disposal actions.
 */
class EquipmentDashboardController extends Controller
{
    public function __construct(
        private readonly EquipmentStatusService $statusService,
        private readonly EquipmentCaseService $caseService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json([
            'counts' => $this->statusService->counts(),
            'labels' => collect(EquipmentStatus::DASHBOARD)
                ->mapWithKeys(fn ($status) => [$status => EquipmentStatus::label($status)]),
            'warranty_outcomes' => EquipmentStatus::WARRANTY_OUTCOMES,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                ...EquipmentStatus::DASHBOARD,
                'warranty',
                'awaiting_assessment',
                EquipmentStatus::RETURNED_TO_SERVICE,
            ])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'status' => $data['status'],
            'rows' => $this->statusService->rows($data['status'], $data),
        ]);
    }

    public function showCase(EquipmentCase $case): JsonResponse
    {
        return response()->json($this->statusService->presentCase(
            $case->load(['equipment.category', 'serialNumber', 'itemReturn', 'events.performer', 'reporter'])
        ));
    }

    public function openCase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin' => ['required', Rule::in([EquipmentCase::ORIGIN_STOCK, EquipmentCase::ORIGIN_RETURN])],
            'equipment_id' => ['nullable', 'integer', 'required_if:origin,'.EquipmentCase::ORIGIN_STOCK],
            'item_return_id' => ['nullable', 'integer', 'required_if:origin,'.EquipmentCase::ORIGIN_RETURN],
            'serial_number_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in([EquipmentStatus::FOR_REPAIR, EquipmentStatus::FOR_DISPOSAL])],
            'reason' => ['required', 'string', 'max:2000'],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:255'],
        ], $this->messages());

        return $this->respond(fn () => $this->caseService->open($request->user(), $data), 201);
    }

    public function transition(Request $request, EquipmentCase $case, string $action): JsonResponse
    {
        $rules = [
            'reason' => ['required', 'string', 'max:2000'],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:255'],
        ];

        $rules += match ($action) {
            'warranty-decision' => ['outcome' => ['required', Rule::in(array_keys(EquipmentStatus::WARRANTY_OUTCOMES))]],
            'serviceability' => ['passed' => ['required', 'boolean']],
            'disposal-approval' => [
                'approval_status' => ['required', Rule::in(['approved', 'rejected'])],
                'approved_by_name' => ['required', 'string', 'max:255'],
            ],
            'dispose' => ['method' => ['nullable', 'string', 'max:255']],
            'iirup-values' => [
                'unit_cost' => ['nullable', 'numeric', 'min:0'],
                'accumulated_impairment' => ['nullable', 'numeric', 'min:0'],
                'carrying_amount' => ['nullable', 'numeric', 'min:0'],
                'appraised_value' => ['nullable', 'numeric', 'min:0'],
                'sale_amount' => ['nullable', 'numeric', 'min:0'],
                'disposal_sale_qty' => ['nullable', 'integer', 'min:0'],
                'disposal_transfer_qty' => ['nullable', 'integer', 'min:0'],
                'disposal_destruction_qty' => ['nullable', 'integer', 'min:0'],
                'disposal_others_qty' => ['nullable', 'integer', 'min:0'],
                'disposal_others_specify' => ['nullable', 'string', 'max:255'],
                'or_number' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };

        if ($action === 'disposal-approval' && ! $request->user()->isAdmin()) {
            abort(403, 'Only administrators can record disposal approval.');
        }

        $data = $request->validate($rules, $this->messages());
        $user = $request->user();

        return $this->respond(fn () => match ($action) {
            'warranty-decision' => $this->caseService->recordWarrantyDecision($case, $user, $data),
            'repair' => $this->caseService->recordRepair($case, $user, $data),
            'serviceability' => $this->caseService->recordServiceability($case, $user, $data),
            'return-to-available' => $this->caseService->returnToAvailable($case, $user, $data),
            'move-to-disposal' => $this->caseService->moveToDisposal($case, $user, $data),
            'back-to-repair' => $this->caseService->moveBackToRepair($case, $user, $data),
            'disposal-assessment' => $this->caseService->recordDisposalAssessment($case, $user, $data),
            'disposal-approval' => $this->caseService->recordDisposalApproval($case, $user, $data),
            'dispose' => $this->caseService->markDisposed($case, $user, $data),
            'iirup-values' => $this->caseService->recordIirupValues($case, $user, $data),
            default => abort(404, 'Unknown action.'),
        });
    }

    public function updateWarranty(Request $request, Equipment $equipment): JsonResponse
    {
        $data = $request->validate([
            'warranty_provider' => ['nullable', 'string', 'max:255'],
            'warranty_reference' => ['nullable', 'string', 'max:255'],
            'warranty_start_date' => ['nullable', 'date'],
            'warranty_end_date' => ['nullable', 'date', 'after_or_equal:warranty_start_date', 'required_with:warranty_provider,warranty_reference,warranty_start_date'],
        ]);

        $equipment->update($data);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Equipments',
            "Recorded warranty coverage for {$equipment->name} ({$equipment->property_number}): "
                .($data['warranty_end_date'] ?? null ? 'until '.$data['warranty_end_date'] : 'cleared')
        );

        return response()->json($equipment->fresh());
    }

    private function respond(callable $callback, int $status = 200): JsonResponse
    {
        try {
            $case = $callback();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'case' => $this->statusService->presentCase(
                $case->load(['equipment.category', 'serialNumber', 'itemReturn', 'events.performer', 'reporter'])
            ),
            'counts' => $this->statusService->counts(),
        ], $status);
    }

    private function messages(): array
    {
        return [
            'reason.required' => 'Enter the reason / remarks for this action.',
            'event_date.required' => 'Enter the date of this action.',
            'reference.required' => 'Enter the supporting reference (e.g. job order, memo, claim, PTR/IIRUP no.).',
        ];
    }
}

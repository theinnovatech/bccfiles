<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\UserRole;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplyRequestController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = SupplyRequest::query()
            ->with(['department', 'requester', 'approver', 'details.item', 'details.equipment'])
            ->orderByDesc('request_date');

        $user = $request->user();

        if ($user->role === UserRole::DepartmentUser) {
            $query->where('requested_by', $user->id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $requestType = RequestType::tryFrom($request->input('request_type'));

        $rules = [
            'department_id' => ['required', 'exists:departments,id'],
            'request_type' => ['required', Rule::enum(RequestType::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
        ];

        if ($requestType === RequestType::Items) {
            $rules['items.*.item_id'] = ['required', 'exists:items,id'];
        } elseif ($requestType === RequestType::Equipments) {
            $rules['items.*.equipment_id'] = ['required', 'exists:equipments,id'];
        }

        $data = $request->validate($rules);
        $requestType = RequestType::from($data['request_type']);

        $user = $request->user();

        if ($user->role === UserRole::DepartmentUser && $user->department_id !== (int) $data['department_id']) {
            return response()->json(['message' => 'You can only submit requests for your department.'], 403);
        }

        $supplyRequest = DB::transaction(function () use ($data, $user, $requestType) {
            $requestModel = SupplyRequest::create([
                'request_number' => ReferenceNumberGenerator::forRequest(),
                'request_type' => $requestType,
                'department_id' => $data['department_id'],
                'requested_by' => $user->id,
                'status' => RequestStatus::Pending,
                'remarks' => $data['remarks'] ?? null,
                'request_date' => now(),
            ]);

            foreach ($data['items'] as $line) {
                $detail = [
                    'quantity_requested' => $line['quantity_requested'],
                ];

                if ($requestType === RequestType::Items) {
                    $detail['item_id'] = $line['item_id'];
                } else {
                    $detail['equipment_id'] = $line['equipment_id'];
                }

                $requestModel->details()->create($detail);
            }

            return $requestModel;
        });

        $this->activityLogService->log(
            $user,
            'Submitted',
            'Requests',
            "Submitted supply request {$supplyRequest->request_number}"
        );

        $supplyRequest->load(['department', 'requester', 'details.item', 'details.equipment']);

        $this->notifyStaffOfNewRequest($supplyRequest, $user);

        return response()->json($supplyRequest, 201);
    }

    public function show(SupplyRequest $supplyRequest): JsonResponse
    {
        return response()->json($supplyRequest->load(['department', 'requester', 'approver', 'details.item', 'details.equipment', 'issuances.details']));
    }

    public function approve(Request $request, SupplyRequest $supplyRequest): JsonResponse
    {
        if ($supplyRequest->status !== RequestStatus::Pending) {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $supplyRequest->update([
            'status' => RequestStatus::Approved,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Approved',
            'Requests',
            "Approved supply request {$supplyRequest->request_number}"
        );

        $supplyRequest = $supplyRequest->fresh()->load(['department', 'requester', 'approver', 'details.item', 'details.equipment']);

        $this->notifyRequesterOfApproval($supplyRequest);

        return response()->json($supplyRequest);
    }

    public function reject(Request $request, SupplyRequest $supplyRequest): JsonResponse
    {
        $data = $request->validate(['remarks' => ['required', 'string']]);

        if ($supplyRequest->status !== RequestStatus::Pending) {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $supplyRequest->update([
            'status' => RequestStatus::Rejected,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'remarks' => $data['remarks'],
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Rejected',
            'Requests',
            "Rejected supply request {$supplyRequest->request_number}"
        );

        $supplyRequest = $supplyRequest->fresh()->load(['department', 'requester', 'approver', 'details.item', 'details.equipment']);

        $this->notifyRequesterOfRejection($supplyRequest);

        return response()->json($supplyRequest);
    }

    private function notifyStaffOfNewRequest(SupplyRequest $supplyRequest, User $submitter): void
    {
        $departmentName = $supplyRequest->department?->name ?? 'Department';
        $staffUsers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Admin, UserRole::SupplyOfficer])
            ->get();

        foreach ($staffUsers as $staffUser) {
            $this->notificationService->send(
                $staffUser,
                'supply_request_submitted',
                'New Supply Request',
                "{$submitter->name} from {$departmentName} submitted {$supplyRequest->request_number}.",
                [
                    'request_id' => $supplyRequest->id,
                    'request_number' => $supplyRequest->request_number,
                    'url' => "/requests/{$supplyRequest->id}",
                ],
            );
        }
    }

    private function notifyRequesterOfApproval(SupplyRequest $supplyRequest): void
    {
        $requester = $supplyRequest->requester;

        if (! $requester) {
            return;
        }

        $this->notificationService->send(
            $requester,
            'supply_request_approved',
            'Request Approved',
            "Your supply request {$supplyRequest->request_number} has been approved.",
            [
                'request_id' => $supplyRequest->id,
                'request_number' => $supplyRequest->request_number,
                'url' => "/requests/{$supplyRequest->id}",
            ],
        );
    }

    private function notifyRequesterOfRejection(SupplyRequest $supplyRequest): void
    {
        $requester = $supplyRequest->requester;

        if (! $requester) {
            return;
        }

        $this->notificationService->send(
            $requester,
            'supply_request_rejected',
            'Request Rejected',
            "Your supply request {$supplyRequest->request_number} has been rejected.",
            [
                'request_id' => $supplyRequest->id,
                'request_number' => $supplyRequest->request_number,
                'url' => "/requests/{$supplyRequest->id}",
            ],
        );
    }
}

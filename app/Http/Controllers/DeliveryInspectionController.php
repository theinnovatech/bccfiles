<?php

namespace App\Http\Controllers;

use App\Models\DeliveryInspection;
use App\Models\DeliveryInspectionLine;
use App\Services\DeliveryInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class DeliveryInspectionController extends Controller
{
    public function __construct(private readonly DeliveryInspectionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = DeliveryInspection::query()
            ->with(['lines.item:id,item_name,barcode,item_number', 'lines.equipment:id,name,property_number', 'poster:id,name', 'creator:id,name'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('reference')) {
            $reference = trim((string) $request->string('reference'));
            $query->where(fn ($q) => $q->where('delivery_reference', $reference)->orWhere('purchase_reference', $reference));
        }

        return response()->json($query->get());
    }

    public function show(DeliveryInspection $inspection): JsonResponse
    {
        return response()->json($inspection->load(['lines.item', 'lines.equipment.serialNumbers', 'poster', 'creator', 'inspector']));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->service->save($request->user(), $this->validated($request)), 201);
    }

    public function update(Request $request, DeliveryInspection $inspection): JsonResponse
    {
        return $this->respond(fn () => $this->service->save($request->user(), $this->validated($request), $inspection));
    }

    public function post(Request $request, DeliveryInspection $inspection): JsonResponse
    {
        try {
            $result = $this->service->post($request->user(), $inspection);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'inspection' => $result['inspection'],
            'already_posted' => $result['already_posted'],
            'message' => $result['already_posted']
                ? 'This inspection was already posted. No stock was added again.'
                : 'Accepted quantities were posted to inventory.',
        ]);
    }

    public function destroy(Request $request, DeliveryInspection $inspection): JsonResponse
    {
        if ($inspection->isPosted()) {
            return response()->json(['message' => 'Posted inspections are part of the stock history and cannot be deleted.'], 422);
        }

        $inspection->delete();

        return response()->json(['message' => 'Draft inspection deleted.']);
    }

    private function respond(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json($callback(), $status);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'delivery_reference' => ['nullable', 'string', 'max:255'],
            'purchase_reference' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'delivery_date' => ['nullable', 'date', 'before_or_equal:today'],
            'inspection_date' => ['required', 'date', 'before_or_equal:today'],
            'inspector_name' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_type' => ['required', Rule::in(['item', 'equipment'])],
            'lines.*.item_id' => ['nullable', 'required_if:lines.*.line_type,item', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'lines.*.equipment_id' => ['nullable', 'required_if:lines.*.line_type,equipment', 'integer', Rule::exists('equipments', 'id')->whereNull('deleted_at')],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.expected_quantity' => ['required', 'integer', 'min:0'],
            'lines.*.delivered_quantity' => ['required', 'integer', 'min:0'],
            'lines.*.accepted_quantity' => ['required', 'integer', 'min:0'],
            'lines.*.rejected_quantity' => ['required', 'integer', 'min:0'],
            'lines.*.condition' => ['required', Rule::in(array_keys(DeliveryInspectionLine::CONDITIONS))],
            'lines.*.discrepancy_remarks' => ['nullable', 'string', 'max:2000'],
            'lines.*.serial_numbers' => ['nullable', 'array'],
            'lines.*.serial_numbers.*' => ['nullable', 'string', 'max:255'],
        ], [
            'inspection_date.required' => 'Enter the inspection date.',
            'inspector_name.required' => 'Enter the inspector name.',
        ]);
    }
}

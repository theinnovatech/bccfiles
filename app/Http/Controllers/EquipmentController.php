<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Services\ActivityLogService;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EquipmentController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Equipment::query()
                ->with('category')
                ->orderBy('name')
                ->get()
        );
    }

    public function findByBarcode(string $barcode): JsonResponse
    {
        $equipment =             Equipment::query()
                ->with('category')
                ->where(function ($query) use ($barcode) {
                    $query->where('barcode', $barcode)
                        ->orWhere('property_number', $barcode)
                        ->orWhere('inventory_number', $barcode);
                })
                ->first();

        return response()->json(['equipment' => $equipment]);
    }

    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['nullable', 'integer', 'exists:equipments,id'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (empty($data['equipment_id']) && empty($data['barcode'])) {
            return response()->json([
                'message' => 'Select an equipment or provide a barcode, property number, or inventory number.',
            ], 422);
        }

        $equipment = ! empty($data['equipment_id'])
            ? Equipment::query()->with('category')->findOrFail($data['equipment_id'])
            : Equipment::query()
                ->with('category')
                ->where(function ($query) use ($data) {
                    $query->where('barcode', $data['barcode'])
                        ->orWhere('property_number', $data['barcode'])
                        ->orWhere('inventory_number', $data['barcode']);
                })
                ->firstOrFail();

        $equipment->increment('quantity', $data['quantity']);
        $equipment->refresh()->load('category');

        $remarks = trim((string) ($data['remarks'] ?? ''));
        $ref = $equipment->inventory_number ?: $equipment->property_number;
        $message = "Received {$data['quantity']} unit(s) for equipment {$equipment->name} ({$ref})";
        if ($remarks !== '') {
            $message .= " — {$remarks}";
        }

        $this->activityLogService->log(
            $request->user(),
            'Received',
            'Equipments',
            $message
        );

        return response()->json([
            'equipment' => $equipment,
            'quantity_received' => $data['quantity'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['nullable', 'string', 'max:255', 'unique:equipments,barcode'],
            'name' => ['required', 'string', 'max:255'],
            'equipment_category_id' => ['required', 'exists:equipment_categories,id'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'life_span_years' => ['required', 'integer', 'min:1', 'max:100'],
            'specs' => ['nullable', 'string'],
        ]);

        $equipment = DB::transaction(function () use ($data) {
            return Equipment::create([
                ...$data,
                'property_number' => ReferenceNumberGenerator::forEquipment(),
                'inventory_number' => ReferenceNumberGenerator::forInventory(),
            ]);
        });

        $this->activityLogService->log(
            $request->user(),
            'Created',
            'Equipments',
            "Created equipment {$equipment->name} ({$equipment->inventory_number})"
        );

        return response()->json($equipment->load('category'), 201);
    }

    public function show(Equipment $equipment): JsonResponse
    {
        return response()->json($equipment->load('category'));
    }

    public function update(Request $request, Equipment $equipment): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('equipments', 'barcode')->ignore($equipment->id)],
            'name' => ['required', 'string', 'max:255'],
            'equipment_category_id' => ['required', 'exists:equipment_categories,id'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'life_span_years' => ['required', 'integer', 'min:0', 'max:100'],
            'specs' => ['nullable', 'string'],
        ]);

        $equipment->update($data);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Equipments',
            "Updated equipment {$equipment->name} (".($equipment->inventory_number ?: $equipment->property_number).")"
        );

        return response()->json($equipment->load('category'));
    }

    public function updatePropertyNumber(Request $request, Equipment $equipment): JsonResponse
    {
        $data = $request->validate([
            'property_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('equipments', 'property_number')->ignore($equipment->id),
            ],
        ]);

        $propertyNumber = trim($data['property_number']);

        $equipment->update([
            'property_number' => $propertyNumber,
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Equipments',
            "Linked hard-copy property number {$propertyNumber} to equipment {$equipment->name}"
        );

        return response()->json($equipment->fresh()->load('category'));
    }

    public function destroy(Request $request, Equipment $equipment): JsonResponse
    {
        $name = $equipment->name;
        $equipment->delete();

        $this->activityLogService->log(
            $request->user(),
            'Deleted',
            'Equipments',
            "Deleted equipment {$name}"
        );

        return response()->json(['message' => 'Equipment moved to deleted data.']);
    }
}

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
            Equipment::query()->with('category')->orderBy('name')->get()
        );
    }

    public function findByBarcode(string $barcode): JsonResponse
    {
        $equipment = Equipment::query()
            ->with('category')
            ->where('barcode', $barcode)
            ->first();

        return response()->json(['equipment' => $equipment]);
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
            'specs' => ['nullable', 'string'],
        ]);

        $equipment = DB::transaction(function () use ($data) {
            return Equipment::create([
                ...$data,
                'property_number' => ReferenceNumberGenerator::forEquipment(),
            ]);
        });

        $this->activityLogService->log(
            $request->user(),
            'Created',
            'Equipments',
            "Created equipment {$equipment->name} ({$equipment->property_number})"
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
            'specs' => ['nullable', 'string'],
        ]);

        $equipment->update($data);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Equipments',
            "Updated equipment {$equipment->name} ({$equipment->property_number})"
        );

        return response()->json($equipment->load('category'));
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

<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Item::query()->with(['category', 'unit', 'location']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('current_stock', '<=', 'minimum_stock')->where('current_stock', '>', 0);
        }

        if ($request->boolean('out_of_stock')) {
            $query->where('current_stock', 0);
        }

        return response()->json($query->orderBy('item_name')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:255', 'unique:items,barcode'],
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'location_id' => ['required', 'exists:storage_locations,id'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(ItemStatus::class)],
        ]);

        $data['status'] = $data['status'] ?? ItemStatus::Active->value;
        $data['current_stock'] = 0;

        $item = Item::create($data);
        $this->activityLogService->log(
            $request->user(),
            'Registered',
            'Items',
            "Registered item {$item->item_name} ({$item->barcode})"
        );

        return response()->json($item->load(['category', 'unit', 'location']), 201);
    }

    public function show(Item $item): JsonResponse
    {
        return response()->json($item->load(['category', 'unit', 'location']));
    }

    public function findByBarcode(string $barcode): JsonResponse
    {
        $item = Item::query()
            ->with(['category', 'unit', 'location'])
            ->where('barcode', $barcode)
            ->first();

        return response()->json(['item' => $item]);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:255', 'unique:items,barcode,'.$item->id],
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'location_id' => ['required', 'exists:storage_locations,id'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(ItemStatus::class)],
        ]);

        $item->update($data);
        $this->activityLogService->log($request->user(), 'Updated', 'Items', "Updated item {$item->item_name}");

        return response()->json($item->load(['category', 'unit', 'location']));
    }

    public function destroy(Request $request, Item $item): JsonResponse
    {
        if ($item->stockMovements()->exists()) {
            return response()->json(['message' => 'Cannot delete item with stock history.'], 422);
        }

        $name = $item->item_name;
        $item->delete();
        $this->activityLogService->log($request->user(), 'Deleted', 'Items', "Deleted item {$name}");

        return response()->json(['message' => 'Item moved to deleted data.']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\ActivityLogService;
use App\Services\LowStockAlertService;
use App\Services\DocumentNumberService;
use App\Support\NumberScopes;
use Illuminate\Validation\ValidationException;
use App\Support\ReferenceNumberGenerator;
use App\Support\StockStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly LowStockAlertService $lowStockAlertService,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Item::query()->select('items.*')->with(['category', 'unit', 'location']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('item_number', 'like', "%{$search}%")
                    ->orWhere('inventory_number', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('stock_status')) {
            $query->withStockStatus((string) $request->string('stock_status'));
        }

        if ($request->boolean('low_stock')) {
            $query->withStockStatus(StockStatus::LOW);
        }

        if ($request->boolean('out_of_stock')) {
            $query->withStockStatus(StockStatus::OUT);
        }

        if ($request->boolean('with_receipts')) {
            $query->addSelect([
                'last_received_on' => StockMovement::query()
                    ->selectRaw('DATE(MAX(stock_movements.created_at))')
                    ->whereColumn('stock_movements.item_id', 'items.id')
                    ->where('stock_movements.transaction_type', TransactionType::In->value),
            ]);
        }

        if ($request->boolean('all')) {
            return response()->json($query->orderBy('item_name')->get());
        }

        return response()->json($query->orderBy('item_name')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['nullable', 'string', 'max:255', 'unique:items,barcode'],
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'location_id' => ['required', 'exists:storage_locations,id'],
            'minimum_stock' => ['required', 'integer', 'min:1'],
            'reference_stock' => ['required', 'integer', 'min:1'],
            'low_stock_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'status' => ['nullable', Rule::enum(ItemStatus::class)],
            'item_number' => ['nullable', 'string', 'max:255'],
        ], $this->messages());

        $data['barcode'] = filled($data['barcode'] ?? null) ? trim($data['barcode']) : null;
        $data['status'] = $data['status'] ?? ItemStatus::Active->value;
        $data['current_stock'] = 0;

        $item = DB::transaction(function () use ($data) {
            return Item::create([
                ...$data,
                // Stock No. (Stock Card number): typed, or generated from the configured format.
                'item_number' => $this->numbers->resolve('stock_card_number', $data['item_number'] ?? null, NumberScopes::stockCard(), null, 'item_number'),
                'inventory_number' => ReferenceNumberGenerator::forInventory(),
            ]);
        });

        $identifier = $item->displayIdentifier();
        $this->activityLogService->log(
            $request->user(),
            'Registered',
            'Items',
            "Registered item {$item->item_name} ({$identifier})"
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
            ->where(function ($query) use ($barcode) {
                $query->where('barcode', $barcode)
                    ->orWhere('item_number', $barcode)
                    ->orWhere('inventory_number', $barcode);
            })
            ->first();

        return response()->json(['item' => $item]);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('items', 'barcode')->ignore($item->id)],
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'location_id' => ['required', 'exists:storage_locations,id'],
            'minimum_stock' => ['required', 'integer', 'min:1'],
            'reference_stock' => ['required', 'integer', 'min:1'],
            'low_stock_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'status' => ['required', Rule::enum(ItemStatus::class)],
            'item_number' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], $this->messages());

        $data['barcode'] = filled($data['barcode'] ?? null) ? trim($data['barcode']) : null;

        if (array_key_exists('item_number', $data)) {
            $stockNo = $this->numbers->clean($data['item_number']);
            if ($stockNo === $this->numbers->clean($item->item_number)) {
                unset($data['item_number']);
            } else {
                if (! $request->user()->isAdmin() && ! $request->user()->isSupplyOfficer()) {
                    throw ValidationException::withMessages(['item_number' => 'Only administrators and supply officers can change the Stock No.']);
                }
                if ($stockNo === null) {
                    throw ValidationException::withMessages(['item_number' => 'The Stock No. cannot be blank.']);
                }
                if (NumberScopes::stockCard($item->id)($stockNo)) {
                    throw ValidationException::withMessages(['item_number' => "Stock No. {$stockNo} is already used by another item."]);
                }
                $data['item_number'] = $stockNo;
            }
        }

        $item->update($data);
        $item->refresh();
        $this->lowStockAlertService->evaluateItem($item);
        $this->activityLogService->log($request->user(), 'Updated', 'Items', "Updated item {$item->item_name}");

        return response()->json($item->load(['category', 'unit', 'location']));
    }

    private function messages(): array
    {
        return [
            'low_stock_percent.min' => 'The low-stock percentage must be between 1 and 100.',
            'low_stock_percent.max' => 'The low-stock percentage must be between 1 and 100.',
            'reference_stock.min' => 'The reference stock quantity must be at least 1.',
        ];
    }

    public function updateStockNumber(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'item_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('items', 'item_number')->ignore($item->id),
            ],
        ]);

        $stockNumber = trim($data['item_number']);

        $item->update([
            'item_number' => $stockNumber,
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Items',
            "Linked hard-copy stock number {$stockNumber} to item {$item->item_name}"
        );

        return response()->json($item->fresh()->load(['category', 'unit', 'location']));
    }

    public function destroy(Request $request, Item $item): JsonResponse
    {
        $name = $item->item_name;
        $item->delete();
        $this->activityLogService->log($request->user(), 'Deleted', 'Items', "Deleted item {$name}");

        return response()->json(['message' => 'Item moved to deleted data.']);
    }
}

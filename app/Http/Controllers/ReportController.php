<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DeliveryInspection;
use App\Models\Equipment;
use App\Models\Item;
use App\Services\EquipmentStatusService;
use App\Services\FormService;
use App\Services\ReportExportService;
use App\Services\ReportService;
use App\Support\EquipmentStatus;
use App\Support\StockStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public const TYPES = [
        'inventory',
        'equipment-inventory',
        'stock-movements',
        'issuance',
        'returns',
        'low-stock',
        'monthly-consumption',
        'stock-card',
        'property-card',
        'equipment-status',
        'delivery-inspections',
    ];

    public function __construct(
        private readonly ReportService $reportService,
        private readonly ReportExportService $exportService,
        private readonly EquipmentStatusService $equipmentStatusService,
        private readonly FormService $forms,
    ) {}

    public function show(Request $request, string $type): JsonResponse
    {
        $filters = $this->filters($request, $type);

        return response()->json([
            'type' => $type,
            'item' => $type === 'stock-card' ? Item::query()
                ->with(['category', 'unit', 'location'])
                ->find($filters['item_id'] ?? null) : null,
            'equipment' => $type === 'property-card' ? Equipment::query()
                ->with('category')
                ->find($filters['equipment_id'] ?? null) : null,
            'filters' => $this->filterLabels($type, $filters),
            'data' => $this->getData($type, $filters),
        ]);
    }

    public function pdf(Request $request, string $type): Response
    {
        $filters = $this->filters($request, $type);
        $data = $this->getData($type, $filters);
        $title = $this->titleFor($type);
        $item = $type === 'stock-card'
            ? Item::query()->with(['category', 'unit', 'location'])->find($filters['item_id'] ?? null)
            : null;
        $equipment = $type === 'property-card'
            ? Equipment::query()->with('category')->find($filters['equipment_id'] ?? null)
            : null;
        $filename = $this->filenameFor($type, $item, $equipment).'.pdf';

        $pdf = Pdf::loadView('reports.generic', [
            'title' => $title,
            'type' => $type,
            'rows' => $data,
            'item' => $item,
            'equipment' => $equipment,
            'filterLabels' => $this->filterLabels($type, $filters),
            'generatedAt' => now()->format('Y-m-d H:i'),
            'formSettings' => [
                // Stock Card agency line and Property Card header (Settings > Forms).
                'agency' => $this->forms->setting('form_agency')
                    ?? $this->forms->setting('form_entity_name')
                    ?? \App\Models\Setting::getValue('organization_name'),
                'entity_name' => $this->forms->setting('form_entity_name')
                    ?? \App\Models\Setting::getValue('organization_name'),
                'fund_cluster' => $this->forms->setting('form_fund_cluster'),
            ],
        ]);

        if (in_array($type, ['equipment-status', 'delivery-inspections'], true)) {
            $pdf->setPaper('a4', 'landscape');
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('preview') ? 'inline' : 'attachment').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * Revision 10: Excel export honouring the same filters (and route permissions) as the
     * on-screen report.
     */
    public function xlsx(Request $request, string $type): Response
    {
        $filters = $this->filters($request, $type);
        $item = $type === 'stock-card' ? Item::query()->find($filters['item_id'] ?? null) : null;
        $equipment = $type === 'property-card' ? Equipment::query()->find($filters['equipment_id'] ?? null) : null;

        $notes = [];
        if ($item) {
            $notes[] = "Item: {$item->item_name} ({$item->displayIdentifier()})";
        }
        if ($equipment) {
            $notes[] = "Equipment: {$equipment->name} ({$equipment->property_number})";
        }

        $xlsx = $this->exportService->build(
            $type,
            collect($this->getData($type, $filters)),
            $this->titleFor($type),
            $this->filterLabels($type, $filters),
            ['notes' => $notes]
        );

        return response($xlsx->toString(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filenameFor($type, $item, $equipment).'.xlsx"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request, string $type): array
    {
        if (! in_array($type, self::TYPES, true)) {
            abort(404, 'Report not found.');
        }

        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'stock_status' => ['nullable', Rule::in(StockStatus::keys())],
            'transaction_type' => ['nullable', Rule::in(['IN', 'OUT', 'RETURN', 'ADJUSTMENT'])],
            'movement_type' => ['nullable', Rule::in(['receipt', 'issue'])],
            'condition' => ['nullable', Rule::in(['good', 'damaged'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'status' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:255'],
            'item_id' => [Rule::requiredIf($type === 'stock-card'), 'nullable', 'integer', 'exists:items,id'],
            'equipment_id' => [Rule::requiredIf($type === 'property-card'), 'nullable', 'integer', 'exists:equipments,id'],
        ]);
    }

    private function getData(string $type, array $filters): mixed
    {
        return match ($type) {
            'inventory' => $this->reportService->inventory($filters['stock_status'] ?? null),
            'equipment-inventory' => $this->reportService->equipmentInventory(),
            'stock-movements' => $this->reportService->stockMovements(
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters['transaction_type'] ?? null,
            ),
            'issuance' => $this->reportService->issuances(
                $filters['department_id'] ?? null,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
            ),
            'returns' => $this->reportService->returns(
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters['condition'] ?? null,
                $filters['department_id'] ?? null,
            ),
            'low-stock' => $this->reportService->lowStock($filters['stock_status'] ?? null),
            'monthly-consumption' => $this->reportService->monthlyConsumption($filters['year'] ?? null),
            'stock-card' => $this->reportService->stockCard(
                (int) $filters['item_id'],
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters['transaction_type'] ?? null,
            ),
            'property-card' => $this->reportService->propertyCard(
                (int) $filters['equipment_id'],
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters['movement_type'] ?? null,
            ),
            'equipment-status' => $this->equipmentStatusRows($filters),
            'delivery-inspections' => DeliveryInspection::query()
                ->with(['lines.item', 'lines.equipment'])
                ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('inspection_date', '>=', $from))
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('inspection_date', '<=', $to))
                ->when(in_array($filters['status'] ?? null, ['draft', 'posted'], true), fn ($q) => $q->where('status', $filters['status']))
                ->orderByDesc('inspection_date')
                ->get(),
            default => abort(404, 'Report not found.'),
        };
    }

    private function equipmentStatusRows(array $filters): Collection
    {
        $status = $filters['status'] ?? null;
        $statuses = $status && in_array($status, [...EquipmentStatus::DASHBOARD, 'warranty', 'awaiting_assessment'], true)
            ? [$status]
            : EquipmentStatus::DASHBOARD;

        return collect($statuses)
            ->flatMap(fn ($value) => $this->equipmentStatusService->rows($value, ['search' => $filters['search'] ?? null]))
            ->values();
    }

    /**
     * @return array<string, string|null>
     */
    private function filterLabels(string $type, array $filters): array
    {
        return array_filter([
            'From' => $filters['from'] ?? null,
            'To' => $filters['to'] ?? null,
            'Department' => isset($filters['department_id']) ? Department::withTrashed()->find($filters['department_id'])?->name : null,
            'Stock status' => isset($filters['stock_status']) ? StockStatus::label($filters['stock_status']) : null,
            'Transaction type' => $filters['transaction_type'] ?? null,
            'Movement' => $filters['movement_type'] ?? null,
            'Condition' => isset($filters['condition']) ? ($filters['condition'] === 'damaged' ? 'Unserviceable' : 'Good') : null,
            'Year' => isset($filters['year']) ? (string) $filters['year'] : ($type === 'monthly-consumption' ? (string) now()->year : null),
            'Status' => isset($filters['status']) ? match (true) {
                $type === 'delivery-inspections' => ucfirst($filters['status']),
                $filters['status'] === 'warranty' => 'Active warranty cases',
                $filters['status'] === 'awaiting_assessment' => 'Unserviceable',
                default => EquipmentStatus::label($filters['status']),
            } : null,
            'Search' => $filters['search'] ?? null,
        ], fn ($value) => filled($value));
    }

    private function titleFor(string $type): string
    {
        return match ($type) {
            'inventory' => 'Supply Inventory Report',
            'equipment-inventory' => 'Equipment Inventory Report',
            'stock-movements' => 'Stock Movement Report',
            'stock-card' => 'Stock Card Report',
            'property-card' => 'Property Card Report',
            'issuance' => 'Issuance Report',
            'returns' => 'Equipment Return Report',
            'low-stock' => 'Low Stock Report',
            'monthly-consumption' => 'Monthly Supply Consumption Report',
            'equipment-status' => 'Equipment Status Report',
            'delivery-inspections' => 'Delivery Inspection Report',
            default => 'Report',
        };
    }

    private function filenameFor(string $type, ?Item $item = null, ?Equipment $equipment = null): string
    {
        if ($type === 'stock-card' && $item) {
            $label = Str::slug($item->item_name) ?: (Str::slug((string) $item->barcode) ?: "item-{$item->id}");

            return "Stock-Card-{$label}";
        }

        if ($type === 'property-card' && $equipment) {
            $label = Str::slug($equipment->property_number ?: $equipment->name) ?: "equipment-{$equipment->id}";

            return "Property-Card-{$label}";
        }

        return Str::of($this->titleFor($type))->replace(' ', '-')->toString();
    }
}

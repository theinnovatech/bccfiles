<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Item;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function show(Request $request, string $type): JsonResponse
    {
        if ($type === 'stock-card') {
            $request->validate([
                'item_id' => ['required', 'exists:items,id'],
            ]);
        }

        if ($type === 'property-card') {
            $request->validate([
                'equipment_id' => ['required', 'exists:equipments,id'],
            ]);
        }

        return response()->json([
            'type' => $type,
            'item' => $type === 'stock-card' ? Item::query()
                ->with(['category', 'unit', 'location'])
                ->find($request->integer('item_id')) : null,
            'equipment' => $type === 'property-card' ? Equipment::query()
                ->with('category')
                ->find($request->integer('equipment_id')) : null,
            'data' => $this->getData($type, $request),
        ]);
    }

    public function pdf(Request $request, string $type): Response
    {
        if ($type === 'stock-card') {
            $request->validate([
                'item_id' => ['required', 'exists:items,id'],
            ]);
        }

        if ($type === 'property-card') {
            $request->validate([
                'equipment_id' => ['required', 'exists:equipments,id'],
            ]);
        }

        $data = $this->getData($type, $request);
        $title = $this->titleFor($type, $request);
        $item = $type === 'stock-card'
            ? Item::query()->with(['category', 'unit', 'location'])->find($request->integer('item_id'))
            : null;
        $equipment = $type === 'property-card'
            ? Equipment::query()->with('category')->find($request->integer('equipment_id'))
            : null;
        $filename = $this->filenameFor($type, $item, $equipment);

        $pdf = Pdf::loadView('reports.generic', [
            'title' => $title,
            'type' => $type,
            'rows' => $data,
            'item' => $item,
            'equipment' => $equipment,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]);

        if ($request->boolean('preview')) {
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function getData(string $type, Request $request): mixed
    {
        return match ($type) {
            'inventory' => $this->reportService->inventory(),
            'equipment-inventory' => $this->reportService->equipmentInventory(),
            'stock-movements' => $this->reportService->stockMovements(
                $request->query('from'),
                $request->query('to')
            ),
            'issuance' => $this->reportService->issuances(
                $this->departmentIdForReport($request)
            ),
            'returns' => $this->reportService->returns(),
            'low-stock' => $this->reportService->lowStock(),
            'physical-inventory' => $this->reportService->physicalInventory(),
            'monthly-consumption' => $this->reportService->monthlyConsumption(),
            'stock-card' => $this->reportService->stockCard(
                $request->integer('item_id'),
                $request->query('from'),
                $request->query('to'),
                $request->query('transaction_type'),
            ),
            'property-card' => $this->reportService->propertyCard(
                $request->integer('equipment_id'),
                $request->query('from'),
                $request->query('to'),
                $request->query('movement_type'),
            ),
            default => abort(404, 'Report not found.'),
        };
    }

    private function departmentIdForReport(Request $request): ?int
    {
        if ($request->user()->isDepartmentUser()) {
            return $request->user()->department_id;
        }

        if (! $request->filled('department_id')) {
            return null;
        }

        return $request->integer('department_id');
    }

    private function titleFor(string $type, Request $request): string
    {
        if ($type === 'stock-card') {
            return 'Stock Card Report';
        }

        if ($type === 'property-card') {
            return 'Property Card Report';
        }

        return match ($type) {
            'inventory' => 'Supply Inventory Report',
            'equipment-inventory' => 'Equipment Inventory Report',
            'stock-movements' => 'Stock Movement Report',
            'stock-card' => 'Stock Card Report',
            'issuance' => 'Issuance Report',
            'returns' => 'Return Report',
            'low-stock' => 'Low Stock Report',
            'physical-inventory' => 'Physical Inventory Report',
            'monthly-consumption' => 'Monthly Supply Consumption Report',
            default => 'Report',
        };
    }

    private function filenameFor(string $type, ?Item $item = null, ?Equipment $equipment = null): string
    {
        if ($type === 'stock-card' && $item) {
            $label = Str::slug($item->item_name);

            if ($label === '') {
                $label = Str::slug($item->barcode) ?: "item-{$item->id}";
            }

            return "Stock-Card-{$label}.pdf";
        }

        if ($type === 'property-card' && $equipment) {
            $label = Str::slug($equipment->property_number ?: $equipment->name);

            if ($label === '') {
                $label = "equipment-{$equipment->id}";
            }

            return "Property-Card-{$label}.pdf";
        }

        return match ($type) {
            'inventory' => 'Supply-Inventory-Report.pdf',
            'equipment-inventory' => 'Equipment-Inventory-Report.pdf',
            'stock-movements' => 'Stock-Movements-Report.pdf',
            'stock-card' => 'Stock-Card-Report.pdf',
            'property-card' => 'Property-Card-Report.pdf',
            'issuance' => 'Issuance-Report.pdf',
            'returns' => 'Returns-Report.pdf',
            'low-stock' => 'Low-Stock-Report.pdf',
            'physical-inventory' => 'Physical-Inventory-Report.pdf',
            'monthly-consumption' => 'Monthly-Consumption-Report.pdf',
            default => 'Report.pdf',
        };
    }
}

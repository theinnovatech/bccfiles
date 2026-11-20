<?php

namespace App\Services;

use App\Models\DeliveryInspection;
use App\Models\Equipment;
use App\Models\Issuance;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Models\StockMovement;
use App\Support\Xlsx\XlsxWriter;
use Illuminate\Support\Collection;

/**
 * Revision 10: .xlsx exports for the report screens. Columns are typed so dates and
 * quantities open in Excel as real date / numeric values.
 */
class ReportExportService
{
    /**
     * @param  array<string, string>  $filterLabels  human-readable active filters
     */
    public function build(string $type, Collection $rows, string $title, array $filterLabels = [], array $context = []): XlsxWriter
    {
        $columns = $this->columns($type, $context);
        $flatRows = $this->flatten($type, $rows);

        $xlsx = new XlsxWriter();
        $sheet = $xlsx->addSheet($title);
        $count = count($columns);

        $xlsx->addRow($sheet, [$title], XlsxWriter::STYLE_TITLE);
        $xlsx->mergeRow($sheet, 1, $count);
        $xlsx->addRow($sheet, ['Generated', XlsxWriter::dateTime(now())], XlsxWriter::STYLE_BOLD);

        $filters = collect($filterLabels)->filter(fn ($value) => filled($value))
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode('; ');
        $xlsx->addRow($sheet, ['Filters', $filters !== '' ? $filters : 'None (all records)'], XlsxWriter::STYLE_BOLD);

        foreach ($context['notes'] ?? [] as $note) {
            $noteRow = $xlsx->addRow($sheet, [$note], XlsxWriter::STYLE_NOTICE);
            $xlsx->mergeRow($sheet, $noteRow, $count);
        }

        $headerRow = $xlsx->addRow($sheet, array_column($columns, 0), XlsxWriter::STYLE_HEADER);
        $xlsx->freezeBelowRow($sheet, $headerRow);

        foreach ($flatRows as $row) {
            $xlsx->addRow($sheet, array_map(fn ($column) => ($column[2])($row), $columns));
        }

        $xlsx->setColumnWidths($sheet, array_map(fn ($column) => $column[1], $columns));

        return $xlsx;
    }

    /**
     * Some reports are one row per line (issuance details, inspection lines).
     */
    private function flatten(string $type, Collection $rows): Collection
    {
        return match ($type) {
            'issuance' => $rows->flatMap(function (Issuance $issuance) {
                return $issuance->details->isEmpty()
                    ? [['issuance' => $issuance, 'detail' => null]]
                    : $issuance->details->map(fn ($detail) => ['issuance' => $issuance, 'detail' => $detail]);
            }),
            'delivery-inspections' => $rows->flatMap(function (DeliveryInspection $inspection) {
                return $inspection->lines->isEmpty()
                    ? [['inspection' => $inspection, 'line' => null]]
                    : $inspection->lines->map(fn ($line) => ['inspection' => $inspection, 'line' => $line]);
            }),
            default => $rows,
        };
    }

    /**
     * @return list<array{0: string, 1: float, 2: callable}>
     */
    private function columns(string $type, array $context): array
    {
        $d = fn ($value) => XlsxWriter::date($value);
        $dt = fn ($value) => XlsxWriter::dateTime($value);
        $t = fn ($value) => XlsxWriter::text($value);
        $n = fn ($value) => $value === null || $value === '' ? null : (int) $value;

        return match ($type) {
            'inventory', 'low-stock' => [
                ['Stock No.', 16, fn (Item $r) => $t($r->item_number)],
                ['Barcode', 18, fn (Item $r) => $t($r->barcode)],
                ['Inventory No.', 16, fn (Item $r) => $t($r->inventory_number)],
                ['Item', 32, fn (Item $r) => $r->item_name],
                ['Category', 20, fn (Item $r) => $r->category?->name],
                ['Unit', 10, fn (Item $r) => $r->unit?->abbreviation],
                ['Location', 18, fn (Item $r) => $r->location?->name],
                ['Current Stock', 14, fn (Item $r) => $n($r->current_stock)],
                ['Minimum Stock', 14, fn (Item $r) => $n($r->minimum_stock)],
                ['Reference Stock', 15, fn (Item $r) => $n($r->reference_stock)],
                ['Low-Stock %', 11, fn (Item $r) => $r->effective_low_stock_percent],
                ['Low-Stock Threshold', 16, fn (Item $r) => $r->reference_stock_missing ? null : $r->low_stock_threshold],
                ['Stock Status', 14, fn (Item $r) => $r->stock_status_label],
                ['Reference Configured', 14, fn (Item $r) => $r->reference_stock_missing ? 'No — needs setup' : 'Yes'],
                ['Item Status', 12, fn (Item $r) => ucfirst((string) $r->status?->value)],
            ],
            'equipment-inventory' => [
                ['Barcode', 16, fn (Equipment $r) => $t($r->barcode)],
                ['Property No.', 18, fn (Equipment $r) => $t($r->property_number)],
                ['Inventory No.', 16, fn (Equipment $r) => $t($r->inventory_number)],
                ['Property Card No.', 16, fn (Equipment $r) => $t($r->property_card_number)],
                ['Equipment', 28, fn (Equipment $r) => $r->name],
                ['Brand', 16, fn (Equipment $r) => $r->brand],
                ['Model', 16, fn (Equipment $r) => $r->model],
                ['Category', 20, fn (Equipment $r) => $r->category?->name],
                ['Type', 16, fn (Equipment $r) => $r->type],
                ['Available Qty', 12, fn (Equipment $r) => $n($r->quantity)],
                ['Origin', 10, fn (Equipment $r) => $r->source_return_id ? 'Returned' : 'Fresh'],
                ['Date Acquired', 13, fn (Equipment $r) => $d($r->date_acquired)],
                ['Life Span', 18, fn (Equipment $r) => $r->formattedRemainingLifeSpan()],
                ['Warranty Until', 13, fn (Equipment $r) => $d($r->warranty_end_date)],
                ['Description', 30, fn (Equipment $r) => $r->description],
            ],
            'stock-movements' => [
                ['Date', 17, fn (StockMovement $r) => $dt($r->created_at)],
                ['Reference No.', 16, fn (StockMovement $r) => $t($r->reference_number)],
                ['Barcode / Item No.', 18, fn (StockMovement $r) => $t($r->item?->barcode ?: $r->item?->item_number)],
                ['Item', 30, fn (StockMovement $r) => $r->item?->item_name],
                ['Type', 12, fn (StockMovement $r) => $r->transaction_type?->label() ?? $r->transaction_type],
                ['Quantity', 10, fn (StockMovement $r) => $n($r->quantity)],
                ['Previous Balance', 14, fn (StockMovement $r) => $n($r->previous_stock)],
                ['New Balance', 12, fn (StockMovement $r) => $n($r->new_stock)],
                ['Performed By', 18, fn (StockMovement $r) => $r->performer?->name],
                ['Remarks', 40, fn (StockMovement $r) => $r->remarks],
            ],
            'issuance' => [
                ['Issuance No.', 16, fn ($r) => $t($r['issuance']->issuance_number)],
                ['Issued Date', 17, fn ($r) => $dt($r['issuance']->issued_date)],
                ['Received By', 24, fn ($r) => $r['issuance']->recipientDisplayName()],
                ['Employee ID', 14, fn ($r) => $t($r['issuance']->recipient_employee_number ?? $r['issuance']->receiver?->employee_number)],
                ['Unit / Department', 24, fn ($r) => $r['issuance']->departmentDisplayName()],
                ['Processed By', 18, fn ($r) => $r['issuance']->issuer?->name],
                ['Type', 10, fn ($r) => $r['detail'] ? ($r['detail']->equipment_id ? 'Equipment' : 'Item') : null],
                ['Item / Equipment', 28, fn ($r) => $r['detail']?->equipment?->name ?? $r['detail']?->item?->item_name],
                ['Brand / Model', 22, fn ($r) => $r['detail']?->equipment?->brand_model],
                ['Barcode / Property No.', 20, fn ($r) => $t($r['detail']?->property_number ?? $r['detail']?->barcode)],
                ['Inventory No.', 16, fn ($r) => $t($r['detail']?->inventory_number)],
                ['Date Acquired', 13, fn ($r) => $d($r['detail']?->date_acquired)],
                ['Quantity', 10, fn ($r) => $n($r['detail']?->quantity)],
            ],
            'returns' => [
                ['Date Returned', 17, fn (ItemReturn $r) => $dt($r->date_returned)],
                ['Reference No.', 16, fn (ItemReturn $r) => $t($r->reference_number)],
                ['Equipment', 26, fn (ItemReturn $r) => $r->equipment?->name ?? $r->custom_equipment_name],
                ['Brand / Model', 22, fn (ItemReturn $r) => $r->equipment?->brand_model],
                ['Property No.', 18, fn (ItemReturn $r) => $t($r->issuanceDetail?->property_number ?? $r->custom_property_number)],
                ['Unit / Department', 22, fn (ItemReturn $r) => $r->department?->name],
                ['Returned By', 22, fn (ItemReturn $r) => $r->borrowerDisplayName()],
                ['Recorded By', 18, fn (ItemReturn $r) => $r->returner?->name],
                ['Quantity', 10, fn (ItemReturn $r) => $n($r->quantity)],
                ['Condition', 16, fn (ItemReturn $r) => $r->condition_label],
                ['Restocked', 10, fn (ItemReturn $r) => $r->restocked ? 'Yes' : 'No'],
                ['Remarks', 36, fn (ItemReturn $r) => $r->reason],
            ],
            'monthly-consumption' => [
                ['Department', 32, fn ($r) => $r->department],
                ['Total Issued', 14, fn ($r) => $n($r->total)],
            ],
            'stock-card' => [
                ['Date', 17, fn ($r) => $dt($r->created_at)],
                ['Reference No.', 16, fn ($r) => $t($r->reference_number)],
                ['Type', 12, fn ($r) => $r->transaction_type?->label() ?? $r->transaction_type],
                ['Receipt Qty', 12, fn ($r) => in_array($r->transaction_type?->value ?? $r->transaction_type, ['IN', 'RETURN'], true) ? $n($r->quantity) : null],
                ['Issue Qty', 12, fn ($r) => ($r->transaction_type?->value ?? $r->transaction_type) === 'OUT' ? $n($r->quantity) : null],
                ['Adjustment Qty', 14, fn ($r) => ($r->transaction_type?->value ?? $r->transaction_type) === 'ADJUSTMENT' ? $n($r->new_stock - $r->previous_stock) : null],
                ['Balance', 10, fn ($r) => $n($r->new_stock)],
                ['Office', 24, fn ($r) => $r->department_office],
                ['Remarks', 36, fn ($r) => $r->remarks],
            ],
            'property-card' => [
                ['Date', 17, fn ($r) => $dt($r['movement_date'] ?? null)],
                ['Reference No.', 18, fn ($r) => $t($r['reference_number'] ?? null)],
                ['Receipt Qty', 12, fn ($r) => $n($r['receipt_qty'] ?? null)],
                ['Issue Qty', 12, fn ($r) => $n($r['issue_qty'] ?? null)],
                ['Office / Officer', 30, fn ($r) => $r['office_officer'] ?? null],
                ['Balance', 10, fn ($r) => $n($r['balance_qty'] ?? null)],
                ['Remarks', 30, fn ($r) => $r['remarks'] ?? null],
            ],
            'equipment-status' => [
                ['Status', 18, fn ($r) => $r['status_label'] ?? null],
                ['Case No.', 15, fn ($r) => $t($r['case_number'] ?? null)],
                ['Equipment', 26, fn ($r) => $r['name'] ?? null],
                ['Brand / Model', 22, fn ($r) => $r['brand_model'] ?? null],
                ['Category', 18, fn ($r) => $r['category'] ?? null],
                ['Property No.', 18, fn ($r) => $t($r['property_number'] ?? null)],
                ['Inventory No.', 16, fn ($r) => $t($r['inventory_number'] ?? null)],
                ['Serial No(s).', 22, fn ($r) => $t($r['serial_number'] ?? null)],
                ['Quantity', 10, fn ($r) => $n($r['quantity'] ?? null)],
                ['Condition', 14, fn ($r) => $r['condition_label'] ?? null],
                ['Custody', 14, fn ($r) => $r['custody_label'] ?? null],
                ['Holder', 22, fn ($r) => $r['holder'] ?? null],
                ['Unit', 22, fn ($r) => $r['unit'] ?? null],
                ['Date', 13, fn ($r) => $d($r['date'] ?? null)],
                ['Date Acquired', 13, fn ($r) => $d($r['date_acquired'] ?? null)],
                ['Reported Issue / Remarks', 36, fn ($r) => $r['reported_issue'] ?? ($r['reason'] ?? null)],
                ['Warranty Review', 16, fn ($r) => ! empty($r['warranty_active']) ? 'Pending review' : ($r['warranty_outcome_label'] ?? null)],
                ['Warranty Claim Ref.', 18, fn ($r) => $t($r['warranty_claim_reference'] ?? null)],
                ['Disposal Approval', 14, fn ($r) => isset($r['disposal_approval_status']) ? ucfirst((string) $r['disposal_approval_status']) : null],
                ['Disposal Date', 13, fn ($r) => $d($r['disposal_date'] ?? null)],
                ['Disposal Reference', 18, fn ($r) => $t($r['disposal_reference'] ?? null)],
            ],
            'delivery-inspections' => [
                ['Inspection No.', 15, fn ($r) => $t($r['inspection']->inspection_number)],
                ['Delivery Receipt', 16, fn ($r) => $t($r['inspection']->delivery_reference)],
                ['Purchase Order', 16, fn ($r) => $t($r['inspection']->purchase_reference)],
                ['Supplier', 22, fn ($r) => $r['inspection']->supplier],
                ['Delivery Date', 13, fn ($r) => $d($r['inspection']->delivery_date)],
                ['Inspection Date', 13, fn ($r) => $d($r['inspection']->inspection_date)],
                ['Inspector', 20, fn ($r) => $r['inspection']->inspector_name],
                ['Status', 10, fn ($r) => ucfirst($r['inspection']->status)],
                ['Item / Equipment', 28, fn ($r) => $r['line']?->item?->item_name ?? $r['line']?->equipment?->name ?? $r['line']?->description],
                ['Expected', 10, fn ($r) => $n($r['line']?->expected_quantity)],
                ['Delivered', 10, fn ($r) => $n($r['line']?->delivered_quantity)],
                ['Accepted', 10, fn ($r) => $n($r['line']?->accepted_quantity)],
                ['Rejected', 10, fn ($r) => $n($r['line']?->rejected_quantity)],
                ['Condition', 16, fn ($r) => $r['line']?->condition_label],
                ['Discrepancy Remarks', 34, fn ($r) => $r['line']?->discrepancy_remarks],
                ['Posted At', 17, fn ($r) => $dt($r['line']?->posted_at)],
            ],
            default => [
                ['Data', 80, fn ($r) => json_encode($r)],
            ],
        };
    }
}

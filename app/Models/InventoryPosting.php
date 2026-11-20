<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger of inventory postings. posting_key is unique, so the same source line can
 * never be posted to stock twice.
 */
class InventoryPosting extends Model
{
    protected $fillable = [
        'posting_key',
        'delivery_inspection_id',
        'delivery_inspection_line_id',
        'item_id',
        'equipment_id',
        'quantity',
        'stock_movement_id',
        'posted_by',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public static function keyForInspectionLine(int $lineId): string
    {
        return "delivery-inspection-line:{$lineId}";
    }
}

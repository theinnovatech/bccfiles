<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryInspectionLine extends Model
{
    public const CONDITIONS = [
        'good' => 'Good',
        'partial' => 'Partial delivery',
        'damaged' => 'Damaged',
        'wrong_item' => 'Wrong / non-conforming item',
        'mixed' => 'Mixed (some damaged)',
    ];

    protected $fillable = [
        'delivery_inspection_id',
        'line_type',
        'item_id',
        'equipment_id',
        'description',
        'expected_quantity',
        'delivered_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'condition',
        'discrepancy_remarks',
        'serial_numbers',
        'posted_at',
    ];

    protected $appends = [
        'condition_label',
        'shortfall_quantity',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'delivered_quantity' => 'integer',
            'accepted_quantity' => 'integer',
            'rejected_quantity' => 'integer',
            'serial_numbers' => 'array',
            'posted_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(DeliveryInspection::class, 'delivery_inspection_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function posting(): HasOne
    {
        return $this->hasOne(InventoryPosting::class);
    }

    public function getConditionLabelAttribute(): string
    {
        return self::CONDITIONS[$this->condition] ?? ucfirst((string) $this->condition);
    }

    public function getShortfallQuantityAttribute(): int
    {
        return max(0, (int) $this->expected_quantity - (int) $this->delivered_quantity);
    }
}

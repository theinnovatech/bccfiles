<?php

namespace App\Models;

use App\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'barcode',
        'item_name',
        'description',
        'brand',
        'category_id',
        'unit_id',
        'location_id',
        'minimum_stock',
        'current_stock',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
            'minimum_stock' => 'integer',
            'current_stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'location_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->current_stock > 0 && $this->current_stock <= $this->minimum_stock;
    }

    public function isOutOfStock(): bool
    {
        return $this->current_stock === 0;
    }
}

<?php

namespace App\Models;

use App\Enums\ItemStatus;
use App\Support\StockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'barcode',
        'item_number',
        'inventory_number',
        'item_name',
        'description',
        'brand',
        'category_id',
        'unit_id',
        'location_id',
        'minimum_stock',
        'reference_stock',
        'low_stock_percent',
        'unit_cost',
        'current_stock',
        'status',
        'stock_alert_level',
        'stock_alert_since',
    ];

    protected $appends = [
        'stock_status',
        'stock_status_label',
        'reference_stock_missing',
        'low_stock_threshold',
        'effective_low_stock_percent',
    ];

    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
            'minimum_stock' => 'integer',
            'reference_stock' => 'integer',
            'low_stock_percent' => 'float',
            'stock_alert_since' => 'datetime',
            'unit_cost' => 'decimal:2',
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

    public function stockStatus(?int $available = null): string
    {
        return StockStatus::classify(
            $available ?? (int) $this->current_stock,
            $this->reference_stock,
            $this->minimum_stock,
            $this->low_stock_percent
        );
    }

    public function getEffectiveLowStockPercentAttribute(): ?float
    {
        return StockStatus::hasReference($this->reference_stock)
            ? StockStatus::effectivePercent($this->low_stock_percent)
            : null;
    }

    public function getStockStatusAttribute(): string
    {
        return $this->stockStatus();
    }

    public function getStockStatusLabelAttribute(): string
    {
        return StockStatus::label($this->stockStatus());
    }

    public function getReferenceStockMissingAttribute(): bool
    {
        return $this->reference_stock === null || (int) $this->reference_stock < 1;
    }

    public function getLowStockThresholdAttribute(): float
    {
        return StockStatus::threshold($this->reference_stock, $this->minimum_stock, $this->low_stock_percent);
    }

    public function isLowStock(): bool
    {
        return $this->stockStatus() === StockStatus::LOW;
    }

    public function isOutOfStock(): bool
    {
        return $this->stockStatus() === StockStatus::OUT;
    }

    public function scopeWithStockStatus(Builder $query, ?string $status): Builder
    {
        return StockStatus::apply($query, $status, $this->getTable());
    }

    public function displayIdentifier(): string
    {
        return $this->barcode
            ?: ($this->inventory_number ?: ($this->item_number ?: '—'));
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()
            ->where(function ($query) use ($code) {
                $query->where('barcode', $code)
                    ->orWhere('item_number', $code)
                    ->orWhere('inventory_number', $code);
            })
            ->first();
    }
}

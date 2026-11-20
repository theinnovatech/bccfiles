<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentSerialNumber extends Model
{
    use SoftDeletes;

    public const STATUS_IN_STOCK = 'in_stock';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_FOR_REPAIR = 'for_repair';
    public const STATUS_FOR_DISPOSAL = 'for_disposal';
    public const STATUS_DISPOSED = 'disposed';

    protected $fillable = [
        'equipment_id',
        'serial_number',
        'property_number',
        'inventory_number',
        'status',
        'issuance_detail_id',
        'item_return_id',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function issuanceDetail(): BelongsTo
    {
        return $this->belongsTo(IssuanceDetail::class);
    }

    public function itemReturn(): BelongsTo
    {
        return $this->belongsTo(ItemReturn::class);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_STOCK)->whereNull('issuance_detail_id');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ISSUED);
    }
}

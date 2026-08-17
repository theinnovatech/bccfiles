<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'name',
        'property_number',
        'inventory_number',
        'barcode',
        'equipment_category_id',
        'description',
        'type',
        'quantity',
        'life_span_years',
        'specs',
        'date_acquired',
        'source_return_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'life_span_years' => 'integer',
            'date_acquired' => 'date',
        ];
    }

    public function hasReachedLifespan(?DateTimeInterface $asOf = null): bool
    {
        if (! $this->life_span_years || ! $this->date_acquired) {
            return false;
        }

        $limit = $this->date_acquired->copy()->startOfDay()->addYears($this->life_span_years);
        $asOfDate = Carbon::parse($asOf ?? now())->startOfDay();

        return $limit->lte($asOfDate);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function sourceReturn(): BelongsTo
    {
        return $this->belongsTo(ItemReturn::class, 'source_return_id');
    }
}

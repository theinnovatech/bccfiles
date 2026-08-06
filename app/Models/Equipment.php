<?php

namespace App\Models;

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
        'source_return_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'life_span_years' => 'integer',
        ];
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

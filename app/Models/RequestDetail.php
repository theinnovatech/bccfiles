<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestDetail extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'request_id',
        'item_id',
        'equipment_id',
        'quantity_requested',
        'quantity_issued',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_issued' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}

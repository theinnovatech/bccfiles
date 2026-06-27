<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockCountItem extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'session_id',
        'item_id',
        'expected_quantity',
        'physical_quantity',
        'variance',
        'adjustment_created',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'physical_quantity' => 'integer',
            'variance' => 'integer',
            'adjustment_created' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockCountSession::class, 'session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

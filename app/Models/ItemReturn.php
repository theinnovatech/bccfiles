<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemReturn extends Model
{
    use SoftDeletes;
    protected $table = 'returns';

    protected $fillable = [
        'issuance_id',
        'item_id',
        'quantity',
        'reason',
        'returned_by',
        'date_returned',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'date_returned' => 'datetime',
        ];
    }

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(Issuance::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}

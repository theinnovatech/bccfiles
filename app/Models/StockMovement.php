<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'item_id',
        'transaction_type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reference_number',
        'remarks',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'quantity' => 'integer',
            'previous_stock' => 'integer',
            'new_stock' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

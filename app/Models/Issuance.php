<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Issuance extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'issuance_number',
        'request_id',
        'issued_by',
        'received_by',
        'issued_date',
    ];

    protected function casts(): array
    {
        return ['issued_date' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'request_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'received_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(IssuanceDetail::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ItemReturn::class, 'issuance_id');
    }
}

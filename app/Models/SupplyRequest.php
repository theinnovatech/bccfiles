<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplyRequest extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'request_number',
        'request_type',
        'department_id',
        'requested_by',
        'approved_by',
        'status',
        'remarks',
        'request_date',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'request_type' => RequestType::class,
            'request_date' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(RequestDetail::class, 'request_id');
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(Issuance::class, 'request_id');
    }
}

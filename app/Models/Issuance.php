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
        'idempotency_key',
        'department_id',
        'department_name',
        'issued_by',
        'received_by',
        'received_by_name',
        'recipient_employee_number',
        'recipient_designation',
        'recipient_source',
        'channel',
        'issued_date',
    ];

    protected $hidden = [
        'idempotency_key',
    ];

    protected $appends = [
        'recipient_display_name',
        'unit_display_name',
    ];

    protected function casts(): array
    {
        return ['issued_date' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Unit recorded at issuance. New issuances always store a snapshot in department_name,
     * so later directory/department edits never rewrite history. Legacy rows fall back
     * to the linked department.
     */
    public function departmentDisplayName(): ?string
    {
        return $this->department_name
            ?: ($this->department?->name ?: $this->receiver?->department?->name);
    }

    /**
     * Recipient name recorded at issuance (snapshot first, then the legacy link).
     */
    public function recipientDisplayName(): ?string
    {
        return $this->received_by_name ?: $this->receiver?->name;
    }

    public function getRecipientDisplayNameAttribute(): ?string
    {
        return $this->recipientDisplayName();
    }

    public function getUnitDisplayNameAttribute(): ?string
    {
        return $this->departmentDisplayName();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'received_by')->withTrashed();
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

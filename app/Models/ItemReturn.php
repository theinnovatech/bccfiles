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
        'equipment_id',
        'department_id',
        'borrower_employee_id',
        'borrower_name',
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

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'borrower_employee_id');
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function borrowerDisplayName(): string
    {
        return $this->borrower?->name
            ?? $this->borrower_name
            ?? '—';
    }
}

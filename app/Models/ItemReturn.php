<?php

namespace App\Models;

use App\Enums\ReturnCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemReturn extends Model
{
    use SoftDeletes;

    protected $table = 'returns';

    protected $fillable = [
        'reference_number',
        'issuance_id',
        'item_id',
        'equipment_id',
        'custom_equipment_name',
        'custom_property_number',
        'custom_inventory_number',
        'custom_equipment_type',
        'custom_equipment_category',
        'department_id',
        'borrower_employee_id',
        'borrower_name',
        'quantity',
        'reason',
        'condition',
        'restocked',
        'returned_by',
        'date_returned',
    ];

    protected $appends = [
        'reissuable',
        'lifespan_reached',
        'condition_label',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'date_returned' => 'datetime',
            'condition' => ReturnCondition::class,
            'restocked' => 'boolean',
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

    public function getConditionLabelAttribute(): string
    {
        $condition = $this->condition instanceof ReturnCondition
            ? $this->condition
            : ReturnCondition::tryFrom((string) $this->condition);

        return $condition?->label() ?? 'Returned well';
    }

    public function getLifespanReachedAttribute(): bool
    {
        if (! $this->equipment) {
            return false;
        }

        return $this->equipment->hasReachedLifespan($this->date_returned);
    }

    public function getReissuableAttribute(): bool
    {
        if ($this->condition !== ReturnCondition::Good) {
            return false;
        }

        if (! $this->equipment_id || ! $this->equipment) {
            return false;
        }

        return ! $this->lifespan_reached;
    }
}

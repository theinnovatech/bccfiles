<?php

namespace App\Models;

use App\Support\EquipmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentCase extends Model
{
    use SoftDeletes;

    public const ORIGIN_STOCK = 'stock';
    public const ORIGIN_RETURN = 'return';

    protected $fillable = [
        'case_number',
        'equipment_id',
        'equipment_serial_number_id',
        'item_return_id',
        'origin',
        'quantity',
        'status',
        'condition',
        'custody',
        'reported_issue',
        'reported_on',
        'reported_by',
        'repair_outcome',
        'serviceability_passed',
        'serviceability_checked_on',
        'serviceability_checked_by',
        'warranty_status',
        'warranty_end_date_at_report',
        'warranty_claim_reference',
        'warranty_outcome',
        'warranty_decision_notes',
        'warranty_decided_on',
        'warranty_decided_by',
        'disposal_assessment',
        'disposal_approval_status',
        'disposal_approved_by_name',
        'disposal_approval_reference',
        'disposal_approval_date',
        'disposal_method',
        'disposal_date',
        'disposal_reference',
        'unit_cost',
        'accumulated_impairment',
        'carrying_amount',
        'disposal_sale_qty',
        'disposal_transfer_qty',
        'disposal_destruction_qty',
        'disposal_others_qty',
        'disposal_others_specify',
        'appraised_value',
        'or_number',
        'sale_amount',
        'closed_at',
    ];

    protected $appends = [
        'status_label',
        'condition_label',
        'custody_label',
        'warranty_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'serviceability_passed' => 'boolean',
            'reported_on' => 'date',
            'serviceability_checked_on' => 'date',
            'warranty_end_date_at_report' => 'date',
            'warranty_decided_on' => 'date',
            'disposal_approval_date' => 'date',
            'disposal_date' => 'date',
            'closed_at' => 'datetime',
            'unit_cost' => 'decimal:2',
            'accumulated_impairment' => 'decimal:2',
            'carrying_amount' => 'decimal:2',
            'appraised_value' => 'decimal:2',
            'sale_amount' => 'decimal:2',
            'disposal_sale_qty' => 'integer',
            'disposal_transfer_qty' => 'integer',
            'disposal_destruction_qty' => 'integer',
            'disposal_others_qty' => 'integer',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(EquipmentSerialNumber::class, 'equipment_serial_number_id')->withTrashed();
    }

    public function itemReturn(): BelongsTo
    {
        return $this->belongsTo(ItemReturn::class, 'item_return_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EquipmentCaseEvent::class)->orderBy('event_date')->orderBy('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function scopeWarrantyActive(Builder $query): Builder
    {
        return $query->where('warranty_status', EquipmentStatus::WARRANTY_PENDING)->whereNull('closed_at');
    }

    public function isWarrantyPending(): bool
    {
        return $this->warranty_status === EquipmentStatus::WARRANTY_PENDING;
    }

    public function getStatusLabelAttribute(): string
    {
        return EquipmentStatus::label($this->status);
    }

    public function getConditionLabelAttribute(): string
    {
        return EquipmentStatus::conditionLabel($this->condition);
    }

    public function getCustodyLabelAttribute(): string
    {
        return EquipmentStatus::custodyLabel($this->custody);
    }

    public function getWarrantyActiveAttribute(): bool
    {
        return $this->isWarrantyPending() && $this->closed_at === null;
    }
}

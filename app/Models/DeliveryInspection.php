<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryInspection extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'inspection_number',
        'delivery_reference',
        'purchase_reference',
        'supplier',
        'delivery_date',
        'inspection_date',
        'inspector_name',
        'inspected_by',
        'status',
        'remarks',
        'posted_at',
        'posted_by',
        'created_by',
    ];

    protected $appends = [
        'is_partial',
        'has_rejections',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'inspection_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryInspectionLine::class)->orderBy('id');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(InventoryPosting::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function getIsPartialAttribute(): bool
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        return $lines->contains(fn ($line) => (int) $line->delivered_quantity < (int) $line->expected_quantity);
    }

    public function getHasRejectionsAttribute(): bool
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        return $lines->contains(fn ($line) => (int) $line->rejected_quantity > 0);
    }
}

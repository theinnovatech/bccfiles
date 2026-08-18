<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'name',
        'property_number',
        'inventory_number',
        'barcode',
        'equipment_category_id',
        'description',
        'type',
        'quantity',
        'life_span_years',
        'specs',
        'date_acquired',
        'lifespan_expires_on',
        'source_return_id',
    ];

    protected $appends = [
        'origin',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'life_span_years' => 'integer',
            'date_acquired' => 'date',
            'lifespan_expires_on' => 'date',
        ];
    }

    public function hasReachedLifespan(?DateTimeInterface $asOf = null, ?DateTimeInterface $acquiredOverride = null): bool
    {
        $asOfDate = Carbon::parse($asOf ?? now())->startOfDay();
        $expires = $this->expiryFromAcquired($acquiredOverride);

        if (! $expires) {
            return $this->life_span_years === 0;
        }

        return $expires->lte($asOfDate);
    }

    public function remainingLifeSpanYears(?DateTimeInterface $asOf = null, ?DateTimeInterface $acquiredOverride = null): ?int
    {
        if ($this->life_span_years === null && ! $this->lifespan_expires_on && ! $acquiredOverride) {
            return null;
        }

        $expires = $this->expiryFromAcquired($acquiredOverride);
        if (! $expires) {
            return $this->life_span_years !== null ? (int) $this->life_span_years : null;
        }

        $asOfDate = Carbon::parse($asOf ?? now())->startOfDay();
        if ($asOfDate->gte($expires)) {
            return 0;
        }

        $diff = $asOfDate->diff($expires);
        $years = (int) $diff->y;

        // Less than one full year left still counts as 1 year remaining
        // (e.g. acquired 2022, 5-year span, today 2026 → 1 year left, not 0).
        if ($years === 0 && ($diff->m > 0 || $diff->d > 0 || $diff->h > 0 || $diff->i > 0 || $diff->s > 0)) {
            return 1;
        }

        return $years;
    }

    public function expiryFromAcquired(?DateTimeInterface $acquired = null): ?Carbon
    {
        $start = $acquired
            ? Carbon::parse($acquired)->startOfDay()
            : $this->date_acquired?->copy()->startOfDay();

        if ($acquired && $start && $this->life_span_years && ! $this->isReturnedStock()) {
            return $start->copy()->addYears((int) $this->life_span_years);
        }

        if ($this->lifespan_expires_on) {
            return $this->lifespan_expires_on->copy()->startOfDay();
        }

        if (! $start || ! $this->life_span_years) {
            return null;
        }

        return $start->copy()->addYears((int) $this->life_span_years);
    }

    public function getOriginAttribute(): string
    {
        return $this->source_return_id ? 'returned' : 'fresh';
    }

    public function isReturnedStock(): bool
    {
        return $this->source_return_id !== null;
    }

    public function ensureLifespanExpiresOn(?string $dateAcquired = null): void
    {
        if ($this->lifespan_expires_on || ! $this->life_span_years) {
            return;
        }

        $acquired = $dateAcquired
            ? Carbon::parse($dateAcquired)->startOfDay()
            : $this->date_acquired?->copy()->startOfDay();

        if (! $acquired) {
            return;
        }

        $this->lifespan_expires_on = $acquired->copy()->addYears((int) $this->life_span_years);
        $this->save();
    }

    public function reduceRemainingLifeSpan(DateTimeInterface $returnedOn): void
    {
        $this->ensureLifespanExpiresOn();

        $remaining = $this->remainingLifeSpanYears($returnedOn);
        if ($remaining === null || (int) $this->life_span_years === $remaining) {
            return;
        }

        $this->update(['life_span_years' => $remaining]);
    }

    public function lifespanExpiryDate(): ?Carbon
    {
        if ($this->lifespan_expires_on) {
            return $this->lifespan_expires_on->copy()->startOfDay();
        }

        if (! $this->life_span_years || ! $this->date_acquired) {
            return null;
        }

        return $this->date_acquired->copy()->startOfDay()->addYears((int) $this->life_span_years);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function sourceReturn(): BelongsTo
    {
        return $this->belongsTo(ItemReturn::class, 'source_return_id');
    }
}

<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Remaining life span in complete years, leftover months, and leftover days.
     * Months are never rounded up to a year; leftover days are never rounded up to a month.
     *
     * @return array{years: int, months: int, days: int}|null
     */
    public function remainingLifeSpan(?DateTimeInterface $asOf = null, ?DateTimeInterface $acquiredOverride = null): ?array
    {
        if ($this->life_span_years === null && ! $this->lifespan_expires_on && ! $acquiredOverride) {
            return null;
        }

        $expires = $this->expiryFromAcquired($acquiredOverride);
        if (! $expires) {
            if ($this->life_span_years === null) {
                return null;
            }

            return [
                'years' => (int) $this->life_span_years,
                'months' => 0,
                'days' => 0,
            ];
        }

        $asOfDate = Carbon::parse($asOf ?? $this->lifeSpanAsOfDate() ?? now())->startOfDay();
        if ($asOfDate->gte($expires)) {
            return ['years' => 0, 'months' => 0, 'days' => 0];
        }

        $diff = $asOfDate->diff($expires);

        return [
            'years' => (int) $diff->y,
            'months' => (int) $diff->m,
            'days' => (int) $diff->d,
        ];
    }

    public function remainingLifeSpanYears(?DateTimeInterface $asOf = null, ?DateTimeInterface $acquiredOverride = null): ?int
    {
        $parts = $this->remainingLifeSpan($asOf, $acquiredOverride);

        return $parts === null ? null : $parts['years'];
    }

    public function lifeSpanAsOfDate(): ?Carbon
    {
        if (! $this->isReturnedStock()) {
            return null;
        }

        $this->loadMissing('sourceReturn');

        return $this->sourceReturn?->date_returned?->copy()->startOfDay();
    }

    public function formattedRemainingLifeSpan(?DateTimeInterface $asOf = null, ?DateTimeInterface $acquiredOverride = null): ?string
    {
        $parts = $this->remainingLifeSpan($asOf ?? $this->lifeSpanAsOfDate(), $acquiredOverride);
        if ($parts === null) {
            return null;
        }

        $expires = $this->expiryFromAcquired($acquiredOverride);
        $remaining = $expires !== null || $acquiredOverride !== null || $this->date_acquired !== null;

        return self::formatLifeSpanParts($parts, $remaining);
    }

    /**
     * @param  array{years: int, months: int, days: int}  $parts
     */
    public static function formatLifeSpanParts(array $parts, bool $remaining = false): string
    {
        $years = (int) ($parts['years'] ?? 0);
        $months = (int) ($parts['months'] ?? 0);
        $days = (int) ($parts['days'] ?? 0);
        $chunks = [];

        if ($years > 0) {
            $chunks[] = $years.' '.($years === 1 ? 'yr' : 'yrs');
        }

        if ($months > 0) {
            $chunks[] = $months.' '.($months === 1 ? 'mo' : 'mos');
        }

        if ($years === 0 && $months === 0 && $days > 0) {
            $chunks[] = $days.' '.($days === 1 ? 'day' : 'days');
        }

        if ($chunks === []) {
            $label = '0 yrs';
        } else {
            $label = implode(' ', $chunks);
        }

        return $remaining ? $label.' remaining' : $label;
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

    public function issuanceDetails(): HasMany
    {
        return $this->hasMany(IssuanceDetail::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ItemReturn::class);
    }
}

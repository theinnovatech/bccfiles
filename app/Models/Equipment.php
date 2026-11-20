<?php

namespace App\Models;

use App\Support\PropertyClass;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'name',
        'brand',
        'model',
        'property_number',
        'inventory_number',
        'property_card_number',
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
        'warranty_provider',
        'warranty_reference',
        'warranty_start_date',
        'warranty_end_date',
        'unit_cost',
        'property_class',
    ];

    protected $appends = [
        'origin',
        'property_class_label',
        'brand_model',
        'unit_count',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'life_span_years' => 'integer',
            'date_acquired' => 'date',
            'lifespan_expires_on' => 'date',
            'warranty_start_date' => 'date',
            'warranty_end_date' => 'date',
            'unit_cost' => 'decimal:2',
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

    /**
     * Whether recorded warranty coverage is active on the given date.
     */
    public function hasActiveWarranty(?DateTimeInterface $on = null): bool
    {
        if (! $this->warranty_end_date) {
            return false;
        }

        $date = Carbon::parse($on ?? now())->startOfDay();

        if ($this->warranty_start_date && $this->warranty_start_date->copy()->startOfDay()->gt($date)) {
            return false;
        }

        return $this->warranty_end_date->copy()->startOfDay()->gte($date);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(EquipmentCase::class);
    }

    public function getBrandModelAttribute(): ?string
    {
        $value = trim(implode(' ', array_filter([$this->brand, $this->model])));

        return $value === '' ? null : $value;
    }

    /**
     * Number of physical units recorded on this equipment (serialised units that are not
     * disposed). Null for legacy records without serial numbers, where units are not tracked
     * one by one.
     */
    public function getUnitCountAttribute(): ?int
    {
        $serials = $this->relationLoaded('serialNumbers') ? $this->serialNumbers : $this->serialNumbers()->get();
        $units = $serials->where('status', '!=', EquipmentSerialNumber::STATUS_DISPOSED);

        return $serials->isEmpty() ? null : $units->count();
    }

    /**
     * Whether this record represents exactly one physical unit, so its own property and
     * inventory numbers are the unit's numbers.
     */
    public function isSingleUnit(): bool
    {
        return $this->unit_count === 1;
    }

    /**
     * Name with brand, model and type, as printed on documents.
     */
    public function documentDescription(): string
    {
        return trim(implode(' — ', array_filter([$this->name, $this->brand_model, $this->type])));
    }

    public function getPropertyClassLabelAttribute(): string
    {
        return PropertyClass::label($this->property_class);
    }

    public function isSemiExpendable(): bool
    {
        return $this->property_class === PropertyClass::SEMI_EXPENDABLE;
    }

    public function getOriginAttribute(): string
    {
        return $this->source_return_id ? 'returned' : 'fresh';
    }

    /**
     * How the Property No. of an issuance line is decided for this record (mirrors
     * IssuanceController::resolveLinePropertyNumber, used by the forms to auto-fill and lock):
     *   unit   - each unit carries its own recorded number (read-only)
     *   record - single unit: the record's own number (read-only)
     *   returned - legacy returned-stock record: the record's number unless typed
     *   typed  - several units under one record number: type the unit's number from the paper form
     */
    public function propertyNumberRule(): string
    {
        $inStock = $this->inStockSerialNumbers();
        if ($inStock->isNotEmpty() && $inStock->every(fn ($unit) => filled($unit->property_number))) {
            return 'unit';
        }
        if ($this->isReturnedStock()) {
            return 'returned';
        }
        if ($this->isSingleUnit() && filled($this->property_number)) {
            return 'record';
        }

        return 'typed';
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

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(EquipmentSerialNumber::class)->orderBy('id');
    }

    /**
     * @param  list<string>  $serialNumbers
     */
    public function syncInStockSerialNumbers(array $serialNumbers): void
    {
        $normalized = collect($serialNumbers)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        $quantity = (int) $this->quantity;
        if ($normalized->count() !== $quantity) {
            throw new InvalidArgumentException(
                "Enter exactly {$quantity} serial number(s), matching the quantity."
            );
        }

        if ($normalized->count() !== $normalized->map(fn ($v) => mb_strtolower($v))->unique()->count()) {
            throw new InvalidArgumentException('Serial numbers on this equipment must be unique.');
        }

        // Units of this record that are in stock may keep their serials; any other unit
        // (another record, or this record's issued / under repair / disposed units) is a conflict.
        $taken = EquipmentSerialNumber::query()
            ->whereIn('serial_number', $normalized)
            ->where(function ($query) {
                $query->where('equipment_id', '!=', $this->id)
                    ->orWhere('status', '!=', EquipmentSerialNumber::STATUS_IN_STOCK);
            })
            ->pluck('serial_number');

        if ($taken->isNotEmpty()) {
            throw new InvalidArgumentException(
                'Serial number already in use: '.$taken->unique()->implode(', ')
            );
        }

        // Update in place: unchanged serials keep their unit record (internal ID, recorded
        // property / inventory numbers and history). Only removed serials are deleted and only
        // new serials are created.
        $existing = $this->serialNumbers()->inStock()->get();
        $kept = [];

        foreach ($normalized as $serialNumber) {
            $match = $existing->first(fn ($row) => ! in_array($row->id, $kept, true) && $row->serial_number === $serialNumber)
                ?? $existing->first(fn ($row) => ! in_array($row->id, $kept, true) && mb_strtolower($row->serial_number) === mb_strtolower($serialNumber));

            if ($match) {
                if ($match->serial_number !== $serialNumber) {
                    $match->update(['serial_number' => $serialNumber]);
                }
                $kept[] = $match->id;

                continue;
            }

            $created = $this->serialNumbers()->create([
                'serial_number' => $serialNumber,
                'status' => EquipmentSerialNumber::STATUS_IN_STOCK,
            ]);
            $kept[] = $created->id;
        }

        $existing->reject(fn ($row) => in_array($row->id, $kept, true))->each->delete();
    }

    /**
     * @param  list<string>  $serialNumbers
     */
    public function appendInStockSerialNumbers(array $serialNumbers): void
    {
        $normalized = collect($serialNumbers)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            return;
        }

        if ($normalized->count() !== $normalized->unique()->count()) {
            throw new InvalidArgumentException('Serial numbers on this equipment must be unique.');
        }

        $taken = EquipmentSerialNumber::query()
            ->whereIn('serial_number', $normalized)
            ->pluck('serial_number');

        if ($taken->isNotEmpty()) {
            throw new InvalidArgumentException(
                'Serial number already in use: '.$taken->unique()->implode(', ')
            );
        }

        foreach ($normalized as $serialNumber) {
            $this->serialNumbers()->create([
                'serial_number' => $serialNumber,
                'status' => EquipmentSerialNumber::STATUS_IN_STOCK,
            ]);
        }
    }

    public function inStockSerialNumbers(): Collection
    {
        return $this->serialNumbers
            ->where('status', EquipmentSerialNumber::STATUS_IN_STOCK)
            ->whereNull('issuance_detail_id')
            ->values();
    }
}

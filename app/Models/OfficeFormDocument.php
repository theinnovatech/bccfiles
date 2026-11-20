<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The locked copy of an issued Local RIV or Inventory Custodian Slip. Reprints use this payload,
 * so they keep the original names, offices, descriptions, costs and signatories even when master
 * records or settings change later. Rows are never updated after creation.
 */
class OfficeFormDocument extends Model
{
    public const RIV = 'riv';
    public const ICS = 'ics';
    public const IIRUP = 'iirup';

    protected $fillable = [
        'form_type',
        'document_number',
        'issuance_id',
        'as_of_date',
        'template_revision',
        'payload',
        'issued_by',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'issued_at' => 'datetime',
            'as_of_date' => 'date:Y-m-d',
            'template_revision' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(Issuance::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}

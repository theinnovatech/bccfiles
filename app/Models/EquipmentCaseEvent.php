<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentCaseEvent extends Model
{
    protected $fillable = [
        'equipment_case_id',
        'action',
        'from_status',
        'to_status',
        'reason',
        'event_date',
        'reference',
        'performed_by',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'details' => 'array',
        ];
    }

    public function equipmentCase(): BelongsTo
    {
        return $this->belongsTo(EquipmentCase::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

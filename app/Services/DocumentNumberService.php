<?php

namespace App\Services;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Organization-defined numbering for official numbers (feedback item 4).
 *
 * Each document type is either
 *   - manual: authorized users type the number the organization assigned, or
 *   - auto:   the system generates it from a user-defined format.
 *
 * Format tokens: {YYYY} {YY} {MM} {DD} and {SEQ} (the sequence, zero-padded to "padding"
 * digits). Everything else in the format is copied as typed, so prefixes, separators and
 * letters are the organization's own, e.g. "PROP-{YYYY}-{SEQ}" or "{YYYY}-{MM}-{DD}-LV-{SEQ}".
 *
 * Sequences reset never, yearly or monthly. They are incremented with a single atomic
 * INSERT ... ON DUPLICATE KEY UPDATE inside the caller's transaction, so simultaneous
 * submissions get different numbers; a generated number that already exists (e.g. typed
 * manually earlier) is skipped. Numbers are stored as text and trimmed; previously assigned
 * numbers never change when settings change.
 */
class DocumentNumberService
{
    public const TYPES = [
        'equipment_property_number' => ['label' => 'Equipment Property Number', 'required' => true],
        'equipment_inventory_number' => ['label' => 'Equipment Inventory Number', 'required' => false],
        'stock_card_number' => ['label' => 'Stock Card Number (Stock No.)', 'required' => true],
        'property_card_number' => ['label' => 'Property Card Number', 'required' => false],
        'ics_number' => ['label' => 'ICS Number', 'required' => true],
        'riv_number' => ['label' => 'Local RIV Number', 'required' => true],
        'iirup_number' => ['label' => 'IIRUP Number', 'required' => true],
    ];

    public const MODES = ['manual', 'auto'];
    public const RESET_RULES = ['never', 'yearly', 'monthly'];

    /**
     * @return array{document_type: string, label: string, mode: string, format: string, padding: int, reset_rule: string, required: bool}
     */
    public function settings(string $type): array
    {
        $this->assertType($type);
        $row = DB::table('document_number_settings')->where('document_type', $type)->first();

        return [
            'document_type' => $type,
            'label' => self::TYPES[$type]['label'],
            'required' => self::TYPES[$type]['required'],
            'mode' => $row->mode ?? 'manual',
            'format' => $row->format ?? strtoupper(explode('_', $type)[0]).'-{YYYY}-{SEQ}',
            'padding' => (int) ($row->padding ?? 4),
            'reset_rule' => $row->reset_rule ?? 'yearly',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(function (string $type) {
            $settings = $this->settings($type);

            return $settings + [
                'preview' => $this->preview($settings),
                'next_preview' => $settings['mode'] === 'auto' ? $this->peekNext($type, $settings) : null,
            ];
        }, array_keys(self::TYPES));
    }

    /**
     * Validate and save a type's settings. Returns the saved settings.
     */
    public function save(string $type, array $data, ?int $userId = null): array
    {
        $this->assertType($type);
        $settings = $this->validateSettings($data);

        DB::table('document_number_settings')->updateOrInsert(
            ['document_type' => $type],
            [
                'mode' => $settings['mode'],
                'format' => $settings['format'],
                'padding' => $settings['padding'],
                'reset_rule' => $settings['reset_rule'],
                'updated_by' => $userId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $this->settings($type);
    }

    /**
     * @return array{mode: string, format: string, padding: int, reset_rule: string}
     */
    public function validateSettings(array $data): array
    {
        $mode = (string) ($data['mode'] ?? '');
        $format = trim((string) ($data['format'] ?? ''));
        $padding = (int) ($data['padding'] ?? 4);
        $reset = (string) ($data['reset_rule'] ?? 'never');
        $errors = [];

        if (! in_array($mode, self::MODES, true)) {
            $errors['mode'] = 'Choose manual or automatic numbering.';
        }
        if (! in_array($reset, self::RESET_RULES, true)) {
            $errors['reset_rule'] = 'Choose never, yearly or monthly.';
        }
        if ($padding < 1 || $padding > 10) {
            $errors['padding'] = 'Sequence padding must be between 1 and 10 digits.';
        }

        if ($mode === 'auto') {
            if ($format === '' || mb_strlen($format) > 100) {
                $errors['format'] = 'Enter a format of at most 100 characters.';
            } elseif (substr_count($format, '{SEQ}') !== 1) {
                $errors['format'] = 'The format must contain {SEQ} exactly once.';
            } elseif (preg_match('/\{(?!YYYY\}|YY\}|MM\}|DD\}|SEQ\})[^}]*\}/', $format)) {
                $errors['format'] = 'Unknown token. Use only {YYYY}, {YY}, {MM}, {DD} and {SEQ}.';
            } elseif ($reset === 'yearly' && ! preg_match('/\{YYYY\}|\{YY\}/', $format)) {
                $errors['format'] = 'A yearly reset needs {YYYY} or {YY} in the format, otherwise numbers would repeat each year.';
            } elseif ($reset === 'monthly' && (! preg_match('/\{YYYY\}|\{YY\}/', $format) || ! str_contains($format, '{MM}'))) {
                $errors['format'] = 'A monthly reset needs the year ({YYYY} or {YY}) and {MM} in the format, otherwise numbers would repeat.';
            }
        } elseif ($format === '') {
            $format = '{SEQ}';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['mode' => $mode, 'format' => $format, 'padding' => $padding, 'reset_rule' => $reset];
    }

    /**
     * Example of the format for a given date and sequence value.
     */
    public function preview(array $settings, ?Carbon $date = null, int $sequence = 1): string
    {
        return $this->format($settings['format'], (int) $settings['padding'], $date ?? now(), $sequence);
    }

    /**
     * The number the next automatic generation would produce (without using it).
     */
    public function peekNext(string $type, ?array $settings = null, ?Carbon $date = null): string
    {
        $settings ??= $this->settings($type);
        $date ??= now();
        $last = (int) DB::table('document_number_sequences')
            ->where('document_type', $type)
            ->where('period_key', $this->periodKey($settings['reset_rule'], $date))
            ->value('last_value');

        return $this->format($settings['format'], (int) $settings['padding'], $date, $last + 1);
    }

    /**
     * Resolve the number to save: a typed number (manual mode, or an explicit value in auto
     * mode) or a generated one. $exists(string $number): bool checks the correct uniqueness
     * scope and must exclude the record being edited.
     */
    public function resolve(string $type, ?string $typed, Closure $exists, ?Carbon $date = null, string $field = 'number'): ?string
    {
        $settings = $this->settings($type);
        $typed = $this->clean($typed);

        if ($typed !== null) {
            if ($exists($typed)) {
                throw ValidationException::withMessages([
                    $field => "{$settings['label']} {$typed} is already in use.",
                ]);
            }

            return $typed;
        }

        if ($settings['mode'] === 'manual') {
            if ($settings['required']) {
                throw ValidationException::withMessages([
                    $field => "Enter the {$settings['label']} assigned by the organization (numbering is set to manual).",
                ]);
            }

            return null;
        }

        return $this->generate($type, $exists, $date, $settings);
    }

    /**
     * Generate the next number for an automatic type. Runs in (or opens) a transaction; the
     * sequence row stays locked until the caller's transaction commits.
     */
    public function generate(string $type, Closure $exists, ?Carbon $date = null, ?array $settings = null): string
    {
        $settings ??= $this->settings($type);
        $date ??= now();
        $period = $this->periodKey($settings['reset_rule'], $date);

        return DB::transaction(function () use ($type, $exists, $date, $settings, $period) {
            for ($attempt = 0; $attempt < 10000; $attempt++) {
                // The upsert takes an exclusive lock on the counter row that is held until the
                // caller's transaction ends, so a simultaneous request waits here and then gets the
                // next value. (LAST_INSERT_ID() is not used: on a new row it returns the table's
                // auto-increment id instead of the counter.)
                DB::statement(
                    'INSERT INTO document_number_sequences (document_type, period_key, last_value, created_at, updated_at) '
                    .'VALUES (?, ?, 1, NOW(), NOW()) '
                    .'ON DUPLICATE KEY UPDATE last_value = last_value + 1, updated_at = NOW()',
                    [$type, $period]
                );
                $sequence = (int) DB::table('document_number_sequences')
                    ->where('document_type', $type)
                    ->where('period_key', $period)
                    ->lockForUpdate()
                    ->value('last_value');
                $number = $this->format($settings['format'], (int) $settings['padding'], $date, $sequence);

                if (! $exists($number)) {
                    return $number;
                }
            }

            throw new RuntimeException("Unable to generate a free {$settings['label']}. Check the numbering format.");
        });
    }

    public function format(string $format, int $padding, Carbon $date, int $sequence): string
    {
        return strtr($format, [
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
            '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    public function periodKey(string $reset, Carbon $date): string
    {
        return match ($reset) {
            'yearly' => $date->format('Y'),
            'monthly' => $date->format('Y-m'),
            default => 'all',
        };
    }

    /**
     * Official numbers are text: trim accidental surrounding whitespace, keep everything else
     * (leading zeros, letters, separators).
     */
    public function clean(?string $value): ?string
    {
        $value = $value === null ? '' : trim(preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $value) ?? '');

        return $value === '' ? null : $value;
    }

    private function assertType(string $type): void
    {
        if (! array_key_exists($type, self::TYPES)) {
            throw new RuntimeException("Unknown document type {$type}.");
        }
    }
}

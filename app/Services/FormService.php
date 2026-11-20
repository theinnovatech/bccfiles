<?php

namespace App\Services;

use App\Enums\ReturnCondition;
use App\Models\EquipmentCase;
use App\Models\Issuance;
use App\Models\OfficeFormDocument;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\EquipmentStatus;
use App\Support\NumberScopes;
use App\Support\Xlsx\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Office forms from SDO_Reports_Reference_Pack (revision 2): Local RIV, Inventory Custodian Slip
 * and IIRUP (semi-expendable), built on the supplied layouts and aligned with the SDO Inventory
 * desktop app.
 *
 * Each form has its own data builder; the PDF (resources/views/forms) and Excel renderers only
 * draw these arrays. Money is kept in centavos while computing. Unknown values stay null and
 * print blank; zero prints as 0.00. Nothing is derived from cost or useful life. Generating a form
 * never changes stock, custody or equipment status.
 *
 * The Local RIV and ICS are numbered documents: issuing one stores a locked copy
 * (office_form_documents) that every reprint uses. Before that, previews are marked DRAFT.
 * The IIRUP is generated from the current disposal cases as of a date.
 */
class FormService
{
    public const TEMPLATE_REVISION = 2;

    public function __construct(private readonly DocumentNumberService $numbers) {}

    public const RIV_HEADER = [
        'LUNGSOD NG IRIGA',
        'Rehiyon V (Bikol)',
        'TANGGAPANG PANSANGAY NG MGA PAARALAN',
        'Lungsod ng Iriga',
    ];

    public const ICS_HEADER = [
        'KAGAWARAN NG EDUKASYON',
        'REHIYON V (BIKOL)',
        'TANGGAPANG PANSANGAY NG MGA PAARALAN',
        'LUNGSOD NG IRIGA',
    ];

    public const IIRUP_TITLE = 'INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE SEMI-EXPENDABLE PROPERTY';

    // Certification wording as printed on the supplied IIRUP.
    public const IIRUP_REQUEST_TEXT = 'I HEREBY request inspection and disposition, pursuant to Section 79 of P.D. No. 1445, of the property enumerated above.';
    public const IIRUP_INSPECTED_TEXT = 'I CERTIFY that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgement, the best for the public interest.';
    public const IIRUP_WITNESS_TEXT = 'I CERTIFY that I have witnessed the disposition of the Articles enumerated on this report this ____ day of _____________, _____.';

    // ------------------------------------------------------------------ issued copies

    public function issuedDocument(string $form, Issuance $issuance): ?OfficeFormDocument
    {
        return OfficeFormDocument::query()
            ->where('form_type', $form)
            ->where('issuance_id', $issuance->id)
            ->first();
    }

    /**
     * The form to print: the issued copy when there is one, otherwise a draft from current records.
     */
    public function forIssuance(string $form, Issuance $issuance, ?User $user = null): array
    {
        $document = $this->issuedDocument($form, $issuance);
        if ($document) {
            return $document->payload + ['issued_document_id' => $document->id];
        }

        return match ($form) {
            OfficeFormDocument::RIV => $this->riv($issuance, [], $user),
            OfficeFormDocument::ICS => $this->ics($issuance, [], $user),
            default => throw new InvalidArgumentException('Unknown form.'),
        };
    }

    /**
     * Issue a Local RIV or ICS: assign its number and save the locked copy (once per issuance).
     */
    public function issue(string $form, Issuance $issuance, User $user, array $overrides = []): OfficeFormDocument
    {
        if ($existing = $this->issuedDocument($form, $issuance)) {
            return $existing;
        }

        return DB::transaction(function () use ($form, $issuance, $user, $overrides) {
            $issuedAt = now();
            $number = $this->numbers->resolve(
                $form.'_number',
                $overrides['number'] ?? null,
                NumberScopes::formDocument($form),
                $issuedAt,
                'number'
            );

            $data = match ($form) {
                OfficeFormDocument::RIV => $this->riv($issuance, $overrides, $user),
                OfficeFormDocument::ICS => $this->ics($issuance, $overrides, $user),
                default => throw new InvalidArgumentException('Unknown form.'),
            };

            $data['number'] = $number;
            $data['meta'] = [
                'kind' => 'issued',
                'template_revision' => self::TEMPLATE_REVISION,
                'issued_at' => $issuedAt->toIso8601String(),
                'issued_by' => $user->name,
                'generated_at' => $issuedAt->toIso8601String(),
                'generated_by' => $user->name,
            ];

            return OfficeFormDocument::create([
                'form_type' => $form,
                'document_number' => $number,
                'issuance_id' => $issuance->id,
                'template_revision' => self::TEMPLATE_REVISION,
                'payload' => $data,
                'issued_by' => $user->id,
                'issued_at' => $issuedAt,
            ]);
        });
    }

    /**
     * Issue an IIRUP as of a date: assign its number (typed or from the configured format) and
     * keep a locked copy of the report as it stood.
     */
    public function issueIirup(?string $asOf, User $user, ?string $typedNumber = null): OfficeFormDocument
    {
        return DB::transaction(function () use ($asOf, $user, $typedNumber) {
            $issuedAt = now();
            $data = $this->iirup($asOf, $user);
            $data['number'] = $this->numbers->resolve('iirup_number', $typedNumber, NumberScopes::formDocument('iirup'), $issuedAt, 'number');
            $data['meta'] = [
                'kind' => 'issued',
                'template_revision' => self::TEMPLATE_REVISION,
                'issued_at' => $issuedAt->toIso8601String(),
                'issued_by' => $user->name,
                'generated_at' => $issuedAt->toIso8601String(),
                'generated_by' => $user->name,
            ];

            return OfficeFormDocument::create([
                'form_type' => 'iirup',
                'document_number' => $data['number'],
                'issuance_id' => null,
                'as_of_date' => $data['as_of'],
                'template_revision' => self::TEMPLATE_REVISION,
                'payload' => $data,
                'issued_by' => $user->id,
                'issued_at' => $issuedAt,
            ]);
        });
    }

    // ------------------------------------------------------------------ Local RIV

    public function riv(Issuance $issuance, array $overrides = [], ?User $user = null): array
    {
        $issuance->loadMissing(['details.item.unit', 'issuer', 'receiver.department', 'department']);
        $lines = $issuance->details->filter(fn ($detail) => $detail->item_id)->values();

        if ($lines->isEmpty()) {
            throw new InvalidArgumentException('The Local RIV lists supplies. This issuance has no supply lines.');
        }

        return [
            'form' => OfficeFormDocument::RIV,
            'title' => 'LOCAL RIV',
            'number' => null,
            'date' => $issuance->issued_date?->toDateString(),
            'issuance_number' => $issuance->issuance_number,
            'lines' => $lines->map(function ($detail) {
                $item = $detail->item;
                $unitCost = $this->centavos($item?->unit_cost);

                return [
                    'quantity' => (int) $detail->quantity,
                    'unit' => $item?->unit?->abbreviation ?: $item?->unit?->name,
                    'description' => trim(implode(' — ', array_filter([
                        $item?->item_name,
                        $item?->brand,
                        $item?->description,
                    ]))),
                    'unit_cost' => $this->money($unitCost),
                    'total_cost' => $unitCost === null ? null : $this->money($unitCost * (int) $detail->quantity),
                ];
            })->all(),
            'approved_by' => $this->signatory('form_approved_by'),
            'requested_by' => [
                'name' => $issuance->recipientDisplayName(),
                'designation' => $issuance->recipient_designation ?: $issuance->departmentDisplayName(),
            ],
            'received_by' => ['name' => null, 'designation' => null],
            'meta' => $this->draftMeta($user),
        ];
    }

    // ------------------------------------------------------------------ ICS

    public function ics(Issuance $issuance, array $overrides = [], ?User $user = null): array
    {
        $issuance->loadMissing(['details.equipment', 'issuer', 'receiver.department', 'department']);
        $equipmentDetails = $issuance->details->filter(fn ($detail) => $detail->equipment_id)->values();

        if ($equipmentDetails->isEmpty()) {
            throw new InvalidArgumentException('The Inventory Custodian Slip lists equipment. This issuance has no equipment lines.');
        }

        $eligible = $equipmentDetails->filter(fn ($detail) => $detail->equipment?->isSemiExpendable());
        $excluded = $equipmentDetails->reject(fn ($detail) => $detail->equipment?->isSemiExpendable())
            ->map(fn ($detail) => ($detail->equipment?->name ?? 'Equipment').' ('.$detail->equipment?->property_class_label.')')
            ->unique()
            ->values()
            ->all();

        if ($eligible->isEmpty()) {
            throw new InvalidArgumentException(
                'The ICS lists semi-expendable property only. Set the property class of '.implode(', ', $excluded)
                .' to "Semi-expendable property" (Supply Master > Equipment > Edit) before printing the ICS.'
            );
        }

        $groups = $eligible->groupBy(function ($detail) {
            $equipment = $detail->equipment;

            return implode('|', [$equipment->id, (string) $equipment->unit_cost, (string) $equipment->life_span_years]);
        });

        $lineOverrides = collect($overrides['lines'] ?? [])->keyBy('key');

        $lines = $groups->map(function (Collection $details, string $key) use ($lineOverrides) {
            $equipment = $details->first()->equipment;
            $quantity = (int) $details->sum('quantity');
            $unitCost = $this->centavos($equipment->unit_cost);
            $override = $lineOverrides->get($key, []);
            $years = $equipment->life_span_years;
            $defaultLife = $years === null ? null : $years.' '.((int) $years === 1 ? 'year' : 'years');

            return [
                'key' => $key,
                'quantity' => $quantity,
                'unit' => filled($override['unit'] ?? null) ? trim($override['unit']) : null,
                'unit_cost' => $this->money($unitCost),
                'total_cost' => $unitCost === null ? null : $this->money($unitCost * $quantity),
                'description' => $equipment->documentDescription(),
                // The per-unit accountability number typed from the paper form at issuance.
                'item_numbers' => $details
                    ->map(fn ($detail) => $detail->property_number ?: $detail->inventory_number)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'useful_life' => array_key_exists('useful_life', $override)
                    ? (filled($override['useful_life']) ? trim($override['useful_life']) : null)
                    : $defaultLife,
            ];
        })->values()->all();

        return [
            'form' => OfficeFormDocument::ICS,
            'title' => 'INVENTORY CUSTODIAN SLIP',
            'number' => null,
            'date' => $issuance->issued_date?->toDateString(),
            'issuance_number' => $issuance->issuance_number,
            'entity_name' => array_key_exists('entity_name', $overrides) ? $overrides['entity_name'] : $this->entityName(),
            'fund_cluster' => array_key_exists('fund_cluster', $overrides) ? $overrides['fund_cluster'] : $this->setting('form_fund_cluster'),
            'lines' => $lines,
            'details' => [],
            'excluded' => $excluded,
            'received_from' => [
                'name' => $issuance->issuer?->name ?? $this->setting('form_issued_by_name'),
                'designation' => $this->setting('form_issued_by_designation'),
            ],
            'received_by' => [
                'name' => $issuance->recipientDisplayName(),
                'designation' => $issuance->recipient_designation ?: $issuance->departmentDisplayName(),
            ],
            'meta' => $this->draftMeta($user),
        ];
    }

    // ------------------------------------------------------------------ IIRUP (semi-expendable)

    public function iirup(?string $asOf = null, ?User $user = null): array
    {
        $asOfDate = $asOf ? Carbon::parse($asOf)->endOfDay() : now()->endOfDay();

        $cases = EquipmentCase::query()
            ->with(['equipment.category', 'serialNumber', 'itemReturn.issuanceDetail'])
            ->whereIn('status', [EquipmentStatus::FOR_DISPOSAL, EquipmentStatus::DISPOSED])
            ->whereDate('reported_on', '<=', $asOfDate->toDateString())
            ->orderBy('reported_on')
            ->orderBy('id')
            ->get();

        $eligible = $cases->filter(fn (EquipmentCase $case) => $case->equipment?->isSemiExpendable());

        $totalCost = 0;
        $hasTotal = false;
        $lines = $eligible->map(function (EquipmentCase $case) use (&$totalCost, &$hasTotal) {
            $equipment = $case->equipment;
            $detail = $case->itemReturn?->issuanceDetail;
            $quantity = (int) $case->quantity;
            $unitCost = $this->centavos($case->unit_cost ?? $equipment?->unit_cost);
            $lineTotal = $unitCost === null ? null : $unitCost * $quantity;
            if ($lineTotal !== null) {
                $totalCost += $lineTotal;
                $hasTotal = true;
            }

            $modes = [$case->disposal_sale_qty, $case->disposal_transfer_qty, $case->disposal_destruction_qty, $case->disposal_others_qty];
            $allocated = array_filter($modes, fn ($v) => $v !== null);

            return [
                'date' => ($detail?->date_acquired ?? $equipment?->date_acquired)?->toDateString(),
                'particulars' => trim(implode(' — ', array_filter([
                    $equipment?->documentDescription(),
                    $case->serialNumber ? 'SN '.$case->serialNumber->serial_number : null,
                ]))),
                'property_number' => $case->serialNumber?->property_number ?: ($detail?->property_number ?? $equipment?->property_number),
                'quantity' => $quantity,
                'unit_cost' => $this->money($unitCost),
                'total_cost' => $this->money($lineTotal),
                'impairment' => $this->nullableMoney($case->accumulated_impairment),
                'carrying_amount' => $this->nullableMoney($case->carrying_amount),
                'remarks' => $case->disposal_assessment ?: $case->reported_issue,
                'sale' => $case->disposal_sale_qty,
                'transfer' => $case->disposal_transfer_qty,
                'destruction' => $case->disposal_destruction_qty,
                'others' => $case->disposal_others_qty,
                'others_specify' => $case->disposal_others_specify,
                'disposal_total' => $allocated === [] ? null : array_sum($allocated),
                'appraised_value' => $this->nullableMoney($case->appraised_value),
                'or_number' => $case->or_number,
                'sale_amount' => $this->nullableMoney($case->sale_amount),
                'case_number' => $case->case_number,
            ];
        })->values()->all();

        $excluded = $cases->count() - $eligible->count();

        return [
            'form' => 'iirup',
            'number' => null,
            'title' => self::IIRUP_TITLE,
            'as_of' => $asOfDate->toDateString(),
            'entity_name' => $this->entityName(),
            'fund_cluster' => $this->setting('form_fund_cluster'),
            'station' => $this->setting('form_station'),
            'accountable_officer' => $this->signatory('form_issued_by'),
            'approved_by' => $this->signatory('form_approved_by'),
            'inspection_officer' => $this->signatory('form_inspection_officer'),
            'witness' => $this->signatory('form_witness'),
            'lines' => $lines,
            'total_cost' => $hasTotal ? $this->money($totalCost) : null,
            'excluded_count' => $excluded,
            'meta' => ['kind' => 'live'] + $this->draftMeta($user),
        ];
    }

    // ------------------------------------------------------------------ Excel copies

    public function toXlsx(array $form): XlsxWriter
    {
        return match ($form['form']) {
            'riv' => $this->rivXlsx($form),
            'ics' => $this->icsXlsx($form),
            'iirup' => $this->iirupXlsx($form),
            default => throw new InvalidArgumentException('Unknown form.'),
        };
    }

    private function rivXlsx(array $f): XlsxWriter
    {
        $x = new XlsxWriter();
        $s = $x->addSheet('Local RIV');
        $x->setColumnWidths($s, [8, 10, 52, 14, 14]);

        foreach (self::RIV_HEADER as $i => $line) {
            $r = $x->addRow($s, [XlsxWriter::styled($line, $i % 2 === 0 ? XlsxWriter::FORM_CENTER_BOLD : XlsxWriter::FORM_CENTER)]);
            $x->merge($s, "A{$r}:E{$r}");
        }
        $x->addRow($s, []);
        $x->addRow($s, ['', '', '', XlsxWriter::styled('No:', XlsxWriter::FORM_LABEL), XlsxWriter::styled($f['number'] ?? '', XlsxWriter::FORM_UNDERLINE)]);
        $x->addRow($s, ['', '', '', XlsxWriter::styled('Date:', XlsxWriter::FORM_LABEL), XlsxWriter::styled($this->longDate($f['date']), XlsxWriter::FORM_UNDERLINE)]);
        $r = $x->addRow($s, [XlsxWriter::styled('LOCAL RIV', XlsxWriter::FORM_TITLE)]);
        $x->merge($s, "A{$r}:E{$r}");
        $x->addRow($s, [XlsxWriter::styled('To Supply Office:', XlsxWriter::FORM_TEXT)]);
        $x->addRow($s, ['', XlsxWriter::styled('Pls. furnish the following:', XlsxWriter::FORM_TEXT)]);
        $x->addRow($s, array_map(fn ($h) => XlsxWriter::styled($h, XlsxWriter::FORM_HEAD), ['QTY', 'UNIT', 'ITEM/DESCRIPTION', 'UNIT COST', 'TOTAL COST']));

        foreach ($f['lines'] as $line) {
            $x->addRow($s, [
                XlsxWriter::styled($line['quantity'], XlsxWriter::FORM_QTY),
                XlsxWriter::styled($line['unit'], XlsxWriter::FORM_CELL_CENTER),
                XlsxWriter::styled($line['description'], XlsxWriter::FORM_CELL),
                XlsxWriter::styled($line['unit_cost'], XlsxWriter::FORM_MONEY),
                XlsxWriter::styled($line['total_cost'], XlsxWriter::FORM_MONEY),
            ]);
        }
        for ($i = count($f['lines']); $i < 15; $i++) {
            $x->addRow($s, array_fill(0, 5, XlsxWriter::styled(null, XlsxWriter::FORM_CELL)));
        }

        $x->addRow($s, []);
        $x->addRow($s, [XlsxWriter::styled('APPROVED;', XlsxWriter::FORM_LABEL), '', '', XlsxWriter::styled('REQUESTED BY:', XlsxWriter::FORM_LABEL)]);
        $r = $x->addRow($s, [XlsxWriter::styled(strtoupper((string) ($f['approved_by']['name'] ?? '')), XlsxWriter::FORM_UNDERLINE_LEFT), '', '', XlsxWriter::styled($f['requested_by']['name'] ?? '', XlsxWriter::FORM_UNDERLINE), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE)]);
        $x->merge($s, "A{$r}:C{$r}");
        $x->merge($s, "D{$r}:E{$r}");
        $r = $x->addRow($s, [XlsxWriter::styled($f['approved_by']['designation'] ?? '', XlsxWriter::FORM_TEXT), '', '', XlsxWriter::styled($f['requested_by']['designation'] ?? '', XlsxWriter::FORM_CENTER)]);
        $x->merge($s, "D{$r}:E{$r}");
        $x->addRow($s, ['', '', '', XlsxWriter::styled('RECEIVED BY:', XlsxWriter::FORM_LABEL)]);
        $r = $x->addRow($s, ['', '', '', XlsxWriter::styled($f['received_by']['name'] ?? '', XlsxWriter::FORM_UNDERLINE), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE)]);
        $x->merge($s, "D{$r}:E{$r}");

        $this->metaRow($x, $s, $f, 5);

        return $x;
    }

    private function icsXlsx(array $f): XlsxWriter
    {
        $x = new XlsxWriter();
        $s = $x->addSheet('ICS');
        $x->setColumnWidths($s, [7, 9, 13, 13, 40, 18, 14]);

        $r = $x->addRow($s, [XlsxWriter::styled('INVENTORY CUSTODIAN SLIP', XlsxWriter::FORM_TITLE)]);
        $x->merge($s, "A{$r}:G{$r}");
        foreach (self::ICS_HEADER as $line) {
            $r = $x->addRow($s, [XlsxWriter::styled($line, XlsxWriter::FORM_CENTER_BOLD)]);
            $x->merge($s, "A{$r}:G{$r}");
        }
        $x->addRow($s, []);
        $r = $x->addRow($s, [XlsxWriter::styled('ENTITY NAME:', XlsxWriter::FORM_TEXT), '', XlsxWriter::styled($f['entity_name'] ?? '', XlsxWriter::FORM_UNDERLINE_LEFT), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_LEFT), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_LEFT), XlsxWriter::styled('ICS NO.', XlsxWriter::FORM_TEXT), XlsxWriter::styled($f['number'] ?? '', XlsxWriter::FORM_UNDERLINE_BOLD)]);
        $x->merge($s, "A{$r}:B{$r}");
        $x->merge($s, "C{$r}:E{$r}");
        $r = $x->addRow($s, [XlsxWriter::styled('FUND CLUSTER:', XlsxWriter::FORM_TEXT), '', XlsxWriter::styled($f['fund_cluster'] ?? '', XlsxWriter::FORM_UNDERLINE_LEFT), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_LEFT), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_LEFT)]);
        $x->merge($s, "A{$r}:B{$r}");
        $x->merge($s, "C{$r}:E{$r}");

        $h = fn ($t) => XlsxWriter::styled($t, XlsxWriter::FORM_HEAD);
        $r1 = $x->addRow($s, [$h('QTY'), $h('UNIT'), $h('AMOUNT'), $h(null), $h('DESCRIPTION'), $h('Inventory Item No.'), $h('Estimated Useful Life')]);
        $r2 = $x->addRow($s, [$h(null), $h(null), $h('UNIT COST'), $h('TOTAL COST'), $h(null), $h(null), $h(null)]);
        foreach (['A', 'B', 'E', 'F', 'G'] as $col) {
            $x->merge($s, "{$col}{$r1}:{$col}{$r2}");
        }
        $x->merge($s, "C{$r1}:D{$r1}");

        foreach ($f['lines'] as $line) {
            $x->addRow($s, [
                XlsxWriter::styled($line['quantity'], XlsxWriter::FORM_QTY),
                XlsxWriter::styled($line['unit'], XlsxWriter::FORM_CELL_CENTER),
                XlsxWriter::styled($line['unit_cost'], XlsxWriter::FORM_MONEY),
                XlsxWriter::styled($line['total_cost'], XlsxWriter::FORM_MONEY),
                XlsxWriter::styled($line['description'], XlsxWriter::FORM_CELL),
                XlsxWriter::styled(implode("\n", $line['item_numbers']), XlsxWriter::FORM_CELL_CENTER),
                XlsxWriter::styled($line['useful_life'], XlsxWriter::FORM_CELL_CENTER),
            ]);
        }
        foreach ($f['details'] ?? [] as $detail) {
            $x->addRow($s, [XlsxWriter::styled(null, XlsxWriter::FORM_CELL), XlsxWriter::styled(null, XlsxWriter::FORM_CELL), XlsxWriter::styled(null, XlsxWriter::FORM_CELL), XlsxWriter::styled(null, XlsxWriter::FORM_CELL), XlsxWriter::styled($detail, XlsxWriter::FORM_CELL_CENTER), XlsxWriter::styled(null, XlsxWriter::FORM_CELL), XlsxWriter::styled(null, XlsxWriter::FORM_CELL)]);
        }
        for ($i = count($f['lines']); $i < 12; $i++) {
            $x->addRow($s, array_fill(0, 7, XlsxWriter::styled(null, XlsxWriter::FORM_CELL)));
        }

        $r = $x->addRow($s, [XlsxWriter::styled('RECEIVED FROM:', XlsxWriter::FORM_CELL_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_CELL), XlsxWriter::styled(null, XlsxWriter::FORM_CELL), XlsxWriter::styled(null, XlsxWriter::FORM_CELL), XlsxWriter::styled('RECEIVED BY:', XlsxWriter::FORM_CELL_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_CELL), XlsxWriter::styled(null, XlsxWriter::FORM_CELL)]);
        $x->merge($s, "A{$r}:D{$r}");
        $x->merge($s, "E{$r}:G{$r}");
        $r = $x->addRow($s, [XlsxWriter::styled($f['received_from']['name'] ?? '', XlsxWriter::FORM_SIGN_NAME), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_NAME), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_NAME), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_NAME), XlsxWriter::styled($f['received_by']['name'] ?? '', XlsxWriter::FORM_SIGN_NAME), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_NAME), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_NAME)]);
        $x->setRowHeight($s, $r, 36);
        $x->merge($s, "A{$r}:D{$r}");
        $x->merge($s, "E{$r}:G{$r}");
        $r = $x->addRow($s, [XlsxWriter::styled($f['received_from']['designation'] ?? '', XlsxWriter::FORM_SIGN_DESIG), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_DESIG), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_DESIG), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_DESIG), XlsxWriter::styled($f['received_by']['designation'] ?? '', XlsxWriter::FORM_SIGN_DESIG), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_DESIG), XlsxWriter::styled(null, XlsxWriter::FORM_SIGN_DESIG)]);
        $x->merge($s, "A{$r}:D{$r}");
        $x->merge($s, "E{$r}:G{$r}");

        $this->metaRow($x, $s, $f, 7);

        return $x;
    }

    private function iirupXlsx(array $f): XlsxWriter
    {
        $x = new XlsxWriter();
        $s = $x->addSheet('IIRUP');
        $x->setColumnWidths($s, [11, 24, 18, 7, 12, 12, 14, 12, 20, 7, 9, 11, 13, 7, 12, 11, 12]);
        $x->setLandscape($s);

        $r = $x->addRow($s, [XlsxWriter::styled(self::IIRUP_TITLE, XlsxWriter::FORM_TITLE_SMALL)]);
        $x->merge($s, "A{$r}:Q{$r}");
        $r = $x->addRow($s, [XlsxWriter::styled('As Of '.$this->longDate($f['as_of']), XlsxWriter::FORM_CENTER)]);
        $x->merge($s, "A{$r}:Q{$r}");
        if (filled($f['number'] ?? null)) {
            $r = $x->addRow($s, [XlsxWriter::styled('IIRUP No. '.$f['number'], XlsxWriter::FORM_CENTER_BOLD)]);
            $x->merge($s, "A{$r}:Q{$r}");
        }
        $x->addRow($s, []);
        $r = $x->addRow($s, [
            XlsxWriter::styled('Entity Name:', XlsxWriter::FORM_LABEL), XlsxWriter::styled($f['entity_name'] ?? '', XlsxWriter::FORM_TEXT), '',
            XlsxWriter::styled($f['accountable_officer']['name'] ?? '', XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), '',
            XlsxWriter::styled($f['accountable_officer']['designation'] ?? '', XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), '',
            XlsxWriter::styled($f['station'] ?? '', XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), '', '',
            XlsxWriter::styled('Fund Cluster:', XlsxWriter::FORM_LABEL), XlsxWriter::styled($f['fund_cluster'] ?? '', XlsxWriter::FORM_TEXT),
        ]);
        $x->merge($s, "D{$r}:F{$r}");
        $x->merge($s, "H{$r}:I{$r}");
        $x->merge($s, "K{$r}:L{$r}");
        $r = $x->addRow($s, ['', '', '', XlsxWriter::styled('(Name of Accountable Officer)', XlsxWriter::FORM_CAPTION), '', '', '', XlsxWriter::styled('(Designation)', XlsxWriter::FORM_CAPTION), '', '', XlsxWriter::styled('(Station)', XlsxWriter::FORM_CAPTION)]);
        $x->merge($s, "D{$r}:F{$r}");
        $x->merge($s, "H{$r}:I{$r}");
        $x->merge($s, "K{$r}:L{$r}");

        $h = fn ($t) => XlsxWriter::styled($t, XlsxWriter::FORM_HEAD);
        $r1 = $x->addRow($s, array_merge([$h('INVENTORY')], array_fill(0, 8, $h(null)), [$h('INSPECTION and DISPOSAL')], array_fill(0, 7, $h(null))));
        $r2 = $x->addRow($s, [
            $h('Date'), $h('Particulars/ Articles'), $h('Semi-expendable Property No.'), $h('QTY'), $h('Unit Cost'), $h('Total Cost'),
            $h('Accumulated Impairment Losses'), $h('Carrying Amount'), $h('Remarks'),
            $h('DISPOSAL'), $h(null), $h(null), $h(null), $h(null), $h('Appraised Value'), $h('RECORD OF SALE'), $h(null),
        ]);
        $r3 = $x->addRow($s, array_merge(array_fill(0, 9, $h(null)), [$h('Sale'), $h('Transfer'), $h('Destruction'), $h('Others (Specify)'), $h('Total'), $h(null), $h('OR No.'), $h('Amount')]));
        $x->setRowHeight($s, $r2, 30);
        $x->merge($s, "A{$r1}:I{$r1}");
        $x->merge($s, "J{$r1}:Q{$r1}");
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'O'] as $col) {
            $x->merge($s, "{$col}{$r2}:{$col}{$r3}");
        }
        $x->merge($s, "J{$r2}:N{$r2}");
        $x->merge($s, "P{$r2}:Q{$r2}");

        foreach ($f['lines'] as $line) {
            $others = $line['others'] === null
                ? ($line['others_specify'] ?? null)
                : trim($line['others'].($line['others_specify'] ? "\n".$line['others_specify'] : ''));
            $x->addRow($s, [
                XlsxWriter::styled(XlsxWriter::date($line['date']), XlsxWriter::FORM_DATE),
                XlsxWriter::styled($line['particulars'], XlsxWriter::FORM_CELL),
                XlsxWriter::styled(XlsxWriter::text($line['property_number']), XlsxWriter::FORM_CELL_CENTER),
                XlsxWriter::styled($line['quantity'], XlsxWriter::FORM_QTY),
                XlsxWriter::styled($line['unit_cost'], XlsxWriter::FORM_MONEY),
                XlsxWriter::styled($line['total_cost'], XlsxWriter::FORM_MONEY),
                XlsxWriter::styled($line['impairment'], XlsxWriter::FORM_MONEY),
                XlsxWriter::styled($line['carrying_amount'], XlsxWriter::FORM_MONEY),
                XlsxWriter::styled($line['remarks'], XlsxWriter::FORM_CELL),
                XlsxWriter::styled($line['sale'], XlsxWriter::FORM_QTY),
                XlsxWriter::styled($line['transfer'], XlsxWriter::FORM_QTY),
                XlsxWriter::styled($line['destruction'], XlsxWriter::FORM_QTY),
                XlsxWriter::styled($others, XlsxWriter::FORM_CELL_CENTER),
                XlsxWriter::styled($line['disposal_total'], XlsxWriter::FORM_QTY),
                XlsxWriter::styled($line['appraised_value'], XlsxWriter::FORM_MONEY),
                XlsxWriter::styled(XlsxWriter::text($line['or_number']), XlsxWriter::FORM_CELL_CENTER),
                XlsxWriter::styled($line['sale_amount'], XlsxWriter::FORM_MONEY),
            ]);
        }
        for ($i = count($f['lines']); $i < 4; $i++) {
            $x->addRow($s, array_fill(0, 17, XlsxWriter::styled(null, XlsxWriter::FORM_CELL)));
        }
        $total = array_fill(0, 17, XlsxWriter::styled(null, XlsxWriter::FORM_CELL));
        $total[0] = XlsxWriter::styled('Total', XlsxWriter::FORM_CELL_BOLD);
        $total[5] = XlsxWriter::styled($f['total_cost'], XlsxWriter::FORM_MONEY_BOLD);
        $x->addRow($s, $total);

        $x->addRow($s, []);
        $r = $x->addRow($s, [XlsxWriter::styled(self::IIRUP_REQUEST_TEXT, XlsxWriter::FORM_TEXT), '', '', '', '', '', '', '', '', XlsxWriter::styled(self::IIRUP_INSPECTED_TEXT, XlsxWriter::FORM_CENTER_WRAP), '', '', '', '', XlsxWriter::styled(self::IIRUP_WITNESS_TEXT, XlsxWriter::FORM_CENTER_WRAP)]);
        $x->merge($s, "A{$r}:I{$r}");
        $x->merge($s, "J{$r}:N{$r}");
        $x->merge($s, "O{$r}:Q{$r}");
        $x->setRowHeight($s, $r, 42);
        $x->addRow($s, [XlsxWriter::styled('Requested by:', XlsxWriter::FORM_LABEL), '', '', '', '', XlsxWriter::styled('Approved by:', XlsxWriter::FORM_LABEL)]);
        $r = $x->addRow($s, [
            '', XlsxWriter::styled($f['accountable_officer']['name'] ?? '', XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), '',
            '', XlsxWriter::styled($f['approved_by']['name'] ?? '', XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD),
            XlsxWriter::styled($f['inspection_officer']['name'] ?? '', XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD),
            XlsxWriter::styled($f['witness']['name'] ?? '', XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE_BOLD),
        ]);
        $x->setRowHeight($s, $r, 28);
        $x->merge($s, "B{$r}:D{$r}");
        $x->merge($s, "G{$r}:I{$r}");
        $x->merge($s, "J{$r}:N{$r}");
        $x->merge($s, "O{$r}:Q{$r}");
        $r = $x->addRow($s, ['', XlsxWriter::styled('(Signature over Printed Name of Accountable Officer)', XlsxWriter::FORM_CAPTION), '', '', '', '', '', '', '', XlsxWriter::styled('(Signature Over Printed Name of Inspection Officer)', XlsxWriter::FORM_CAPTION), '', '', '', '', XlsxWriter::styled('(Signature over Printed Name of Witness)', XlsxWriter::FORM_CAPTION)]);
        $x->merge($s, "B{$r}:D{$r}");
        $x->merge($s, "J{$r}:N{$r}");
        $x->merge($s, "O{$r}:Q{$r}");
        $r = $x->addRow($s, [
            '', XlsxWriter::styled($f['accountable_officer']['designation'] ?? '', XlsxWriter::FORM_UNDERLINE), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE), '',
            '', XlsxWriter::styled($f['approved_by']['designation'] ?? '', XlsxWriter::FORM_UNDERLINE), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE), XlsxWriter::styled(null, XlsxWriter::FORM_UNDERLINE),
            '', '', '', '', '', XlsxWriter::styled($f['witness']['designation'] ?? '', XlsxWriter::FORM_CENTER_BOLD),
        ]);
        $x->merge($s, "B{$r}:D{$r}");
        $x->merge($s, "G{$r}:I{$r}");
        $x->merge($s, "O{$r}:Q{$r}");
        $r = $x->addRow($s, ['', XlsxWriter::styled('(Designation of Accountable Officer)', XlsxWriter::FORM_CAPTION)]);
        $x->merge($s, "B{$r}:D{$r}");

        $this->metaRow($x, $s, $f, 17);

        return $x;
    }

    private function metaRow(XlsxWriter $x, int $sheet, array $form, int $columns): void
    {
        $x->addRow($sheet, []);
        $r = $x->addRow($sheet, [XlsxWriter::styled($this->metaLine($form), XlsxWriter::FORM_META)]);
        $x->merge($sheet, 'A'.$r.':'.XlsxWriter::column($columns - 1).$r);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Small grey footer line describing what the copy is.
     */
    public function metaLine(array $form): string
    {
        $meta = $form['meta'] ?? [];
        $kind = $meta['kind'] ?? 'draft';
        $generated = isset($meta['generated_at']) ? Carbon::parse($meta['generated_at'])->format('Y-m-d H:i') : now()->format('Y-m-d H:i');

        return match ($kind) {
            'issued' => sprintf(
                'Issued copy %s · issued %s by %s · template rev. %d · reprints use this saved copy',
                $form['number'] ?? '',
                isset($meta['issued_at']) ? Carbon::parse($meta['issued_at'])->format('Y-m-d H:i') : '',
                $meta['issued_by'] ?? '—',
                $meta['template_revision'] ?? self::TEMPLATE_REVISION
            ),
            'live' => sprintf('Generated from current records on %s by %s · OBIMS', $generated, $meta['generated_by'] ?? '—'),
            default => sprintf('DRAFT — not yet issued · generated %s by %s · issue it to assign the number and lock this copy', $generated, $meta['generated_by'] ?? '—'),
        };
    }

    public function longDate(?string $date): string
    {
        return $date ? Carbon::parse($date)->format('F j, Y') : '';
    }

    private function draftMeta(?User $user): array
    {
        return [
            'kind' => 'draft',
            'template_revision' => self::TEMPLATE_REVISION,
            'generated_at' => now()->toIso8601String(),
            'generated_by' => $user?->name,
        ];
    }

    private function centavos(mixed $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return (int) round(((float) $amount) * 100);
    }

    private function money(?int $centavos): ?float
    {
        return $centavos === null ? null : round($centavos / 100, 2);
    }

    private function nullableMoney(mixed $amount): ?float
    {
        return $this->money($this->centavos($amount));
    }

    private function entityName(): ?string
    {
        return $this->setting('form_entity_name')
            ?? Setting::getValue('organization_name', AppServiceProvider::DEFAULT_ORGANIZATION_NAME);
    }

    /**
     * @return array{name: ?string, designation: ?string}
     */
    private function signatory(string $prefix): array
    {
        return [
            'name' => $this->setting($prefix.'_name'),
            'designation' => $this->setting($prefix.'_designation'),
        ];
    }

    public function setting(string $key): ?string
    {
        $value = trim((string) Setting::getValue($key, ''));

        return $value === '' ? null : $value;
    }
}

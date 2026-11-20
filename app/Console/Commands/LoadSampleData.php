<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\EquipmentCase;
use App\Models\EquipmentSerialNumber;
use App\Models\Issuance;
use App\Models\IssuanceDetail;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Models\User;
use App\Support\SampleDataClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Loads realistic sample data for checking the feedback scenarios (feed-back.md).
 *
 * Every record is created through the application's own HTTP endpoints, signed in as the
 * administrator, so the same validation, numbering, stock and alert rules run as when staff
 * use the screens. Nothing is inserted directly. The admin account is kept.
 */
class LoadSampleData extends Command
{
    protected $signature = 'obims:sample-data {--force : Load even when items or equipment already exist}';

    protected $description = 'Load sample data through the real endpoints (masters, items at 501/500/499/0 of reference 1,000, equipment with brand/model, issuances, returns, a full repair cycle, forms).';

    private SampleDataClient $client;

    /** @var array<string, int> */
    private array $departments = [];

    /** @var array<string, int> */
    private array $employees = [];

    /** @var array<string, int> */
    private array $items = [];

    /** @var array<string, int> */
    private array $equipment = [];

    public function handle(): int
    {
        if (! $this->option('force') && (Item::query()->exists() || Equipment::query()->exists())) {
            $this->error('Items or equipment already exist. Use --force to add the sample data anyway.');

            return self::FAILURE;
        }

        $admin = User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->first();
        if (! $admin) {
            $this->error('No active administrator account found.');

            return self::FAILURE;
        }

        $this->client = new SampleDataClient($this->laravel, $admin);

        $this->step('Master data', fn () => $this->masters());
        $this->step('Employee directory', fn () => $this->directory());
        $this->step('Supplies (reference stock and percentages)', fn () => $this->supplies());
        $this->step('Supply issuances (balances 501 / 500 / 499 / 0 of 1,000)', fn () => $this->supplyIssuances());
        $this->step('Replenishment example', fn () => $this->replenishment());
        $this->step('Equipment with brand / model and official numbers', fn () => $this->equipmentRecords());
        $this->step('Equipment issuances', fn () => $this->equipmentIssuances());
        $this->step('Returns and the full repair cycle', fn () => $this->returnsAndRepairs());
        $this->step('Delivery inspections', fn () => $this->inspections());
        $this->step('Office forms (RIV, ICS, IIRUP)', fn () => $this->forms());

        $this->newLine();
        $this->summary();

        return self::SUCCESS;
    }

    private function step(string $label, callable $callback): void
    {
        $this->components->task($label, function () use ($callback) {
            $callback();

            return true;
        });
    }

    // ------------------------------------------------------------------ masters

    private function masters(): void
    {
        foreach ([
            ['ICT Unit', 'ICT'],
            ['Administrative Office', 'ADMIN'],
            ['Curriculum Implementation Division', 'CID'],
            ['School Governance and Operations Division', 'SGOD'],
            ['Records Section', 'REC'],
        ] as [$name, $code]) {
            $this->departments[$code] = $this->client->ok('POST', '/departments', ['name' => $name, 'code' => $code, 'is_active' => true])['id'];
        }

        foreach (['Office Supplies', 'Cleaning Supplies', 'ICT Consumables'] as $name) {
            $this->client->ok('POST', '/categories', ['name' => $name, 'is_active' => true]);
        }
        foreach ([['Piece', 'pc'], ['Ream', 'rm'], ['Box', 'box'], ['Bottle', 'btl'], ['Roll', 'roll']] as [$name, $abbr]) {
            $this->client->ok('POST', '/units', ['name' => $name, 'abbreviation' => $abbr]);
        }
        foreach ([['Main Supply Room', 'MSR'], ['Annex Storage', 'ANX']] as [$name, $code]) {
            $this->client->ok('POST', '/locations', ['name' => $name, 'code' => $code, 'is_active' => true]);
        }
        foreach (['ICT Equipment', 'Office Equipment', 'Furniture and Fixtures'] as $name) {
            $this->client->ok('POST', '/equipment-categories', ['name' => $name, 'is_active' => true]);
        }
    }

    private function directory(): void
    {
        foreach ([
            ['ana', 'Ana Marie Santos', 'ICT', 'Information Technology Officer I', 'EMP-0101'],
            ['ben', 'Benjamin Reyes', 'ICT', 'Computer Maintenance Technologist', 'EMP-0102'],
            ['carla', 'Carla Dizon', 'ADMIN', 'Administrative Officer IV', 'EMP-0201'],
            ['dan', 'Daniel Mendoza', 'CID', 'Education Program Supervisor', 'EMP-0301'],
            ['ella', 'Ella Fernandez', 'SGOD', 'Senior Education Program Specialist', 'EMP-0401'],
            ['fred', 'Fredrick Bautista', 'REC', 'Records Officer II', 'EMP-0501'],
            ['gina', 'Gina Villanueva', 'ADMIN', 'Administrative Assistant III', 'EMP-0202'],
            ['hector', 'Hector Ramos', 'CID', 'Public Schools District Supervisor', 'EMP-0302'],
        ] as [$key, $name, $unit, $position, $number]) {
            $this->employees[$key] = $this->client->ok('POST', '/employee-directory', [
                'name' => $name,
                'department_id' => $this->departments[$unit],
                'position' => $position,
                'employee_number' => $number,
            ])['id'];
        }
    }

    // ------------------------------------------------------------------ supplies

    private function supplies(): void
    {
        $category = fn (string $name) => \App\Models\Category::query()->where('name', $name)->value('id');
        $unit = fn (string $name) => \App\Models\Unit::query()->where('name', $name)->value('id');
        $location = fn (string $code) => \App\Models\StorageLocation::query()->where('code', $code)->value('id');

        $rows = [
            // key, name, category, unit, location, reference, percent, opening receipt, stock no. (null = automatic)
            ['pen', 'Ballpoint Pen, Black', 'Office Supplies', 'Piece', 'MSR', 1000, null, 1000, null],
            ['paper', 'Bond Paper, A4, 70gsm', 'Office Supplies', 'Ream', 'MSR', 1000, null, 1000, null],
            ['folder', 'Folder, Long, Kraft', 'Office Supplies', 'Piece', 'MSR', 1000, null, 1000, null],
            ['tape', 'Correction Tape', 'Office Supplies', 'Piece', 'MSR', 1000, null, 1000, null],
            // Item with its own percentage (25%): threshold 50 of 200.
            ['alcohol', 'Isopropyl Alcohol 70%, 500 ml', 'Cleaning Supplies', 'Bottle', 'ANX', 200, 25, 200, null],
            // Typed Stock No. with leading zeros (manual entry while numbering is automatic).
            ['toner', 'Toner Cartridge, Black (Laser)', 'ICT Consumables', 'Piece', 'ANX', 20, null, 20, '000-TC-0042'],
            ['wire', 'Staple Wire No. 35', 'Office Supplies', 'Box', 'MSR', 100, null, 120, null],
            ['masking', 'Masking Tape, 1 inch', 'Office Supplies', 'Roll', 'MSR', 50, null, 50, null],
        ];

        foreach ($rows as [$key, $name, $cat, $u, $loc, $reference, $percent, $opening, $stockNo]) {
            $item = $this->client->ok('POST', '/items', array_filter([
                'item_name' => $name,
                'category_id' => $category($cat),
                'unit_id' => $unit($u),
                'location_id' => $location($loc),
                'minimum_stock' => 1,
                'reference_stock' => $reference,
                'low_stock_percent' => $percent,
                'unit_cost' => match ($key) {
                    'pen' => 8.50, 'paper' => 245.00, 'folder' => 6.25, 'tape' => 35.00,
                    'alcohol' => 89.00, 'toner' => 2450.00, 'wire' => 32.00, default => 28.00,
                },
                'item_number' => $stockNo,
            ], fn ($v) => $v !== null));
            $this->items[$key] = $item['id'];

            $this->client->ok('POST', '/stock/receive', [
                'barcode' => $item['item_number'],
                'quantity' => $opening,
                'remarks' => 'Opening stock (sample data)',
            ]);
        }

        // A stock count correction: 3 rolls found damaged.
        $this->client->ok('POST', '/stock/adjust', [
            'barcode' => Item::find($this->items['masking'])->item_number,
            'actual_count' => 47,
            'reason' => 'damaged',
            'remarks' => 'Water damage found during count (sample data)',
        ]);
    }

    private function supplyIssuances(): void
    {
        // Reference 1,000 at the default 50% -> threshold 500.
        $this->issueItems('ana', [['pen', 499]]);      // 501 left -> In Stock
        $this->issueItems('carla', [['paper', 500]]);  // 500 left -> Low Stock (exactly at threshold)
        $this->issueItems('dan', [['folder', 501]]);   // 499 left -> Low Stock
        $this->issueItems('ella', [['tape', 1000]]);   // 0 left -> Out of Stock
        $this->issueItems('fred', [['alcohol', 140], ['wire', 30]]); // alcohol 60 > 50 -> In Stock; wire 90 > 50
    }

    private function replenishment(): void
    {
        // Toner: reference 20, threshold 10. Issue 12 -> 8 left (Low Stock, alert raised).
        $this->issueItems('ben', [['toner', 12]]);
        // Receive 15 -> 23 left, above the threshold: status In Stock and the alert resolves.
        // The reference stays 20 (receiving never changes it unless "full replenishment" is ticked).
        $this->client->ok('POST', '/stock/receive', [
            'barcode' => Item::find($this->items['toner'])->item_number,
            'quantity' => 15,
            'remarks' => 'Replenishment delivery (sample data)',
        ]);
    }

    /**
     * @param  list<array{0: string, 1: int}>  $lines
     */
    private function issueItems(string $employee, array $lines): array
    {
        return $this->client->ok('POST', '/issuances', [
            'issuance_type' => 'items',
            'idempotency_key' => 'sample-'.Str::uuid(),
            'recipient_source' => 'directory',
            'received_by' => $this->employees[$employee],
            'items' => array_map(fn ($line) => [
                'item_id' => $this->items[$line[0]],
                'quantity' => $line[1],
                'date_acquired' => now()->toDateString(),
            ], $lines),
        ]);
    }

    // ------------------------------------------------------------------ equipment

    private function equipmentRecords(): void
    {
        $category = fn (string $name) => \App\Models\EquipmentCategory::query()->where('name', $name)->value('id');

        $rows = [
            // key, name, brand, model, type, category, serials, class, cost, life, numbers
            ['laptop', 'Laptop Computer', 'Lenovo', 'ThinkPad E14 Gen 5', 'Portable computer', 'ICT Equipment',
                ['PF4LX0A1', 'PF4LX0A2', 'PF4LX0A3'], 'semi_expendable', 48500.00, 5, []],
            // Single unit with a typed official number that has leading zeros.
            ['desktop', 'Desktop Computer', 'Acer', 'Veriton M200-A520', 'Desktop computer', 'ICT Equipment',
                ['DTVR2M0099'], 'ppe', 52000.00, 5, ['property_number' => '00045-SDO-2024', 'inventory_number' => '0007-INV', 'property_card_number' => 'PC-000045']],
            ['printer', 'Printer', 'Epson', 'EcoTank L3210', 'Ink tank printer', 'Office Equipment',
                ['X8FN012345', 'X8FN012346'], 'semi_expendable', 9800.00, 3, []],
            ['projector', 'Projector', 'Epson', 'EB-X51', 'LCD projector', 'Office Equipment',
                ['XEBX51-7781'], 'semi_expendable', 27500.00, 5, []],
            ['chair', 'Office Chair', null, null, 'Swivel chair', 'Furniture and Fixtures',
                ['CHR-001', 'CHR-002', 'CHR-003', 'CHR-004'], 'semi_expendable', 3500.00, 5, []],
        ];

        foreach ($rows as [$key, $name, $brand, $model, $type, $cat, $serials, $class, $cost, $life, $numbers]) {
            $record = $this->client->ok('POST', '/equipments', array_filter([
                'name' => $name,
                'brand' => $brand,
                'model' => $model,
                'type' => $type,
                'equipment_category_id' => $category($cat),
                'quantity' => count($serials),
                'serial_numbers' => $serials,
                'life_span_years' => $life,
                'property_class' => $class,
                'unit_cost' => $cost,
                'description' => "{$name} for SDO operations (sample data)",
                'specs' => match ($key) {
                    'laptop' => 'Intel Core i5-1335U, 16 GB RAM, 512 GB SSD, 14" FHD',
                    'desktop' => 'AMD Ryzen 5, 8 GB RAM, 256 GB SSD, 21.5" monitor',
                    'printer' => 'Print/scan/copy, USB, 33 ppm',
                    'projector' => '3,600 lumens, XGA, HDMI',
                    default => null,
                },
                ...$numbers,
            ], fn ($v) => $v !== null));
            $this->equipment[$key] = $record['id'];
        }
    }

    private function unitIds(string $equipment, array $serials): array
    {
        return EquipmentSerialNumber::query()
            ->where('equipment_id', $this->equipment[$equipment])
            ->whereIn('serial_number', $serials)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    private function equipmentIssuances(): void
    {
        // Laptop record holds 3 units: each issued unit gets its own Property No. from the paper form.
        $this->issueEquipment('ana', '2026-08-03', [
            ['laptop', ['PF4LX0A1'], '2026-ICT-0101', 'INV-ICT-0101'],
        ]);
        $this->issueEquipment('ben', '2026-08-03', [
            ['laptop', ['PF4LX0A2'], '2026-ICT-0102', null],
        ]);
        // Single-unit record: its official number (with leading zeros) is used automatically.
        $this->issueEquipment('carla', '2026-08-10', [
            ['desktop', ['DTVR2M0099'], null, null],
        ]);
        $this->issueEquipment('dan', '2026-08-17', [
            ['printer', ['X8FN012345'], '2026-OE-0201', null],
            ['projector', ['XEBX51-7781'], null, null],
        ]);
        $this->issueEquipment('ella', '2026-09-01', [
            ['chair', ['CHR-001'], '2026-FF-0301', null],
            ['chair', ['CHR-002'], '2026-FF-0302', null],
        ]);
    }

    /**
     * @param  list<array{0: string, 1: list<string>, 2: ?string, 3: ?string}>  $lines
     */
    private function issueEquipment(string $employee, string $date, array $lines): array
    {
        return $this->client->ok('POST', '/issuances', [
            'issuance_type' => 'equipments',
            'idempotency_key' => 'sample-'.Str::uuid(),
            'recipient_source' => 'directory',
            'received_by' => $this->employees[$employee],
            'use_custom_date' => true,
            'issued_date' => $date,
            'items' => array_map(fn ($line) => array_filter([
                'equipment_id' => $this->equipment[$line[0]],
                'serial_number_ids' => $this->unitIds($line[0], $line[1]),
                'quantity' => count($line[1]),
                'property_number' => $line[2],
                'inventory_number' => $line[3],
                'date_acquired' => '2025-03-10',
            ], fn ($v) => $v !== null), $lines),
        ]);
    }

    private function detailFor(string $equipment, string $serial): IssuanceDetail
    {
        $unit = EquipmentSerialNumber::query()
            ->where('equipment_id', $this->equipment[$equipment])
            ->where('serial_number', $serial)
            ->firstOrFail();

        return IssuanceDetail::query()->with('issuance')->findOrFail($unit->issuance_detail_id);
    }

    private function returnUnit(string $equipment, string $serial, string $employee, string $date, string $nextStatus, string $reason): array
    {
        $detail = $this->detailFor($equipment, $serial);

        return $this->client->ok('POST', '/returns', [
            'issuance_detail_id' => $detail->id,
            'equipment_id' => $detail->equipment_id,
            'department_id' => $detail->issuance->department_id,
            'borrower_employee_id' => $this->employees[$employee],
            'quantity' => 1,
            'serial_number_ids' => $this->unitIds($equipment, [$serial]),
            'next_status' => $nextStatus,
            'idempotency_key' => 'sample-'.Str::uuid(),
            'date_returned' => $date,
            'reason' => $reason,
        ]);
    }

    private function caseAction(int $caseId, string $action, string $date, array $extra = []): array
    {
        return $this->client->ok('POST', "/equipment-status/cases/{$caseId}/{$action}", [
            'reason' => $extra['reason'] ?? 'Recorded (sample data)',
            'event_date' => $date,
            'reference' => $extra['reference'] ?? 'REF-'.strtoupper(Str::random(6)),
        ] + $extra);
    }

    private function returnsAndRepairs(): void
    {
        // 1) Laptop unit PF4LX0A1 (2026-ICT-0101): returned For Repair, repaired, passed, back to
        //    Available on the SAME record with the SAME numbers, then issued again.
        $return = $this->returnUnit('laptop', 'PF4LX0A1', 'ana', '2026-09-15', 'for_repair', 'Keyboard not responding');
        $case = EquipmentCase::query()->where('item_return_id', $return['id'])->firstOrFail();
        $this->caseAction($case->id, 'repair', '2026-09-22', ['reason' => 'Keyboard replaced by ICT Unit', 'reference' => 'JO-2026-0915']);
        $this->caseAction($case->id, 'serviceability', '2026-09-23', ['passed' => true, 'reason' => 'All keys and ports tested OK', 'reference' => 'SC-2026-0923']);
        $this->caseAction($case->id, 'return-to-available', '2026-09-24', ['reason' => 'Cleared for issuance', 'reference' => 'RTA-2026-0924']);
        // Re-issue: the unit's recorded number fills in (no number is typed).
        $this->issueEquipment('gina', '2026-10-01', [['laptop', ['PF4LX0A1'], null, null]]);

        // 2) Laptop unit PF4LX0A2: normal return in good condition -> Available on its record.
        $this->returnUnit('laptop', 'PF4LX0A2', 'ben', '2026-10-06', 'available', 'End of assignment');

        // 3) Desktop: returned For Repair, repair recorded, serviceability not yet checked (in progress).
        $return = $this->returnUnit('desktop', 'DTVR2M0099', 'carla', '2026-10-20', 'for_repair', 'Does not power on');
        $case = EquipmentCase::query()->where('item_return_id', $return['id'])->firstOrFail();
        $this->caseAction($case->id, 'repair', '2026-10-27', ['reason' => 'Power supply replaced; awaiting test', 'reference' => 'JO-2026-1020']);

        // 4) Printer unit X8FN012345: returned Unserviceable (awaiting assessment) -> appears on the IIRUP.
        $this->returnUnit('printer', 'X8FN012345', 'dan', '2026-10-28', 'unserviceable', 'Print head clogged beyond cleaning');

        // 5) Office chair CHR-001 returned in good condition; CHR-002 stays issued.
        $this->returnUnit('chair', 'CHR-001', 'ella', '2026-11-03', 'available', 'Transferred office');

        // 6) Printer unit X8FN012346 (never issued): case opened from stock, For Repair.
        $this->client->ok('POST', '/equipment-status/cases', [
            'origin' => 'stock',
            'equipment_id' => $this->equipment['printer'],
            'serial_number_id' => $this->unitIds('printer', ['X8FN012346'])[0],
            'quantity' => 1,
            'status' => 'for_repair',
            'reason' => 'Paper feed roller worn (found during inventory)',
            'event_date' => '2026-11-05',
            'reference' => 'INSP-2026-1105',
        ]);
    }

    private function inspections(): void
    {
        // Posted inspection: 50 boxes of staple wire delivered, 48 accepted, 2 damaged.
        $inspection = $this->client->ok('POST', '/delivery-inspections', [
            'delivery_reference' => 'DR-2026-1107',
            'purchase_reference' => 'PO-2026-0311',
            'supplier' => 'Bicol Office Supplies Trading',
            'delivery_date' => '2026-11-07',
            'inspection_date' => '2026-11-07',
            'inspector_name' => 'Gina Villanueva',
            'remarks' => 'Sample data',
            'lines' => [[
                'line_type' => 'item',
                'item_id' => $this->items['wire'],
                'expected_quantity' => 50,
                'delivered_quantity' => 50,
                'accepted_quantity' => 48,
                'rejected_quantity' => 2,
                'condition' => 'mixed',
                'discrepancy_remarks' => '2 boxes crushed in transit',
            ]],
        ]);
        $this->client->ok('POST', "/delivery-inspections/{$this->id($inspection)}/post", []);

        // Draft inspection (not posted): stock is unchanged until it is posted.
        $this->client->ok('POST', '/delivery-inspections', [
            'delivery_reference' => 'DR-2026-1110',
            'supplier' => 'Naga ICT Solutions',
            'delivery_date' => '2026-11-10',
            'inspection_date' => '2026-11-10',
            'inspector_name' => 'Benjamin Reyes',
            'lines' => [[
                'line_type' => 'item',
                'item_id' => $this->items['toner'],
                'expected_quantity' => 10,
                'delivered_quantity' => 10,
                'accepted_quantity' => 10,
                'rejected_quantity' => 0,
                'condition' => 'good',
            ]],
        ]);
    }

    private function id(array $response): int
    {
        return (int) ($response['id'] ?? $response['inspection']['id'] ?? throw new RuntimeException('No id in response.'));
    }

    private function forms(): void
    {
        $itemIssuance = Issuance::query()
            ->whereHas('details', fn ($query) => $query->whereNotNull('item_id'))
            ->orderBy('id')
            ->firstOrFail();
        $this->client->ok('POST', "/forms/riv/{$itemIssuance->id}/issue", []);

        $laptopIssuance = $this->detailFor('laptop', 'PF4LX0A1')->issuance;
        $status = $this->client->ok('GET', "/forms/ics/{$laptopIssuance->id}");
        $this->client->ok('POST', "/forms/ics/{$laptopIssuance->id}/issue", [
            'entity_name' => $status['draft']['entity_name'] ?: 'Schools Division Office of Iriga City',
            'fund_cluster' => $status['draft']['fund_cluster'] ?? null,
            'lines' => array_map(fn ($line) => [
                'key' => $line['key'],
                'unit' => $line['unit'] ?: 'unit',
                'useful_life' => '5 years',
            ], $status['draft']['lines']),
        ]);

        $this->client->ok('POST', '/forms/iirup/issue', ['as_of' => now()->toDateString()]);
    }

    // ------------------------------------------------------------------ summary

    private function summary(): void
    {
        $this->info('Supplies (threshold = reference x percentage):');
        $this->table(
            ['Stock No.', 'Item', 'Remaining', 'Reference', '%', 'Threshold', 'Status', 'Alert'],
            Item::query()->orderBy('id')->get()->map(fn (Item $item) => [
                $item->item_number,
                $item->item_name,
                $item->current_stock,
                $item->reference_stock,
                $item->effective_low_stock_percent,
                $item->low_stock_threshold,
                $item->stock_status_label,
                $item->stock_alert_level ?? '—',
            ])
        );

        $this->info('Equipment units (each keeps its numbers through issuance, return and repair):');
        $this->table(
            ['Record', 'Brand / Model', 'Record Property No.', 'Serial', 'Unit Property No.', 'Unit status'],
            EquipmentSerialNumber::query()->with('equipment')->orderBy('equipment_id')->orderBy('id')->get()->map(fn ($unit) => [
                "#{$unit->equipment_id} {$unit->equipment?->name}",
                $unit->equipment?->brand_model ?? '—',
                $unit->equipment?->property_number,
                $unit->serial_number,
                $unit->property_number ?? '—',
                $unit->status,
            ])
        );

        $this->line(sprintf(
            'Issuances: %d · Returns: %d · Equipment cases: %d · Equipment records: %d',
            Issuance::query()->count(),
            ItemReturn::query()->count(),
            EquipmentCase::query()->count(),
            Equipment::query()->count(),
        ));
    }
}

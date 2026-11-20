<?php

/*
 * Verification scenarios from feed-back.md (items 1-6). Every scenario goes through the HTTP
 * endpoints the screens use.
 */

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCase;
use App\Models\EquipmentCategory;
use App\Models\EquipmentSerialNumber;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Models\ObimsNotification;
use App\Models\OfficeFormDocument;
use App\Models\Setting;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\StockStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::setValue('allow_negative_stock', 'false', 'inventory');
    Setting::setValue('low_stock_percent_default', '50', 'inventory');
    StockStatus::flushDefault();

    $this->admin = User::create([
        'name' => 'Feedback Admin', 'email' => 'fb-admin@test.local', 'password' => 'password',
        'role' => UserRole::Admin, 'is_active' => true,
    ]);
    $this->category = Category::create(['name' => 'Office', 'is_active' => true]);
    $this->unit = Unit::create(['name' => 'Pieces', 'abbreviation' => 'pcs']);
    $this->location = StorageLocation::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
    $this->department = Department::create(['name' => 'ICT Unit', 'code' => 'ICT', 'is_active' => true]);
    $this->employee = Employee::create([
        'employee_number' => 'EMP-2001', 'name' => 'Ana Santos', 'position' => 'IT Officer I',
        'department_id' => $this->department->id, 'is_active' => true,
    ]);
    $this->other = Employee::create([
        'employee_number' => 'EMP-2002', 'name' => 'Ben Reyes', 'position' => 'Technician',
        'department_id' => $this->department->id, 'is_active' => true,
    ]);
    $this->eqCategory = EquipmentCategory::create(['name' => 'ICT', 'is_active' => true]);
});

function fbRegisterEquipment(array $overrides = []): array
{
    $serials = $overrides['serial_numbers'] ?? ['SN-1', 'SN-2'];

    return test()->actingAs(test()->admin)->postJson('/equipments', array_merge([
        'name' => 'Laptop', 'brand' => 'Lenovo', 'model' => 'ThinkPad E14', 'type' => 'Portable',
        'equipment_category_id' => test()->eqCategory->id, 'quantity' => count($serials),
        'serial_numbers' => $serials, 'life_span_years' => 5, 'property_class' => 'semi_expendable',
    ], $overrides))->assertCreated()->json();
}

function fbUnit(int $equipmentId, string $serial): EquipmentSerialNumber
{
    return EquipmentSerialNumber::query()->where('equipment_id', $equipmentId)->where('serial_number', $serial)->firstOrFail();
}

function fbIssue(int $equipmentId, array $serials, ?string $propertyNumber, string $date = '2026-08-03', ?int $employeeId = null)
{
    return test()->actingAs(test()->admin)->postJson('/issuances', [
        'issuance_type' => 'equipments',
        'received_by' => $employeeId ?? test()->employee->id,
        'use_custom_date' => true,
        'issued_date' => $date,
        'items' => [array_filter([
            'equipment_id' => $equipmentId,
            'serial_number_ids' => array_map(fn ($s) => fbUnit($equipmentId, $s)->id, $serials),
            'quantity' => count($serials),
            'property_number' => $propertyNumber,
            'date_acquired' => '2025-03-10',
        ], fn ($v) => $v !== null)],
    ]);
}

function fbReturn(int $equipmentId, string $serial, string $nextStatus, string $date, array $extra = [])
{
    $unit = fbUnit($equipmentId, $serial);
    $detail = \App\Models\IssuanceDetail::with('issuance')->findOrFail($unit->issuance_detail_id);

    return test()->actingAs(test()->admin)->postJson('/returns', array_merge([
        'issuance_detail_id' => $detail->id,
        'equipment_id' => $equipmentId,
        'department_id' => $detail->issuance->department_id,
        'borrower_employee_id' => test()->employee->id,
        'quantity' => 1,
        'serial_number_ids' => [$unit->id],
        'next_status' => $nextStatus,
        'date_returned' => $date,
    ], $extra));
}

function fbCase(int $caseId, string $action, string $date, array $extra = [])
{
    return test()->actingAs(test()->admin)->postJson("/equipment-status/cases/{$caseId}/{$action}", array_merge([
        'reason' => 'Recorded', 'event_date' => $date, 'reference' => 'REF-1',
    ], $extra));
}

function fbItem(int $reference, ?float $percent = null, array $extra = []): Item
{
    $data = test()->actingAs(test()->admin)->postJson('/items', array_merge([
        'item_name' => 'Bond Paper '.uniqid(), 'category_id' => test()->category->id, 'unit_id' => test()->unit->id,
        'location_id' => test()->location->id, 'minimum_stock' => 1, 'reference_stock' => $reference,
        'low_stock_percent' => $percent,
    ], $extra))->assertCreated()->json();

    return Item::findOrFail($data['id']);
}

function fbReceive(Item $item, int $qty, bool $updateReference = false)
{
    return test()->actingAs(test()->admin)->postJson('/stock/receive', [
        'barcode' => $item->fresh()->item_number, 'quantity' => $qty, 'update_reference' => $updateReference,
    ])->assertSuccessful();
}

function fbIssueItem(Item $item, int $qty)
{
    return test()->actingAs(test()->admin)->postJson('/issuances', [
        'issuance_type' => 'items',
        'received_by' => test()->employee->id,
        'items' => [['item_id' => $item->id, 'quantity' => $qty, 'date_acquired' => now()->toDateString()]],
    ])->assertCreated();
}

// ------------------------------------------------------------------ Scenario 1

test('scenario 1: repair cycle returns the unit to Available on its original record with the same numbers', function () {
    $equipment = fbRegisterEquipment();
    $id = $equipment['id'];

    fbIssue($id, ['SN-1'], 'U-0001')->assertCreated();
    $return = fbReturn($id, 'SN-1', 'for_repair', '2026-09-15')->assertCreated()->json();
    $case = EquipmentCase::where('item_return_id', $return['id'])->firstOrFail();

    expect(fbUnit($id, 'SN-1')->status)->toBe(EquipmentSerialNumber::STATUS_FOR_REPAIR)
        ->and(Equipment::find($id)->quantity)->toBe(1);

    // Not Available before repair and serviceability are recorded.
    fbCase($case->id, 'return-to-available', '2026-09-20')->assertStatus(422);
    fbCase($case->id, 'repair', '2026-09-22', ['reason' => 'Keyboard replaced'])->assertSuccessful();
    fbCase($case->id, 'return-to-available', '2026-09-22')->assertStatus(422);
    fbCase($case->id, 'serviceability', '2026-09-23', ['passed' => true])->assertSuccessful();
    fbCase($case->id, 'return-to-available', '2026-09-24')->assertSuccessful();

    $unit = fbUnit($id, 'SN-1');
    expect(Equipment::withTrashed()->count())->toBe(1)                 // no new equipment record
        ->and($unit->equipment_id)->toBe($id)                            // same record
        ->and($unit->status)->toBe(EquipmentSerialNumber::STATUS_IN_STOCK)
        ->and($unit->property_number)->toBe('U-0001')                    // same property number
        ->and(Equipment::find($id)->quantity)->toBe(2)
        ->and(Equipment::find($id)->property_number)->toBe($equipment['property_number'])
        ->and($case->fresh()->status)->toBe('returned_to_service');
});

// ------------------------------------------------------------------ Scenario 2

test('scenario 2: re-issuing a repaired unit keeps its number and links the history to the same record', function () {
    $id = fbRegisterEquipment()['id'];
    fbIssue($id, ['SN-1'], 'U-0001')->assertCreated();
    $return = fbReturn($id, 'SN-1', 'available', '2026-09-15')->assertCreated()->json();
    expect($return['restocked'])->toBeTrue();

    // A different typed number for a unit that already carries one is rejected, not overwritten.
    fbIssue($id, ['SN-1'], 'OTHER-9', '2026-10-01')->assertStatus(422);
    expect(fbUnit($id, 'SN-1')->property_number)->toBe('U-0001');

    // No number typed: the unit's recorded number is used.
    $second = fbIssue($id, ['SN-1'], null, '2026-10-01', test()->other->id)->assertCreated();
    expect($second->json('details.0.property_number'))->toBe('U-0001');

    $details = \App\Models\IssuanceDetail::where('equipment_id', $id)->orderBy('id')->get();
    expect($details)->toHaveCount(2)
        ->and($details->pluck('property_number')->unique()->values()->all())->toBe(['U-0001'])
        ->and(ItemReturn::where('equipment_id', $id)->count())->toBe(1)
        ->and(Equipment::withTrashed()->count())->toBe(1);
});

test('normal returns require the actual date, cannot be processed twice, and are atomic', function () {
    $id = fbRegisterEquipment()['id'];
    fbIssue($id, ['SN-1'], 'U-0001')->assertCreated();

    fbReturn($id, 'SN-1', 'available', '2026-09-15', ['date_returned' => null])->assertStatus(422)->assertJsonValidationErrors('date_returned');
    fbReturn($id, 'SN-1', 'available', '2026-08-01')->assertStatus(422); // before the issuance date

    $first = fbReturn($id, 'SN-1', 'available', '2026-09-15', ['idempotency_key' => 'ret-1'])->assertCreated();
    // Retried submission with the same key returns the original return.
    $this->actingAs($this->admin)->postJson('/returns', [
        'issuance_detail_id' => \App\Models\IssuanceDetail::where('equipment_id', $id)->value('id'),
        'equipment_id' => $id, 'department_id' => $this->department->id, 'borrower_employee_id' => $this->employee->id,
        'quantity' => 1, 'serial_number_ids' => [fbUnit($id, 'SN-1')->id], 'next_status' => 'available',
        'date_returned' => '2026-09-15', 'idempotency_key' => 'ret-1',
    ])->assertOk()->assertJsonPath('id', $first->json('id'));

    expect(ItemReturn::count())->toBe(1)->and(Equipment::find($id)->quantity)->toBe(2);

    // A second return of the same (already returned) line is refused.
    $this->actingAs($this->admin)->postJson('/returns', [
        'issuance_detail_id' => \App\Models\IssuanceDetail::where('equipment_id', $id)->value('id'),
        'equipment_id' => $id, 'department_id' => $this->department->id, 'borrower_employee_id' => $this->employee->id,
        'quantity' => 1, 'serial_number_ids' => [fbUnit($id, 'SN-1')->id], 'next_status' => 'available',
        'date_returned' => '2026-09-16',
    ])->assertStatus(422);
    expect(ItemReturn::count())->toBe(1)->and(Equipment::find($id)->quantity)->toBe(2);
});

// ------------------------------------------------------------------ Scenario 3

test('scenario 3: saving equipment with an unchanged property number succeeds; real conflicts are rejected', function () {
    $a = fbRegisterEquipment(['property_number' => 'P-100', 'serial_numbers' => ['A-1']]);
    $b = fbRegisterEquipment(['property_number' => 'P-200', 'serial_numbers' => ['B-1']]);

    $payload = fn (array $eq, array $changes = []) => array_merge([
        'name' => 'Laptop', 'brand' => 'Lenovo', 'model' => 'ThinkPad E14', 'type' => 'Portable',
        'equipment_category_id' => $this->eqCategory->id, 'quantity' => 1,
        'serial_numbers' => [$eq['serial_numbers'][0]['serial_number']], 'life_span_years' => 5,
        'property_number' => $eq['property_number'], 'inventory_number' => $eq['inventory_number'],
    ], $changes);

    // Unchanged number (also with surrounding spaces) saves without a duplicate error.
    $this->actingAs($this->admin)->putJson("/equipments/{$a['id']}", $payload($a, ['name' => 'Laptop (edited)']))->assertOk();
    $this->actingAs($this->admin)->putJson("/equipments/{$a['id']}", $payload($a, ['property_number' => '  P-100  ']))->assertOk();
    expect(Equipment::find($a['id'])->property_number)->toBe('P-100');

    // Another record's number is a genuine conflict.
    $this->actingAs($this->admin)->putJson("/equipments/{$a['id']}", $payload($a, ['property_number' => 'P-200']))
        ->assertStatus(422)->assertJsonValidationErrors('property_number');
    // Another record's inventory number too.
    $this->actingAs($this->admin)->putJson("/equipments/{$a['id']}", $payload($a, ['inventory_number' => $b['inventory_number']]))
        ->assertStatus(422)->assertJsonValidationErrors('inventory_number');

    // A new unique number is accepted; the record keeps its ID and its unit follows the number.
    $this->actingAs($this->admin)->putJson("/equipments/{$a['id']}", $payload($a, ['property_number' => 'P-101']))->assertOk();
    expect(Equipment::find($a['id'])->property_number)->toBe('P-101')
        ->and(fbUnit($a['id'], 'A-1')->property_number)->toBe('P-101')
        ->and(fbUnit($a['id'], 'A-1')->id)->toBe($a['serial_numbers'][0]['id']);
});

// ------------------------------------------------------------------ Scenario 4

test('scenario 4: brand and model are stored and auto-populated in lookups, lists, forms and exports', function () {
    $eq = fbRegisterEquipment(['brand' => '  Epson ', 'model' => 'EB-X51', 'name' => 'Projector', 'serial_numbers' => ['PJ-1']]);
    expect(Equipment::find($eq['id'])->brand)->toBe('Epson');

    $this->actingAs($this->admin)->getJson('/scan-issue/lookup?code=PJ-1')
        ->assertOk()->assertJsonPath('equipment.brand', 'Epson')->assertJsonPath('equipment.model', 'EB-X51')
        // Single unit: the record's number is recorded on the unit, so it is filled in and locked.
        ->assertJsonPath('equipment.property_number_rule', 'unit')
        ->assertJsonPath('equipment.in_stock_serials.0.property_number', $eq['property_number']);

    $listed = collect($this->actingAs($this->admin)->getJson('/equipments/list')->json());
    expect($listed->firstWhere('id', $eq['id'])['brand_model'])->toBe('Epson EB-X51');

    $issued = fbIssue($eq['id'], ['PJ-1'], null)->assertCreated();
    $outstanding = collect($this->actingAs($this->admin)->getJson('/issuances/outstanding-equipments')->json());
    expect($outstanding->first()['brand_model'])->toBe('Epson EB-X51');

    $ics = $this->actingAs($this->admin)->getJson("/forms/ics/{$issued->json('id')}")->assertOk();
    expect($ics->json('draft.lines.0.description'))->toContain('Epson EB-X51');

    $xlsx = $this->actingAs($this->admin)->get('/reports/equipment-inventory/xlsx')->assertOk();
    $zip = tempnam(sys_get_temp_dir(), 'x');
    file_put_contents($zip, $xlsx->getContent());
    // ext-zip is not enabled in XAMPP's PHP; read the sheet the same way the export test does.
    $sheet = shell_exec('python -c "import zipfile,sys;print(zipfile.ZipFile(sys.argv[1]).read(\'xl/worksheets/sheet1.xml\').decode())" '.escapeshellarg($zip));
    @unlink($zip);
    expect($sheet)->toContain('Brand')->toContain('Epson')->toContain('EB-X51');
});

// ------------------------------------------------------------------ Scenario 5

test('scenario 5: the server resolves dependent numbers from the selected unit and rejects stale values', function () {
    $id = fbRegisterEquipment(['serial_numbers' => ['SN-1', 'SN-2', 'SN-3']])['id'];
    fbIssue($id, ['SN-1'], 'U-0001')->assertCreated();
    fbReturn($id, 'SN-1', 'available', '2026-09-15')->assertCreated();

    // Client switched from SN-1 to SN-2 but still sends SN-1's number: refused (SN-2 has none,
    // and U-0001 belongs to another unit only if it is another record's; here it is typed for SN-2).
    // Selecting SN-1 again with SN-2's (stale) typed number is refused because SN-1 carries U-0001.
    fbIssue($id, ['SN-1'], 'U-0002', '2026-10-01')->assertStatus(422);
    // The record's shared number cannot stand in for one unit of a multi-unit record.
    $record = Equipment::find($id)->property_number;
    fbIssue($id, ['SN-2'], $record, '2026-10-01')->assertStatus(422);
    // Units carrying different numbers cannot share one line.
    fbIssue($id, ['SN-2'], 'U-0002', '2026-10-01')->assertCreated();
    fbReturn($id, 'SN-2', 'available', '2026-10-05')->assertCreated();
    fbIssue($id, ['SN-1', 'SN-2'], null, '2026-10-06')->assertStatus(422);

    // Recipient details are resolved on the server; the designation is kept on the issuance.
    $issuance = fbIssue($id, ['SN-3'], 'U-0003', '2026-10-06', test()->other->id)->assertCreated();
    expect($issuance->json('received_by_name'))->toBe('Ben Reyes')
        ->and(\App\Models\Issuance::find($issuance->json('id'))->recipient_designation)->toBe('Technician');
});

// ------------------------------------------------------------------ Scenario 6

test('scenario 6: official numbers keep leading zeros and are trimmed text', function () {
    $eq = fbRegisterEquipment(['property_number' => ' 000123 ', 'inventory_number' => '0007', 'property_card_number' => '00045', 'serial_numbers' => ['Z-1']]);
    $fresh = Equipment::find($eq['id']);
    expect($fresh->property_number)->toBe('000123')
        ->and($fresh->inventory_number)->toBe('0007')
        ->and($fresh->property_card_number)->toBe('00045');

    // "000123" and "123" are different numbers.
    fbRegisterEquipment(['property_number' => '123', 'serial_numbers' => ['Z-2']]);
    $this->actingAs($this->admin)->postJson('/equipments', [
        'name' => 'X', 'type' => 'X', 'equipment_category_id' => $this->eqCategory->id, 'quantity' => 1,
        'serial_numbers' => ['Z-3'], 'life_span_years' => 5, 'property_number' => '000123',
    ])->assertStatus(422)->assertJsonValidationErrors('property_number');

    $item = fbItem(100, null, ['item_number' => '  0042 ']);
    expect($item->item_number)->toBe('0042');

    // The single unit's number on the issuance keeps the zeros.
    $issued = fbIssue($eq['id'], ['Z-1'], null)->assertCreated();
    expect($issued->json('details.0.property_number'))->toBe('000123');
});

// ------------------------------------------------------------------ Scenario 7

test('scenario 7: numbering formats, preview, resets and manual mode', function () {
    $numbers = app(DocumentNumberService::class);

    $this->actingAs($this->admin)->postJson('/document-numbering/preview', [
        'document_type' => 'equipment_property_number', 'mode' => 'auto', 'format' => 'EQ-{YY}{MM}-{SEQ}', 'padding' => 3, 'reset_rule' => 'monthly',
    ])->assertOk()->assertJsonPath('examples.first_today', 'EQ-'.now()->format('ym').'-001');

    // Invalid formats are refused before saving.
    foreach ([
        ['format' => 'EQ-{YYYY}', 'reset_rule' => 'never'],             // no {SEQ}
        ['format' => 'EQ-{SEQ}', 'reset_rule' => 'yearly'],             // yearly without year
        ['format' => 'EQ-{YYYY}-{SEQ}', 'reset_rule' => 'monthly'],     // monthly without month
        ['format' => 'EQ-{FOO}-{SEQ}', 'reset_rule' => 'never'],        // unknown token
    ] as $bad) {
        $this->actingAs($this->admin)->putJson('/document-numbering/equipment_property_number', $bad + ['mode' => 'auto', 'padding' => 4])
            ->assertStatus(422);
    }

    $this->actingAs($this->admin)->putJson('/document-numbering/equipment_property_number', [
        'mode' => 'auto', 'format' => 'EQ-{YY}{MM}-{SEQ}', 'padding' => 3, 'reset_rule' => 'monthly',
    ])->assertOk();

    $none = fn () => false;
    DB::transaction(function () use ($numbers, $none) {
        expect($numbers->generate('equipment_property_number', $none, Carbon::parse('2026-11-05')))->toBe('EQ-2611-001')
            ->and($numbers->generate('equipment_property_number', $none, Carbon::parse('2026-11-20')))->toBe('EQ-2611-002')
            ->and($numbers->generate('equipment_property_number', $none, Carbon::parse('2026-12-01')))->toBe('EQ-2612-001');
    });

    // Yearly reset and a taken number being skipped.
    $numbers->save('ics_number', ['mode' => 'auto', 'format' => 'ICS-{YYYY}-{SEQ}', 'padding' => 4, 'reset_rule' => 'yearly']);
    $taken = fn (string $n) => $n === 'ICS-2027-0001';
    expect($numbers->generate('ics_number', $taken, Carbon::parse('2027-01-02')))->toBe('ICS-2027-0002')
        ->and($numbers->generate('ics_number', $none, Carbon::parse('2028-01-02')))->toBe('ICS-2028-0001');

    // Never reset.
    $numbers->save('riv_number', ['mode' => 'auto', 'format' => 'RIV-{SEQ}', 'padding' => 5, 'reset_rule' => 'never']);
    expect($numbers->generate('riv_number', $none, Carbon::parse('2026-01-01')))->toBe('RIV-00001')
        ->and($numbers->generate('riv_number', $none, Carbon::parse('2030-06-01')))->toBe('RIV-00002');

    // Manual mode: the number must be typed; a typed number is used as is.
    $this->actingAs($this->admin)->putJson('/document-numbering/equipment_property_number', [
        'mode' => 'manual', 'format' => '', 'padding' => 4, 'reset_rule' => 'never',
    ])->assertOk();
    $this->actingAs($this->admin)->postJson('/equipments', [
        'name' => 'X', 'type' => 'X', 'equipment_category_id' => $this->eqCategory->id, 'quantity' => 1,
        'serial_numbers' => ['M-1'], 'life_span_years' => 5,
    ])->assertStatus(422)->assertJsonValidationErrors('property_number');
    $manual = fbRegisterEquipment(['property_number' => 'SDO-PAPER-77', 'serial_numbers' => ['M-2']]);
    expect($manual['property_number'])->toBe('SDO-PAPER-77');

    // Non-admins cannot change numbering.
    $officer = User::create(['name' => 'SO', 'email' => 'so@test.local', 'password' => 'password', 'role' => UserRole::SupplyOfficer, 'is_active' => true]);
    $this->actingAs($officer)->putJson('/document-numbering/ics_number', ['mode' => 'manual', 'padding' => 4, 'reset_rule' => 'never'])->assertForbidden();
});

test('scenario 7: document numbers are shared by all lines and unique per form type', function () {
    $eq = fbRegisterEquipment(['serial_numbers' => ['L-1', 'L-2']]);
    $issued = test()->actingAs(test()->admin)->postJson('/issuances', [
        'issuance_type' => 'equipments', 'received_by' => $this->employee->id, 'use_custom_date' => true, 'issued_date' => '2026-08-03',
        'items' => [
            ['equipment_id' => $eq['id'], 'serial_number_ids' => [fbUnit($eq['id'], 'L-1')->id], 'quantity' => 1, 'property_number' => 'U-1', 'date_acquired' => '2025-01-01'],
            ['equipment_id' => $eq['id'], 'serial_number_ids' => [fbUnit($eq['id'], 'L-2')->id], 'quantity' => 1, 'property_number' => 'U-2', 'date_acquired' => '2025-01-01'],
        ],
    ])->assertCreated();
    $issuanceId = $issued->json('id');
    $draft = $this->actingAs($this->admin)->getJson("/forms/ics/{$issuanceId}")->json('draft');
    $lines = array_map(fn ($l) => ['key' => $l['key'], 'unit' => 'unit'], $draft['lines']);

    $this->actingAs($this->admin)->postJson("/forms/ics/{$issuanceId}/issue", ['entity_name' => 'SDO', 'lines' => $lines, 'number' => ' ICS-0001 '])
        ->assertCreated()->assertJsonPath('document_number', 'ICS-0001');
    $doc = OfficeFormDocument::where('form_type', 'ics')->first();
    // Both units are on one ICS line (same equipment and cost) under the one ICS number.
    expect($doc->payload['lines'])->toHaveCount(1)
        ->and($doc->payload['lines'][0]['item_numbers'])->toBe(['U-1', 'U-2'])
        ->and($doc->payload['number'])->toBe('ICS-0001');

    // Same number for another RIV is fine (different form type); a second ICS cannot reuse it.
    $item = fbItem(100);
    fbReceive($item, 10);
    $riv = fbIssueItem($item, 1)->json('id');
    $this->actingAs($this->admin)->postJson("/forms/riv/{$riv}/issue", ['number' => 'ICS-0001'])->assertCreated();

    $eq2 = fbRegisterEquipment(['serial_numbers' => ['Q-1']]);
    $issued2 = fbIssue($eq2['id'], ['Q-1'], null)->json('id');
    $draft2 = $this->actingAs($this->admin)->getJson("/forms/ics/{$issued2}")->json('draft');
    $this->actingAs($this->admin)->postJson("/forms/ics/{$issued2}/issue", [
        'entity_name' => 'SDO', 'number' => 'ICS-0001', 'lines' => array_map(fn ($l) => ['key' => $l['key'], 'unit' => 'unit'], $draft2['lines']),
    ])->assertStatus(422)->assertJsonValidationErrors('number');
});

// ------------------------------------------------------------------ Scenario 8 + 9

test('scenario 8: reference 1,000 at 50% gives 501 In Stock, 500 and 499 Low Stock with one alert, 0 Out of Stock', function () {
    $item = fbItem(1000);
    fbReceive($item, 1000);
    expect($item->fresh()->low_stock_threshold)->toBe(500.0);

    fbIssueItem($item, 499);
    $fresh = $item->fresh();
    expect($fresh->current_stock)->toBe(501)->and($fresh->stock_status)->toBe('in_stock')
        ->and($fresh->stock_alert_level)->toBeNull()
        ->and(ObimsNotification::where('type', 'low_stock')->count())->toBe(0);

    fbIssueItem($item, 1);
    $fresh = $item->fresh();
    expect($fresh->current_stock)->toBe(500)->and($fresh->stock_status)->toBe('low_stock')
        ->and($fresh->stock_alert_level)->toBe('low')
        ->and(ObimsNotification::where('type', 'low_stock')->count())->toBe(1);

    fbIssueItem($item, 1);
    $this->artisan('stock:check-low')->assertSuccessful();
    $fresh = $item->fresh();
    expect($fresh->current_stock)->toBe(499)->and($fresh->stock_status)->toBe('low_stock')
        ->and(ObimsNotification::where('type', 'low_stock')->count())->toBe(1); // no duplicate

    // The reference is not changed by issuance.
    expect($fresh->reference_stock)->toBe(1000);

    fbIssueItem($item, 499);
    $fresh = $item->fresh();
    expect($fresh->current_stock)->toBe(0)->and($fresh->stock_status)->toBe('out_of_stock')
        ->and($fresh->stock_alert_level)->toBe('out')
        ->and(ObimsNotification::where('type', 'out_of_stock')->count())->toBe(1)
        ->and(ObimsNotification::where('type', 'low_stock')->whereNull('read_at')->count())->toBe(0);

    // Lists and dashboard agree with the item.
    $this->actingAs($this->admin)->getJson('/dashboard/summary')->assertOk();
    $low = $this->actingAs($this->admin)->getJson('/items/list?all=1')->json();
    $row = collect($low['data'] ?? $low)->firstWhere('id', $item->id);
    expect($row['stock_status'])->toBe('out_of_stock')
        ->and((float) $row['low_stock_threshold'])->toBe(500.0)
        ->and((float) $row['effective_low_stock_percent'])->toBe(50.0);
});

test('scenario 9: replenishing above the threshold clears the status and resolves the alert', function () {
    $item = fbItem(20);
    fbReceive($item, 20);
    fbIssueItem($item, 12); // 8 left, threshold 10
    expect($item->fresh()->stock_status)->toBe('low_stock')
        ->and(ObimsNotification::where('type', 'low_stock')->whereNull('read_at')->count())->toBe(1);

    fbReceive($item, 15); // 23 left, reference unchanged
    $fresh = $item->fresh();
    expect($fresh->stock_status)->toBe('in_stock')
        ->and($fresh->reference_stock)->toBe(20)
        ->and($fresh->stock_alert_level)->toBeNull()
        ->and(ObimsNotification::where('type', 'low_stock')->whereNull('read_at')->count())->toBe(0);

    // Explicit full replenishment sets the reference to the new balance.
    fbReceive($item, 7, true);
    expect($item->fresh()->reference_stock)->toBe(30);

    // A second drop raises one new alert.
    fbIssueItem($item, 16); // 14 left of 30 at 50% -> Low
    expect(ObimsNotification::where('type', 'low_stock')->count())->toBe(2);
});

test('percentages are validated and a changed percentage recalculates status', function () {
    $this->actingAs($this->admin)->postJson('/items', [
        'item_name' => 'X', 'category_id' => $this->category->id, 'unit_id' => $this->unit->id, 'location_id' => $this->location->id,
        'minimum_stock' => 1, 'reference_stock' => 100, 'low_stock_percent' => 0,
    ])->assertStatus(422)->assertJsonValidationErrors('low_stock_percent');
    $this->actingAs($this->admin)->postJson('/items', [
        'item_name' => 'X', 'category_id' => $this->category->id, 'unit_id' => $this->unit->id, 'location_id' => $this->location->id,
        'minimum_stock' => 1, 'reference_stock' => 100, 'low_stock_percent' => 101,
    ])->assertStatus(422)->assertJsonValidationErrors('low_stock_percent');

    $item = fbItem(100, 25);
    fbReceive($item, 30);
    expect($item->fresh()->stock_status)->toBe('in_stock'); // 30 > 25

    // Item percentage changed to 40% -> threshold 40 -> Low, alert raised.
    $this->actingAs($this->admin)->putJson("/items/{$item->id}", [
        'item_name' => $item->item_name, 'category_id' => $item->category_id, 'unit_id' => $item->unit_id,
        'location_id' => $item->location_id, 'minimum_stock' => 1, 'reference_stock' => 100, 'low_stock_percent' => 40,
        'status' => 'active', 'item_number' => $item->item_number,
    ])->assertOk();
    expect($item->fresh()->stock_status)->toBe('low_stock')->and($item->fresh()->stock_alert_level)->toBe('low');

    // Default percentage: invalid values are refused without changing anything.
    $this->actingAs($this->admin)->putJson('/settings', ['settings' => [
        ['key' => 'organization_name', 'value' => 'Changed', 'group' => 'general'],
        ['key' => 'low_stock_percent_default', 'value' => '0', 'group' => 'inventory'],
    ]])->assertStatus(422);
    expect(Setting::getValue('organization_name'))->not->toBe('Changed');

    // Default 50% -> 20%: an item using the default at 30 of 100 goes from Low to In Stock.
    $default = fbItem(100);
    fbReceive($default, 30);
    expect($default->fresh()->stock_status)->toBe('low_stock');
    $this->actingAs($this->admin)->putJson('/settings', ['settings' => [
        ['key' => 'low_stock_percent_default', 'value' => '20', 'group' => 'inventory'],
    ]])->assertOk();
    expect($default->fresh()->stock_status)->toBe('in_stock')->and($default->fresh()->stock_alert_level)->toBeNull();
});

// ------------------------------------------------------------------ Scenario 10

test('scenario 10: existing documents keep their numbers and stay accessible after settings change', function () {
    $item = fbItem(100);
    fbReceive($item, 10);
    $issuanceId = fbIssueItem($item, 2)->json('id');
    $number = $this->actingAs($this->admin)->postJson("/forms/riv/{$issuanceId}/issue", [])->assertCreated()->json('document_number');

    $this->actingAs($this->admin)->putJson('/document-numbering/riv_number', [
        'mode' => 'auto', 'format' => 'NEW-{YYYY}-{SEQ}', 'padding' => 6, 'reset_rule' => 'yearly',
    ])->assertOk();

    $this->actingAs($this->admin)->getJson("/forms/riv/{$issuanceId}")->assertJsonPath('document_number', $number);
    $this->actingAs($this->admin)->get("/forms/riv/{$issuanceId}/pdf")->assertOk();
    $this->actingAs($this->admin)->get("/forms/riv/{$issuanceId}/xlsx")->assertOk();

    // IIRUP issued with a number; reprint from the saved copy.
    $iirup = $this->actingAs($this->admin)->postJson('/forms/iirup/issue', ['as_of' => now()->toDateString()])->assertCreated();
    $docId = $iirup->json('id');
    $this->actingAs($this->admin)->get("/forms/iirup-documents/{$docId}/pdf")->assertOk();
    expect(collect($this->actingAs($this->admin)->getJson('/forms/iirup-documents')->json())->pluck('document_number'))
        ->toContain($iirup->json('document_number'));

    // Equipment registered before the change keeps its number; reports still load.
    $eq = fbRegisterEquipment(['serial_numbers' => ['K-1']]);
    $this->actingAs($this->admin)->putJson('/document-numbering/equipment_property_number', [
        'mode' => 'auto', 'format' => 'ZZ-{SEQ}', 'padding' => 2, 'reset_rule' => 'never',
    ])->assertOk();
    expect(Equipment::find($eq['id'])->property_number)->toBe($eq['property_number']);
    $this->actingAs($this->admin)->get('/reports/equipment-inventory/pdf')->assertOk();
    $this->actingAs($this->admin)->get("/reports/property-card/pdf?equipment_id={$eq['id']}")->assertSuccessful();
});

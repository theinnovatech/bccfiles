<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Issuance;
use App\Models\Item;
use App\Models\OfficeFormDocument;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\User;
use App\Services\FormService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Acceptance checks from SDO_Reports_Reference_Pack (revision 2) for the web forms.
 * Set FORM_SAMPLES_DIR to write the generated PDFs / workbooks for visual comparison.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::setValue('allow_negative_stock', 'false', 'inventory');
    Setting::setValue('form_entity_name', 'SDO IRIGA CITY', 'forms');
    Setting::setValue('form_fund_cluster', '01-Regular Fund', 'forms');
    Setting::setValue('form_approved_by_name', 'Approver One', 'forms');
    Setting::setValue('form_approved_by_designation', 'Supply Officer', 'forms');

    $this->admin = User::create([
        'name' => 'Forms Admin', 'email' => 'forms@test.local', 'password' => 'password',
        'role' => UserRole::Admin, 'is_active' => true,
    ]);
    $this->unit = Unit::create(['name' => 'Pieces', 'abbreviation' => 'pcs']);
    $this->category = Category::create(['name' => 'Office', 'is_active' => true]);
    $this->location = StorageLocation::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
    $this->department = Department::create(['name' => 'Curriculum Unit', 'code' => 'CID', 'is_active' => true]);
    $this->employee = Employee::create([
        'employee_number' => 'EMP-77', 'name' => 'Maria Reyes', 'department_id' => $this->department->id, 'is_active' => true,
    ]);
    $this->eqCategory = EquipmentCategory::create(['name' => 'ICT', 'is_active' => true]);
});

function formsEquipment(array $overrides = [], int $qty = 2): Equipment
{
    $equipment = Equipment::create(array_merge([
        'name' => 'Universal Wireless Microphone', 'property_number' => 'PROP-'.uniqid(), 'inventory_number' => 'INV-'.uniqid(),
        'equipment_category_id' => test()->eqCategory->id, 'type' => 'Wireless', 'quantity' => $qty,
        'life_span_years' => 2, 'date_acquired' => '2026-01-10', 'unit_cost' => 2500, 'property_class' => 'semi_expendable',
    ], $overrides));
    foreach (range(1, $qty) as $n) {
        $equipment->serialNumbers()->create(['serial_number' => 'MIC-'.uniqid().$n, 'status' => 'in_stock']);
    }

    return $equipment;
}

function formsIssueEquipment(Equipment $equipment, int $qty = 2): Issuance
{
    $response = test()->actingAs(test()->admin)->postJson('/issuances', [
        'issuance_type' => 'equipments', 'received_by' => test()->employee->id,
        'items' => [[
            'equipment_id' => $equipment->id, 'property_number' => '2026-PILV-0005', 'quantity' => $qty,
            'date_acquired' => '2026-01-10', 'serial_number_ids' => $equipment->serialNumbers()->pluck('id')->take($qty)->all(),
        ]],
    ])->assertCreated();

    return Issuance::findOrFail($response->json('id'));
}

function formsSample(string $url, string $file): void
{
    $dir = getenv('FORM_SAMPLES_DIR');
    if (! $dir) {
        return;
    }
    $response = test()->actingAs(test()->admin)->get($url)->assertOk();
    file_put_contents(rtrim($dir, '/\\').'/'.$file, $response->getContent());
}

test('ICS sample arithmetic: 2 x 2,500.00 = 5,000.00, grouped on one line', function () {
    $issuance = formsIssueEquipment(formsEquipment());
    $ics = app(FormService::class)->ics($issuance);

    expect($ics['lines'])->toHaveCount(1)
        ->and($ics['lines'][0]['quantity'])->toBe(2)
        ->and($ics['lines'][0]['unit_cost'])->toBe(2500.0)
        ->and($ics['lines'][0]['total_cost'])->toBe(5000.0)
        ->and($ics['lines'][0]['item_numbers'])->toBe(['2026-PILV-0005'])
        ->and($ics['lines'][0]['useful_life'])->toBe('2 years')
        ->and($ics['entity_name'])->toBe('SDO IRIGA CITY')
        ->and($ics['received_by']['name'])->toBe('Maria Reyes')
        ->and($ics['received_by']['designation'])->toBe('Curriculum Unit');

    formsSample("/forms/ics/{$issuance->id}/pdf", 'ics-draft.pdf');
});

test('ICS lists semi-expendable property only', function () {
    $issuance = formsIssueEquipment(formsEquipment(['property_class' => null]));

    $this->actingAs($this->admin)->get("/forms/ics/{$issuance->id}/pdf")
        ->assertStatus(422)
        ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_contains($m, 'Semi-expendable'))->etc());
});

test('issued ICS reprints are identical after master records and signatories change', function () {
    $equipment = formsEquipment();
    $issuance = formsIssueEquipment($equipment);
    $key = app(FormService::class)->ics($issuance)['lines'][0]['key'];

    $issued = $this->actingAs($this->admin)->postJson("/forms/ics/{$issuance->id}/issue", [
        'entity_name' => 'SDO IRIGA CITY', 'fund_cluster' => '01-Regular Fund',
        'lines' => [['key' => $key, 'unit' => 'piece', 'useful_life' => '2 years']],
    ])->assertCreated()->json();
    expect($issued['document_number'])->toBe('ICS-'.now()->year.'-0001');

    $first = $this->actingAs($this->admin)->get("/forms/ics/{$issuance->id}/xlsx")->assertOk()->getContent();
    formsSample("/forms/ics/{$issuance->id}/pdf", 'ics-issued.pdf');

    // Change everything the form was built from.
    $equipment->update(['unit_cost' => 9999, 'name' => 'Renamed Microphone', 'life_span_years' => 9]);
    $this->employee->update(['name' => 'Someone Else']);
    Setting::setValue('form_entity_name', 'CHANGED ENTITY', 'forms');
    $this->admin->update(['name' => 'Renamed Admin']);

    $payload = OfficeFormDocument::firstOrFail()->payload;
    expect($payload['lines'][0]['unit'])->toBe('piece')
        ->and($payload['lines'][0]['total_cost'])->toEqual(5000)
        ->and($payload['lines'][0]['description'])->toContain('Universal Wireless Microphone')
        ->and($payload['entity_name'])->toBe('SDO IRIGA CITY')
        ->and($payload['received_by']['name'])->toBe('Maria Reyes')
        ->and($payload['received_from']['name'])->toBe('Forms Admin');

    // Issuing again returns the same document; the locked copy cannot be edited.
    $this->actingAs($this->admin)->postJson("/forms/ics/{$issuance->id}/issue", [
        'entity_name' => 'X', 'lines' => [['key' => $key, 'unit' => 'box']],
    ])->assertOk()->assertJsonPath('already_issued', true);
    expect(OfficeFormDocument::firstOrFail()->update(['document_number' => 'TAMPERED']))->toBeFalse()
        ->and(OfficeFormDocument::firstOrFail()->document_number)->toBe($issued['document_number']);

    // Reprint uses the saved copy (sheet XML carries the original values).
    $second = $this->actingAs($this->admin)->get("/forms/ics/{$issuance->id}/xlsx")->assertOk()->getContent();
    $sheet = fn ($bin) => shell_exec('python -c "import zipfile,sys;print(zipfile.ZipFile(sys.argv[1]).read(\'xl/worksheets/sheet1.xml\').decode())" '.escapeshellarg(tap(tempnam(sys_get_temp_dir(), 'x'), fn ($p) => file_put_contents($p, $bin))));
    $firstXml = $sheet($first);
    $secondXml = $sheet($second);
    expect($secondXml)->toContain('Maria Reyes')->toContain('SDO IRIGA CITY')->not->toContain('Someone Else')->not->toContain('CHANGED ENTITY')
        ->and(preg_replace('/Issued copy.*?<\/t>/', '', $secondXml))->toBe(preg_replace('/Issued copy.*?<\/t>/', '', $firstXml));
});

test('Local RIV: blank unit cost stays blank, zero prints zero, issuing assigns a number', function () {
    $paper = Item::create([
        'item_name' => 'Bond Paper A4', 'category_id' => $this->category->id, 'unit_id' => $this->unit->id,
        'location_id' => $this->location->id, 'minimum_stock' => 1, 'reference_stock' => 20, 'current_stock' => 0,
        'status' => 'active', 'item_number' => 'ITEM-A4',
    ]);
    $free = Item::create([
        'item_name' => 'Free Sample', 'category_id' => $this->category->id, 'unit_id' => $this->unit->id,
        'location_id' => $this->location->id, 'minimum_stock' => 1, 'reference_stock' => 20, 'current_stock' => 0,
        'status' => 'active', 'item_number' => 'ITEM-FREE', 'unit_cost' => 0,
    ]);
    app(\App\Services\InventoryService::class)->receiveStock($paper, 10, $this->admin, 'receipt');
    app(\App\Services\InventoryService::class)->receiveStock($free, 10, $this->admin, 'receipt');
    $movementsBefore = StockMovement::count();

    $issuance = Issuance::findOrFail($this->actingAs($this->admin)->postJson('/issuances', [
        'issuance_type' => 'items', 'received_by' => $this->employee->id,
        'items' => [['item_id' => $paper->id, 'quantity' => 3], ['item_id' => $free->id, 'quantity' => 2]],
    ])->assertCreated()->json('id'));

    $riv = app(FormService::class)->riv($issuance);
    expect($riv['lines'][0]['unit_cost'])->toBeNull()
        ->and($riv['lines'][0]['total_cost'])->toBeNull()
        ->and($riv['lines'][1]['unit_cost'])->toBe(0.0)
        ->and($riv['lines'][1]['total_cost'])->toBe(0.0)
        ->and($riv['requested_by']['name'])->toBe('Maria Reyes')
        ->and($riv['approved_by']['name'])->toBe('Approver One');

    $this->actingAs($this->admin)->postJson("/forms/riv/{$issuance->id}/issue")
        ->assertCreated()->assertJsonPath('document_number', 'RIV-'.now()->year.'-0001');
    formsSample("/forms/riv/{$issuance->id}/pdf", 'riv-issued.pdf');
    formsSample("/forms/riv/{$issuance->id}/xlsx", 'riv-issued.xlsx');

    // Generating and issuing forms never posts stock movements.
    expect(StockMovement::count())->toBe($movementsBefore + 2);
});

test('IIRUP lists semi-expendable disposal cases with Accounting values and validates disposal quantities', function () {
    $semi = formsEquipment(['name' => 'Electric Fan', 'type' => 'Stand fan', 'unit_cost' => 1200], 3);
    $ppe = formsEquipment(['name' => 'Generator', 'property_class' => 'ppe', 'unit_cost' => 90000], 1);

    $open = function (Equipment $equipment, int $index) {
        return $this->actingAs($this->admin)->postJson('/equipment-status/cases', [
            'origin' => 'stock', 'equipment_id' => $equipment->id,
            'serial_number_id' => $equipment->serialNumbers()->orderBy('id')->skip($index)->value('id'),
            'status' => 'for_disposal', 'reason' => 'Beyond repair', 'event_date' => now()->toDateString(), 'reference' => 'INSP-1',
        ])->assertCreated()->json('case.case_id');
    };
    $caseA = $open($semi, 0);
    $caseB = $open($semi, 1);
    $open($ppe, 0);

    $values = fn (array $extra) => array_merge(['reason' => 'Values from Accounting', 'event_date' => now()->toDateString(), 'reference' => 'ACCTG-9'], $extra);
    // Quantities cannot exceed the reported quantity; Others needs a description; OR needs a sale.
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$caseA}/iirup-values", $values(['disposal_sale_qty' => 1, 'disposal_destruction_qty' => 1]))->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$caseA}/iirup-values", $values(['disposal_others_qty' => 1]))->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$caseA}/iirup-values", $values(['or_number' => 'OR-1']))->assertStatus(422);

    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$caseA}/iirup-values", $values([
        'accumulated_impairment' => 0, 'carrying_amount' => 150.5, 'disposal_sale_qty' => 1,
        'appraised_value' => 200, 'or_number' => 'OR-12345', 'sale_amount' => 180,
    ]))->assertOk();
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$caseB}/iirup-values", $values([
        'disposal_others_qty' => 1, 'disposal_others_specify' => 'Donation',
    ]))->assertOk();

    $iirup = app(FormService::class)->iirup();
    expect($iirup['lines'])->toHaveCount(2)
        ->and($iirup['excluded_count'])->toBe(1)
        ->and($iirup['total_cost'])->toBe(2400.0)
        ->and($iirup['lines'][0]['impairment'])->toBe(0.0)
        ->and($iirup['lines'][0]['carrying_amount'])->toBe(150.5)
        ->and($iirup['lines'][0]['disposal_total'])->toBe(1)
        ->and($iirup['lines'][0]['or_number'])->toBe('OR-12345')
        ->and($iirup['lines'][1]['impairment'])->toBeNull()
        ->and($iirup['lines'][1]['others_specify'])->toBe('Donation')
        ->and($iirup['lines'][1]['sale_amount'])->toBeNull();

    // Recording IIRUP values does not change the unit status.
    $this->actingAs($this->admin)->getJson('/equipment-status/summary')->assertJsonPath('counts.for_disposal', 3);

    formsSample('/forms/iirup/pdf', 'iirup.pdf');
    formsSample('/forms/iirup/xlsx', 'iirup.xlsx');
});

test('long descriptions and many lines flow onto continuation pages', function () {
    $items = collect(range(1, 40))->map(fn ($n) => tap(Item::create([
        'item_name' => "Supply item {$n} ".str_repeat('with a very long description ', 4), 'category_id' => $this->category->id,
        'unit_id' => $this->unit->id, 'location_id' => $this->location->id, 'minimum_stock' => 1, 'reference_stock' => 20,
        'current_stock' => 0, 'status' => 'active', 'item_number' => "ITEM-L{$n}", 'unit_cost' => 12.5,
    ]), fn ($item) => app(\App\Services\InventoryService::class)->receiveStock($item, 5, $this->admin, 'receipt')));

    $issuance = Issuance::findOrFail($this->actingAs($this->admin)->postJson('/issuances', [
        'issuance_type' => 'items', 'received_by' => $this->employee->id,
        'items' => $items->map(fn ($item) => ['item_id' => $item->id, 'quantity' => 2])->all(),
    ])->assertCreated()->json('id'));

    $pdf = $this->actingAs($this->admin)->get("/forms/riv/{$issuance->id}/pdf")->assertOk()->getContent();
    expect(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBeGreaterThan(1);
    formsSample("/forms/riv/{$issuance->id}/pdf", 'riv-long.pdf');
});

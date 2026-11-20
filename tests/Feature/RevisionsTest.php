<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentSerialNumber;
use App\Models\Issuance;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\User;
use App\Support\StockStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::setValue('allow_negative_stock', 'false', 'inventory');
    Setting::setValue('department_user_pages', json_encode(['dashboard']), 'permissions');

    $this->admin = User::create([
        'name' => 'Test Admin', 'email' => 'admin@test.local', 'password' => 'password',
        'role' => UserRole::Admin, 'is_active' => true,
    ]);
    $this->category = Category::create(['name' => 'Office', 'is_active' => true]);
    $this->unit = Unit::create(['name' => 'Pieces', 'abbreviation' => 'pcs']);
    $this->location = StorageLocation::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
    $this->department = Department::create(['name' => 'Records Unit', 'code' => 'REC', 'is_active' => true]);
    $this->otherDepartment = Department::create(['name' => 'Cashier', 'code' => 'CASH', 'is_active' => true]);
    $this->employee = Employee::create([
        'employee_number' => 'EMP-1001', 'name' => 'Juan Dela Cruz',
        'department_id' => $this->department->id, 'is_active' => true,
    ]);
    $this->eqCategory = EquipmentCategory::create(['name' => 'ICT', 'is_active' => true]);
});

function makeItem(array $overrides = []): Item
{
    return Item::create(array_merge([
        'item_name' => 'Bond Paper', 'category_id' => test()->category->id, 'unit_id' => test()->unit->id,
        'location_id' => test()->location->id, 'minimum_stock' => 1, 'reference_stock' => 20,
        'current_stock' => 0, 'status' => 'active', 'item_number' => 'ITEM-'.uniqid(),
    ], $overrides));
}

function receive(Item $item, int $qty): void
{
    app(\App\Services\InventoryService::class)->receiveStock($item, $qty, test()->admin, 'test receipt');
}

function makeEquipment(int $qty = 2, array $serials = ['SN-A', 'SN-B'], array $overrides = []): Equipment
{
    $equipment = Equipment::create(array_merge([
        'name' => 'Laptop', 'property_number' => 'PROP-'.uniqid(), 'inventory_number' => 'INV-'.uniqid(),
        'equipment_category_id' => test()->eqCategory->id, 'type' => 'i5', 'quantity' => $qty,
        'life_span_years' => 5, 'date_acquired' => '2026-01-15',
    ], $overrides));

    foreach ($serials as $serial) {
        $equipment->serialNumbers()->create(['serial_number' => $serial, 'status' => 'in_stock']);
    }

    return $equipment;
}

function issueItemPayload(Item $item, int $qty, array $extra = []): array
{
    return array_merge([
        'issuance_type' => 'items',
        'received_by' => test()->employee->id,
        'items' => [['item_id' => $item->id, 'quantity' => $qty]],
    ], $extra);
}

// ---------------------------------------------------------------- Revision 3
test('stock status boundaries: zero, exactly 50%, just above 50%', function () {
    expect(StockStatus::classify(0, 20, 1))->toBe(StockStatus::OUT)
        ->and(StockStatus::classify(10, 20, 1))->toBe(StockStatus::LOW)
        ->and(StockStatus::classify(11, 20, 1))->toBe(StockStatus::IN)
        ->and(StockStatus::classify(1, 20, 1))->toBe(StockStatus::LOW)
        // odd reference: 50% of 15 = 7.5 -> 7 low, 8 in stock
        ->and(StockStatus::classify(7, 15, 1))->toBe(StockStatus::LOW)
        ->and(StockStatus::classify(8, 15, 1))->toBe(StockStatus::IN)
        // legacy item without reference: falls back to 2 x minimum (= previous behaviour)
        ->and(StockStatus::classify(5, null, 5))->toBe(StockStatus::LOW)
        ->and(StockStatus::classify(6, null, 5))->toBe(StockStatus::IN);

    $out = makeItem(['current_stock' => 0]);
    $half = makeItem(['current_stock' => 10]);
    $above = makeItem(['current_stock' => 11]);

    // SQL filter used by lists, dashboards, alerts and exports agrees with the PHP rule.
    expect(Item::query()->withStockStatus(StockStatus::OUT)->pluck('id')->all())->toBe([$out->id])
        ->and(Item::query()->withStockStatus(StockStatus::LOW)->pluck('id')->all())->toBe([$half->id])
        ->and(Item::query()->withStockStatus(StockStatus::IN)->pluck('id')->all())->toBe([$above->id])
        ->and($half->fresh()->stock_status)->toBe(StockStatus::LOW)
        ->and($above->fresh()->stock_status)->toBe(StockStatus::IN);

    $this->actingAs($this->admin)->getJson('/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('low_stock_items', 1)
        ->assertJsonPath('out_of_stock', 1);
});

test('low stock alert fires at exactly 50% and not above', function () {
    $item = makeItem(['current_stock' => 0]);
    receive($item, 11);
    expect(\App\Models\ObimsNotification::query()->where('type', 'low_stock')->count())->toBe(0);

    app(\App\Services\InventoryService::class)->issueStock($item->fresh(), 1, $this->admin, 'test');
    expect(\App\Models\ObimsNotification::query()->where('type', 'low_stock')->count())->toBe(1);
});

// ---------------------------------------------------------------- Revision 4
test('minimum stock cannot be set below 1', function () {
    $payload = [
        'item_name' => 'Ink', 'category_id' => $this->category->id, 'unit_id' => $this->unit->id,
        'location_id' => $this->location->id, 'minimum_stock' => 0, 'reference_stock' => 10,
    ];

    $this->actingAs($this->admin)->postJson('/items', $payload)->assertStatus(422)->assertJsonValidationErrors('minimum_stock');
    $this->actingAs($this->admin)->postJson('/items', [...$payload, 'minimum_stock' => 1])->assertCreated();

    $item = makeItem();
    $this->actingAs($this->admin)->putJson("/items/{$item->id}", [
        'item_name' => 'Bond Paper', 'category_id' => $this->category->id, 'unit_id' => $this->unit->id,
        'location_id' => $this->location->id, 'minimum_stock' => 0, 'reference_stock' => 20, 'status' => 'active',
    ])->assertStatus(422)->assertJsonValidationErrors('minimum_stock');
});

// ---------------------------------------------------------------- Revision 5
test('recipient is validated on the server and snapshot preserved', function () {
    $item = makeItem();
    receive($item, 10);

    // Free-typed name is no longer accepted.
    $this->actingAs($this->admin)->postJson('/issuances', [
        'issuance_type' => 'items', 'received_by_name' => 'Someone Typed', 'department_name' => 'Fake Unit',
        'items' => [['item_id' => $item->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('received_by');

    // Unknown and inactive employees are rejected.
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 1, ['received_by' => 999999]))
        ->assertStatus(422);
    $inactive = Employee::create(['employee_number' => 'EMP-2', 'name' => 'Inactive', 'department_id' => $this->department->id, 'is_active' => false]);
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 1, ['received_by' => $inactive->id]))
        ->assertStatus(422);

    // A tampered unit / name in the payload is ignored; the server derives them from the directory.
    $response = $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 2, [
        'department_id' => $this->otherDepartment->id, 'received_by_name' => 'Tampered', 'department_name' => 'Tampered Unit',
    ]))->assertCreated();

    $issuance = Issuance::findOrFail($response->json('id'));
    expect($issuance->department_id)->toBe($this->department->id)
        ->and($issuance->received_by_name)->toBe('Juan Dela Cruz')
        ->and($issuance->department_name)->toBe('Records Unit')
        ->and($issuance->recipient_employee_number)->toBe('EMP-1001')
        ->and($issuance->issued_by)->toBe($this->admin->id);

    // Later directory edits do not rewrite history.
    $this->actingAs($this->admin)->putJson("/employee-directory/{$this->employee->id}", [
        'employee_number' => 'EMP-1001', 'name' => 'Juan D. Cruz', 'department_id' => $this->otherDepartment->id,
    ])->assertOk();

    $fresh = $issuance->fresh();
    expect($fresh->recipient_display_name)->toBe('Juan Dela Cruz')
        ->and($fresh->unit_display_name)->toBe('Records Unit');
});

// ---------------------------------------------------------------- Revision 2 / 8: double submission
test('repeat submission with the same key does not issue twice', function () {
    $item = makeItem();
    receive($item, 10);
    $payload = issueItemPayload($item, 3, ['idempotency_key' => 'test-key-123', 'channel' => 'scan']);

    $first = $this->actingAs($this->admin)->postJson('/issuances', $payload)->assertCreated();
    $second = $this->actingAs($this->admin)->postJson('/issuances', $payload)->assertOk();

    expect($second->json('duplicate'))->toBeTrue()
        ->and($second->json('id'))->toBe($first->json('id'))
        ->and(Issuance::count())->toBe(1)
        ->and($item->fresh()->current_stock)->toBe(7)
        ->and(StockMovement::where('transaction_type', 'OUT')->count())->toBe(1);
});

test('scan issuance rejects unavailable supply items', function () {
    $item = makeItem();
    receive($item, 2);

    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 5, ['channel' => 'scan', 'idempotency_key' => 'k1']))
        ->assertStatus(422);
    $item->update(['status' => 'inactive']);
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 1, ['channel' => 'scan', 'idempotency_key' => 'k2']))
        ->assertStatus(422);
    expect($item->fresh()->current_stock)->toBe(2);

    $this->actingAs($this->admin)->getJson('/scan-issue/lookup?code='.$item->item_number)
        ->assertOk()->assertJsonPath('type', 'item')->assertJsonPath('available', false);
});

// ---------------------------------------------------------------- Revision 7: restricted equipment
test('equipment in repair, disposal or disposed states cannot be issued', function () {
    $equipment = makeEquipment(2, ['SN-A', 'SN-B']);
    $serialA = $equipment->serialNumbers()->where('serial_number', 'SN-A')->first();

    $open = $this->actingAs($this->admin)->postJson('/equipment-status/cases', [
        'origin' => 'stock', 'equipment_id' => $equipment->id, 'serial_number_id' => $serialA->id,
        'status' => 'for_repair', 'reason' => 'Will not power on', 'event_date' => now()->toDateString(), 'reference' => 'JO-001',
    ])->assertCreated();

    expect($equipment->fresh()->quantity)->toBe(1)
        ->and($serialA->fresh()->status)->toBe('for_repair');

    $this->actingAs($this->admin)->postJson('/issuances', [
        'issuance_type' => 'equipments', 'received_by' => $this->employee->id,
        'items' => [[
            'equipment_id' => $equipment->id, 'property_number' => 'NEW-PROP-1', 'quantity' => 1,
            'date_acquired' => '2026-01-15', 'serial_number_ids' => [$serialA->id],
        ]],
    ])->assertStatus(422)->assertJson(fn ($json) => $json->where('message', fn ($m) => str_contains($m, 'For Repair'))->etc());

    $this->actingAs($this->admin)->getJson('/scan-issue/lookup?code=SN-A')
        ->assertOk()->assertJsonPath('available', false)->assertJsonPath('unit_status', 'for_repair');

    // Cannot return to Available without a serviceability check.
    $caseId = $open->json('case.case_id');
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$caseId}/return-to-available", [
        'reason' => 'done', 'event_date' => now()->toDateString(), 'reference' => 'JO-001',
    ])->assertStatus(422);

    // Repair -> passed check -> Available.
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$caseId}/repair", ['reason' => 'Replaced PSU', 'event_date' => now()->toDateString(), 'reference' => 'JO-001'])->assertOk();
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$caseId}/serviceability", ['passed' => true, 'reason' => 'Boots and passes diagnostics', 'event_date' => now()->toDateString(), 'reference' => 'QA-1'])->assertOk();
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$caseId}/return-to-available", ['reason' => 'Clear for issuance', 'event_date' => now()->toDateString(), 'reference' => 'QA-1'])->assertOk();

    expect($equipment->fresh()->quantity)->toBe(2)
        ->and($serialA->fresh()->status)->toBe('in_stock');

    // Disposal requires approval before Disposed.
    $serialB = $equipment->serialNumbers()->where('serial_number', 'SN-B')->first();
    $disposal = $this->actingAs($this->admin)->postJson('/equipment-status/cases', [
        'origin' => 'stock', 'equipment_id' => $equipment->id, 'serial_number_id' => $serialB->id,
        'status' => 'for_disposal', 'reason' => 'Beyond economic repair', 'event_date' => now()->toDateString(), 'reference' => 'IIRUP-1',
    ])->assertCreated();
    $disposalId = $disposal->json('case.case_id');
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$disposalId}/dispose", ['reason' => 'Sold', 'event_date' => now()->toDateString(), 'reference' => 'OR-1'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$disposalId}/disposal-assessment", ['reason' => 'Mainboard failure', 'event_date' => now()->toDateString(), 'reference' => 'IIRUP-1'])->assertOk();
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$disposalId}/disposal-approval", ['approval_status' => 'approved', 'approved_by_name' => 'SDS', 'reason' => 'Approved', 'event_date' => now()->toDateString(), 'reference' => 'MEMO-1'])->assertOk();
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$disposalId}/dispose", ['reason' => 'Sold', 'event_date' => now()->toDateString(), 'reference' => 'OR-1', 'method' => 'Sale'])->assertOk();

    expect($serialB->fresh()->status)->toBe('disposed');
    $counts = $this->actingAs($this->admin)->getJson('/equipment-status/summary')->assertOk()->json('counts');
    expect($counts['available'])->toBe(1)->and($counts['disposed'])->toBe(1)->and($counts['for_repair'])->toBe(0);
});

test('active warranty routes the case for review before repair', function () {
    $equipment = makeEquipment(1, ['SN-W'], ['warranty_end_date' => now()->addYear()->toDateString(), 'warranty_start_date' => '2026-01-01']);
    $serial = $equipment->serialNumbers()->first();

    $case = $this->actingAs($this->admin)->postJson('/equipment-status/cases', [
        'origin' => 'stock', 'equipment_id' => $equipment->id, 'serial_number_id' => $serial->id,
        'status' => 'for_repair', 'reason' => 'Screen flicker', 'event_date' => now()->toDateString(), 'reference' => 'JO-9',
    ])->assertCreated()->json('case');

    expect($case['warranty_active'])->toBeTrue();
    $this->actingAs($this->admin)->getJson('/equipment-status/summary')->assertJsonPath('counts.warranty_active', 1);

    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$case['case_id']}/repair", ['reason' => 'Fixed', 'event_date' => now()->toDateString(), 'reference' => 'JO-9'])
        ->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$case['case_id']}/warranty-decision", ['outcome' => 'claim_denied', 'reason' => 'Physical damage not covered', 'event_date' => now()->toDateString(), 'reference' => 'CLAIM-77'])
        ->assertOk();
    $this->actingAs($this->admin)->postJson("/equipment-status/cases/{$case['case_id']}/repair", ['reason' => 'Fixed in-house', 'event_date' => now()->toDateString(), 'reference' => 'JO-9'])
        ->assertOk();
});

// ---------------------------------------------------------------- Revision 8
test('partial delivery posts only accepted quantity and cannot post twice', function () {
    $item = makeItem();

    $inspection = $this->actingAs($this->admin)->postJson('/delivery-inspections', [
        'delivery_reference' => 'DR-100', 'purchase_reference' => 'PO-55', 'supplier' => 'ACME',
        'delivery_date' => now()->toDateString(), 'inspection_date' => now()->toDateString(), 'inspector_name' => 'Inspector A',
        'lines' => [[
            'line_type' => 'item', 'item_id' => $item->id, 'expected_quantity' => 10, 'delivered_quantity' => 6,
            'accepted_quantity' => 5, 'rejected_quantity' => 1, 'condition' => 'mixed', 'discrepancy_remarks' => '4 short, 1 torn ream',
        ]],
    ])->assertCreated()->json();

    expect($inspection['is_partial'])->toBeTrue()->and($item->fresh()->current_stock)->toBe(0);

    $this->actingAs($this->admin)->postJson("/delivery-inspections/{$inspection['id']}/post")->assertOk()->assertJsonPath('already_posted', false);
    $this->actingAs($this->admin)->postJson("/delivery-inspections/{$inspection['id']}/post")->assertOk()->assertJsonPath('already_posted', true);

    expect($item->fresh()->current_stock)->toBe(5)
        ->and((int) StockMovement::where('item_id', $item->id)->where('transaction_type', 'IN')->sum('quantity'))->toBe(5)
        ->and(\App\Models\InventoryPosting::count())->toBe(1);

    // Posted inspections can no longer be edited.
    $this->actingAs($this->admin)->putJson("/delivery-inspections/{$inspection['id']}", [
        'delivery_reference' => 'DR-100', 'inspection_date' => now()->toDateString(), 'inspector_name' => 'X',
        'lines' => [['line_type' => 'item', 'item_id' => $item->id, 'expected_quantity' => 10, 'delivered_quantity' => 10, 'accepted_quantity' => 10, 'rejected_quantity' => 0, 'condition' => 'good']],
    ])->assertStatus(422);

    // accepted + rejected must equal delivered
    $this->actingAs($this->admin)->postJson('/delivery-inspections', [
        'delivery_reference' => 'DR-101', 'inspection_date' => now()->toDateString(), 'inspector_name' => 'Inspector A',
        'lines' => [['line_type' => 'item', 'item_id' => $item->id, 'expected_quantity' => 5, 'delivered_quantity' => 5, 'accepted_quantity' => 3, 'rejected_quantity' => 0, 'condition' => 'good']],
    ])->assertStatus(422);
});

// ---------------------------------------------------------------- Revision 9
test('issuance requires date acquired and returns require a valid returned date', function () {
    $item = makeItem(['current_stock' => 5]); // no receipt on record => no auto-fill
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 1))
        ->assertStatus(422)->assertJson(fn ($json) => $json->where('message', fn ($m) => str_contains($m, 'Date Acquired'))->etc());
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 1, ['items' => [['item_id' => $item->id, 'quantity' => 1, 'date_acquired' => '2026-02-01']]]))
        ->assertCreated();

    $equipment = makeEquipment(1, ['SN-D'], ['date_acquired' => null]);
    $serial = $equipment->serialNumbers()->first();
    // Single-unit record: its official property number is used (feedback item 1), none is typed.
    $line = ['equipment_id' => $equipment->id, 'quantity' => 1, 'serial_number_ids' => [$serial->id]];
    $base = ['issuance_type' => 'equipments', 'received_by' => $this->employee->id, 'use_custom_date' => true, 'issued_date' => '2026-03-01'];

    $this->actingAs($this->admin)->postJson('/issuances', [...$base, 'items' => [$line]])->assertStatus(422);
    $issued = $this->actingAs($this->admin)->postJson('/issuances', [...$base, 'items' => [[...$line, 'date_acquired' => '2026-02-15']]])->assertCreated();
    expect($equipment->fresh()->date_acquired->toDateString())->toBe('2026-02-15')
        ->and($issued->json('details.0.property_number'))->toBe($equipment->property_number);

    $detailId = $issued->json('details.0.id');
    $return = [
        'issuance_detail_id' => $detailId, 'department_id' => $this->department->id, 'borrower_employee_id' => $this->employee->id,
        'quantity' => 1, 'serial_number_ids' => [$serial->id], 'condition' => 'damaged',
    ];
    $this->actingAs($this->admin)->postJson('/returns', $return)->assertStatus(422)->assertJsonValidationErrors('date_returned');
    $this->actingAs($this->admin)->postJson('/returns', [...$return, 'date_returned' => '2026-02-20'])->assertStatus(422);
    $saved = $this->actingAs($this->admin)->postJson('/returns', [...$return, 'date_returned' => '2026-03-05'])->assertCreated();
    expect($saved->json('condition_label'))->toBe('Unserviceable');
});

// ---------------------------------------------------------------- Monthly consumption report fix
test('monthly consumption counts each issuance once for the department that received it', function () {
    $item = makeItem();
    receive($item, 100);
    $records = Employee::create(['employee_number' => 'EMP-3', 'name' => 'Cashier Staff', 'department_id' => $this->otherDepartment->id, 'is_active' => true]);

    // Same item issued three times to two departments.
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 10))->assertCreated();
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 5))->assertCreated();
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 7, ['received_by' => $records->id]))->assertCreated();

    // Legacy issuance with only a typed department name (no department link).
    $legacy = Issuance::create([
        'issuance_number' => 'ISS-LEGACY-1', 'department_id' => null, 'department_name' => 'Legacy Unit',
        'issued_by' => $this->admin->id, 'received_by_name' => 'Old Record', 'issued_date' => now(),
    ]);
    $legacy->details()->create(['item_id' => $item->id, 'quantity' => 3]);

    $rows = app(\App\Services\ReportService::class)->monthlyConsumption()
        ->mapWithKeys(fn ($row) => [$row->department => (int) $row->total])
        ->all();

    expect($rows)->toBe([
        'Records Unit' => 15,
        'Cashier' => 7,
        'Legacy Unit' => 3,
    ])
        // The three tracked issuances reconcile with the ledger's OUT movements.
        ->and((int) StockMovement::where('transaction_type', 'OUT')->sum('quantity'))->toBe(22);

    // Other years are excluded.
    expect(app(\App\Services\ReportService::class)->monthlyConsumption(now()->year - 1))->toBeEmpty();
});

// ---------------------------------------------------------------- Revision 10
test('excel exports honour filters and permissions', function () {
    $item = makeItem();
    receive($item, 10);
    // Backdated before the only receipt: Date Acquired cannot be auto-filled, so it is entered.
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 2, [
        'use_custom_date' => true, 'issued_date' => now()->subDays(10)->toDateString(),
        'items' => [['item_id' => $item->id, 'quantity' => 2, 'date_acquired' => now()->subDays(30)->toDateString()]],
    ]))->assertCreated();
    $this->actingAs($this->admin)->postJson('/issuances', issueItemPayload($item, 3))->assertCreated();

    $response = $this->actingAs($this->admin)->get('/reports/issuance/xlsx?from='.now()->subDays(2)->toDateString())
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->getContent());
    $xml = shell_exec('python -c "import zipfile,sys;print(zipfile.ZipFile(sys.argv[1]).read(\'xl/worksheets/sheet1.xml\').decode())" '.escapeshellarg($path));
    @unlink($path);

    expect($xml)->toContain('Received By')
        // only the in-range issuance (qty 3) is exported, as a numeric cell
        ->and(substr_count($xml, 'ISS-'))->toBe(1)
        ->and($xml)->toMatch('/<c r="M\d+"><v>3<\/v><\/c>/')
        // dates are numeric serials with the date style
        ->and($xml)->toMatch('/<c r="B\d+" s="3"><v>\d+\.\d+<\/v><\/c>/');

    $employeeUser = User::create(['name' => 'Emp', 'email' => 'emp@test.local', 'password' => 'password', 'role' => UserRole::DepartmentUser, 'is_active' => true]);
    $this->actingAs($employeeUser)->get('/reports/issuance/xlsx')->assertForbidden();
});

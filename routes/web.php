<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminSetupController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeletedDataController;
use App\Http\Controllers\DeliveryInspectionController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDirectoryController;
use App\Http\Controllers\EquipmentDashboardController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\EquipmentCategoryController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\IssuanceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ItemReturnController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PersonLookupController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\PredictiveInventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanIssueController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StorageLocationController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => redirect('/'));

Route::get('/about', fn (PageController $pages) => $pages->show('about'));

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    // Forgot password: emailed 6-digit code or one-time link.
    Route::get('/reset-password', [PasswordResetController::class, 'show']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->middleware('throttle:5,1');
    Route::post('/forgot-password/verify', [PasswordResetController::class, 'checkCode'])->middleware('throttle:10,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');
});

// First-time setup: only while no administrator account exists (see RedirectToAdminSetup).
Route::get('/setup', [AdminSetupController::class, 'show']);
Route::post('/setup', [AdminSetupController::class, 'store'])->middleware('throttle:10,1');

Route::get('/', function (PageController $pages) {
    if (Auth::check()) {
        return $pages->show('dashboard');
    }

    return $pages->show('login');
})->name('login');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'updatePassword']);

    Route::get('/search', GlobalSearchController::class);
    Route::get('/notifications/list', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->whereNumber('notification');

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/recent-movements', [DashboardController::class, 'recentMovements']);
    Route::get('/dashboard/recent-issuance', [DashboardController::class, 'recentIssuance']);
    Route::get('/dashboard/recent-returns', [DashboardController::class, 'recentReturns']);
    Route::get('/dashboard/charts', [DashboardController::class, 'charts']);

    Route::get('/departments/list', [DepartmentController::class, 'index']);
    Route::get('/employees/list', [EmployeeController::class, 'index']);
    Route::get('/categories/list', [CategoryController::class, 'index']);
    Route::get('/equipment-categories/list', [EquipmentCategoryController::class, 'index']);
    Route::get('/equipments/list', [EquipmentController::class, 'index']);
    Route::get('/units/list', [UnitController::class, 'index']);
    Route::get('/locations/list', [StorageLocationController::class, 'index']);

    // Shared item lookups for inventory / transaction pages employees may be granted.
    Route::middleware('staff_or_page:items,stock.operations,stock.registration,issuance,returns,inventory.predictions,reports,person-lookup,catalog-details,scan-issue,delivery-inspection,equipment-dashboard')->group(function () {
        Route::get('/items/barcode/{barcode}', [ItemController::class, 'findByBarcode']);
        Route::get('/items/list', [ItemController::class, 'index']);
        Route::get('/items/{item}', [ItemController::class, 'show'])->whereNumber('item');
    });

    Route::middleware('staff_or_page:items,stock.registration')->group(function () {
        Route::post('/items', [ItemController::class, 'store']);
        Route::put('/items/{item}', [ItemController::class, 'update'])->whereNumber('item');
        Route::delete('/items/{item}', [ItemController::class, 'destroy'])->whereNumber('item');
    });

    Route::middleware('staff_or_page:reports')->group(function () {
        Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf']);
        Route::get('/reports/{type}/xlsx', [ReportController::class, 'xlsx']);
        Route::get('/reports/{type}', [ReportController::class, 'show']);
    });

    // Revision 6 & 10: Local RIV / ICS per issuance, IIRUP for unserviceable property.
    Route::middleware('staff_or_page:issuance,reports,person-lookup,scan-issue')->group(function () {
        Route::get('/forms/{form}/{issuance}', [FormController::class, 'status'])
            ->whereIn('form', ['riv', 'ics'])
            ->whereNumber('issuance');
        Route::get('/forms/{form}/{issuance}/{format}', [FormController::class, 'issuanceForm'])
            ->whereIn('form', ['riv', 'ics'])
            ->whereIn('format', ['pdf', 'xlsx'])
            ->whereNumber('issuance');
    });

    Route::post('/forms/{form}/{issuance}/issue', [FormController::class, 'issue'])
        ->whereIn('form', ['riv', 'ics'])
        ->whereNumber('issuance')
        ->middleware('staff_or_page:issuance,scan-issue');

    Route::get('/forms/iirup/{format}', [FormController::class, 'iirup'])
        ->whereIn('format', ['pdf', 'xlsx'])
        ->middleware('staff_or_page:reports,equipment-dashboard');
    Route::get('/forms/iirup-documents', [FormController::class, 'iirupDocuments'])
        ->middleware('staff_or_page:reports,equipment-dashboard');
    Route::get('/forms/iirup-documents/{document}/{format}', [FormController::class, 'iirupDocument'])
        ->whereNumber('document')
        ->whereIn('format', ['pdf', 'xlsx'])
        ->middleware('staff_or_page:reports,equipment-dashboard');
    Route::post('/forms/iirup/issue', [FormController::class, 'issueIirup'])
        ->middleware('role:admin,supply_officer');

    // Feedback item 5: the default low-stock percentage used for items without their own.
    Route::get('/stock-rule', fn () => response()->json([
        'default_percent' => \App\Support\StockStatus::defaultPercent(),
    ]));

    // Feedback item 4: organization-defined document numbering.
    Route::get('/document-numbering', [\App\Http\Controllers\DocumentNumberController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/document-numbering/preview', [\App\Http\Controllers\DocumentNumberController::class, 'preview']);
        Route::put('/document-numbering/{type}', [\App\Http\Controllers\DocumentNumberController::class, 'update']);
    });
    Route::put('/equipments/{equipment}/units/{unit}', [EquipmentController::class, 'updateUnit'])
        ->whereNumber('equipment')
        ->whereNumber('unit')
        ->middleware('role:admin,supply_officer');

    // Revision 5: unit employee directory.
    Route::get('/employee-directory/list', [EmployeeDirectoryController::class, 'index'])
        ->middleware('staff_or_page:employee-directory,issuance,scan-issue,returns');

    Route::middleware('staff_or_page:employee-directory')->group(function () {
        Route::post('/employee-directory', [EmployeeDirectoryController::class, 'store']);
        Route::put('/employee-directory/{employee}', [EmployeeDirectoryController::class, 'update'])->whereNumber('employee');
        Route::put('/employee-directory/{employee}/active', [EmployeeDirectoryController::class, 'setActive'])->whereNumber('employee');
    });

    // Revision 1 & 7: equipment dashboard + repair / warranty / disposal workflow.
    Route::middleware('staff_or_page:equipment-dashboard')->group(function () {
        Route::get('/equipment-status/summary', [EquipmentDashboardController::class, 'summary']);
        Route::get('/equipment-status/list', [EquipmentDashboardController::class, 'list']);
        Route::get('/equipment-status/cases/{case}', [EquipmentDashboardController::class, 'showCase'])->whereNumber('case');
    });

    Route::middleware('role:admin,supply_officer')->group(function () {
        Route::post('/equipment-status/cases', [EquipmentDashboardController::class, 'openCase']);
        Route::post('/equipment-status/cases/{case}/{action}', [EquipmentDashboardController::class, 'transition'])
            ->whereNumber('case')
            ->whereIn('action', ['warranty-decision', 'repair', 'serviceability', 'return-to-available', 'move-to-disposal', 'back-to-repair', 'disposal-assessment', 'disposal-approval', 'dispose', 'iirup-values']);
        Route::put('/equipments/{equipment}/warranty', [EquipmentDashboardController::class, 'updateWarranty'])->whereNumber('equipment');
    });

    // Revision 2: scan to issue (confirmation posts to /issuances).
    Route::get('/scan-issue/lookup', [ScanIssueController::class, 'lookup'])->middleware('staff_or_page:scan-issue');

    // Revision 8: delivery inspection.
    Route::middleware('staff_or_page:delivery-inspection')->group(function () {
        Route::get('/delivery-inspections/list', [DeliveryInspectionController::class, 'index']);
        Route::get('/delivery-inspections/{inspection}', [DeliveryInspectionController::class, 'show'])->whereNumber('inspection');
        Route::post('/delivery-inspections', [DeliveryInspectionController::class, 'store']);
        Route::put('/delivery-inspections/{inspection}', [DeliveryInspectionController::class, 'update'])->whereNumber('inspection');
        Route::post('/delivery-inspections/{inspection}/post', [DeliveryInspectionController::class, 'post'])->whereNumber('inspection');
        Route::delete('/delivery-inspections/{inspection}', [DeliveryInspectionController::class, 'destroy'])->whereNumber('inspection');
    });

    // Master-data mutations stay with admin / supply officer (Settings page).
    Route::middleware('role:admin,supply_officer')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);

        Route::post('/equipment-categories', [EquipmentCategoryController::class, 'store']);
        Route::put('/equipment-categories/{equipmentCategory}', [EquipmentCategoryController::class, 'update']);
        Route::delete('/equipment-categories/{equipmentCategory}', [EquipmentCategoryController::class, 'destroy']);
        Route::get('/equipment-categories/{equipmentCategory}', [EquipmentCategoryController::class, 'show']);

        Route::post('/units', [UnitController::class, 'store']);
        Route::put('/units/{unit}', [UnitController::class, 'update']);
        Route::delete('/units/{unit}', [UnitController::class, 'destroy']);
        Route::get('/units/{unit}', [UnitController::class, 'show']);

        Route::post('/locations', [StorageLocationController::class, 'store']);
        Route::put('/locations/{storageLocation}', [StorageLocationController::class, 'update']);
        Route::delete('/locations/{storageLocation}', [StorageLocationController::class, 'destroy']);
        Route::get('/locations/{storageLocation}', [StorageLocationController::class, 'show']);
    });

    Route::middleware('staff_or_page:stock.operations')->group(function () {
        Route::post('/stock/receive', [StockController::class, 'receive']);
        Route::post('/stock/adjust', [StockController::class, 'adjust']);
        Route::get('/stock/movements', [StockController::class, 'movements']);
        Route::post('/equipments/receive', [EquipmentController::class, 'receive']);
    });

    Route::middleware('staff_or_page:items,stock.operations,stock.registration,issuance,returns,person-lookup,catalog-details,scan-issue,delivery-inspection,equipment-dashboard')->group(function () {
        Route::get('/equipments/barcode/{barcode}', [EquipmentController::class, 'findByBarcode']);
        Route::get('/equipments/{equipment}/history', [EquipmentController::class, 'history'])->whereNumber('equipment');
        Route::get('/equipments/{equipment}', [EquipmentController::class, 'show'])->whereNumber('equipment');
    });

    Route::middleware('staff_or_page:stock.registration')->group(function () {
        Route::post('/equipments', [EquipmentController::class, 'store']);
        Route::put('/equipments/{equipment}', [EquipmentController::class, 'update'])->whereNumber('equipment');
        Route::delete('/equipments/{equipment}', [EquipmentController::class, 'destroy'])->whereNumber('equipment');
    });

    Route::put('/equipments/{equipment}/property-number', [EquipmentController::class, 'updatePropertyNumber'])
        ->middleware('role:admin,supply_officer')
        ->whereNumber('equipment');

    Route::put('/items/{item}/stock-number', [ItemController::class, 'updateStockNumber'])
        ->middleware('role:admin,supply_officer')
        ->whereNumber('item');

    Route::middleware('staff_or_page:issuance,person-lookup,reports,scan-issue')->group(function () {
        Route::get('/issuances/list', [IssuanceController::class, 'index']);
        Route::get('/issuances/outstanding-equipments', [IssuanceController::class, 'outstandingEquipments']);
        Route::get('/issuances/{issuance}', [IssuanceController::class, 'show'])->whereNumber('issuance');
    });

    Route::middleware('staff_or_page:issuance,scan-issue')->group(function () {
        Route::post('/issuances', [IssuanceController::class, 'store']);
    });

    Route::middleware('staff_or_page:returns,person-lookup,reports')->group(function () {
        Route::get('/returns/list', [ItemReturnController::class, 'index']);
        Route::get('/returns/returned-equipments', [ItemReturnController::class, 'returnedEquipments']);
    });

    Route::middleware('staff_or_page:returns')->group(function () {
        Route::post('/returns', [ItemReturnController::class, 'store']);
    });

    Route::middleware('staff_or_page:person-lookup,catalog-details')->group(function () {
        Route::get('/lookups/suggestions', [PersonLookupController::class, 'suggestions']);
    });

    Route::middleware('staff_or_page:person-lookup')->group(function () {
        Route::get('/lookups/by-person', [PersonLookupController::class, 'show']);
        Route::get('/lookups/by-item', [PersonLookupController::class, 'byItem']);
        Route::get('/lookups/by-equipment', [PersonLookupController::class, 'byEquipment']);
    });

    Route::middleware('staff_or_page:activity-logs')->group(function () {
        Route::get('/activity-logs/list', [ActivityLogController::class, 'index']);
    });

    Route::middleware('staff_or_page:inventory.predictions')->group(function () {
        Route::get('/inventory/predictions/list', [PredictiveInventoryController::class, 'index']);
        Route::get('/inventory/predictions/{item}/detail', [PredictiveInventoryController::class, 'show'])->whereNumber('item');
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
        Route::get('/departments/{department}', [DepartmentController::class, 'show']);

        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::get('/employees/{employee}', [EmployeeController::class, 'show']);

        Route::get('/settings/list', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);

        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::put('/permissions', [PermissionController::class, 'update']);

        Route::get('/users/list', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::get('/deleted-data/list', [DeletedDataController::class, 'index']);
        Route::post('/deleted-data/{type}/{id}/restore', [DeletedDataController::class, 'restore'])->whereNumber('id');
        Route::delete('/deleted-data/{type}/{id}/force', [DeletedDataController::class, 'forceDestroy'])->whereNumber('id');

        Route::get('/backups/list', [BackupController::class, 'index']);
        Route::post('/backups', [BackupController::class, 'store']);
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->where('filename', 'obims-[\d\-]+\.sql');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->where('filename', 'obims-[\d\-]+\.sql');
        Route::get('/backups/restore-log', [BackupController::class, 'restoreLog']);
        Route::get('/backups/{filename}/inspect', [BackupController::class, 'inspect'])->where('filename', 'obims-[\d\-]+\.sql');
        Route::post('/backups/{filename}/restore', [BackupController::class, 'restore'])->where('filename', 'obims-[\d\-]+\.sql');
    });

    Route::get('/stock/operations', fn (PageController $pages) => $pages->show('stock.operations'))->middleware('staff_or_page:stock.operations');
    Route::get('/stock/registration', fn (PageController $pages) => $pages->show('stock.registration'))->middleware('staff_or_page:stock.registration');
    Route::get('/stock/card-management', fn (PageController $pages) => $pages->show('stock.card-management'))->middleware('role:admin,supply_officer');
    Route::get('/stock/property-card-management', fn (PageController $pages) => $pages->show('stock.property-card-management'))->middleware('role:admin,supply_officer');
    Route::get('/stock/receive', fn () => redirect('/stock/operations?tab=receive'))->middleware('staff_or_page:stock.operations');
    Route::get('/stock/adjust', fn () => redirect('/stock/operations?tab=adjust'))->middleware('staff_or_page:stock.operations');
    Route::get('/items/register', fn () => redirect('/stock/registration#register-item'))->middleware('staff_or_page:stock.registration');
    Route::get('/issuance', fn (PageController $pages) => $pages->show('issuance'))->middleware('staff_or_page:issuance');
    Route::get('/scan-issue', fn (PageController $pages) => $pages->show('scan-issue'))->middleware('staff_or_page:scan-issue');
    Route::get('/equipment-dashboard', fn (PageController $pages) => $pages->show('equipment-dashboard'))->middleware('staff_or_page:equipment-dashboard');
    Route::get('/delivery-inspection', fn (PageController $pages) => $pages->show('delivery-inspection'))->middleware('staff_or_page:delivery-inspection');
    Route::get('/employee-directory', fn (PageController $pages) => $pages->show('employee-directory'))->middleware('staff_or_page:employee-directory');
    Route::get('/returns', fn (PageController $pages) => $pages->show('returns'))->middleware('staff_or_page:returns');
    Route::get('/person-lookup', fn (PageController $pages) => $pages->show('person-lookup'))->middleware('staff_or_page:person-lookup');
    Route::get('/catalog-details', fn (PageController $pages) => $pages->show('catalog-details'))->middleware('staff_or_page:catalog-details');
    Route::get('/reports', fn (PageController $pages) => $pages->show('reports'))->middleware('staff_or_page:reports');
    Route::get('/items', fn (PageController $pages) => $pages->show('items'))->middleware('staff_or_page:items');
    Route::get('/inventory/predictions', fn (PageController $pages) => $pages->show('inventory.predictions'))->middleware('staff_or_page:inventory.predictions');
    Route::get('/inventory/master-data', fn () => redirect('/settings'))->middleware('role:admin,supply_officer');
    Route::get('/categories', fn () => redirect('/settings?tab=categories'))->middleware('role:admin,supply_officer');
    Route::get('/equipment-categories', fn () => redirect('/settings?tab=equipment-categories'))->middleware('role:admin,supply_officer');
    Route::get('/units', fn () => redirect('/settings?tab=units'))->middleware('role:admin,supply_officer');
    Route::get('/locations', fn () => redirect('/settings?tab=locations'))->middleware('role:admin,supply_officer');
    Route::get('/departments', fn (PageController $pages) => $pages->show('departments'))->middleware('role:admin');
    Route::get('/employees', fn (PageController $pages) => $pages->show('employees'))->middleware('role:admin');
    Route::get('/activity-logs', fn (PageController $pages) => $pages->show('activity-logs'))->middleware('staff_or_page:activity-logs');
    Route::get('/settings', fn (PageController $pages) => $pages->show('settings'))->middleware('role:admin,supply_officer');
    Route::get('/permissions/manage', fn (PageController $pages) => $pages->show('permissions'))->middleware('role:admin');
    Route::get('/users', fn (PageController $pages) => $pages->show('users'))->middleware('role:admin');
    Route::get('/deleted-data', fn (PageController $pages) => $pages->show('deleted-data'))->middleware('role:admin');
    Route::get('/backup-files', fn (PageController $pages) => $pages->show('backup-files'))->middleware('role:admin');
    Route::get('/profile', fn (PageController $pages) => $pages->show('profile'));
});

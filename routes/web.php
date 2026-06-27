<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeletedDataController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EquipmentCategoryController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\IssuanceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ItemReturnController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PredictiveInventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StorageLocationController;
use App\Http\Controllers\SupplyRequestController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Models\SupplyRequest as SupplyRequestModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => redirect('/'));

Route::get('/about', fn (PageController $pages) => $pages->show('about'));

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/', function (PageController $pages) {
    if (Auth::check()) {
        return $pages->show('dashboard');
    }

    return $pages->show('login');
})->name('login');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/notifications/list', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->whereNumber('notification');

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/recent-movements', [DashboardController::class, 'recentMovements']);
    Route::get('/dashboard/recent-issuance', [DashboardController::class, 'recentIssuance']);
    Route::get('/dashboard/recent-returns', [DashboardController::class, 'recentReturns']);
    Route::get('/dashboard/charts', [DashboardController::class, 'charts']);

    Route::get('/items/barcode/{barcode}', [ItemController::class, 'findByBarcode']);
    Route::get('/items/list', [ItemController::class, 'index']);
    Route::get('/items/register', fn () => redirect('/stock/operations?tab=register'))->middleware('role:admin,supply_officer');
    Route::post('/items', [ItemController::class, 'store']);
    Route::put('/items/{item}', [ItemController::class, 'update'])->whereNumber('item');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->whereNumber('item');
    Route::get('/items/{item}', [ItemController::class, 'show'])->whereNumber('item');

    Route::get('/requests/list', [SupplyRequestController::class, 'index']);
    Route::post('/requests', [SupplyRequestController::class, 'store']);
    Route::get('/requests/{supplyRequest}/detail', [SupplyRequestController::class, 'show'])->whereNumber('supplyRequest');
    Route::post('/requests/{supplyRequest}/approve', [SupplyRequestController::class, 'approve'])->middleware('role:admin,supply_officer');
    Route::post('/requests/{supplyRequest}/reject', [SupplyRequestController::class, 'reject'])->middleware('role:admin,supply_officer');

    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf']);
    Route::get('/reports/{type}', [ReportController::class, 'show']);

    Route::get('/departments/list', [DepartmentController::class, 'index']);
    Route::get('/employees/list', [EmployeeController::class, 'index']);
    Route::get('/categories/list', [CategoryController::class, 'index']);
    Route::get('/equipment-categories/list', [EquipmentCategoryController::class, 'index']);
    Route::get('/equipments/list', [EquipmentController::class, 'index']);
    Route::get('/units/list', [UnitController::class, 'index']);
    Route::get('/locations/list', [StorageLocationController::class, 'index']);

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

        Route::post('/stock/receive', [StockController::class, 'receive']);
        Route::post('/stock/adjust', [StockController::class, 'adjust']);
        Route::get('/stock/movements', [StockController::class, 'movements']);

        Route::get('/equipments/barcode/{barcode}', [EquipmentController::class, 'findByBarcode']);
        Route::post('/equipments', [EquipmentController::class, 'store']);
        Route::put('/equipments/{equipment}', [EquipmentController::class, 'update'])->whereNumber('equipment');
        Route::delete('/equipments/{equipment}', [EquipmentController::class, 'destroy'])->whereNumber('equipment');
        Route::get('/equipments/{equipment}', [EquipmentController::class, 'show'])->whereNumber('equipment');

        Route::get('/stock-count/sessions/list', [StockCountController::class, 'index']);
        Route::post('/stock-count/sessions', [StockCountController::class, 'storeSession']);
        Route::get('/stock-count/sessions', [StockCountController::class, 'index']);
        Route::get('/stock-count/sessions/{session}', [StockCountController::class, 'showSession'])->whereNumber('session');
        Route::post('/stock-count/sessions/{session}/items', [StockCountController::class, 'addItem'])->whereNumber('session');
        Route::post('/stock-count/sessions/{session}/complete', [StockCountController::class, 'complete'])->whereNumber('session');

        Route::get('/issuances/list', [IssuanceController::class, 'index']);
        Route::post('/issuances', [IssuanceController::class, 'store']);
        Route::get('/issuances/{issuance}', [IssuanceController::class, 'show'])->whereNumber('issuance');

        Route::get('/returns/list', [ItemReturnController::class, 'index']);
        Route::post('/returns', [ItemReturnController::class, 'store']);

        Route::get('/activity-logs/list', [ActivityLogController::class, 'index']);

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

        Route::get('/users/list', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::get('/deleted-data/list', [DeletedDataController::class, 'index']);
        Route::post('/deleted-data/{type}/{id}/restore', [DeletedDataController::class, 'restore'])->whereNumber('id');
        Route::delete('/deleted-data/{type}/{id}/force', [DeletedDataController::class, 'forceDestroy'])->whereNumber('id');
    });

    Route::get('/stock/operations', fn (PageController $pages) => $pages->show('stock.operations'))->middleware('role:admin,supply_officer');
    Route::get('/stock/card-management', fn (PageController $pages) => $pages->show('stock.card-management'))->middleware('role:admin,supply_officer');
    Route::get('/stock/property-card-management', fn (PageController $pages) => $pages->show('stock.property-card-management'))->middleware('role:admin,supply_officer');
    Route::get('/stock/receive', fn () => redirect('/stock/operations?tab=receive'))->middleware('role:admin,supply_officer');
    Route::get('/stock/adjust', fn () => redirect('/stock/operations?tab=adjust'))->middleware('role:admin,supply_officer');
    Route::get('/stock/count', fn (PageController $pages) => $pages->show('stock.count'))->middleware('role:admin,supply_officer');
    Route::get('/stock/count/records', fn (PageController $pages) => $pages->show('stock.count-records'))->middleware('role:admin,supply_officer');
    Route::get('/requests/create', fn (PageController $pages) => $pages->show('requests.create'));
    Route::get('/issuance', fn (PageController $pages) => $pages->show('issuance'))->middleware('role:admin,supply_officer');
    Route::get('/returns', fn (PageController $pages) => $pages->show('returns'))->middleware('role:admin,supply_officer');
    Route::get('/reports', fn (PageController $pages) => $pages->show('reports'));
    Route::get('/items', fn (PageController $pages) => $pages->show('items'));
    Route::get('/inventory/predictions', fn (PageController $pages) => $pages->show('inventory.predictions'))->middleware('role:admin,supply_officer');
    Route::get('/inventory/master-data', fn (PageController $pages) => $pages->show('inventory.master-data'))->middleware('role:admin,supply_officer');
    Route::get('/categories', fn () => redirect('/inventory/master-data?tab=categories'))->middleware('role:admin,supply_officer');
    Route::get('/equipment-categories', fn () => redirect('/inventory/master-data?tab=equipment-categories'))->middleware('role:admin,supply_officer');
    Route::get('/units', fn () => redirect('/inventory/master-data?tab=units'))->middleware('role:admin,supply_officer');
    Route::get('/locations', fn () => redirect('/inventory/master-data?tab=locations'))->middleware('role:admin,supply_officer');
    Route::get('/requests', fn (PageController $pages) => $pages->show('requests'));
    Route::get('/departments', fn (PageController $pages) => $pages->show('departments'))->middleware('role:admin');
    Route::get('/employees', fn (PageController $pages) => $pages->show('employees'))->middleware('role:admin');
    Route::get('/activity-logs', fn (PageController $pages) => $pages->show('activity-logs'))->middleware('role:admin,supply_officer');
    Route::get('/settings', fn (PageController $pages) => $pages->show('settings'))->middleware('role:admin');
    Route::get('/users', fn (PageController $pages) => $pages->show('users'))->middleware('role:admin');
    Route::get('/deleted-data', fn (PageController $pages) => $pages->show('deleted-data'))->middleware('role:admin');

    Route::get('/requests/{supplyRequest}', fn (PageController $pages, SupplyRequestModel $supplyRequest) => $pages->show('requests.show', [
        'requestId' => $supplyRequest->id,
    ]))->whereNumber('supplyRequest');
});

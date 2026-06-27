<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Issuance;
use App\Models\IssuanceDetail;
use App\Models\Item;
use App\Models\ItemReturn;
use App\Models\ObimsNotification;
use App\Models\RequestDetail;
use App\Models\Setting;
use App\Models\StockCountItem;
use App\Models\StockCountSession;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\SupplyRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SoftDeleteRegistry
{
    public static function entities(): array
    {
        return [
            'categories' => [
                'model' => Category::class,
                'label' => 'Categories',
                'name_fields' => ['name'],
            ],
            'units' => [
                'model' => Unit::class,
                'label' => 'Units of Measure',
                'name_fields' => ['name'],
            ],
            'locations' => [
                'model' => StorageLocation::class,
                'label' => 'Storage Locations',
                'name_fields' => ['name', 'code'],
            ],
            'departments' => [
                'model' => Department::class,
                'label' => 'Departments',
                'name_fields' => ['name', 'code'],
            ],
            'employees' => [
                'model' => Employee::class,
                'label' => 'Employees',
                'name_fields' => ['name', 'employee_number'],
            ],
            'equipments' => [
                'model' => Equipment::class,
                'label' => 'Equipments',
                'name_fields' => ['property_number', 'barcode', 'name', 'type'],
            ],
            'equipment_categories' => [
                'model' => EquipmentCategory::class,
                'label' => 'Equipment Categories',
                'name_fields' => ['name'],
            ],
            'users' => [
                'model' => User::class,
                'label' => 'Admin Accounts',
                'name_fields' => ['name', 'email'],
            ],
            'items' => [
                'model' => Item::class,
                'label' => 'Items',
                'name_fields' => ['item_name', 'barcode'],
            ],
            'settings' => [
                'model' => Setting::class,
                'label' => 'Settings',
                'name_fields' => ['key'],
            ],
            'supply_requests' => [
                'model' => SupplyRequest::class,
                'label' => 'Supply Requests',
                'name_fields' => ['request_number'],
            ],
            'request_details' => [
                'model' => RequestDetail::class,
                'label' => 'Request Details',
                'name_fields' => ['id'],
            ],
            'issuances' => [
                'model' => Issuance::class,
                'label' => 'Issuances',
                'name_fields' => ['issuance_number'],
            ],
            'issuance_details' => [
                'model' => IssuanceDetail::class,
                'label' => 'Issuance Details',
                'name_fields' => ['id'],
            ],
            'returns' => [
                'model' => ItemReturn::class,
                'label' => 'Returns',
                'name_fields' => ['id'],
            ],
            'stock_movements' => [
                'model' => StockMovement::class,
                'label' => 'Stock Movements',
                'name_fields' => ['reference_number', 'id'],
            ],
            'stock_count_sessions' => [
                'model' => StockCountSession::class,
                'label' => 'Stock Count Sessions',
                'name_fields' => ['session_number'],
            ],
            'stock_count_items' => [
                'model' => StockCountItem::class,
                'label' => 'Stock Count Items',
                'name_fields' => ['id'],
            ],
            'activity_logs' => [
                'model' => ActivityLog::class,
                'label' => 'Activity Logs',
                'name_fields' => ['description', 'id'],
            ],
            'obims_notifications' => [
                'model' => ObimsNotification::class,
                'label' => 'Notifications',
                'name_fields' => ['title'],
            ],
        ];
    }

    public static function resolve(string $type): ?array
    {
        return self::entities()[$type] ?? null;
    }

    public static function types(): array
    {
        return array_keys(self::entities());
    }

    public static function displayName(Model $model, array $config): string
    {
        foreach ($config['name_fields'] as $field) {
            $value = $model->getAttribute($field);

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return "#{$model->getKey()}";
    }
}

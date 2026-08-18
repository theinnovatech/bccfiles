<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\Item;
use App\Services\PagePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json([]);
        }

        $user = $request->user();
        $like = '%'.$query.'%';
        $results = [];

        foreach ($this->pagesForUser($user) as $page) {
            $haystack = strtolower($page['label'].' '.$page['group'].' '.$page['keywords']);
            if (! str_contains($haystack, strtolower($query))) {
                continue;
            }

            $results[] = [
                'type' => 'page',
                'key' => 'page:'.$page['url'],
                'label' => $page['label'],
                'subtitle' => $page['group'],
                'url' => $page['url'],
            ];
        }

        if ($this->canSearchCatalog($user)) {
            Item::query()
                ->with(['category', 'unit'])
                ->where(function ($q) use ($like) {
                    $q->where('item_name', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhere('item_number', 'like', $like)
                        ->orWhere('inventory_number', 'like', $like)
                        ->orWhere('brand', 'like', $like);
                })
                ->orderBy('item_name')
                ->limit(8)
                ->get()
                ->each(function (Item $item) use (&$results, $user) {
                    $subtitle = collect([
                        $item->item_number ? 'Stock: '.$item->item_number : null,
                        $item->barcode ? 'Barcode: '.$item->barcode : null,
                        $item->category?->name,
                    ])->filter()->implode(' · ');

                    $results[] = [
                        'type' => 'item',
                        'key' => 'item:'.$item->id,
                        'label' => $item->item_name,
                        'subtitle' => $subtitle,
                        'url' => $this->catalogResultUrl($user, 'item', $item->id, $item->item_name),
                        'item_id' => $item->id,
                    ];
                });

            Equipment::query()
                ->with('category')
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('property_number', 'like', $like)
                        ->orWhere('inventory_number', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhere('type', 'like', $like);
                })
                ->orderBy('name')
                ->limit(8)
                ->get()
                ->each(function (Equipment $equipment) use (&$results, $user) {
                    $subtitle = collect([
                        $equipment->origin === 'returned' ? 'Returned' : 'Fresh',
                        $equipment->property_number ? 'Property: '.$equipment->property_number : null,
                        $equipment->category?->name,
                        $equipment->type,
                    ])->filter()->implode(' · ');

                    $results[] = [
                        'type' => 'equipment',
                        'key' => 'equipment:'.$equipment->id,
                        'label' => $equipment->name,
                        'subtitle' => $subtitle,
                        'url' => $this->catalogResultUrl($user, 'equipment', $equipment->id, $equipment->name),
                        'equipment_id' => $equipment->id,
                    ];
                });
        }

        if (PagePermissionService::userCanAccessPage($user, 'person-lookup')
            || $user->role === UserRole::Admin) {
            Employee::query()
                ->with('department')
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like)
                        ->orWhere('position', 'like', $like);
                })
                ->orderBy('name')
                ->limit(8)
                ->get()
                ->each(function (Employee $employee) use (&$results, $user) {
                    $subtitle = collect([
                        $employee->employee_number ? 'ID: '.$employee->employee_number : null,
                        $employee->department?->name,
                        $employee->position,
                    ])->filter()->implode(' · ');

                    $url = PagePermissionService::userCanAccessPage($user, 'person-lookup')
                        ? '/person-lookup?type=person&employee_id='.$employee->id.'&name='.urlencode($employee->name)
                        : '/employees';

                    $results[] = [
                        'type' => 'person',
                        'key' => 'person:'.$employee->id,
                        'label' => $employee->name,
                        'subtitle' => $subtitle,
                        'url' => $url,
                        'employee_id' => $employee->id,
                    ];
                });
        }

        if ($user->role === UserRole::Admin) {
            Department::query()
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                })
                ->orderBy('name')
                ->limit(6)
                ->get()
                ->each(function (Department $department) use (&$results) {
                    $results[] = [
                        'type' => 'department',
                        'key' => 'department:'.$department->id,
                        'label' => $department->name,
                        'subtitle' => $department->code ? 'Code: '.$department->code : 'Department',
                        'url' => '/departments',
                    ];
                });
        }

        return response()->json($results);
    }

    private function canSearchCatalog($user): bool
    {
        return PagePermissionService::userCanAccessAnyPage($user, [
            'catalog-details',
            'items',
            'stock.operations',
            'stock.registration',
            'issuance',
            'returns',
            'inventory.predictions',
            'person-lookup',
        ]);
    }

    private function catalogResultUrl($user, string $type, int $id, string $name): string
    {
        if (PagePermissionService::userCanAccessPage($user, 'catalog-details')) {
            return '/catalog-details?type='.$type.'&id='.$id;
        }

        if (PagePermissionService::userCanAccessPage($user, 'person-lookup')) {
            $param = $type === 'item' ? 'item_id' : 'equipment_id';

            return '/person-lookup?type='.$type.'&'.$param.'='.$id.'&name='.urlencode($name);
        }

        return $type === 'item' ? '/items' : '/stock/registration';
    }

    /**
     * @return list<array{label: string, url: string, group: string, keywords: string, page?: string, roles?: list<string>}>
     */
    private function pagesForUser($user): array
    {
        $role = $user->role->value;
        $employeePages = $role === UserRole::DepartmentUser->value
            ? PagePermissionService::allowedPagesForDepartmentUsers()
            : [];

        $pages = [
            ['label' => 'Dashboard', 'url' => '/', 'group' => 'Overview', 'keywords' => 'home overview', 'page' => 'dashboard', 'roles' => ['admin', 'supply_officer', 'department_user']],
            ['label' => 'Supply Master', 'url' => '/items', 'group' => 'Inventory', 'keywords' => 'items supplies catalog', 'page' => 'items', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Estimated Stock', 'url' => '/inventory/predictions', 'group' => 'Inventory', 'keywords' => 'predictions life stock', 'page' => 'inventory.predictions', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Stock Operations', 'url' => '/stock/operations', 'group' => 'Inventory', 'keywords' => 'receive adjust barcode', 'page' => 'stock.operations', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Registration', 'url' => '/stock/registration', 'group' => 'Inventory', 'keywords' => 'register item equipment', 'page' => 'stock.registration', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Catalog Details', 'url' => '/catalog-details', 'group' => 'Inventory', 'keywords' => 'item equipment details lookup', 'page' => 'catalog-details', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Stock Card Management', 'url' => '/stock/card-management', 'group' => 'Inventory', 'keywords' => 'stock card movements', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Property Card Management', 'url' => '/stock/property-card-management', 'group' => 'Inventory', 'keywords' => 'property card equipment', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Item Issuance', 'url' => '/issuance', 'group' => 'Transactions', 'keywords' => 'issue release', 'page' => 'issuance', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Equipment Returns', 'url' => '/returns', 'group' => 'Transactions', 'keywords' => 'return borrow', 'page' => 'returns', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Records Lookup', 'url' => '/person-lookup', 'group' => 'Transactions', 'keywords' => 'person history lookup', 'page' => 'person-lookup', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Departments', 'url' => '/departments', 'group' => 'Organization', 'keywords' => 'office unit', 'roles' => ['admin']],
            ['label' => 'Employees', 'url' => '/employees', 'group' => 'Organization', 'keywords' => 'staff people', 'roles' => ['admin']],
            ['label' => 'Reports', 'url' => '/reports', 'group' => 'Insights', 'keywords' => 'export pdf', 'page' => 'reports', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Activity Logs', 'url' => '/activity-logs', 'group' => 'Insights', 'keywords' => 'audit history', 'page' => 'activity-logs', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'Admin Accounts', 'url' => '/users', 'group' => 'System', 'keywords' => 'users accounts', 'roles' => ['admin']],
            ['label' => 'Permissions', 'url' => '/permissions/manage', 'group' => 'System', 'keywords' => 'access rights', 'roles' => ['admin']],
            ['label' => 'Deleted Data', 'url' => '/deleted-data', 'group' => 'System', 'keywords' => 'trash restore', 'roles' => ['admin']],
            ['label' => 'Backup Files', 'url' => '/backup-files', 'group' => 'System', 'keywords' => 'backup sql', 'roles' => ['admin']],
            ['label' => 'Settings', 'url' => '/settings', 'group' => 'System', 'keywords' => 'categories units locations', 'roles' => ['admin', 'supply_officer']],
            ['label' => 'About OBIMS', 'url' => '/about', 'group' => 'System', 'keywords' => 'about info', 'roles' => ['admin', 'supply_officer', 'department_user']],
            ['label' => 'My Profile', 'url' => '/profile', 'group' => 'System', 'keywords' => 'account password name settings', 'roles' => ['admin', 'supply_officer', 'department_user']],
        ];

        return array_values(array_filter($pages, function (array $page) use ($role, $employeePages) {
            if ($role === UserRole::DepartmentUser->value) {
                $pageKey = $page['page'] ?? null;

                if ($pageKey === null) {
                    return in_array('department_user', $page['roles'] ?? [], true);
                }

                return in_array($pageKey, $employeePages, true);
            }

            return in_array($role, $page['roles'] ?? [], true);
        }));
    }
}

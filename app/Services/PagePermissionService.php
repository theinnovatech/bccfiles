<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;

class PagePermissionService
{
    public const SETTING_KEY = 'department_user_pages';

    public const DEFAULT_PAGES = ['dashboard'];

    /**
     * Pages admins may grant to employee (department_user) accounts.
     * Dashboard is always included and cannot be removed.
     *
     * @return list<array{key: string, label: string, description: string, group: string, always_on?: bool}>
     */
    public static function grantablePages(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'description' => 'Home overview (always available to employees).',
                'group' => 'Overview',
                'always_on' => true,
            ],
            [
                'key' => 'items',
                'label' => 'Supply Master',
                'description' => 'Browse and manage supply items.',
                'group' => 'Inventory',
            ],
            [
                'key' => 'inventory.predictions',
                'label' => 'Estimated Stock',
                'description' => 'View predictive stock estimates.',
                'group' => 'Inventory',
            ],
            [
                'key' => 'stock.operations',
                'label' => 'Stock Operations',
                'description' => 'Receive and adjust stock.',
                'group' => 'Inventory',
            ],
            [
                'key' => 'stock.registration',
                'label' => 'Registration',
                'description' => 'Register items and equipment.',
                'group' => 'Inventory',
            ],
            [
                'key' => 'catalog-details',
                'label' => 'Catalog Details',
                'description' => 'Search and view full item or equipment details.',
                'group' => 'Inventory',
            ],
            [
                'key' => 'issuance',
                'label' => 'Item Issuance',
                'description' => 'Issue supplies and equipment to people.',
                'group' => 'Transactions',
            ],
            [
                'key' => 'returns',
                'label' => 'Equipment Returns',
                'description' => 'Record equipment returns.',
                'group' => 'Transactions',
            ],
            [
                'key' => 'person-lookup',
                'label' => 'Records Lookup',
                'description' => 'Look up issuance and return history by person, item, or equipment.',
                'group' => 'Transactions',
            ],
            [
                'key' => 'reports',
                'label' => 'Reports',
                'description' => 'View and export inventory reports.',
                'group' => 'Insights',
            ],
            [
                'key' => 'activity-logs',
                'label' => 'Activity Logs',
                'description' => 'View system activity history.',
                'group' => 'Insights',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function grantableKeys(): array
    {
        return array_column(self::grantablePages(), 'key');
    }

    /**
     * Map sidebar/page URLs to permission keys.
     */
    public static function keyForUrl(string $url): ?string
    {
        $path = trim($url, '/');

        return match ($path) {
            '' => 'dashboard',
            'items' => 'items',
            'inventory/predictions' => 'inventory.predictions',
            'stock/operations' => 'stock.operations',
            'stock/registration' => 'stock.registration',
            'catalog-details' => 'catalog-details',
            'issuance' => 'issuance',
            'returns' => 'returns',
            'person-lookup' => 'person-lookup',
            'reports' => 'reports',
            'activity-logs' => 'activity-logs',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function allowedPagesForDepartmentUsers(): array
    {
        $raw = Setting::getValue(self::SETTING_KEY);
        $pages = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($pages)) {
            return self::DEFAULT_PAGES;
        }

        $allowed = array_values(array_intersect(
            array_map('strval', $pages),
            self::grantableKeys()
        ));

        if (! in_array('dashboard', $allowed, true)) {
            array_unshift($allowed, 'dashboard');
        }

        return array_values(array_unique($allowed));
    }

    /**
     * @param  list<string>  $pages
     * @return list<string>
     */
    public static function setDepartmentUserPages(array $pages): array
    {
        $allowed = array_values(array_intersect(
            array_map('strval', $pages),
            self::grantableKeys()
        ));

        if (! in_array('dashboard', $allowed, true)) {
            array_unshift($allowed, 'dashboard');
        }

        $allowed = array_values(array_unique($allowed));

        Setting::setValue(self::SETTING_KEY, json_encode($allowed), 'permissions');

        return $allowed;
    }

    public static function userCanAccessPage(User $user, string $pageKey): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role === UserRole::Admin || $user->role === UserRole::SupplyOfficer) {
            return true;
        }

        if ($user->role !== UserRole::DepartmentUser) {
            return false;
        }

        return in_array($pageKey, self::allowedPagesForDepartmentUsers(), true);
    }

    /**
     * Whether staff (admin/supply) or a department user with any of the given pages may proceed.
     *
     * @param  list<string>  $pageKeys
     */
    public static function userCanAccessAnyPage(User $user, array $pageKeys): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role === UserRole::Admin || $user->role === UserRole::SupplyOfficer) {
            return true;
        }

        if ($user->role !== UserRole::DepartmentUser) {
            return false;
        }

        $allowed = self::allowedPagesForDepartmentUsers();

        foreach ($pageKeys as $key) {
            if (in_array($key, $allowed, true)) {
                return true;
            }
        }

        return false;
    }
}

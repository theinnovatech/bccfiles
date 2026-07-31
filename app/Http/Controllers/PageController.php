<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show(string $page, array $pageProps = [], ?string $title = null): View
    {
        if ($page === 'login') {
            return view('login');
        }

        if ($page === 'about') {
            return view('about');
        }

        return view('layouts.obims', [
            'page' => $page,
            'pageProps' => $pageProps,
            'title' => $title ?? $this->titleFor($page),
        ]);
    }

    private function titleFor(string $page): string
    {
        return match ($page) {
            'dashboard' => 'Dashboard',
            'items' => 'Supply Master',
            'stock.operations' => 'Stock Operations',
            'stock.registration' => 'Registration',
            'stock.card-management' => 'Stock Card Management',
            'stock.property-card-management' => 'Property Card Management',
            'inventory.predictions' => 'Estimated Stock Life',
            'categories' => 'Categories',
            'units' => 'Units of Measure',
            'locations' => 'Storage Locations',
            'issuance' => 'Item Issuance',
            'returns' => 'Equipment Returns',
            'person-lookup' => 'Person Lookup',
            'departments' => 'Departments',
            'employees' => 'Employees',
            'reports' => 'Reports',
            'activity-logs' => 'Activity Logs',
            'settings' => 'Settings',
            'permissions' => 'Employee Permissions',
            'users' => 'Admin Accounts',
            'deleted-data' => 'Deleted Data',
            'backup-files' => 'Backup Files',
            default => 'OBIMS',
        };
    }
}

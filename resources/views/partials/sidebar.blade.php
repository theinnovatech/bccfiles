@php
    $role = auth()->user()->role->value;

    $menu = [
        ['label' => 'Dashboard', 'url' => '/', 'icon' => 'home', 'roles' => ['admin', 'supply_officer', 'department_user']],
        ['label' => 'Supply Master', 'url' => '/items', 'icon' => 'box', 'roles' => ['admin', 'supply_officer', 'department_user']],
        ['label' => 'Estimated Stock', 'url' => '/inventory/predictions', 'icon' => 'chart-line', 'roles' => ['admin', 'supply_officer']],
        ['label' => 'Stock Operations', 'url' => '/stock/operations', 'icon' => 'barcode', 'roles' => ['admin', 'supply_officer']],
        ['label' => 'Master Data', 'url' => '/inventory/master-data', 'icon' => 'tags', 'roles' => ['admin', 'supply_officer']],
        ['label' => 'Physical Inventory', 'url' => '/stock/count', 'icon' => 'check-square', 'roles' => ['admin', 'supply_officer']],
        ['label' => 'Supply Requests', 'url' => '/requests', 'icon' => 'inbox', 'roles' => ['admin', 'supply_officer', 'department_user']],
        ['label' => 'Item Issuance', 'url' => '/issuance', 'icon' => 'send', 'roles' => ['admin', 'supply_officer']],
        ['label' => 'Returns', 'url' => '/returns', 'icon' => 'replay', 'roles' => ['admin', 'supply_officer']],
        ['label' => 'Departments', 'url' => '/departments', 'icon' => 'building', 'roles' => ['admin']],
        ['label' => 'Employees', 'url' => '/employees', 'icon' => 'users', 'roles' => ['admin']],
        ['label' => 'Reports', 'url' => '/reports', 'icon' => 'chart-bar', 'roles' => ['admin', 'supply_officer', 'department_user']],
        ['label' => 'Activity Logs', 'url' => '/activity-logs', 'icon' => 'history', 'roles' => ['admin', 'supply_officer']],
        ['label' => 'Settings', 'url' => '/settings', 'icon' => 'cog', 'roles' => ['admin']],
        ['label' => 'Admin Accounts', 'url' => '/users', 'icon' => 'user-edit', 'roles' => ['admin']],
        ['label' => 'Deleted Data', 'url' => '/deleted-data', 'icon' => 'trash', 'roles' => ['admin']],
    ];
@endphp

<aside id="app-sidebar" class="deped-sidebar">
    <div class="deped-accent-bar"></div>
    <div class="deped-sidebar-brand">
        <h1 class="deped-sidebar-title">OBIMS</h1>
        <p id="app-organization-name" class="deped-sidebar-subtitle">{{ $organizationName }}</p>
    </div>
    @php
        $currentPath = trim(request()->path(), '/');

        $visibleMenu = array_values(array_filter(
            $menu,
            fn ($item) => in_array($role, $item['roles'], true)
        ));

        $activeUrl = collect($visibleMenu)
            ->filter(function ($item) use ($currentPath) {
                $menuPath = ltrim($item['url'], '/');

                if ($menuPath === '') {
                    return $currentPath === '';
                }

                return $currentPath === $menuPath
                    || str_starts_with($currentPath, $menuPath . '/');
            })
            ->sortByDesc(fn ($item) => strlen($item['url']))
            ->value('url');
    @endphp

    <nav class="flex-1 space-y-1 overflow-y-auto p-3">
        @foreach ($menu as $item)
            @if (in_array($role, $item['roles'], true))
                @php
                    $isActive = $item['url'] === $activeUrl;
                @endphp
                <a
                    href="{{ url($item['url']) }}"
                    class="shadcn-sidebar-link {{ $isActive ? 'shadcn-sidebar-link-active' : '' }}"
                >
                    @include('partials.sidebar-icon', ['name' => $item['icon']])
                    <span>{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-white/10 p-3">
        <a
            href="{{ url('/about') }}"
            class="flex items-center gap-3 rounded-md px-3 py-2 text-xs font-medium text-white/60 transition-colors hover:bg-white/10 hover:text-white"
        >
            @include('partials.sidebar-icon', ['name' => 'info'])
            <span>About OBIMS</span>
        </a>
    </div>
</aside>

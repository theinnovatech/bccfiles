@php
    $role = auth()->user()->role->value;

    $menuGroups = [
        [
            'label' => 'Overview',
            'items' => [
                ['label' => 'Dashboard', 'url' => '/', 'icon' => 'home', 'roles' => ['admin', 'supply_officer']],
            ],
        ],
        [
            'label' => 'Inventory',
            'items' => [
                ['label' => 'Supply Master', 'url' => '/items', 'icon' => 'box', 'roles' => ['admin', 'supply_officer']],
                ['label' => 'Estimated Stock', 'url' => '/inventory/predictions', 'icon' => 'chart-line', 'roles' => ['admin', 'supply_officer']],
                ['label' => 'Stock Operations', 'url' => '/stock/operations', 'icon' => 'barcode', 'roles' => ['admin', 'supply_officer']],
                ['label' => 'Registration', 'url' => '/stock/registration', 'icon' => 'plus-square', 'roles' => ['admin', 'supply_officer']],
            ],
        ],
        [
            'label' => 'Transactions',
            'items' => [
                ['label' => 'Item Issuance', 'url' => '/issuance', 'icon' => 'send', 'roles' => ['admin', 'supply_officer']],
                ['label' => 'Equipment Returns', 'url' => '/returns', 'icon' => 'replay', 'roles' => ['admin', 'supply_officer']],
                ['label' => 'Person Lookup', 'url' => '/person-lookup', 'icon' => 'search', 'roles' => ['admin', 'supply_officer']],
            ],
        ],
        [
            'label' => 'Organization',
            'items' => [
                ['label' => 'Departments', 'url' => '/departments', 'icon' => 'building', 'roles' => ['admin']],
                ['label' => 'Employees', 'url' => '/employees', 'icon' => 'users', 'roles' => ['admin']],
            ],
        ],
        [
            'label' => 'Insights',
            'items' => [
                ['label' => 'Reports', 'url' => '/reports', 'icon' => 'chart-bar', 'roles' => ['admin', 'supply_officer']],
                ['label' => 'Activity Logs', 'url' => '/activity-logs', 'icon' => 'history', 'roles' => ['admin', 'supply_officer']],
            ],
        ],
        [
            'label' => 'System',
            'items' => [
                ['label' => 'Admin Accounts', 'url' => '/users', 'icon' => 'user-edit', 'roles' => ['admin']],
                ['label' => 'Deleted Data', 'url' => '/deleted-data', 'icon' => 'trash', 'roles' => ['admin']],
                ['label' => 'Settings', 'url' => '/settings', 'icon' => 'cog', 'roles' => ['admin', 'supply_officer']],
            ],
        ],
    ];

    $flatMenu = collect($menuGroups)->flatMap(fn ($group) => $group['items'])->all();
    $currentPath = trim(request()->path(), '/');

    $visibleMenu = array_values(array_filter(
        $flatMenu,
        fn ($item) => in_array($role, $item['roles'], true)
    ));

    $activeUrl = collect($visibleMenu)
        ->filter(function ($item) use ($currentPath) {
            $menuPath = ltrim($item['url'], '/');

            if ($menuPath === '') {
                return $currentPath === '';
            }

            return $currentPath === $menuPath
                || str_starts_with($currentPath, $menuPath.'/');
        })
        ->sortByDesc(fn ($item) => strlen($item['url']))
        ->value('url');
@endphp

<aside id="app-sidebar" class="deped-sidebar">
    <div class="deped-accent-bar"></div>
    <div class="deped-sidebar-brand">
        <h1 class="deped-sidebar-title">OBIMS</h1>
        <p id="app-organization-name" class="deped-sidebar-subtitle">{{ $organizationName }}</p>
        <p class="deped-sidebar-title-collapsed" aria-hidden="true">OB</p>
    </div>

    <nav class="deped-sidebar-nav flex-1 overflow-y-auto p-3">
        @foreach ($menuGroups as $group)
            @php
                $visibleItems = array_values(array_filter(
                    $group['items'],
                    fn ($item) => in_array($role, $item['roles'], true)
                ));
            @endphp

            @if (count($visibleItems) > 0)
                <div class="sidebar-nav-group">
                    <p class="sidebar-group-label">{{ $group['label'] }}</p>
                    <div class="sidebar-group-items">
                        @foreach ($visibleItems as $item)
                            @php
                                $isActive = $item['url'] === $activeUrl;
                            @endphp
                            <a
                                href="{{ url($item['url']) }}"
                                class="shadcn-sidebar-link {{ $isActive ? 'shadcn-sidebar-link-active' : '' }}"
                                title="{{ $item['label'] }}"
                            >
                                @include('partials.sidebar-icon', ['name' => $item['icon']])
                                <span class="sidebar-link-label">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-white/10 p-3">
        <a
            href="{{ url('/about') }}"
            class="shadcn-sidebar-link text-xs text-white/60 hover:text-white"
            title="About OBIMS"
        >
            @include('partials.sidebar-icon', ['name' => 'info'])
            <span class="sidebar-link-label">About OBIMS</span>
        </a>
    </div>
</aside>

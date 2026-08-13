<header class="deped-header">
    <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
        <button type="button" id="sidebar-toggle" class="sidebar-toggle lg:hidden" aria-label="Open navigation menu" aria-controls="app-sidebar">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>
        <button
            type="button"
            id="sidebar-collapse"
            class="sidebar-toggle hidden lg:flex"
            aria-label="Collapse sidebar"
            aria-controls="app-sidebar"
            aria-expanded="true"
            title="Collapse sidebar"
        >
            <svg class="sidebar-collapse-icon h-5 w-5" data-icon="collapse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
            </svg>
            <svg class="sidebar-collapse-icon h-5 w-5 hidden" data-icon="expand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 4.5l7.5 7.5-7.5 7.5m6-15l7.5 7.5-7.5 7.5" />
            </svg>
        </button>
        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            <img src="{{ asset('images/logo1.png') }}" alt="Kagawaran ng Edukasyon" class="deped-header-logo" />
            <img src="{{ asset('images/logo2.png') }}" alt="Iriga City Division" class="deped-header-logo" />
        </div>
        <div class="hidden min-w-0 border-l border-[#a8b8d4] pl-2 sm:block sm:pl-3 md:max-w-[10rem] lg:max-w-none">
            <h2 class="deped-header-title truncate">{{ $title ?? 'OBIMS' }}</h2>
            <p class="deped-header-subtitle truncate">{{ auth()->user()->role->label() }}</p>
        </div>
        <div id="global-search-app" class="min-w-0 flex-1 max-w-xl"></div>
    </div>
    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        <div id="notification-app"></div>
        <a
            href="{{ url('/profile') }}"
            class="hidden max-w-[8rem] cursor-pointer truncate text-sm text-[#4a6490] transition-colors hover:text-[#001f6b] hover:underline md:inline md:max-w-none"
            title="My Profile"
        >
            {{ auth()->user()->name }}
        </a>
        <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <button type="submit" class="shadcn-btn shadcn-btn-outline shadcn-btn-sm">
                Logout
            </button>
        </form>
    </div>
</header>

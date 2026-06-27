<header class="deped-header">
    <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
        <button type="button" id="sidebar-toggle" class="sidebar-toggle lg:hidden" aria-label="Open navigation menu">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>
        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            <img src="{{ asset('images/logo1.png') }}" alt="Kagawaran ng Edukasyon" class="deped-header-logo" />
            <img src="{{ asset('images/logo2.png') }}" alt="Iriga City Division" class="deped-header-logo" />
        </div>
        <div class="min-w-0 border-l border-[#c8d6ef] pl-2 sm:pl-3">
            <h2 class="deped-header-title truncate">{{ $title ?? 'OBIMS' }}</h2>
            <p class="deped-header-subtitle truncate">{{ auth()->user()->role->label() }}</p>
        </div>
    </div>
    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        <div id="notification-app"></div>
        <span class="hidden max-w-[8rem] truncate text-sm text-[#5b7fbf] md:inline md:max-w-none">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <button type="submit" class="shadcn-btn shadcn-btn-outline shadcn-btn-sm">
                Logout
            </button>
        </form>
    </div>
</header>

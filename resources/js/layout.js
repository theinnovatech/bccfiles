function closeSidebar() {
    document.getElementById('app-sidebar')?.classList.remove('is-open');
    document.getElementById('sidebar-overlay')?.classList.remove('is-visible');
    document.body.classList.remove('sidebar-mobile-open');
}

function openSidebar() {
    document.getElementById('app-sidebar')?.classList.add('is-open');
    document.getElementById('sidebar-overlay')?.classList.add('is-visible');
    document.body.classList.add('sidebar-mobile-open');
}

function initLayout() {
    const toggle = document.getElementById('sidebar-toggle');
    const overlay = document.getElementById('sidebar-overlay');
    const sidebar = document.getElementById('app-sidebar');

    if (!toggle || !sidebar) {
        return;
    }

    toggle.addEventListener('click', () => {
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay?.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 1023px)').matches) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            closeSidebar();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLayout);
} else {
    initLayout();
}

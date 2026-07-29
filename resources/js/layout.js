const SIDEBAR_COLLAPSED_KEY = 'obims.sidebarCollapsed';

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

function isDesktop() {
    return window.matchMedia('(min-width: 1024px)').matches;
}

function updateCollapseButton(collapsed) {
    const button = document.getElementById('sidebar-collapse');

    if (!button) {
        return;
    }

    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    button.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    button.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');

    const collapseIcon = button.querySelector('[data-icon="collapse"]');
    const expandIcon = button.querySelector('[data-icon="expand"]');

    collapseIcon?.classList.toggle('hidden', collapsed);
    expandIcon?.classList.toggle('hidden', !collapsed);
}

function setSidebarCollapsed(collapsed) {
    const sidebar = document.getElementById('app-sidebar');

    document.body.classList.toggle('sidebar-collapsed', collapsed);
    sidebar?.classList.toggle('is-collapsed', collapsed);
    localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? '1' : '0');
    updateCollapseButton(collapsed);
}

function initCollapsedState() {
    const collapsed = localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1';
    setSidebarCollapsed(collapsed);
}

function initLayout() {
    const toggle = document.getElementById('sidebar-toggle');
    const collapse = document.getElementById('sidebar-collapse');
    const overlay = document.getElementById('sidebar-overlay');
    const sidebar = document.getElementById('app-sidebar');

    if (!sidebar) {
        return;
    }

    initCollapsedState();

    toggle?.addEventListener('click', () => {
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    collapse?.addEventListener('click', () => {
        if (!isDesktop()) {
            return;
        }

        setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
    });

    overlay?.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (!isDesktop()) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', () => {
        if (isDesktop()) {
            closeSidebar();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLayout);
} else {
    initLayout();
}

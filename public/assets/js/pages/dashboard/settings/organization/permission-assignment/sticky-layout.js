function elementHeight(element) {
    return Math.max(0, Math.ceil(element?.getBoundingClientRect?.().height ?? element?.offsetHeight ?? 0));
}

function elementTopOffset(container, element, fallback = 0) {
    if (!container || !element) return Math.max(0, fallback);
    const containerTop = Number(container.getBoundingClientRect?.().top ?? 0);
    const elementTop = Number(element.getBoundingClientRect?.().top ?? 0);
    return Math.max(0, Math.ceil(elementTop - containerTop));
}

function syncStickyLayout() {
    const page = document.getElementById('rolePermissionPage');
    if (!page) return;
    const nav = document.querySelector('.top-nav.fixed-top, .top-nav, .navbar.fixed-top, .navbar');
    const navBottom = Math.max(0, Math.ceil(nav?.getBoundingClientRect?.().bottom ?? nav?.offsetHeight ?? 0));
    const contentTop = Math.max(0, Math.ceil(document.querySelector('.main-content')?.getBoundingClientRect?.().top ?? 0));
    const stickyTop = Math.max(0, navBottom - contentTop);
    const roleToolbar = document.querySelector('#role-list-table_wrapper .dt-top');
    const roleListCard = document.getElementById('roleListCard');
    const roleListHeader = document.querySelector('#roleListCard > .card-header');
    const permissionHeader = document.querySelector('#permissionListCard > .card-header');
    const permissionToolbar = document.querySelector('#permission-assignment-table_wrapper .dt-top');
    const individualUserToolbar = document.querySelector('#individual-user-table_wrapper .dt-top');
    const individualUserCard = document.getElementById('individualUserListCard');
    const individualUserHeader = document.querySelector('#individualUserListCard > .card-header');
    const individualPermissionHeader = document.querySelector('#individualPermissionCard > .card-header');
    const individualPermissionToolbar = document.querySelector('#individual-permission-table_wrapper .dt-top');
    page.style.setProperty('--rp-sticky-top', `${stickyTop}px`);
    page.style.setProperty('--rp-role-toolbar-height', `${elementHeight(roleToolbar)}px`);
    page.style.setProperty('--rp-role-list-header-height', `${elementHeight(roleListHeader)}px`);
    page.style.setProperty('--rp-role-list-toolbar-offset', `${elementTopOffset(roleListCard, roleToolbar, 70)}px`);
    page.style.setProperty('--rp-permission-header-height', `${elementHeight(permissionHeader)}px`);
    page.style.setProperty('--rp-permission-toolbar-height', `${elementHeight(permissionToolbar)}px`);
    page.style.setProperty('--rp-individual-user-toolbar-height', `${elementHeight(individualUserToolbar)}px`);
    page.style.setProperty('--rp-individual-user-header-height', `${elementHeight(individualUserHeader)}px`);
    page.style.setProperty('--rp-individual-user-toolbar-offset', `${elementTopOffset(individualUserCard, individualUserToolbar, 70)}px`);
    page.style.setProperty('--rp-individual-permission-header-height', `${elementHeight(individualPermissionHeader)}px`);
    page.style.setProperty('--rp-individual-permission-toolbar-height', `${elementHeight(individualPermissionToolbar)}px`);
}

export function bindPermissionAssignmentStickyLayout() {
    if (typeof window.__permissionAssignmentStickySchedule === 'function') {
        window.__permissionAssignmentStickySchedule();
        return;
    }

    let frame = null;
    const schedule = () => {
        if (frame !== null) return;
        frame = window.requestAnimationFrame(() => {
            frame = null;
            syncStickyLayout();
        });
    };
    window.__permissionAssignmentStickySchedule = schedule;
    schedule();
    window.addEventListener('resize', schedule, { passive: true });
    window.addEventListener('orientationchange', schedule, { passive: true });
    document.getElementById('permissionAssignmentTabs')?.addEventListener('shown.bs.tab', schedule);
    if (window.ResizeObserver) {
        const observer = new ResizeObserver(schedule);
        [
            '#roleListCard',
            '#roleListCard > .card-header',
            '#permissionListCard > .card-header',
            '#permission-assignment-table_wrapper .dt-top',
            '#individualUserListCard',
            '#individualUserListCard > .card-header',
            '#individualPermissionCard > .card-header',
            '#individual-user-table_wrapper .dt-top',
            '#individual-permission-table_wrapper .dt-top',
        ]
            .map(selector => document.querySelector(selector)).filter(Boolean).forEach(element => observer.observe(element));
        window.__permissionAssignmentStickyObserver = observer;
    }
    if (window.MutationObserver) {
        const mutationObserver = new MutationObserver(schedule);
        mutationObserver.observe(document.getElementById('rolePermissionPage'), { childList: true, subtree: true });
        window.__permissionAssignmentStickyMutationObserver = mutationObserver;
    }
}

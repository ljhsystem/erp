(function () {

    'use strict';

    window.__erpSidebarLayoutSync = window.__erpSidebarLayoutSync || false;
    let layoutAnimTimer = null;
    let layoutSyncCleanup = null;

    function saveSidebarCollapsed(collapsed) {
        fetch('/api/settings/system/user-settings/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                page_key: 'layout-sidebar',
                setting_type: 'VIEW',
                settings_json: {
                    collapsed: collapsed === true,
                    updatedAt: new Date().toISOString(),
                },
            }),
        }).catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function () {

        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.getElementById('sidebar-toggle-btn');

        if(!sidebar) return;

        applyRouteContext(sidebar);
        window.requestAnimationFrame(() => sidebar.classList.remove('sidebar-initializing'));

        sidebar.addEventListener('click', function(e){

            if(!sidebar.contains(e.target)) return;

            const link = e.target.closest('.nav-link.toggle');
            if(!link) return;

            const targetId = link.getAttribute('href');
            if(!targetId || !targetId.startsWith('#')) return;

            const menu = sidebar.querySelector(targetId);
            if(!menu) return;

            e.preventDefault();

            const isOpen = menu.classList.contains('show');
            const opened = sidebar.querySelectorAll('.collapse.show');

            opened.forEach(function(el){

                if(el !== menu){
                    setMenuOpen(sidebar, el, false);
                }

            });

            if(isOpen){
                setMenuOpen(sidebar, menu, false);
            }else{
                setMenuOpen(sidebar, menu, true);
            }

        });

        if(!toggleBtn) return;

        if(sidebar.classList.contains('collapsed')){
            toggleBtn.innerHTML = '<i class="bi bi-chevron-right"></i>';
            document.body.classList.add('is-sidebar-collapsed');
        }else{
            toggleBtn.innerHTML = '<i class="bi bi-chevron-left"></i>';
        }

        toggleBtn.addEventListener('click', function(){

            sidebar.classList.toggle('collapsed');

            const collapsed = sidebar.classList.contains('collapsed');

            document.body.classList.toggle('is-sidebar-collapsed', collapsed);

            saveSidebarCollapsed(collapsed);

            toggleBtn.innerHTML = collapsed
                ? '<i class="bi bi-chevron-right"></i>'
                : '<i class="bi bi-chevron-left"></i>';

            startLayoutSync();

        });

    });

    function normalizePath(value){
        let path = String(value || '/').split('?')[0].split('#')[0].trim();
        if(!path.startsWith('/')) path = '/' + path;
        if(path.length > 1) path = path.replace(/\/+$/, '');
        return path || '/';
    }

    function linkPath(link){
        const href = link.getAttribute('href') || '';
        if(href === '' || href.startsWith('#')) return '';

        try {
            return normalizePath(new URL(href, window.location.origin).pathname);
        } catch (error) {
            return normalizePath(href);
        }
    }

    function pathMatches(hrefPath, currentPath){
        if(!hrefPath) return false;
        if(currentPath === hrefPath) return true;
        return hrefPath !== '/' && currentPath.startsWith(hrefPath + '/');
    }

    function canonicalLedgerPath(path){
        const aliases = {
            '/ledger/opening-balances': '/ledger/settings/opening-balances',
            '/ledger/data': '/ledger/data/list',
            '/ledger/transactions': '/ledger/transactions/input',
            '/ledger/transactions/create': '/ledger/transactions/input',
            '/ledger/transaction': '/ledger/transactions/input',
            '/ledger/transaction/create': '/ledger/transactions/input',
            '/ledger/journal': '/ledger/vouchers/input',
        };

        return aliases[path] || path;
    }

    function findBestActiveLink(sidebar, currentPath){
        let best = null;

        sidebar.querySelectorAll('.nav-link:not(.toggle)[href]').forEach((link) => {
            const hrefPath = linkPath(link);
            if(!pathMatches(hrefPath, currentPath)) return;
            if(!best || hrefPath.length > best.path.length){
                best = { link, path: hrefPath };
            }
        });

        return best ? best.link : null;
    }

    function setMenuOpen(sidebar, menu, open){
        if(!menu) return;

        menu.classList.toggle('show', open);
        menu.closest('li')?.classList.toggle('is-expanded', open);

        const toggle = sidebar.querySelector('[href="#' + menu.id + '"]');
        if(toggle){
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.classList.remove('selected');
        }
    }

    function applyRouteContext(sidebar){
        const currentPath = canonicalLedgerPath(normalizePath(sidebar.dataset.currentPath || window.location.pathname));
        const activeLink = findBestActiveLink(sidebar, currentPath);
        const activeMenu = activeLink?.closest('.collapse') || null;

        sidebar.querySelectorAll('.nav-link.active').forEach((link) => {
            if(link === activeLink) return;
            link.classList.remove('active');
            link.removeAttribute('aria-current');
        });

        if(activeLink && !activeLink.classList.contains('active')){
            activeLink.classList.add('active');
            activeLink.setAttribute('aria-current', 'page');
        }

        if(activeMenu){
            const toggle = sidebar.querySelector('[href="#' + activeMenu.id + '"]');
            if(!activeMenu.classList.contains('show') || toggle?.getAttribute('aria-expanded') !== 'true'){
                setMenuOpen(sidebar, activeMenu, true);
            }
        }
    }

    function startLayoutSync(){

        stopLayoutSync();
        window.__erpSidebarLayoutSync = true;

        const sidebar = document.querySelector('.sidebar');
        const duration = 220;
        let finished = false;

        const completeLayoutSync = () => {
            if(finished) return;
            finished = true;

            if(layoutSyncCleanup){
                layoutSyncCleanup();
                layoutSyncCleanup = null;
            }

            fireLayoutResize();

            window.setTimeout(() => {
                window.__erpSidebarLayoutSync = false;
            }, 0);
        };

        const onTransitionEnd = (event) => {
            const propertyName = String(event?.propertyName || '').trim();

            if(event?.target === sidebar && propertyName === 'transform'){
                completeLayoutSync();
            }
        };

        sidebar?.addEventListener('transitionend', onTransitionEnd);

        layoutAnimTimer = setTimeout(() => {
            completeLayoutSync();
        }, duration + 80);

        layoutSyncCleanup = () => {
            sidebar?.removeEventListener('transitionend', onTransitionEnd);

            if(layoutAnimTimer){
                clearTimeout(layoutAnimTimer);
                layoutAnimTimer = null;
            }
        };
    }

    function stopLayoutSync(){
        window.__erpSidebarLayoutSync = false;

        if(layoutSyncCleanup){
            layoutSyncCleanup();
            layoutSyncCleanup = null;
        }

        if(layoutAnimTimer){
            clearTimeout(layoutAnimTimer);
            layoutAnimTimer = null;
        }
    }

    function fireLayoutResize(){
        document.dispatchEvent(new CustomEvent('sidebar:toggled'));
    }

})();

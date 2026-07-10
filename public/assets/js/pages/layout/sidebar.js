// /assets/js/pages/layout/sidebar.js
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

        /* ===============================
           SIDEBAR ACCORDION MENU
        =============================== */

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

        /* ===============================
           SIDEBAR COLLAPSE BUTTON
        =============================== */

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

    function routeMenuId(currentPath){
        const groups = [
            ['/ledger/settings', 'menu-ledger-basic'],
            ['/ledger/accounts', 'menu-ledger-basic'],
            ['/ledger/opening-balances', 'menu-ledger-basic'],
            ['/ledger/data', 'menu-ledger-data'],
            ['/ledger/transactions', 'menu-ledger-voucher'],
            ['/ledger/transaction', 'menu-ledger-voucher'],
            ['/ledger/vouchers/input', 'menu-ledger-voucher'],
            ['/ledger/journal', 'menu-ledger-voucher'],
            ['/ledger/vouchers', 'menu-ledger-voucher'],
            ['/ledger/funds', 'menu-ledger-funds'],
            ['/ledger/book', 'menu-ledger-book'],
            ['/ledger/financial', 'menu-ledger-financial'],
            ['/ledger/assets', 'menu-ledger-asset'],
            ['/ledger/tax', 'menu-ledger-tax'],
            ['/site', null],
        ];

        let matched = null;
        groups.forEach(([prefix, menuId]) => {
            const normalized = normalizePath(prefix);
            if(pathMatches(normalized, currentPath) && (!matched || normalized.length > matched.prefix.length)){
                matched = { prefix: normalized, menuId };
            }
        });

        return matched ? matched.menuId : null;
    }

    function canonicalLedgerPath(path){
        const aliases = {
            '/ledger/accounts': '/ledger/settings/accounts',
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
        const routeMenu = routeMenuId(currentPath);
        const activeMenu = activeLink?.closest('.collapse') || (routeMenu ? sidebar.querySelector('#' + routeMenu) : null);

        sidebar.querySelectorAll('.nav-link.active').forEach((link) => {
            link.classList.remove('active');
            link.removeAttribute('aria-current');
        });

        if(activeLink){
            activeLink.classList.add('active');
            activeLink.setAttribute('aria-current', 'page');
        }

        sidebar.querySelectorAll('.collapse').forEach((menu) => {
            setMenuOpen(sidebar, menu, menu === activeMenu);
        });
    }

    function startLayoutSync(){

        stopLayoutSync();
        window.__erpSidebarLayoutSync = true;

        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const duration = 360;
        let finished = false;

        const completeLayoutSync = () => {
            if(finished) return;
            finished = true;

            if(layoutSyncCleanup){
                layoutSyncCleanup();
                layoutSyncCleanup = null;
            }

            adjustAllDataTables();
            fireLayoutResize();

            window.setTimeout(() => {
                window.__erpSidebarLayoutSync = false;
            }, 0);
        };

        const onTransitionEnd = (event) => {
            const propertyName = String(event?.propertyName || '').trim();

            if(event?.target === sidebar && propertyName === 'width'){
                completeLayoutSync();
                return;
            }

            if(event?.target === mainContent && (propertyName === 'width' || propertyName === 'margin-left')){
                completeLayoutSync();
            }
        };

        sidebar?.addEventListener('transitionend', onTransitionEnd);
        mainContent?.addEventListener('transitionend', onTransitionEnd);

        layoutAnimTimer = setTimeout(() => {
            completeLayoutSync();
        }, duration + 80);

        layoutSyncCleanup = () => {
            sidebar?.removeEventListener('transitionend', onTransitionEnd);
            mainContent?.removeEventListener('transitionend', onTransitionEnd);

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

    function adjustAllDataTables(){

        if(!window.jQuery) return;

        const $ = window.jQuery;

        if(!$.fn.DataTable) return;

        const tables = $.fn.dataTable.tables({ visible: true, api: true });
        if(!tables?.every) return;

        tables.every(function () {
            const table = this;
            if (table?.__dtTableSettings?.refreshLayout) {
                table.__dtTableSettings.refreshLayout({ draw: true });
                return;
            }

            table.columns.adjust().draw(false);
        });
    }

    function fireLayoutResize(){
        window.dispatchEvent(new Event('resize'));
        document.dispatchEvent(new CustomEvent('sidebar:toggled'));
    }

})();

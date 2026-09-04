(function () {
    'use strict';

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    function setDefaultMonth(input) {
        if (!input || input.value) return;
        var now = new Date();
        input.value = now.getFullYear() + '-' + pad2(now.getMonth() + 1);
    }

    function initChart() {
        if (typeof Chart === 'undefined') return null;
        var cvs = document.getElementById('kpiChart');
        if (!cvs) return null;

        try { cvs.removeAttribute('height'); } catch (e) { }

        var ctx = cvs.getContext('2d');
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['영업부', '기술부', '관리부', '현장부'],
                datasets: [{
                    label: '실적 금액 ($)',
                    data: [8700, 5200, 4300, 9500],
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#6f42c1']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    function makeLayoutAdjuster(chart) {
        var root = document.documentElement;
        var kpiMain = document.querySelector('.kpi-main');
        var EXTRA_VAR = '--kpi-extra-vspace';
        var defaultExtra = 20;
        var lastChartHeight = 0;

        function getNumber(value, fallback) {
            if (!value && value !== 0) return fallback;
            var n = Number(String(value).replace('px', '').trim());
            return isNaN(n) ? fallback : n;
        }

        function measureAndApply() {
            if (!kpiMain) return;
            var headerEl = document.querySelector('.top-nav');
            var footerEl = document.querySelector('.footer');
            var sidebarEl = document.querySelector('aside') || document.querySelector('.sidebar') || document.querySelector('#sidebar');

            var computed = getComputedStyle(root);
            var defaultHeader = getNumber(computed.getPropertyValue('--header-height'), 40);
            var defaultFooter = getNumber(computed.getPropertyValue('--footer-height'), 44);
            var defaultSidebar = getNumber(computed.getPropertyValue('--sidebar-width'), 240);
            var defaultSidebarCollapsed = getNumber(computed.getPropertyValue('--sidebar-width-collapsed'), 16);
            var extra = getNumber(computed.getPropertyValue(EXTRA_VAR), defaultExtra);

            var headerH = headerEl ? Math.round(headerEl.getBoundingClientRect().height) : defaultHeader;
            var footerH = footerEl ? Math.round(footerEl.getBoundingClientRect().height) : defaultFooter;
            var sidebarW = sidebarEl ? Math.round(sidebarEl.getBoundingClientRect().width) : defaultSidebar;

            var isCollapsed = document.body.classList.contains('is-sidebar-collapsed') || document.body.classList.contains('is-sidebar-hidden');
            var appliedSidebarW = isCollapsed ? defaultSidebarCollapsed : sidebarW;

            var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
            var vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);

            var maxWidthPx = Math.max(0, vw - appliedSidebarW);
            var maxHeightPx = Math.max(0, vh - headerH - footerH - extra);

            root.style.setProperty('--header-height', headerH + 'px');
            root.style.setProperty('--footer-height', footerH + 'px');
            root.style.setProperty('--sidebar-width', sidebarW + 'px');
            root.style.setProperty('--sidebar-width-collapsed', defaultSidebarCollapsed + 'px');
            root.style.setProperty(EXTRA_VAR, extra + 'px');

            kpiMain.style.maxWidth = maxWidthPx + 'px';
            kpiMain.style.width = '100%';
            kpiMain.style.maxHeight = maxHeightPx + 'px';
            kpiMain.style.overflowX = 'hidden';
            kpiMain.style.overflowY = 'auto';
            kpiMain.style.paddingBottom = (footerH + 12) + 'px';

            var cvs = document.getElementById('kpiChart');
            if (cvs) {
                var cardBody = cvs.closest('.card-body') || cvs.parentElement;

                var target = Math.max(180, Math.min(520, Math.floor(maxHeightPx * 0.35)));

                if (cardBody) {

                    var prev = parseInt(cardBody.dataset._chartHeight || '0', 10);
                    if (Math.abs(prev - target) > 1) {
                        cardBody.style.minHeight = target + 'px';
                        cardBody.style.height = target + 'px';

                        cvs.style.display = 'block';
                        cvs.style.width = '100%';
                        cvs.style.height = target + 'px';
                        try { cvs.removeAttribute('height'); } catch (e) { }
                        cardBody.dataset._chartHeight = String(target);
                        lastChartHeight = target;
                    }
                } else {

                    cvs.style.height = Math.max(180, Math.floor(maxHeightPx * 0.35)) + 'px';
                }

                try {
                    if (chart && typeof chart.resize === 'function') {
                        chart.resize();
                    }
                } catch (e) { /* ignore */ }
            }
        }

        var bodyObserver = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].attributeName === 'class') {
                    measureAndApply();
                    break;
                }
            }
        });
        bodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });

        var resizeTimer = null;
        function debounced() {
            if (resizeTimer) clearTimeout(resizeTimer);
            resizeTimer = setTimeout(measureAndApply, 120);
        }
        window.addEventListener('resize', debounced, { passive: true });
        window.addEventListener('orientationchange', debounced, { passive: true });
        window.addEventListener('load', measureAndApply);
        document.addEventListener('DOMContentLoaded', measureAndApply);


        measureAndApply();

        return {
            update: measureAndApply,
            disconnect: function () { bodyObserver.disconnect(); window.removeEventListener('resize', debounced); }
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        var monthEl = document.getElementById('monthSelect');
        var btn = document.getElementById('kpiSearchBtn');

        setDefaultMonth(monthEl);
        var chart = initChart();

        var adjuster = makeLayoutAdjuster(chart);

        if (btn) {
            btn.addEventListener('click', function () {
                if (chart && monthEl && monthEl.value) {
                    chart.options.plugins.title = chart.options.plugins.title || {};
                    chart.options.plugins.title.display = true;
                    chart.options.plugins.title.text = '기준월: ' + monthEl.value;
                    chart.update();
                }
                if (adjuster && typeof adjuster.update === 'function') adjuster.update();
            });
        }
    });
})();

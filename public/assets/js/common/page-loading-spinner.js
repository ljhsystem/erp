// Common page-entry loading spinner.
(() => {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};
    const AppLoading = window.AppLoading || window.AppCore.AppLoading;
    const FALLBACK_FAILSAFE_DELAY = 12000;
    const DEFAULT_MESSAGE = '\uD398\uC774\uC9C0 \uC9C4\uC785 \uB85C\uB529 \uC911\uC785\uB2C8\uB2E4.';

    const fallbackHolds = new Set(['page']);
    let message = DEFAULT_MESSAGE;
    let showTimer = 0;
    let hideTimer = 0;
    let visibleAt = 0;

    function overlay() {
        return document.getElementById('global-loading-overlay');
    }

    function messageNode(root) {
        let node = root.querySelector('.page-loading-message');
        if (node) return node;

        node = root.querySelector('.global-loading-message');
        if (node) return node;

        node = document.createElement('div');
        node.className = 'page-loading-message';
        root.appendChild(node);
        return node;
    }

    function setMessage(nextMessage) {
        message = String(nextMessage || DEFAULT_MESSAGE).trim() || DEFAULT_MESSAGE;
        const root = overlay();
        if (!root) return;
        messageNode(root).textContent = message;
    }

    function showNow() {
        const root = overlay();
        if (!root || fallbackHolds.size === 0) return;

        window.clearTimeout(hideTimer);
        visibleAt = visibleAt || performance.now();
        root.style.display = 'flex';
        root.classList.add('is-active');
        root.setAttribute('aria-busy', 'true');
        root.setAttribute('aria-live', 'polite');
        messageNode(root).textContent = message;
    }

    function hideNow() {
        const root = overlay();
        if (!root) return;

        root.classList.remove('is-active');
        root.removeAttribute('aria-busy');
        if (root.style.display === 'flex') {
            root.style.display = '';
        }
        visibleAt = 0;
    }

    function scheduleShow() {
        if (!fallbackHolds.size || showTimer) return;
        showTimer = window.setTimeout(() => {
            showTimer = 0;
            showNow();
        }, 500);
    }

    function scheduleHide() {
        window.clearTimeout(showTimer);
        showTimer = 0;

        const root = overlay();
        if (!root || !root.classList.contains('is-active')) {
            hideNow();
            return;
        }

        const elapsed = performance.now() - visibleAt;
        const delay = Math.max(0, 320 - elapsed);
        window.clearTimeout(hideTimer);
        hideTimer = window.setTimeout(hideNow, delay);
    }

    function fallbackShow(nextMessage = DEFAULT_MESSAGE) {
        const token = `fallback:${Date.now()}:${Math.random()}`;
        fallbackHolds.add(token);
        setMessage(nextMessage);
        scheduleShow();
        return () => fallbackRelease(token);
    }

    function fallbackRelease(token = 'manual') {
        fallbackHolds.delete(String(token));
        if (fallbackHolds.size === 0) {
            scheduleHide();
        }
    }

    function fallbackHide(token = 'manual') {
        if (token === 'all') {
            fallbackHolds.clear();
            scheduleHide();
            return;
        }
        fallbackRelease(token);
    }

    function fallbackMarkReady() {
        fallbackRelease('page');
    }

    function adaptFromAppLoading() {
        const shim = {
            hold: (token = 'manual', nextMessage = DEFAULT_MESSAGE) => {
                if (!AppLoading?.hold) return fallbackShow(nextMessage);
                return AppLoading.hold(String(token), nextMessage);
            },
            release: (token = 'manual') => {
                if (!AppLoading?.release) return fallbackRelease(token);
                return AppLoading.release(String(token));
            },
            show: (nextMessage = DEFAULT_MESSAGE) => {
                if (!AppLoading?.show) return fallbackShow(nextMessage);
                return AppLoading.show(nextMessage);
            },
            hide: (token = 'manual') => {
                if (!AppLoading?.hide) return fallbackHide(token);
                return AppLoading.hide(token);
            },
            markReady: () => {
                if (!AppLoading?.markReady) return fallbackMarkReady();
                return AppLoading.markReady();
            },
            setMessage,
            isActive() {
                if (typeof AppLoading?.isActive === 'function') {
                    return AppLoading.isActive();
                }
                return fallbackHolds.size > 0;
            },
            __compatMode: 'AppLoading'
        };

        window.PageLoadingSpinner = shim;
        AppCore.pageLoading = shim;
        return shim;
    }

    const legacy = adaptFromAppLoading();

    const markReady = legacy?.markReady || fallbackMarkReady;
    if (document.readyState === 'complete') {
        window.requestAnimationFrame(markReady);
    } else {
        window.addEventListener('load', () => {
            window.requestAnimationFrame(markReady);
        }, { once: true });
    }

    window.setTimeout(() => {
        fallbackRelease('page');
    }, FALLBACK_FAILSAFE_DELAY);
})();


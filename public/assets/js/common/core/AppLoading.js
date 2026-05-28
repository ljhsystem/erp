(function () {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};

    if (AppCore.AppLoading) {
        window.AppLoading = AppCore.AppLoading;
        return;
    }

    const SHOW_DELAY = 500;
    const MIN_VISIBLE = 320;
    const DEFAULT_MESSAGE = '\uCC98\uB9AC \uC911\uC785\uB2C8\uB2E4...';

    const holds = new Set(['page']);
    let visibleAt = 0;
    let message = DEFAULT_MESSAGE;
    let showTimer = 0;
    let hideTimer = 0;
    let showToken = 0;

    function overlay() {
        return document.getElementById('global-loading-overlay');
    }

    function messageNode(root) {
        let node = root.querySelector('.global-loading-message');
        if (node) return node;

        node = document.createElement('div');
        node.className = 'global-loading-message';
        root.appendChild(node);
        return node;
    }

    function showNow() {
        const root = overlay();
        if (!root || holds.size === 0) return;

        showTimer = 0;
        root.style.display = 'flex';
        root.classList.add('is-active');
        root.setAttribute('aria-busy', 'true');
        root.setAttribute('aria-live', 'polite');
        messageNode(root).textContent = message;
        visibleAt = performance.now();
    }

    function hideNow() {
        const root = overlay();
        if (!root) return;

        root.classList.remove('is-active');
        root.removeAttribute('aria-busy');
        if (holds.size === 0) {
            root.style.display = 'none';
            visibleAt = 0;
        }
    }

    function scheduleShow() {
        if (holds.size === 0 || showTimer) return;
        showToken += 1;
        const localToken = showToken;

        showTimer = window.setTimeout(() => {
            if (localToken !== showToken) return;
            showNow();
        }, SHOW_DELAY);
    }

    function scheduleHide() {
        window.clearTimeout(showTimer);
        showTimer = 0;

        if (!overlay()) return;

        const elapsed = visibleAt ? (performance.now() - visibleAt) : 0;
        const delay = Math.max(0, MIN_VISIBLE - elapsed);

        window.clearTimeout(hideTimer);
        hideTimer = window.setTimeout(hideNow, delay);
    }

    function setMessage(nextMessage) {
        message = String(nextMessage || DEFAULT_MESSAGE).trim() || DEFAULT_MESSAGE;
        const root = overlay();
        if (!root) return;
        const node = messageNode(root);
        if (node) node.textContent = message;
    }

    function show(nextMessage = DEFAULT_MESSAGE) {
        const token = `manual:${Date.now()}:${Math.random()}`;
        holds.add(token);
        setMessage(nextMessage);
        scheduleShow();
        return () => release(token);
    }

    function release(token = 'manual') {
        holds.delete(String(token));
        if (holds.size === 0) {
            scheduleHide();
        }
    }

    function markReady() {
        release('page');
    }

    function showLoading(nextMessage = DEFAULT_MESSAGE) {
        show(nextMessage);
    }

    function hideLoading(token = 'manual') {
        release(token);
    }

    const AppLoading = {
        hold: show,
        release,
        show: showLoading,
        hide: hideLoading,
        markReady,
        setMessage,
        isActive: () => holds.size > 0
    };

    AppCore.showLoading = showLoading;
    AppCore.hideLoading = hideLoading;
    AppCore.showGlobalLoading = showLoading;
    AppCore.hideGlobalLoading = hideLoading;
    AppCore.pageLoading = AppCore.pageLoading || AppLoading;
    AppCore.AppLoading = AppLoading;
    window.AppLoading = AppLoading;

})();

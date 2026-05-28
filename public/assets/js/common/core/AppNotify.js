(function () {
    'use strict';

    const AppCore = window.AppCore || {};

    if (AppCore.AppNotify) {
        window.AppNotify = AppCore.AppNotify;
        if (!window.AppCore.notify && typeof AppCore.AppNotify.notify === 'function') {
            AppCore.notify = AppCore.AppNotify.notify;
        }
        return;
    }

    if (window.AppNotify) {
        AppCore.AppNotify = window.AppNotify;
        if (!window.AppCore.notify && typeof window.AppNotify.notify === 'function') {
            AppCore.notify = window.AppNotify.notify;
        }
        return;
    }

    // Deprecated fallback bridge for environments where notification.js is not loaded yet.
    // Keep behavior stable with legacy global API expectation.
    if (!window.AppCore || !AppCore.notify) {
        window.AppCore = AppCore;
        AppCore.notify = function (type = 'info', message = '', opts = {}) {
            const text = String(message ?? '');
            const level = type === 'error' ? 'error' : 'warn';
            if (typeof console[level] === 'function') {
                console[level](text);
            }
            return undefined;
        };
    }

    if (!window.AppNotify) {
        window.AppNotify = {
            notify: AppCore.notify,
        };
    }
    AppCore.AppNotify = AppCore.AppNotify || window.AppNotify;
})();

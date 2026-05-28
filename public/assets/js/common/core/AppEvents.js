(function () {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};

    if (AppCore.AppEvents) {
        window.AppEvents = AppCore.AppEvents;
        return;
    }

    const listeners = new WeakMap();
    const stack = [];

    function normalizeOptions(options) {
        if (typeof options === 'boolean') {
            return { capture: options };
        }

        return options || {};
    }

    function key(target, type, handler, options) {
        const targetKey = typeof target === 'string' ? target : target;
        return `${String(type)}::${String(handler)}::${JSON.stringify(normalizeOptions(options))}`;
    }

    function on(target, type, handler, options = false) {
        if (!target || !type || typeof handler !== 'function') {
            return () => {};
        }

        target.addEventListener(type, handler, options);

        let map = listeners.get(target);
        if (!map) {
            map = new Map();
            listeners.set(target, map);
        }

        const normalized = map.get(type) || [];
        normalized.push({ handler, options });
        map.set(type, normalized);

        return () => off(target, type, handler, options);
    }

    function off(target, type, handler, options = false) {
        if (!target || !type || typeof handler !== 'function') {
            return;
        }

        target.removeEventListener(type, handler, options);

        const map = listeners.get(target);
        if (!map) {
            return;
        }

        const normalized = map.get(type) || [];
        map.set(type, normalized.filter((entry) => entry.handler !== handler));
    }

    function once(target, type, handler, options = false) {
        const wrapped = function (event) {
            off(target, type, wrapped, options);
            handler(event);
        };
        on(target, type, wrapped, options);
    }

    function onShown(handler, options = false) {
        return on(document, 'shown.bs.modal', handler, options);
    }

    function onHidden(handler, options = false) {
        return on(document, 'hidden.bs.modal', handler, options);
    }

    function pushEscape(handler) {
        if (typeof handler !== 'function') return;
        stack.push(handler);
    }

    function popEscape(handler) {
        if (typeof handler !== 'function') return;
        const index = stack.lastIndexOf(handler);
        if (index >= 0) {
            stack.splice(index, 1);
        }
    }

    function triggerEscape() {
        for (let i = stack.length - 1; i >= 0; i -= 1) {
            const fn = stack[i];
            const handled = fn();
            if (handled !== false) {
                return true;
            }
        }

        return false;
    }

    const AppEvents = {
        on,
        off,
        once,
        key,
        onShown,
        onHidden,
        onDocument: (type, handler, options = false) => on(document, type, handler, options),
        onWindow: (type, handler, options = false) => on(window, type, handler, options),
        onJQDocument: (type, handler, options = false) => {
            if (typeof window.jQuery === 'undefined' || !window.jQuery.fn?.on) {
                return () => {};
            }
            const $ = window.jQuery;
            $(document).on(type, handler, options);
            return () => $(document).off(type, handler, options);
        },
        pushEscape,
        popEscape,
        triggerEscape,
    };

    AppCore.AppEvents = AppEvents;
    window.AppEvents = AppEvents;
    window.AppEventBus = AppEvents;
})();

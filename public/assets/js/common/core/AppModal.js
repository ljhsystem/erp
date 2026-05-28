(function () {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};

    if (AppCore.AppModal) {
        window.AppModal = AppCore.AppModal;
        return;
    }

    const events = window.AppEvents || {};

    function onShown(handler, options = false) {
        if (typeof handler !== 'function') return () => {};
        if (events.onShown) {
            return events.onShown(handler, options);
        }
        const wrapped = (event) => handler(event);
        document.addEventListener('shown.bs.modal', wrapped, options);
        return () => document.removeEventListener('shown.bs.modal', wrapped, options);
    }

    function onHidden(handler, options = false) {
        if (typeof handler !== 'function') return () => {};
        if (events.onHidden) {
            return events.onHidden(handler, options);
        }
        const wrapped = (event) => handler(event);
        document.addEventListener('hidden.bs.modal', wrapped, options);
        return () => document.removeEventListener('hidden.bs.modal', wrapped, options);
    }

    function getModalInstance(node, options = {}) {
        if (!node || !node.classList) {
            return null;
        }

        if (!window.bootstrap?.Modal?.getOrCreateInstance) {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(node, options);
    }

    function show(node, options = {}) {
        const instance = getModalInstance(node, options);
        if (!instance) return null;
        instance.show();
        return instance;
    }

    function hide(node, options = {}) {
        const instance = getModalInstance(node, options);
        if (!instance) return null;
        instance.hide();
        return instance;
    }

    const AppModal = {
        onShown,
        onHidden,
        getModalInstance,
        show,
        hide
    };

    AppCore.AppModal = AppModal;
    window.AppModal = AppModal;
})();

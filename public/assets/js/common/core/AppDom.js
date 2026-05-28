(function () {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};

    if (AppCore.AppDom) {
        window.AppDom = AppCore.AppDom;
        return;
    }

    function stripHtml(value) {
        const text = String(value ?? '');
        if (!text.includes('<')) {
            return text.trim();
        }

        const container = document.createElement('div');
        container.innerHTML = text;
        return (container.textContent || '').trim();
    }

    function queryOne(selector, root = document) {
        return (root || document).querySelector(selector);
    }

    function findAll(selector, root = document) {
        return Array.from((root || document).querySelectorAll(selector));
    }

    const AppDom = {
        stripHtml,
        queryOne,
        findAll,
    };

    AppCore.AppDom = AppDom;
    window.AppDom = AppDom;
})();

// Path: /public/assets/js/common/esc-manager.js
(function () {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};
    const AppEvents = window.AppEvents || null;
    const handlers = [];

    AppCore.ESCStack = AppCore.ESCStack || {
        handlers,
        push(fn) {
            if (typeof fn !== 'function') return;
            this.handlers.push(fn);
        },
        remove(fn) {
            const normalized = this.handlers.filter((handler) => handler !== fn);
            this.handlers.length = 0;
            normalized.forEach((handler) => this.handlers.push(handler));
        },
        trigger() {
            for (let i = this.handlers.length - 1; i >= 0; i -= 1) {
                const handler = this.handlers[i];

                if (!handler) {
                    this.handlers.splice(i, 1);
                    continue;
                }

                const handled = handler();
                if (handled === false) {
                    this.handlers.splice(i, 1);
                    continue;
                }

                return true;
            }

            return false;
        },
    };

    const EscapeCore = {
        push: (fn) => {
            if (typeof fn !== 'function') return;

            if (AppEvents?.pushEscape) {
                AppEvents.pushEscape(fn);
                return;
            }

            AppCore.ESCStack.push(fn);
        },
        pop: (fn) => {
            if (typeof fn !== 'function') return;

            if (AppEvents?.popEscape) {
                AppEvents.popEscape(fn);
                return;
            }

            AppCore.ESCStack.remove(fn);
        },
        trigger: () => {
            if (AppEvents?.triggerEscape) {
                return AppEvents.triggerEscape();
            }
            return AppCore.ESCStack.trigger();
        },
    };

    const onKeydown = AppEvents?.onWindow
        ? (handler) => AppEvents.onWindow('keydown', handler, true)
        : (handler) => {
            window.addEventListener('keydown', handler, true);
            return () => window.removeEventListener('keydown', handler, true);
        };

    const unbindEscape = onKeydown(function (event) {
        if (event.key !== 'Escape') return;

        if (closeOpenSelect2Dropdowns()) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            return;
        }

        const handled = EscapeCore.trigger();
        if (handled) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            return;
        }

        const openModal = getTopVisibleModal();
        if (!openModal) return;

        const beforeCloseEvent = new CustomEvent('esc:modal-before-close', {
            cancelable: true,
            detail: { modal: openModal }
        });

        openModal.dispatchEvent(beforeCloseEvent);

        if (beforeCloseEvent.defaultPrevented) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            return;
        }

        bootstrap?.Modal?.getOrCreateInstance(openModal, { focus: false })?.hide();
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
    });

    function getTopVisibleModal() {
        const modals = Array.from(document.querySelectorAll('.modal.show'));

        if (modals.length === 0) return null;

        return modals
            .map((modal, index) => ({
                modal,
                index,
                zIndex: Number.parseInt(window.getComputedStyle(modal).zIndex, 10) || 0
            }))
            .sort((a, b) => {
                if (a.zIndex !== b.zIndex) return b.zIndex - a.zIndex;
                return b.index - a.index;
            })[0].modal;
    }

    function closeOpenSelect2Dropdowns() {
        if (!document.querySelector('.select2-container--open')) {
            return false;
        }

        const $ = window.jQuery || window.$;
        if (!$?.fn?.select2) {
            return false;
        }

        document.querySelectorAll('select.select2-hidden-accessible').forEach((select) => {
            try {
                $(select).select2('close');
            } catch (error) {
                console.warn('[esc-manager] Select2 close failed', error);
            }
        });

        return true;
    }

    window.ESCStack = AppCore.ESCStack;
    window.__escManagerOff = unbindEscape;
    window.EscapeManager = {
        push: EscapeCore.push,
        pop: EscapeCore.pop,
        trigger: EscapeCore.trigger,
        off: unbindEscape,
    };
})();

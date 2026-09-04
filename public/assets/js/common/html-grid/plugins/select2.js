function resolveAdapter(context = {}) {
    if (typeof context.adapters?.select2 === 'function') {
        return context.adapters.select2;
    }

    const jquery = context.window?.jQuery || context.window?.$;
    if (jquery?.fn?.select2) {
        return ({ editorElement, options = {} }) => {
            jquery(editorElement).select2(options);
            return {
                destroy() {
                    jquery(editorElement).select2('destroy');
                },
            };
        };
    }

    return null;
}

export function createSelect2Plugin(context = {}) {
    let adapter = null;
    let handle = null;
    let editorElement = null;
    let jquery = null;
    const tabTargets = new Set();

    function forwardTab(event) {
        if (event.key !== 'Tab' || !editorElement) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        try {
            jquery?.(editorElement).select2?.('close');
        } catch {}

        editorElement.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Tab',
            code: 'Tab',
            shiftKey: event.shiftKey === true,
            bubbles: true,
            cancelable: true,
        }));
    }

    function bindTabTarget(target) {
        if (!target || tabTargets.has(target)) {
            return;
        }
        target.addEventListener('keydown', forwardTab, true);
        tabTargets.add(target);
    }

    function unbindTabTargets() {
        tabTargets.forEach(target => target.removeEventListener('keydown', forwardTab, true));
        tabTargets.clear();
    }

    function bindSelect2TabBridge() {
        bindTabTarget(editorElement?.nextElementSibling?.querySelector?.('.select2-selection'));
        if (!jquery || !editorElement) {
            return;
        }
        jquery(editorElement).off('select2:open.htmlGridTab').on('select2:open.htmlGridTab', () => {
            setTimeout(() => bindTabTarget(document.querySelector('.select2-container--open .select2-search__field')), 0);
        });
    }

    return {
        init(nextContext = {}) {
            adapter = resolveAdapter({ ...context, ...nextContext });
        },
        mount(nextContext = {}) {
            editorElement = nextContext.editorElement;
            if (!editorElement || editorElement.tagName !== 'SELECT' || !adapter) {
                return;
            }

            jquery = nextContext.window?.jQuery || nextContext.window?.$ || null;

            handle = adapter({
                ...context,
                ...nextContext,
                options: nextContext.column?.meta?.pluginOptions?.select2 || {},
            }) || null;
            bindSelect2TabBridge();
        },
        update(nextContext = {}) {
            handle?.update?.({
                ...context,
                ...nextContext,
            });
        },
        destroy(nextContext = {}) {
            if (jquery && editorElement) {
                jquery(editorElement).off('select2:open.htmlGridTab');
            }
            unbindTabTargets();
            handle?.destroy?.({
                ...context,
                ...nextContext,
            });
            handle = null;
            editorElement = null;
            jquery = null;
        },
    };
}

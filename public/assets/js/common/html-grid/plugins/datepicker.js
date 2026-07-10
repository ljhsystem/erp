function resolveAdapter(context = {}) {
    if (typeof context.adapters?.datepicker === 'function') {
        return context.adapters.datepicker;
    }

    return null;
}

export function createDatepickerPlugin(context = {}) {
    let adapter = null;
    let handle = null;

    return {
        init(nextContext = {}) {
            adapter = resolveAdapter({ ...context, ...nextContext });
        },
        mount(nextContext = {}) {
            const editorElement = nextContext.editorElement;
            if (!editorElement || editorElement.tagName !== 'INPUT' || !adapter) {
                return;
            }

            handle = adapter({
                ...context,
                ...nextContext,
                options: nextContext.column?.meta?.pluginOptions?.datepicker || {},
            }) || null;
        },
        update(nextContext = {}) {
            handle?.update?.({
                ...context,
                ...nextContext,
            });
        },
        destroy(nextContext = {}) {
            handle?.destroy?.({
                ...context,
                ...nextContext,
            });
            handle = null;
        },
    };
}

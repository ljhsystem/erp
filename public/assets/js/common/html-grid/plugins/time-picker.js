function resolveAdapter(context = {}) {
    return typeof context.adapters?.timePicker === 'function'
        ? context.adapters.timePicker
        : null;
}

export function createTimePickerPlugin(context = {}) {
    let adapter = null;
    let handle = null;

    return {
        init(nextContext = {}) {
            adapter = resolveAdapter({ ...context, ...nextContext });
        },
        mount(nextContext = {}) {
            const editorElement = nextContext.editorElement;
            if (!editorElement || editorElement.tagName !== 'INPUT' || !adapter) return;
            handle = adapter({
                ...context,
                ...nextContext,
                options: nextContext.column?.meta?.pluginOptions?.timePicker || {},
            }) || null;
        },
        update(nextContext = {}) {
            handle?.update?.({ ...context, ...nextContext });
        },
        destroy(nextContext = {}) {
            handle?.destroy?.({ ...context, ...nextContext });
            handle = null;
        },
    };
}
function resolveAdapter(context = {}) {
    if (typeof context.adapters?.codePicker === 'function') {
        return context.adapters.codePicker;
    }

    return null;
}

export function createCodePickerPlugin(context = {}) {
    let adapter = null;
    let handle = null;

    return {
        init(nextContext = {}) {
            adapter = resolveAdapter({ ...context, ...nextContext });
        },
        mount(nextContext = {}) {
            if (!nextContext.editorElement || !adapter) {
                return;
            }

            handle = adapter({
                ...context,
                ...nextContext,
                options: nextContext.column?.meta?.pluginOptions?.codePicker || {},
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

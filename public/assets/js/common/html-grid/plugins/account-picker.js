function resolveAdapter(context = {}) {
    if (typeof context.adapters?.accountPicker === 'function') {
        return context.adapters.accountPicker;
    }

    return null;
}

export function createAccountPickerPlugin(context = {}) {
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
                options: nextContext.column?.meta?.pluginOptions?.accountPicker || {},
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

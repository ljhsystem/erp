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

    return {
        init(nextContext = {}) {
            adapter = resolveAdapter({ ...context, ...nextContext });
        },
        mount(nextContext = {}) {
            const editorElement = nextContext.editorElement;
            if (!editorElement || editorElement.tagName !== 'SELECT' || !adapter) {
                return;
            }

            handle = adapter({
                ...context,
                ...nextContext,
                options: nextContext.column?.meta?.pluginOptions?.select2 || {},
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

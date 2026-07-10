export function createCurrencyPlugin(context = {}) {
    let editorElement = null;

    return {
        init() {},
        mount(nextContext = {}) {
            editorElement = nextContext.editorElement || null;
            if (!editorElement) {
                return;
            }

            editorElement.dataset.htmlGridPluginCurrency = 'true';
            editorElement.inputMode = 'decimal';
        },
        update(nextContext = {}) {
            editorElement = nextContext.editorElement || editorElement;
            if (!editorElement) {
                return;
            }

            editorElement.dataset.htmlGridPluginCurrency = 'true';
        },
        destroy() {
            if (editorElement?.dataset) {
                delete editorElement.dataset.htmlGridPluginCurrency;
            }
            editorElement = null;
        },
    };
}

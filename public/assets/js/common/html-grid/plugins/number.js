export function createNumberPlugin(context = {}) {
    let editorElement = null;

    return {
        init() {},
        mount(nextContext = {}) {
            editorElement = nextContext.editorElement || null;
            if (!editorElement) {
                return;
            }

            editorElement.dataset.htmlGridPluginNumber = 'true';
            editorElement.inputMode = editorElement.inputMode || 'decimal';
        },
        update(nextContext = {}) {
            editorElement = nextContext.editorElement || editorElement;
            if (!editorElement) {
                return;
            }

            editorElement.dataset.htmlGridPluginNumber = 'true';
        },
        destroy() {
            if (editorElement?.dataset) {
                delete editorElement.dataset.htmlGridPluginNumber;
            }
            editorElement = null;
        },
    };
}

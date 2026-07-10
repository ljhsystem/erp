function resolveDocument(context = {}) {
    if (context.document) {
        return context.document;
    }
    if (context.host?.ownerDocument) {
        return context.host.ownerDocument;
    }
    if (typeof document !== 'undefined') {
        return document;
    }
    throw new Error('[html-grid] text editor requires document context.');
}

export function createTextEditor(context = {}) {
    const documentRef = resolveDocument(context);
    const element = documentRef.createElement('input');
    element.type = 'text';
    element.className = 'html-grid-editor html-grid-editor-text';

    const initialValue = context.value == null ? '' : String(context.value);
    element.value = initialValue;

    return {
        element,
        create() {
            return element;
        },
        mount(host) {
            if (host && !element.parentNode) {
                host.appendChild(element);
            }
            return element;
        },
        focus() {
            element.focus?.();
        },
        blur() {
            element.blur?.();
        },
        getValue() {
            return element.value;
        },
        setValue(value) {
            element.value = value == null ? '' : String(value);
            return element.value;
        },
        validate() {
            return { valid: true, message: '' };
        },
        isDirty() {
            return element.value !== initialValue;
        },
        destroy() {
            element.remove?.();
        },
    };
}

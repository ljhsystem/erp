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
    throw new Error('[html-grid] select editor requires document context.');
}

function normalizeOptions(options = []) {
    return (Array.isArray(options) ? options : []).map((option) => {
        if (option && typeof option === 'object' && !Array.isArray(option)) {
            return {
                value: String(option.value ?? ''),
                label: String(option.label ?? option.text ?? option.value ?? ''),
            };
        }

        return {
            value: String(option ?? ''),
            label: String(option ?? ''),
        };
    });
}

function renderOptions(selectEl, options = []) {
    selectEl.textContent = '';
    normalizeOptions(options).forEach((option) => {
        const optionEl = selectEl.ownerDocument.createElement('option');
        optionEl.value = option.value;
        optionEl.textContent = option.label;
        selectEl.appendChild(optionEl);
    });
}

export function createSelectEditor(context = {}) {
    const documentRef = resolveDocument(context);
    const element = documentRef.createElement('select');
    element.className = 'html-grid-editor html-grid-editor-select';

    const editorOptions = context.options && typeof context.options === 'object' ? context.options : {};
    renderOptions(element, editorOptions.options || context.column?.meta?.options || []);

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
            const exists = Array.from(element.options || []).some((option) => option.value === element.value);
            return exists || element.value === ''
                ? { valid: true, message: '' }
                : { valid: false, message: '선택 가능한 값이 아닙니다.' };
        },
        isDirty() {
            return element.value !== initialValue;
        },
        destroy() {
            element.remove?.();
        },
    };
}

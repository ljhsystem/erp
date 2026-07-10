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
    throw new Error('[html-grid] date editor requires document context.');
}

function normalizeDateValue(value) {
    const text = String(value || '').trim();
    if (text === '') {
        return '';
    }

    const normalized = text.replaceAll('.', '-').replaceAll('/', '-');
    const match = normalized.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    if (!match) {
        return text;
    }

    const year = match[1];
    const month = String(match[2]).padStart(2, '0');
    const day = String(match[3]).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function createDateEditor(context = {}) {
    const documentRef = resolveDocument(context);
    const element = documentRef.createElement('input');
    element.type = 'date';
    element.className = 'html-grid-editor html-grid-editor-date';

    const initialValue = normalizeDateValue(context.value);
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
            element.value = normalizeDateValue(element.value);
        },
        getValue() {
            return normalizeDateValue(element.value);
        },
        setValue(value) {
            element.value = normalizeDateValue(value);
            return element.value;
        },
        validate() {
            const normalized = normalizeDateValue(element.value);
            const valid = normalized === '' || /^\d{4}-\d{2}-\d{2}$/.test(normalized);
            return valid
                ? { valid: true, message: '' }
                : { valid: false, message: '날짜 형식이 아닙니다.' };
        },
        isDirty() {
            return element.value !== initialValue;
        },
        destroy() {
            element.remove?.();
        },
    };
}

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
    throw new Error('[html-grid] number editor requires document context.');
}

function parseNumericValue(value, allowNegative = true) {
    const text = String(value ?? '').replaceAll(',', '').trim();
    if (text === '') {
        return '';
    }

    const numericValue = Number(text);
    if (!Number.isFinite(numericValue)) {
        return '';
    }

    if (!allowNegative && numericValue < 0) {
        return '';
    }

    return text;
}

function formatNumericInput(value, options = {}) {
    const normalized = parseNumericValue(value, options.allowNegative !== false);
    if (normalized === '') {
        return '';
    }

    const numericValue = Number(normalized);
    const maximumFractionDigits = Number.isInteger(options.maximumFractionDigits) ? options.maximumFractionDigits : 6;
    const minimumFractionDigits = Number.isInteger(options.minimumFractionDigits) ? options.minimumFractionDigits : 0;

    return new Intl.NumberFormat(options.locale || 'ko-KR', {
        minimumFractionDigits,
        maximumFractionDigits,
    }).format(numericValue);
}

export function createNumberEditor(context = {}) {
    const documentRef = resolveDocument(context);
    const options = context.options && typeof context.options === 'object' ? context.options : {};
    const element = documentRef.createElement('input');
    element.type = 'text';
    element.className = 'html-grid-editor html-grid-editor-number';
    element.inputMode = 'decimal';

    const rawInitialValue = context.value == null ? '' : String(context.value);
    const initialValue = formatNumericInput(rawInitialValue, options);
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
            element.value = formatNumericInput(element.value, options);
        },
        getValue() {
            return parseNumericValue(element.value, options.allowNegative !== false);
        },
        setValue(value) {
            element.value = formatNumericInput(value, options);
            return element.value;
        },
        validate() {
            const parsed = parseNumericValue(element.value, options.allowNegative !== false);
            return parsed === '' && String(element.value || '').trim() !== ''
                ? { valid: false, message: '숫자 형식이 아닙니다.' }
                : { valid: true, message: '' };
        },
        isDirty() {
            return element.value !== initialValue;
        },
        destroy() {
            element.remove?.();
        },
    };
}

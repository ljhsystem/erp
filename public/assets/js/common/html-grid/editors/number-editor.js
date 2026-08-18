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

export function normalizeHtmlGridNumberValue(value, allowNegative = true) {
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
    const normalized = normalizeHtmlGridNumberValue(value, options.allowNegative !== false);
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

export function formatHtmlGridNumberWhileTyping(value, caretPosition, options = {}) {
    const text = String(value ?? '');
    const caret = Math.max(0, Math.min(Number(caretPosition) || 0, text.length));
    const semanticBeforeCaret = (text.slice(0, caret).match(/[0-9.\-]/g) || []).length;
    const formatted = formatNumericInput(text, options);
    if (formatted === '') return { value: '', caret: 0 };
    let nextCaret = 0;
    let semanticCount = 0;
    while (nextCaret < formatted.length && semanticCount < semanticBeforeCaret) {
        if (/[0-9.\-]/.test(formatted[nextCaret])) semanticCount += 1;
        nextCaret += 1;
    }
    return { value: formatted, caret: nextCaret };
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
    const liveGrouping = options.liveGrouping === true
        || ['amount', 'currency'].includes(String(context.column?.type || '').toLowerCase())
        || String(context.column?.formatter || '').toLowerCase() === 'currency';
    let composing = false;
    const applyLiveGrouping = () => {
        if (!liveGrouping || composing) return;
        const formatted = formatHtmlGridNumberWhileTyping(element.value, element.selectionStart, options);
        if (formatted.value === element.value) return;
        element.value = formatted.value;
        element.setSelectionRange?.(formatted.caret, formatted.caret);
    };
    const beginComposition = () => { composing = true; };
    const endComposition = () => { composing = false; applyLiveGrouping(); };
    element.addEventListener('input', applyLiveGrouping);
    element.addEventListener('compositionstart', beginComposition);
    element.addEventListener('compositionend', endComposition);

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
            return normalizeHtmlGridNumberValue(element.value, options.allowNegative !== false);
        },
        setValue(value) {
            element.value = formatNumericInput(value, options);
            return element.value;
        },
        validate() {
            const parsed = normalizeHtmlGridNumberValue(element.value, options.allowNegative !== false);
            return parsed === '' && String(element.value || '').trim() !== ''
                ? { valid: false, message: '숫자 형식이 아닙니다.' }
                : { valid: true, message: '' };
        },
        isDirty() {
            return element.value !== initialValue;
        },
        destroy() {
            element.removeEventListener('input', applyLiveGrouping);
            element.removeEventListener('compositionstart', beginComposition);
            element.removeEventListener('compositionend', endComposition);
            element.remove?.();
        },
    };
}

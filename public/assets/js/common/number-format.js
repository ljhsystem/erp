function normalizeNumberString(value) {
    const cleaned = String(value ?? '')
        .replace(/,/g, '')
        .replace(/\s+/g, '')
        .replace(/[^0-9.\-]/g, '');

    let result = '';
    let hasDot = false;
    let hasSign = false;

    for (let index = 0; index < cleaned.length; index += 1) {
        const char = cleaned[index];

        if (char === '-') {
            if (!hasSign && result.length === 0) {
                result += char;
                hasSign = true;
            }
            continue;
        }

        if (char === '.') {
            if (!hasDot) {
                result += char;
                hasDot = true;
            }
            continue;
        }

        result += char;
    }

    return result;
}

function sanitizeParts(value) {
    const normalized = normalizeNumberString(value);
    if (normalized === '') {
        return {
            sign: '',
            integerPart: '',
            decimalPart: '',
            hasDecimal: false,
        };
    }

    const sign = normalized.startsWith('-') ? '-' : '';
    const unsigned = normalized.replace(/-/g, '');
    const [integerRaw = '', ...decimalRawParts] = unsigned.split('.');
    const integerPart = integerRaw.replace(/^0+(?=\d)/, '') || '0';
    const decimalPart = decimalRawParts.join('');

    return {
        sign,
        integerPart,
        decimalPart,
        hasDecimal: unsigned.includes('.'),
    };
}

function addCommas(value) {
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatNumber(value) {
    const parts = sanitizeParts(value);

    if (parts.integerPart === '' && parts.decimalPart === '' && !parts.hasDecimal) {
        return '';
    }

    const integer = addCommas(parts.integerPart || '0');
    if (!parts.hasDecimal) {
        return `${parts.sign}${integer}`;
    }

    return `${parts.sign}${integer}.${parts.decimalPart}`;
}

function parseNumber(value) {
    const normalized = normalizeNumberString(value);
    if (normalized === '' || normalized === '-' || normalized === '.' || normalized === '-.') {
        return 0;
    }

    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
}

function bindNumberInput(input, options = {}) {
    if (!input || input.dataset.numberFormatBound === 'true') {
        return input;
    }

    const emitInput = () => {
        if (typeof options.onInput === 'function') {
            options.onInput(input);
        }
    };

    const emitBlur = () => {
        if (typeof options.onBlur === 'function') {
            options.onBlur(input);
        }
    };

    input.addEventListener('focus', () => {
        input.value = normalizeNumberString(input.value);
    });

    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^0-9.\-]/g, '');
        emitInput();
    });

    input.addEventListener('blur', () => {
        const normalized = normalizeNumberString(input.value);
        input.value = normalized === '' ? '' : formatNumber(normalized);
        emitBlur();
    });

    if (input.value !== '') {
        input.value = formatNumber(input.value);
    }

    input.dataset.numberFormatBound = 'true';
    return input;
}

const NumberFormat = {
    formatNumber,
    parseNumber,
    bindNumberInput,
};

window.NumberFormat = NumberFormat;

export { bindNumberInput, formatNumber, parseNumber };

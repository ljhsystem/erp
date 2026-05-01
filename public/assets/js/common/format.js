// 경로: PROJECT_ROOT . '/public/assets/js/common/format.js'

export function onlyNumber(val) {
    return String(val ?? '').replace(/\D/g, '');
}

export function formatBizNumber(val) {
    const value = onlyNumber(val);

    if (value.length <= 3) return value;
    if (value.length <= 5) return value.replace(/(\d{3})(\d+)/, '$1-$2');

    return value.replace(/(\d{3})(\d{2})(\d+)/, '$1-$2-$3');
}

export function formatCorpNumber(val) {
    const value = onlyNumber(val);

    if (value.length <= 6) return value;

    return value.replace(/(\d{6})(\d+)/, '$1-$2');
}

export function formatMobile(val) {
    const value = onlyNumber(val);

    if (value.length <= 3) return value;
    if (value.length <= 7) return value.replace(/(\d{3})(\d+)/, '$1-$2');

    return value.replace(/(\d{3})(\d{4})(\d+)/, '$1-$2-$3');
}

export function formatPhone(val) {
    const value = onlyNumber(val);

    if (value.startsWith('02')) {
        if (value.length <= 2) return value;
        if (value.length <= 5) return value.replace(/(\d{2})(\d+)/, '$1-$2');
        if (value.length <= 9) return value.replace(/(\d{2})(\d{3})(\d+)/, '$1-$2-$3');

        return value.replace(/(\d{2})(\d{4})(\d+)/, '$1-$2-$3');
    }

    if (value.length <= 3) return value;
    if (value.length <= 6) return value.replace(/(\d{3})(\d+)/, '$1-$2');
    if (value.length <= 10) return value.replace(/(\d{3})(\d{3})(\d+)/, '$1-$2-$3');

    return value.replace(/(\d{3})(\d{4})(\d+)/, '$1-$2-$3');
}

export function formatDateDisplay(val) {
    const value = String(val ?? '').trim();

    if (
        value === '' ||
        value === '0000-00-00' ||
        value === '0000-00-00 00:00:00' ||
        value === 'null' ||
        value === 'undefined'
    ) {
        return '';
    }

    return value;
}

export function formatDateInputValue(val) {
    const digits = onlyNumber(val).slice(0, 8);

    if (digits.length <= 4) {
        return digits;
    }

    if (digits.length <= 6) {
        return `${digits.slice(0, 4)}-${digits.slice(4)}`;
    }

    return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
}

export function formatAmount(val) {
    const num = Number(
        String(val ?? '')
            .replace(/,/g, '')
            .trim()
    );

    if (!Number.isFinite(num)) return '';

    return Math.trunc(num).toLocaleString('ko-KR');
}

export function unformatAmount(val) {
    const cleaned = String(val ?? '')
        .replace(/,/g, '')
        .replace(/[^\d.]/g, '')
        .trim();

    if (cleaned === '') return '';

    const num = Number(cleaned);

    if (!Number.isFinite(num)) return '';

    return String(Math.trunc(num));
}

export function parseNumber(val) {
    const normalized = normalizeNumberString(val);

    if (
        normalized === '' ||
        normalized === '-' ||
        normalized === '.' ||
        normalized === '-.'
    ) {
        return 0;
    }

    const num = Number(normalized);
    return Number.isFinite(num) ? num : 0;
}

export function formatNumber(val) {
    const parts = splitNumberParts(val);

    if (parts.integerPart === '' && parts.decimalPart === '' && !parts.hasDecimal) {
        return '';
    }

    const integer = addThousandsSeparator(parts.integerPart || '0');

    if (!parts.hasDecimal) {
        return `${parts.sign}${integer}`;
    }

    return `${parts.sign}${integer}.${parts.decimalPart}`;
}

export function bindNumberInput(input) {
    if (!input || input.dataset.numberFormatBound === 'true') {
        return input;
    }

    input.addEventListener('focus', () => {
        input.value = normalizeNumberString(input.value);
    });

    input.addEventListener('input', () => {
        input.value = normalizeNumberString(input.value);
    });

    input.addEventListener('blur', () => {
        const normalized = normalizeNumberString(input.value);
        input.value = normalized === '' ? '' : formatNumber(normalized);
    });

    if (String(input.value ?? '').trim() !== '') {
        input.value = formatNumber(input.value);
    }

    input.dataset.numberFormatBound = 'true';
    return input;
}

export function initNumberInputs(selector = '.number-input') {
    document.querySelectorAll(selector).forEach(bindNumberInput);
}

export function formatAccountNumber(val, bankName = '') {
    const value = onlyNumber(val);
    const bank = String(bankName ?? '').trim();

    if (!value) return '';

    switch (bank) {
        case '국민은행':
            if (value.length === 14) {
                return value.replace(/(\d{3})(\d{6})(\d{2})(\d{3})/, '$1-$2-$3-$4');
            }
            break;

        case '신한은행':
            if (value.length === 12) {
                return value.replace(/(\d{3})(\d{3})(\d{6})/, '$1-$2-$3');
            }
            break;

        case '우리은행':
            if (value.length === 13) {
                return value.replace(/(\d{4})(\d{3})(\d{6})/, '$1-$2-$3');
            }
            break;

        default:
            break;
    }

    if (value.length === 11) {
        return value.replace(/(\d{3})(\d{4})(\d{4})/, '$1-$2-$3');
    }

    if (value.length === 12) {
        return value.replace(/(\d{3})(\d{3})(\d{6})/, '$1-$2-$3');
    }

    if (value.length === 13) {
        return value.replace(/(\d{4})(\d{3})(\d{6})/, '$1-$2-$3');
    }

    if (value.length === 14) {
        return value.replace(/(\d{3})(\d{6})(\d{2})(\d{3})/, '$1-$2-$3-$4');
    }

    return value;
}

function normalizeNumberString(val) {
    const cleaned = String(val ?? '')
        .replace(/,/g, '')
        .replace(/\s+/g, '')
        .replace(/[^0-9.\-]/g, '');

    let result = '';
    let hasSign = false;
    let hasDot = false;

    for (let i = 0; i < cleaned.length; i += 1) {
        const char = cleaned[i];

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

function splitNumberParts(val) {
    const normalized = normalizeNumberString(val);

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
    const [integerRaw = '', ...decimalParts] = unsigned.split('.');
    const decimalPart = decimalParts.join('');
    const integerPart = integerRaw.replace(/^0+(?=\d)/, '') || '0';

    return {
        sign,
        integerPart,
        decimalPart,
        hasDecimal: unsigned.includes('.'),
    };
}

function addThousandsSeparator(val) {
    return String(val).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

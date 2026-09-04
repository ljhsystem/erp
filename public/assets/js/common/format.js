const ONLY_DIGIT = /\D/g;
const onlyNumber = (val) => String(val ?? '').replace(ONLY_DIGIT, '');

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

function normalizeIntegerString(value) {
    const normalized = normalizeNumberString(value);
    if (normalized === '' || normalized === '-') return normalized;

    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? String(Math.trunc(parsed)) : '';
}

function parseNumber(value) {
    const normalized = normalizeNumberString(value);

    if (
        normalized === '' ||
        normalized === '-' ||
        normalized === '.' ||
        normalized === '-.'
    ) {
        return 0;
    }

    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
}

function splitParts(value) {
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
    const decimalPart = decimalRawParts.join('');

    return {
        sign,
        integerPart: integerRaw.replace(/^0+(?=\d)/, '') || '0',
        decimalPart,
        hasDecimal: unsigned.includes('.'),
    };
}

function addCommas(value) {
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

export function formatNumber(value) {
    const parts = splitParts(value);

    if (parts.integerPart === '' && parts.decimalPart === '' && !parts.hasDecimal) {
        return '';
    }

    const integer = addCommas(parts.integerPart || '0');
    if (!parts.hasDecimal) {
        return `${parts.sign}${integer}`;
    }

    return `${parts.sign}${integer}.${parts.decimalPart}`;
}

export function rateToPercent(value) {
    if (value === null || value === undefined || value === '') return '';
    const number = Number(value);
    return Number.isFinite(number) ? Number((number * 100).toFixed(10)) : value;
}

export function percentToRate(value) {
    if (value === null || value === undefined || value === '') return '';
    const number = Number(value);
    return Number.isFinite(number) ? Number((number / 100).toFixed(12)) : value;
}

export function bindNumberInput(input, options = {}) {
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

    const normalize = () => {
        input.value = options.integerOnly === true
            ? normalizeIntegerString(input.value)
            : normalizeNumberString(input.value);
        return input.value;
    };

    const formatForInput = () => {
        const cursorAtEnd = input.selectionStart === input.value.length && input.selectionEnd === input.value.length;
        const normalized = options.integerOnly === true
            ? normalizeIntegerString(input.value)
            : normalizeNumberString(input.value);
        input.value = normalized === '' ? '' : formatNumber(normalized);
        if (cursorAtEnd) {
            const end = input.value.length;
            input.setSelectionRange?.(end, end);
        }
    };

    input.addEventListener('focus', () => {
        normalize();
    });

    input.addEventListener('input', () => {
        formatForInput();
        emitInput();
    });

    input.addEventListener('blur', () => {
        const normalized = normalize();
        input.value = normalized === '' ? '' : formatNumber(normalized);
        emitBlur();
    });

    if (String(input.value ?? '').trim() !== '') {
        const normalized = options.integerOnly === true
            ? normalizeIntegerString(input.value)
            : input.value;
        input.value = formatNumber(normalized);
    }

    input.dataset.numberFormatBound = 'true';
    return input;
}

export function bindBizNumberInput(input, options = {}) {
    if (!input || input.dataset.bizNumberFormatBound === 'true') {
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

    const formatForInput = () => {
        input.value = formatBizNumber(input.value);
    };

    input.addEventListener('input', () => {
        formatForInput();
        emitInput();
    });

    input.addEventListener('blur', () => {
        formatForInput();
        emitBlur();
    });

    if (String(input.value ?? '').trim() !== '') {
        input.value = formatBizNumber(input.value);
    }

    input.dataset.bizNumberFormatBound = 'true';
    return input;
}

export function initNumberInputs(selector = '.number-input') {
    document.querySelectorAll(selector).forEach(bindNumberInput);
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
    const raw = String(val ?? '').trim();

    const ymd = raw.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})/);
    if (ymd) {
        const month = Number(ymd[2]);
        const day = Number(ymd[3]);
        if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
            return `${ymd[1]}-${ymd[2].padStart(2, '0')}-${ymd[3].padStart(2, '0')}`;
        }
    }

    const separated = raw.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})/);
    if (separated) {
        const first = Number(separated[1]);
        const second = Number(separated[2]);
        const month = first > 12 && second <= 12 ? separated[2] : separated[1];
        const day = first > 12 && second <= 12 ? separated[1] : separated[2];
        return `${separated[3]}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    }

    const digits = onlyNumber(raw).slice(0, 8);
    if (digits.length <= 4) {
        return digits;
    }
    if (digits.length <= 6) {
        return `${digits.slice(0, 4)}-${digits.slice(4)}`;
    }

    return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
}

export function formatAmount(val) {
    const raw = String(val ?? '').trim();
    if (raw === '') return '';

    const num = parseNumber(raw);
    if (!Number.isFinite(num)) return '';

    return Math.trunc(num).toLocaleString('ko-KR');
}

export function unformatAmount(val) {
    const raw = String(val ?? '').trim();
    if (raw === '') return '';

    const normalized = raw.replace(/,/g, '').replace(/[^\d.\-]/g, '').trim();
    const num = Number(normalized);
    if (!Number.isFinite(num)) return '';

    return String(Math.trunc(num));
}

function normalizeBankName(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, '')
        .replace(/[()\[\]{}._-]/g, '');
}

function applyAccountGroups(value, groups, options = {}) {
    if (!Array.isArray(groups)) {
        return value;
    }

    const totalLength = groups.reduce((sum, size) => sum + size, 0);
    if (totalLength !== value.length && options.partial !== true) {
        return value;
    }
    if (options.partial === true && value.length > totalLength) {
        return value;
    }

    const parts = [];
    let offset = 0;
    groups.some((size) => {
        const part = value.slice(offset, offset + size);
        if (!part) return true;
        parts.push(part);
        offset += size;
        return offset >= value.length;
    });

    return parts.join('-');
}

const BANK_CODE_LIST_URL = '/api/settings/system/code/list?code_group=BANK&filters=%5B%5D';

let bankFormatRegistryLoaded = false;
let bankFormatRegistryPromise = null;
let bankFormatRegistry = [];

const FALLBACK_ACCOUNT_GROUPS = {
    10: [2, 2, 6],
    11: [3, 2, 6],
    12: [3, 3, 6],
    13: [4, 3, 6],
    14: [3, 6, 2, 3],
};

function parseJsonObject(value) {
    if (!value) return {};
    if (typeof value === 'object' && !Array.isArray(value)) return value;
    if (typeof value !== 'string') return {};

    try {
        const parsed = JSON.parse(value);
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (_error) {
        return {};
    }
}

function normalizeAccountFormats(value) {
    if (typeof value === 'string') {
        if (value.includes('#')) {
            const groups = value
                .split('-')
                .map((part) => (part.match(/#/g) || []).length)
                .filter((item) => item > 0);
            const total = groups.reduce((sum, item) => sum + item, 0);
            return total > 0 ? { [String(total)]: groups } : {};
        }

        const groups = value
            .split(/[-,\s]+/)
            .map((item) => Number(item))
            .filter((item) => Number.isInteger(item) && item > 0);
        const total = groups.reduce((sum, item) => sum + item, 0);
        return total > 0 ? { [String(total)]: groups } : {};
    }

    if (Array.isArray(value)) {
        const total = value.map((item) => Number(item)).reduce((sum, item) => (
            Number.isInteger(item) && item > 0 ? sum + item : sum
        ), 0);
        return total > 0 ? { [String(total)]: value } : {};
    }
    if (!value || typeof value !== 'object') return {};

    return Object.entries(value).reduce((carry, [length, groups]) => {
        const key = String(length || '').trim();
        let normalizedGroups = [];

        if (Array.isArray(groups)) {
            normalizedGroups = groups.map((item) => Number(item)).filter((item) => Number.isInteger(item) && item > 0);
        } else if (typeof groups === 'string') {
            if (groups.includes('#')) {
                normalizedGroups = groups
                    .split('-')
                    .map((part) => (part.match(/#/g) || []).length)
                    .filter((item) => item > 0);
            } else {
                normalizedGroups = groups
                    .split(/[-,\s]+/)
                    .map((item) => Number(item))
                    .filter((item) => Number.isInteger(item) && item > 0);
            }
        }

        if (key && normalizedGroups.length) {
            carry[key] = normalizedGroups;
        }

        return carry;
    }, {});
}

function getAccountFormatSource(extra = {}) {
    return extra.account_formats
        || extra.account_format
        || extra.accountFormats
        || extra.account_number_formats
        || extra.account_number_format
        || extra.accountNumberFormats
        || extra.accountNumberFormat
        || extra.account_patterns
        || extra.accountPatterns
        || extra.hyphen_formats
        || extra.hyphenFormats
        || extra.patterns
        || extra.format
        || extra.formats
        || {};
}

function normalizeAliases(value) {
    if (Array.isArray(value)) return value;
    if (typeof value === 'string') {
        return value.split(',').map((item) => item.trim()).filter(Boolean);
    }
    return [];
}

function normalizeBankFormatRow(row = {}) {
    const extra = row.extra && typeof row.extra === 'object'
        ? row.extra
        : parseJsonObject(row.extra_data ?? row.extraData ?? row.attributes_json ?? row.attributes ?? '');
    const aliases = [
        ...normalizeAliases(extra.aliases),
        ...normalizeAliases(extra.alias),
        ...normalizeAliases(extra.search_keys),
        ...normalizeAliases(extra.searchKeys),
    ];
    const code = String(row.code ?? '').trim();
    const name = String(row.code_name ?? row.name ?? row.text ?? '').trim();
    const keys = [
        code,
        onlyNumber(code),
        name,
        row.bank_name,
        row.label,
        ...aliases,
    ].map(normalizeBankName).filter(Boolean);

    return {
        code,
        name,
        keys: Array.from(new Set(keys)),
        patterns: normalizeAccountFormats(getAccountFormatSource(extra)),
        openBanking: extra.open_banking ?? extra.openBanking ?? null,
    };
}

function shouldUseBankRow(row = {}) {
    const active = row.is_active ?? row.active ?? row.use_yn ?? row.status;
    if (active === undefined || active === null || active === '') return true;
    if (typeof active === 'number') return active === 1;
    const normalized = String(active).trim().toUpperCase();
    return ['1', 'Y', 'YES', 'TRUE', 'USE', 'ACTIVE'].includes(normalized);
}

export function setBankAccountFormatRegistry(rows = []) {
    bankFormatRegistry = (Array.isArray(rows) ? rows : [])
        .filter(shouldUseBankRow)
        .map(normalizeBankFormatRow)
        .filter((row) => row.keys.length);
    bankFormatRegistryLoaded = true;
    return bankFormatRegistry;
}

export async function loadBankAccountFormatRegistry(options = {}) {
    if (bankFormatRegistryLoaded && options.force !== true) {
        return bankFormatRegistry;
    }
    if (bankFormatRegistryPromise && options.force !== true) {
        return bankFormatRegistryPromise;
    }
    if (typeof fetch !== 'function') {
        bankFormatRegistryLoaded = true;
        return bankFormatRegistry;
    }

    const url = options.url || BANK_CODE_LIST_URL;
    bankFormatRegistryPromise = fetch(url, { cache: options.cache || 'no-store' })
        .then((response) => (response.ok ? response.json() : Promise.reject(response)))
        .then((json) => {
            const rows = Array.isArray(json) ? json : (json.data || json.items || []);
            return setBankAccountFormatRegistry(rows);
        })
        .catch(() => {
            bankFormatRegistryLoaded = false;
            return bankFormatRegistry;
        })
        .finally(() => {
            bankFormatRegistryPromise = null;
        });

    return bankFormatRegistryPromise;
}

export const loadBankAccountFormats = loadBankAccountFormatRegistry;

function findBankFormatRow(bank) {
    if (!bankFormatRegistryLoaded) return null;
    const bankCode = onlyNumber(bank);
    return bankFormatRegistry.find((entry) => entry.keys.some((key) => (
        key === bank
        || (bankCode && key === bankCode)
        || bank.includes(key)
        || key.includes(bank)
    ))) || null;
}

function formatByConfiguredBank(value, bank) {
    const matched = findBankFormatRow(bank);
    if (!matched) return null;

    const groups = resolveAccountGroupsForLength(matched.patterns, value.length);
    return groups ? applyAccountGroups(value, groups, { partial: true }) : value;
}

function formatByFallbackLength(value) {
    const groups = resolveAccountGroupsForLength(FALLBACK_ACCOUNT_GROUPS, value.length);
    return groups ? applyAccountGroups(value, groups, { partial: true }) : value;
}

function resolveAccountGroupsForLength(patterns = {}, length = 0) {
    const exact = patterns[String(length)] || patterns[length];
    if (exact) return exact;

    const candidates = Object.entries(patterns)
        .map(([key, groups]) => ({
            length: Number(key),
            groups,
        }))
        .filter((item) => Number.isInteger(item.length) && item.length >= length && Array.isArray(item.groups))
        .sort((left, right) => left.length - right.length);

    return candidates[0]?.groups || null;
}

export function formatAccountNumber(val, bankName = '') {
    const value = onlyNumber(val);
    const bank = normalizeBankName(bankName);

    if (!value) return '';
    if (!bank) return value;

    if (!bankFormatRegistryLoaded && !bankFormatRegistryPromise && typeof window !== 'undefined') {
        loadBankAccountFormatRegistry();
    }

    const configured = formatByConfiguredBank(value, bank);
    if (configured !== null) return configured;

    return bankFormatRegistryLoaded ? value : formatByFallbackLength(value);
}

export function unformatAccountNumber(val) {
    return onlyNumber(val);
}

export function bindAccountNumberInput(input, options = {}) {
    if (!input || input.dataset.accountNumberFormatBound === 'true') {
        return input;
    }

    const resolveBankName = () => {
        if (typeof options.bankName === 'function') {
            return options.bankName(input);
        }
        if (typeof options.bankName === 'string') {
            return options.bankName;
        }
        const form = input.closest('form');
        return form?.querySelector('[name="bank_name"]')?.value || input.dataset.bankName || '';
    };

    const format = () => {
        input.value = formatAccountNumber(input.value, resolveBankName());
        if (typeof options.onInput === 'function') {
            options.onInput(input);
        }
    };

    input.addEventListener('input', format);
    input.addEventListener('blur', format);
    if (String(input.value ?? '').trim() !== '') {
        format();
    }

    input.dataset.accountNumberFormatBound = 'true';
    return input;
}

export function initAccountNumberInputs(selector = '[data-format="account_number"]') {
    document.querySelectorAll(selector).forEach((input) => bindAccountNumberInput(input));
}

export {
    onlyNumber,
    normalizeNumberString,
    parseNumber,
};

if (typeof window !== 'undefined') {
    window.NumberFormat = {
        onlyNumber,
        normalizeNumberString,
        parseNumber,
        formatNumber,
        formatAccountNumber,
        unformatAccountNumber,
        loadBankAccountFormats,
        loadBankAccountFormatRegistry,
        setBankAccountFormatRegistry,
        formatBizNumber,
        bindAccountNumberInput,
        bindNumberInput,
        bindBizNumberInput,
        initAccountNumberInputs,
        initNumberInputs,
    };
}

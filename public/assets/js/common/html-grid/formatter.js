function padNumber(value) {
    return String(value).padStart(2, '0');
}

function normalizeNumber(value) {
    if (value == null || value === '') {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const numericValue = Number(String(value).replaceAll(',', '').trim());
    return Number.isFinite(numericValue) ? numericValue : null;
}

function normalizeDateParts(value) {
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return {
            year: value.getFullYear(),
            month: value.getMonth() + 1,
            day: value.getDate(),
        };
    }

    const text = String(value || '').trim();
    if (text === '') {
        return null;
    }

    const normalized = text.replaceAll('.', '-').replaceAll('/', '-');
    const match = normalized.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    if (!match) {
        return null;
    }

    return {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
    };
}

export function formatText(value) {
    return value == null ? '' : String(value);
}

export function formatNumber(value, options = {}) {
    const numericValue = normalizeNumber(value);
    if (numericValue == null) {
        return '';
    }

    const locale = options.locale || 'ko-KR';
    const minimumFractionDigits = Number.isInteger(options.minimumFractionDigits) ? options.minimumFractionDigits : 0;
    const maximumFractionDigits = Number.isInteger(options.maximumFractionDigits)
        ? options.maximumFractionDigits
        : Math.max(minimumFractionDigits, 6);

    return new Intl.NumberFormat(locale, {
        minimumFractionDigits,
        maximumFractionDigits,
    }).format(numericValue);
}

export function formatCurrency(value, options = {}) {
    const numericValue = normalizeNumber(value);
    if (numericValue == null) {
        return '';
    }

    const locale = options.locale || 'ko-KR';
    const currency = options.currency || 'KRW';
    const minimumFractionDigits = Number.isInteger(options.minimumFractionDigits)
        ? options.minimumFractionDigits
        : (currency === 'KRW' ? 0 : 2);
    const maximumFractionDigits = Number.isInteger(options.maximumFractionDigits)
        ? options.maximumFractionDigits
        : minimumFractionDigits;

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits,
        maximumFractionDigits,
    }).format(numericValue);
}

export function formatPercent(value, options = {}) {
    const numericValue = normalizeNumber(value);
    if (numericValue == null) {
        return '';
    }

    const locale = options.locale || 'ko-KR';
    const minimumFractionDigits = Number.isInteger(options.minimumFractionDigits) ? options.minimumFractionDigits : 0;
    const maximumFractionDigits = Number.isInteger(options.maximumFractionDigits)
        ? options.maximumFractionDigits
        : Math.max(minimumFractionDigits, 2);

    return new Intl.NumberFormat(locale, {
        style: 'percent',
        minimumFractionDigits,
        maximumFractionDigits,
    }).format(options.inputScale === 'percent' ? numericValue / 100 : numericValue);
}

export function formatDate(value, options = {}) {
    const parts = normalizeDateParts(value);
    if (!parts) {
        return '';
    }

    const delimiter = options.delimiter || '-';
    return [parts.year, padNumber(parts.month), padNumber(parts.day)].join(delimiter);
}

export function createFormatterRegistry(initialFormatters = {}) {
    const registry = new Map();

    function register(name, formatter) {
        const normalizedName = String(name || '').trim();
        if (normalizedName === '' || typeof formatter !== 'function') {
            throw new Error('[html-grid] formatter registry requires name and formatter.');
        }
        registry.set(normalizedName, formatter);
        return api;
    }

    function unregister(name) {
        registry.delete(String(name || '').trim());
        return api;
    }

    function resolve(name) {
        return registry.get(String(name || '').trim()) || null;
    }

    function format(name, value, options = {}) {
        const formatter = resolve(name);
        if (!formatter) {
            return value == null ? '' : String(value);
        }
        return formatter(value, options);
    }

    function entries() {
        return Array.from(registry.entries());
    }

    const api = {
        register,
        unregister,
        resolve,
        format,
        entries,
    };

    Object.entries({
        text: formatText,
        number: formatNumber,
        currency: formatCurrency,
        date: formatDate,
        percent: formatPercent,
        ...(initialFormatters && typeof initialFormatters === 'object' ? initialFormatters : {}),
    }).forEach(([name, formatter]) => {
        register(name, formatter);
    });

    return api;
}

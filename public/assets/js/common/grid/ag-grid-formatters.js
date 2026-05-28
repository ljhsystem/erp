export function parseGridNumber(value) {
    if (value === null || value === undefined || value === '') return null;
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    const normalized = String(value).replace(/[,\s원₩]/g, '').trim();
    if (normalized === '') return null;
    const number = Number(normalized);
    return Number.isFinite(number) ? number : null;
}

export function gridNumberFormatter(params = {}) {
    const value = parseGridNumber(params.value);
    return value === null ? '' : value.toLocaleString('ko-KR');
}

export function gridNumberParser(params = {}) {
    const value = parseGridNumber(params.newValue);
    return value === null ? '' : value;
}

export function gridDateFormatter(params = {}) {
    return String(params.value ?? '').trim();
}

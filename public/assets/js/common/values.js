import { formatBizNumber, formatDateInputValue } from '/public/assets/js/common/format.js';

export function inputValueKind(key, label = '') {
    const text = `${key || ''} ${label || ''}`;

    if (/business_number|biz_number|사업자\s*등록\s*번호|사업자등록번호/i.test(text)) {
        return 'business-number';
    }

    if (/amount|price|qty|quantity|balance|금액|수량|합계|총액|공급가|공급액|공급금액|부가|세액|세금|입금|출금|잔액|봉사료|수수료|단가/i.test(text)) {
        return 'amount';
    }
    if (/datetime|date_time|일시/i.test(text)) return 'datetime';
    if (/time|시간|시각/i.test(text)) return 'time';
    if (/date|일자|날짜/i.test(text)) return 'date';
    return 'text';
}

export function valueForInput(kind, value) {
    if (kind === 'date') return formatDateInputValue(value);
    if (kind === 'datetime') return normalizeDateTimeInputValue(value);
    if (kind === 'time') return normalizeTimeInputValue(value);
    if (kind === 'business-number') return formatBizNumber(value);
    return value ?? '';
}

export function valueForSave(input) {
    if (input.type === 'checkbox') return input.checked ? '1' : '0';
    const kind = input.dataset.valueKind || '';
    if (isNoneValue(input.value)) return null;
    const rawValue = normalizeNoneValue(input.value);
    if (kind === 'amount') return String(rawValue ?? '').replace(/,/g, '').trim();
    if (kind === 'date') return formatDateInputValue(rawValue);
    if (kind === 'datetime') return normalizeDateTimeInputValue(rawValue);
    if (kind === 'time') return normalizeTimeInputValue(rawValue);
    if (kind === 'business-number') return formatBizNumber(rawValue);
    return rawValue;
}

export function normalizeNoneValue(value) {
    return isNoneValue(value) ? '' : value;
}

export function isNoneValue(value) {
    const raw = String(value ?? '').trim();
    return [
        '__none__',
        '__CODE_NONE__',
        '_none_',
        '__none',
        'none__',
        '선택(없음)',
    ].includes(raw);
}

export function dateFromInputValue(value) {
    const normalized = formatDateInputValue(value);
    const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return Number.isNaN(date.getTime()) ? null : date;
}

export function pad2(value) {
    return String(value ?? '').padStart(2, '0');
}

export function normalizeTimeInputValue(value) {
    const raw = String(value ?? '').trim();
    const clockMatch = raw.match(/(?:^|\D)(\d{1,2}):(\d{2})(?::\d{2})?(?:\D|$)/);
    let hourText = clockMatch?.[1] || '';
    let minuteText = clockMatch?.[2] || '';
    if (!clockMatch) {
        const digits = raw.replace(/\D/g, '');
        const timeDigits = digits.length >= 4 ? digits.slice(-4) : digits;
        if (!/^\d{3,4}$/.test(timeDigits)) return '';
        hourText = timeDigits.length === 3 ? timeDigits.slice(0, 1) : timeDigits.slice(0, 2);
        minuteText = timeDigits.slice(-2);
    }
    const hour = Math.min(23, Math.max(0, Number(hourText || 0)));
    const minute = Math.min(59, Math.max(0, Number(minuteText || 0)));
    return `${pad2(hour)}:${pad2(minute)}`;
}

export function normalizeDateTimeInputValue(value) {
    const raw = String(value ?? '').trim();
    const dateText = formatDateInputValue(raw);
    if (!dateText) return '';
    const timeSource = raw.replace(/^\s*\d{4}[-/.]?\d{1,2}[-/.]?\d{1,2}/, '').trim();
    const timeText = normalizeTimeInputValue(timeSource);
    return timeText ? `${dateText} ${timeText}` : dateText;
}

export function formatPickerDate(date) {
    if (!(date instanceof Date)) return '';
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

export function formatPickerDateTime(date) {
    if (!(date instanceof Date)) return '';
    return `${formatPickerDate(date)} ${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
}

export function dateTimeFromInputValue(value) {
    const date = dateFromInputValue(value);
    if (!date) return null;
    const raw = String(value ?? '').trim();
    const timeSource = raw.replace(/^\s*\d{4}[-/.]?\d{1,2}[-/.]?\d{1,2}/, '').trim();
    const time = normalizeTimeInputValue(timeSource);
    if (time) {
        const [hour, minute] = time.split(':').map((item) => Number(item));
        date.setHours(hour, minute, 0, 0);
    }
    return date;
}

export function applyDateTimeToPicker(picker, value, keepTime = false) {
    if (!picker) return;
    const date = keepTime ? dateTimeFromInputValue(value) : dateFromInputValue(value);
    const target = date || new Date();
    picker.setDate?.(target);
    if (!keepTime) {
        picker.toggleTime?.(false);
        picker.setTime?.({ hour: null, minute: null, meridiem: null });
        return;
    }

    const hour24 = target.getHours();
    const hour12 = hour24 === 0 ? 12 : (hour24 > 12 ? hour24 - 12 : hour24);
    picker.toggleTime?.(true);
    picker.setTime?.({
        hour: hour12,
        minute: target.getMinutes(),
        meridiem: hour24 >= 12 ? 'PM' : 'AM',
    });
}

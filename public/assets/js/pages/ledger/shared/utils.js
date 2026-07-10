import { formatBizNumber, formatDateInputValue } from '/public/assets/js/common/format.js';

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function numericValue(value) {
    if (typeof value === 'number') {
        return Number.isFinite(value)
            ? value
            : null;
    }

    const normalized = String(value ?? '')
        .trim()
        .replace(/[,\s₩￦]/g, '');

    if (
        !normalized
        || normalized === '-'
        || normalized.toLowerCase() === 'nan'
    ) {
        return null;
    }

    const number = Number(normalized);

    return Number.isFinite(number)
        ? number
        : null;
}

export function formatNumber(value) {
    const number = numericValue(value);

    return number === null
        ? '-'
        : number.toLocaleString('ko-KR');
}

export function formatDate(value) {
    if (!value) {
        return '-';
    }

    const normalized = formatDateInputValue(value);

    return normalized || String(value).trim();
}

export function pad2(value) {
    return String(value ?? '').padStart(2, '0');
}

export function normalizeTimeInputValue(value) {
    const raw = String(value ?? '').trim();

    const clockMatch = raw.match(
        /(?:^|\D)(\d{1,2}):(\d{2})(?::\d{2})?(?:\D|$)/
    );

    let hourText = clockMatch?.[1] || '';
    let minuteText = clockMatch?.[2] || '';

    if (!clockMatch) {
        const digits = raw.replace(/\D/g, '');
        const timeDigits = digits.length >= 4
            ? digits.slice(-4)
            : digits;

        if (!/^\d{3,4}$/.test(timeDigits)) {
            return '';
        }

        hourText = timeDigits.length === 3
            ? timeDigits.slice(0, 1)
            : timeDigits.slice(0, 2);

        minuteText = timeDigits.slice(-2);
    }

    const hour = Math.min(
        23,
        Math.max(0, Number(hourText || 0))
    );

    const minute = Math.min(
        59,
        Math.max(0, Number(minuteText || 0))
    );

    return `${pad2(hour)}:${pad2(minute)}`;
}

export function normalizeDateTimeInputValue(value) {
    const raw = String(value ?? '').trim();

    const dateText = formatDateInputValue(raw);

    if (!dateText) {
        return '';
    }

    const timeSource = raw
        .replace(/^\s*\d{4}[-/.]?\d{1,2}[-/.]?\d{1,2}/, '')
        .trim();

    const timeText = normalizeTimeInputValue(timeSource);

    return timeText
        ? `${dateText} ${timeText}`
        : dateText;
}

export function dateFromInputValue(value) {
    const normalized = formatDateInputValue(value);

    const match = normalized.match(
        /^(\d{4})-(\d{2})-(\d{2})$/
    );

    if (!match) {
        return null;
    }

    const date = new Date(
        Number(match[1]),
        Number(match[2]) - 1,
        Number(match[3])
    );

    return Number.isNaN(date.getTime())
        ? null
        : date;
}

export function dateTimeFromInputValue(value) {
    const date = dateFromInputValue(value);

    if (!date) {
        return null;
    }

    const raw = String(value ?? '').trim();

    const timeSource = raw
        .replace(/^\s*\d{4}[-/.]?\d{1,2}[-/.]?\d{1,2}/, '')
        .trim();

    const time = normalizeTimeInputValue(timeSource);

    if (time) {
        const [hour, minute] = time
            .split(':')
            .map((item) => Number(item));

        date.setHours(hour, minute, 0, 0);
    }

    return date;
}

export function formatPickerDate(date) {
    if (!(date instanceof Date)) {
        return '';
    }

    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

export function formatPickerDateTime(date) {
    if (!(date instanceof Date)) {
        return '';
    }

    return `${formatPickerDate(date)} ${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
}

export function valueForInput(kind, value) {
    if (kind === 'date') {
        return formatDateInputValue(value);
    }

    if (kind === 'datetime') {
        return normalizeDateTimeInputValue(value);
    }

    if (kind === 'time') {
        return normalizeTimeInputValue(value);
    }

    if (kind === 'business-number') {
        return formatBizNumber(value);
    }

    return value ?? '';
}

export function valueForSave(input) {
    if (input.type === 'checkbox') {
        return input.checked
            ? '1'
            : '0';
    }

    const kind = input.dataset.valueKind || '';
    if (isNoneValue(input.value)) {
        return null;
    }

    const rawValue = normalizeNoneValue(input.value);

    if (kind === 'amount') {
        return String(rawValue ?? '')
            .replace(/,/g, '')
            .trim();
    }

    if (kind === 'date') {
        return formatDateInputValue(rawValue);
    }

    if (kind === 'datetime') {
        return normalizeDateTimeInputValue(rawValue);
    }

    if (kind === 'time') {
        return normalizeTimeInputValue(rawValue);
    }

    if (kind === 'business-number') {
        return formatBizNumber(rawValue);
    }

    return rawValue;
}

export function normalizeNoneValue(value) {
    return isNoneValue(value) ? '' : value;
}

export function isNoneValue(value) {
    const raw = String(value ?? '').trim();

    return [
        '_none_',
        '__none',
        'none__',
    ].includes(raw);
}

export function mapped(row) {
    return normalizeMappedTransactionAmounts(row?.mapped_payload || {}, row);
}

export function normalizeMappedTransactionAmounts(payload = {}, row = {}) {
    const next = {
        ...payload,
    };

    const type = String(
        next.import_type
        || next.source_type
        || next.data_type
        || row?.import_type
        || row?.source_type
        || ''
    ).trim().toUpperCase();

    const formatColumns = Array.isArray(row?.format_columns) ? row.format_columns : [];
    const formatAmountFrom = (systemField) => {
        const field = String(systemField || '').trim();
        if (!field) return null;

        for (const column of formatColumns) {
            const mappedField = String(column?.system_field_name || column?.system_field || '').trim();
            if (mappedField !== field) {
                continue;
            }

            const candidates = [
                mappedField,
                column?.excel_column_name,
                column?.column_name,
                column?.name,
            ];

            for (const candidate of candidates) {
                const key = String(candidate || '').trim();
                if (!key) continue;
                const amount = numericValue(next[key]);
                if (amount !== null) {
                    return amount;
                }
            }
        }

        return null;
    };
    const amountFrom = (...keys) => {
        for (const key of keys) {
            const amount = numericValue(next[key]);
            if (amount !== null) {
                return amount;
            }

            const formatAmount = formatAmountFrom(key);
            if (formatAmount !== null) {
                return formatAmount;
            }
        }

        return null;
    };

    const supply = amountFrom(
        'supply_amount',
        'item_supply_amount',
        'purchase_amount_krw',
        'local_amount'
    );
    const vat = amountFrom('vat_amount', 'item_vat_amount');
    const service = amountFrom('service_amount') ?? 0;
    const withholding = amountFrom('withholding_amount') ?? 0;
    const fee = amountFrom('fee_amount') ?? 0;

    const totalCandidates = ['total_amount', 'amount'];
    if (['CARD_STATEMENT', 'CARD_APPROVAL'].includes(type)) {
        totalCandidates.unshift('actual_billing_amount', 'billing_amount');
    } else if (type === 'CASH_RECEIPT') {
        totalCandidates.unshift('purchase_amount_krw');
    }
    totalCandidates.push('actual_billing_amount', 'billing_amount', 'purchase_amount_krw');

    const total = amountFrom(...Array.from(new Set(totalCandidates)));

    let adjustment = amountFrom('adjustment_amount');
    if (adjustment === null && total !== null && supply !== null) {
        adjustment = total - supply;
    }
    if (adjustment === null && (vat !== null || service !== 0 || withholding !== 0 || fee !== 0)) {
        adjustment = (vat ?? 0) + service + fee - withholding;
    }

    if (next.supply_amount === undefined || next.supply_amount === null || String(next.supply_amount).trim() === '') {
        if (supply !== null) next.supply_amount = supply;
    }
    if (next.adjustment_amount === undefined || next.adjustment_amount === null || String(next.adjustment_amount).trim() === '') {
        if (adjustment !== null) next.adjustment_amount = adjustment;
    }
    if (next.total_amount === undefined || next.total_amount === null || String(next.total_amount).trim() === '') {
        if (total !== null) {
            next.total_amount = total;
        } else if (supply !== null || adjustment !== null) {
            next.total_amount = (supply ?? 0) + (adjustment ?? 0);
        }
    }

    return next;
}

export function standardDate(row) {
    const payload = mapped(row);

    return payload.transaction_date
        || payload.evidence_date
        || payload.purchase_datetime
        || payload.purchase_date
        || payload.approval_datetime
        || payload.approval_date
        || row?.evidence_date
        || '';
}

export function rowClientName(row) {
    const payload = mapped(row);

    return displayTextFrom(
        row?.client_name,
        payload.client_name,
        payload.client_company_name,
        payload.merchant_company_name,
        payload.supplier_name,
        payload.supplier_company_name,
        payload.customer_name,
        payload.customer_company_name,
        payload.counterparty_name
    );
}

export function rowProjectName(row) {
    const payload = mapped(row);

    return displayTextFrom(
        row?.project_name,
        payload.project_name,
        payload.project_code
    );
}

export function inputValueKind(key, label = '') {
    const text = `${key || ''} ${label || ''}`;

    if (
        /business_number|biz_number|사업자\s*등록\s*번호|사업자등록번호/i
            .test(text)
    ) {
        return 'business-number';
    }

    if (
        /amount|price|qty|quantity|balance|금액|수량|합계|총액|공급가|공급액|공급금액|부가|세액|세금|입금|출금|잔액|봉사료|횟수|단가/i
            .test(text)
    ) {
        return 'amount';
    }

    if (/datetime|date_time|일시/i.test(text)) {
        return 'datetime';
    }

    if (/time|시간|시각/i.test(text)) {
        return 'time';
    }

    if (/date|일자|날짜/i.test(text)) {
        return 'date';
    }

    return 'text';
}

export function bankAccountSourceText(row = {}) {
    const payload = mapped(row);

    return displayTextFrom(
        payload.payment_account_name,
        payload.payment_account_number,
        payload.bank_account_name,
        payload.account_name,
        payload.bank_account,
        payload.account_number
    );
}

export function isUuidValue(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
        .test(String(value ?? '').trim());
}

export function displayTextFrom(...values) {
    for (const value of values) {
        const text = String(value ?? '').trim();
        if (text !== '' && normalizeNoneValue(text) !== '' && !isUuidValue(text)) {
            return text;
        }
    }

    return '';
}

export function resolveDisplayText(item = {}) {
    return displayTextFrom(
        item.display_name,
        item.client_name,
        item.account_name,
        item.bank_account_name,
        item.project_name,
        item.employee_name,
        item.card_name,
        item.name,
        item.code_name,
        item.text
    ) || '-';
}

export function jsonArrayValue(value) {
    if (Array.isArray(value)) return value;

    if (typeof value !== 'string' || value.trim() === '') {
        return [];
    }

    try {
        const decoded = JSON.parse(value);

        return Array.isArray(decoded)
            ? decoded
            : [];

    } catch (error) {
        return [];
    }
}

export function normalizeVoucherLineRowType(value) {
    const raw = String(value ?? '').trim();
    const upper = raw.toUpperCase();

    if (
        raw === '보조'
        || ['AUX', 'AUXILIARY', 'REF', 'REFERENCE'].includes(upper)
    ) {
        return 'AUX';
    }

    return 'JOURNAL';
}

export function normalizeVoucherSourceLineNo(value, fallback) {
    const parsed = Number.parseInt(
        String(value ?? '').replace(/[^\d-]/g, ''),
        10
    );

    return Number.isFinite(parsed) && parsed > 0
        ? parsed
        : fallback;
}

export function isBlankEvidenceValue(value) {
    if (value === null || value === undefined) {
        return true;
    }

    if (Array.isArray(value)) {
        return value.length === 0;
    }

    if (typeof value === 'object') {
        return isBlankEvidenceValue(
            value.value
            ?? value.raw_value
            ?? value.display_value
            ?? ''
        );
    }

    return String(value).trim() === '';
}

export function normalizeTransactionLine(line = {}, payload = {}) {
    const amount = numericValue(
        line.amount
        ?? line.total_amount
        ?? line.supply_amount
        ?? ''
    );

    const qty = numericValue(
        line.item_qty
        ?? line.quantity
        ?? ''
    );

    const price = numericValue(
        line.item_price
        ?? line.unit_price
        ?? ''
    );

    const foreignPrice = numericValue(
        line.foreign_unit_price
        ?? ''
    );

    const foreignAmount = numericValue(
        line.foreign_amount
        ?? ''
    );

    return {
        line_type: line.line_type || line.amount_type || '품목',

        item_date:
            line.item_date
            || payload.transaction_date
            || '',

        item_name: line.item_name || '',

        item_spec:
            line.item_spec
            || line.specification
            || '',

        unit_name: line.unit_name || '',

        item_qty: qty ?? '',

        foreign_unit_price: foreignPrice ?? '',

        foreign_amount: foreignAmount ?? '',

        item_price: price ?? '',

        amount: amount ?? '',

        item_note:
            line.item_note
            || line.description
            || '',
    };
}

export function sourceEditable(row = {}) {
    const status = String(row?.voucher_status || '')
        .trim()
        .toUpperCase();

    return ![
        'CREATED',
        'PROCESSED',
        'DONE',
        'COMPLETED',
        'POSTED',
    ].includes(status);
}

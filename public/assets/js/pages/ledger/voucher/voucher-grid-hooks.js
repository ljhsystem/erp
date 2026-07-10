function normalizeAmount(value) {
    const raw = String(value ?? '').replaceAll(',', '').trim();
    if (raw === '') {
        return '0';
    }

    const numeric = Number(raw);
    return Number.isFinite(numeric) ? String(numeric) : '0';
}

function hasLineContent(line = {}) {
    const refs = Array.isArray(line.refs) ? line.refs : [];
    return Boolean(
        String(line.account_id || '').trim()
        || refs.length > 0
        || Number(line.debit || 0) > 0
        || Number(line.credit || 0) > 0
        || String(line.line_summary || '').trim()
    );
}

function sumAmount(rows = [], key = '') {
    return rows.reduce((total, row) => {
        const numeric = Number(String(row?.values?.[key] ?? 0).replaceAll(',', '').trim());
        return total + (Number.isFinite(numeric) ? numeric : 0);
    }, 0);
}

export function createVoucherLineGridHooks() {
    return {
        createRow({ row = {}, rowIndex = 0 }) {
            return {
                rowId: String(row.rowId || `voucher-line-${Date.now()}-${rowIndex + 1}`),
                rowState: row.rowState || 'created',
                values: {
                    line_no: Number(row.line_no || rowIndex + 1),
                    account_id: String(row.account_id || '').trim(),
                    refs: Array.isArray(row.refs) ? row.refs : [],
                    debit: normalizeAmount(row.debit),
                    credit: normalizeAmount(row.credit),
                    line_summary: String(row.line_summary || '').trim(),
                    row_action: '',
                },
                meta: row.meta && typeof row.meta === 'object' ? row.meta : {},
            };
        },
        normalizeRow(row = {}, { rowIndex = 0 } = {}) {
            return {
                rowId: String(row.rowId || `voucher-line-${Date.now()}-${rowIndex + 1}`),
                rowState: row.rowState || 'clean',
                values: {
                    line_no: Number(row?.values?.line_no || row.line_no || rowIndex + 1),
                    account_id: String(row?.values?.account_id || row.account_id || '').trim(),
                    refs: Array.isArray(row?.values?.refs) ? row.values.refs : (Array.isArray(row.refs) ? row.refs : []),
                    debit: normalizeAmount(row?.values?.debit ?? row.debit),
                    credit: normalizeAmount(row?.values?.credit ?? row.credit),
                    line_summary: String(row?.values?.line_summary || row.line_summary || '').trim(),
                    row_action: '',
                },
                meta: row.meta && typeof row.meta === 'object' ? row.meta : {},
            };
        },
        validator: {
            validateRow({ row }) {
                const accountId = String(row?.values?.account_id || '').trim();
                const debit = Number(normalizeAmount(row?.values?.debit));
                const credit = Number(normalizeAmount(row?.values?.credit));
                const summary = String(row?.values?.line_summary || '').trim();
                const refs = Array.isArray(row?.values?.refs) ? row.values.refs : [];
                const hasContent = Boolean(accountId || debit > 0 || credit > 0 || summary || refs.length > 0);

                if (!hasContent) {
                    return [];
                }

                const messages = [];

                if (!accountId) {
                    messages.push('\uACC4\uC815\uACFC\uBAA9\uC744 \uC120\uD0DD\uD574 \uC8FC\uC138\uC694.');
                }

                if (debit <= 0 && credit <= 0) {
                    messages.push('\uCC28\uBCC0 \uB610\uB294 \uB300\uBCC0 \uAE08\uC561\uC744 \uC785\uB825\uD574 \uC8FC\uC138\uC694.');
                }

                if (debit > 0 && credit > 0) {
                    messages.push('\uCC28\uBCC0\uACFC \uB300\uBCC0\uC740 \uD558\uB098\uB9CC \uC785\uB825\uD560 \uC218 \uC788\uC2B5\uB2C8\uB2E4.');
                }

                return messages;
            },
        },
        footer: {
            buildDefinitions() {
                return [
                    { key: 'debit', type: 'sum', source: 'debit' },
                    { key: 'credit', type: 'sum', source: 'credit' },
                    { key: 'difference', type: 'difference', minuend: 'debit', subtrahend: 'credit' },
                ];
            },
            collectMessages({ rows, values }) {
                if (!Array.isArray(rows) || rows.length === 0) {
                    return '\uBD84\uAC1C \uB77C\uC778\uC744 \uBA3C\uC800 \uC785\uB825\uD574 \uC8FC\uC138\uC694.';
                }

                return Number(values?.difference || 0) === 0
                    ? '\uCC28\uBCC0/\uB300\uBCC0 \uD569\uACC4\uAC00 \uC77C\uCE58\uD569\uB2C8\uB2E4.'
                    : '\uCC28\uBCC0/\uB300\uBCC0 \uD569\uACC4\uAC00 \uC77C\uCE58\uD558\uC9C0 \uC54A\uC2B5\uB2C8\uB2E4.';
            },
            hasDifference({ values }) {
                return Number(values?.difference || 0) !== 0;
            },
        },
        serializer: {
            serializeRow({ row }) {
                const line = {
                    account_id: String(row?.values?.account_id || '').trim(),
                    refs: Array.isArray(row?.values?.refs) ? row.values.refs : [],
                    debit: normalizeAmount(row?.values?.debit),
                    credit: normalizeAmount(row?.values?.credit),
                    line_summary: String(row?.values?.line_summary || '').trim(),
                };

                if (String(row?.rowState || '') === 'deleted' || !hasLineContent(line)) {
                    return null;
                }

                return line;
            },
            serializeMeta() {
                return {};
            },
        },
    };
}

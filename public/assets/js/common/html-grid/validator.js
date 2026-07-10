function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
}

function isBlankValue(value) {
    if (value == null) {
        return true;
    }

    if (typeof value === 'string') {
        return value.trim() === '';
    }

    return false;
}

function normalizeNumber(value) {
    if (isBlankValue(value)) {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const normalized = Number(String(value).replaceAll(',', '').trim());
    return Number.isFinite(normalized) ? normalized : null;
}

function normalizeDate(value) {
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return value;
    }

    if (isBlankValue(value)) {
        return null;
    }

    const text = String(value).trim().replaceAll('.', '-').replaceAll('/', '-');
    if (!/^\d{4}-\d{1,2}-\d{1,2}$/.test(text)) {
        return null;
    }

    const parsed = new Date(`${text}T00:00:00`);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function uniqueMessages(messages = []) {
    return messages
        .map((message) => String(message || '').trim())
        .filter((message, index, array) => message !== '' && array.indexOf(message) === index);
}

function addRowError(bucket, rowId, message) {
    const normalizedRowId = String(rowId || '').trim();
    const normalizedMessage = String(message || '').trim();
    if (normalizedRowId === '' || normalizedMessage === '') {
        return;
    }

    bucket[normalizedRowId] = uniqueMessages([...(bucket[normalizedRowId] || []), normalizedMessage]);
}

function addCellError(bucket, rowId, columnKey, message) {
    const normalizedRowId = String(rowId || '').trim();
    const normalizedColumnKey = String(columnKey || '').trim();
    const normalizedMessage = String(message || '').trim();
    if (normalizedRowId === '' || normalizedColumnKey === '' || normalizedMessage === '') {
        return;
    }

    bucket[normalizedRowId] = bucket[normalizedRowId] || {};
    bucket[normalizedRowId][normalizedColumnKey] = uniqueMessages([
        ...(bucket[normalizedRowId][normalizedColumnKey] || []),
        normalizedMessage,
    ]);
}

function normalizeResultMessages(result, fallbackType) {
    if (!result) {
        return [];
    }

    if (Array.isArray(result)) {
        return result;
    }

    if (typeof result === 'string') {
        return [{ type: fallbackType, message: result }];
    }

    if (typeof result === 'object') {
        if (Array.isArray(result.messages)) {
            return result.messages;
        }

        if (typeof result.message === 'string') {
            return [{ type: result.type || fallbackType, message: result.message }];
        }
    }

    return [];
}

function isRowSkipped(row = {}) {
    return ['deleted'].includes(String(row.rowState || '').trim());
}

function resolveColumns(config = {}, state = {}) {
    if (config.schema?.columns) {
        return config.schema.columns;
    }

    if (Array.isArray(config.columns)) {
        return config.columns;
    }

    if (Array.isArray(state.columns)) {
        return state.columns;
    }

    return [];
}

function buildDefaultCellValidations(row, column) {
    const messages = [];
    const value = row?.values?.[column.key];
    const label = String(column.label || column.key);

    if (column.required && isBlankValue(value)) {
        messages.push({
            type: 'cell',
            columnKey: column.key,
            message: `${label}은(는) 필수입니다.`,
        });
    }

    if (!isBlankValue(value) && column.type === 'number' && normalizeNumber(value) == null) {
        messages.push({
            type: 'cell',
            columnKey: column.key,
            message: `${label}은(는) 숫자여야 합니다.`,
        });
    }

    if (!isBlankValue(value) && column.type === 'date' && normalizeDate(value) == null) {
        messages.push({
            type: 'cell',
            columnKey: column.key,
            message: `${label}은(는) 날짜 형식이어야 합니다.`,
        });
    }

    return messages;
}

function createValidationResult(result = {}) {
    const rowErrors = result.rowErrors && typeof result.rowErrors === 'object' ? cloneValue(result.rowErrors) : {};
    const cellErrors = result.cellErrors && typeof result.cellErrors === 'object' ? cloneValue(result.cellErrors) : {};
    const messages = uniqueMessages(result.messages || []);
    const hasRowErrors = Object.keys(rowErrors).length > 0;
    const hasCellErrors = Object.keys(cellErrors).length > 0;

    return {
        hasError: Boolean(result.hasError || hasRowErrors || hasCellErrors || messages.length > 0),
        rowErrors,
        cellErrors,
        messages,
    };
}

export function createGridValidator(config = {}) {
    const hooks = config.hooks && typeof config.hooks === 'object' ? config.hooks : {};

    function validate(payload = {}) {
        const state = payload.state || config.state || {};
        const rows = Array.isArray(state.rows) ? state.rows : [];
        const columns = resolveColumns(config, state);
        const rowErrors = {};
        const cellErrors = {};
        const messages = [];

        rows.forEach((row, rowIndex) => {
            if (isRowSkipped(row)) {
                return;
            }

            columns.forEach((column) => {
                buildDefaultCellValidations(row, column).forEach((entry) => {
                    addCellError(cellErrors, row.rowId, entry.columnKey, entry.message);
                    messages.push(entry.message);
                });

                if (typeof hooks.validateCell === 'function') {
                    normalizeResultMessages(hooks.validateCell({
                        row,
                        rowIndex,
                        column,
                        value: row?.values?.[column.key],
                        state,
                        reason: payload.reason || '',
                    }), 'cell').forEach((entry) => {
                        const columnKey = String(entry.columnKey || column.key);
                        const message = String(entry.message || '');
                        addCellError(cellErrors, row.rowId, columnKey, message);
                        messages.push(message);
                    });
                }
            });

            if (typeof hooks.validateRow === 'function') {
                normalizeResultMessages(hooks.validateRow({
                    row,
                    rowIndex,
                    columns,
                    state,
                    reason: payload.reason || '',
                }), 'row').forEach((entry) => {
                    const message = String(entry.message || '');
                    addRowError(rowErrors, row.rowId, message);
                    messages.push(message);
                });
            }
        });

        if (typeof hooks.collectMessages === 'function') {
            const extraMessages = hooks.collectMessages({
                state,
                rows,
                columns,
                rowErrors: cloneValue(rowErrors),
                cellErrors: cloneValue(cellErrors),
                messages: uniqueMessages(messages),
                reason: payload.reason || '',
            });
            messages.push(...uniqueMessages(Array.isArray(extraMessages) ? extraMessages : [extraMessages]));
        }

        return createValidationResult({
            rowErrors,
            cellErrors,
            messages,
        });
    }

    return {
        validate,
        createResult: createValidationResult,
    };
}

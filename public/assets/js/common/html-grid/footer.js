function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
}

function uniqueMessages(messages = []) {
    return messages
        .map((message) => String(message || '').trim())
        .filter((message, index, array) => message !== '' && array.indexOf(message) === index);
}

function normalizeNumber(value) {
    if (value == null || value === '') {
        return 0;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    const normalized = Number(String(value).replaceAll(',', '').trim());
    return Number.isFinite(normalized) ? normalized : 0;
}

function isRowIncluded(row = {}) {
    return !['deleted'].includes(String(row.rowState || '').trim());
}

function createFooterResult(result = {}) {
    const values = result.values && typeof result.values === 'object' ? cloneValue(result.values) : {};
    const messages = uniqueMessages(result.messages || []);

    return {
        values,
        messages,
        hasDifference: Boolean(result.hasDifference),
    };
}

function computeDefinition(definition = {}, rows = [], currentValues = {}) {
    const type = String(definition.type || 'sum').trim();
    const source = String(definition.source || definition.key || '').trim();

    if (type === 'count') {
        return rows.length;
    }

    if (type === 'sum') {
        return rows.reduce((total, row) => total + normalizeNumber(row?.values?.[source]), 0);
    }

    if (type === 'difference') {
        const minuendKey = String(definition.minuend || '').trim();
        const subtrahendKey = String(definition.subtrahend || '').trim();
        return normalizeNumber(currentValues[minuendKey]) - normalizeNumber(currentValues[subtrahendKey]);
    }

    if (typeof definition.calculate === 'function') {
        return definition.calculate({ rows, values: cloneValue(currentValues) });
    }

    return null;
}

export function createGridFooter(config = {}) {
    const hooks = config.hooks && typeof config.hooks === 'object' ? config.hooks : {};

    function compute(payload = {}) {
        const state = payload.state || config.state || {};
        const rows = (Array.isArray(state.rows) ? state.rows : []).filter(isRowIncluded);
        const definitions = typeof hooks.buildDefinitions === 'function'
            ? hooks.buildDefinitions({ state, rows, reason: payload.reason || '' }) || []
            : (Array.isArray(config.definitions) ? config.definitions : []);

        const values = {};
        definitions.forEach((definition) => {
            const key = String(definition.key || '').trim();
            if (key === '') {
                return;
            }
            values[key] = computeDefinition(definition, rows, values);
        });

        let messages = [];
        if (typeof hooks.collectMessages === 'function') {
            const result = hooks.collectMessages({
                state,
                rows,
                values: cloneValue(values),
                reason: payload.reason || '',
            });
            messages = uniqueMessages(Array.isArray(result) ? result : [result]);
        }

        let hasDifference = false;
        if (typeof hooks.hasDifference === 'function') {
            hasDifference = Boolean(hooks.hasDifference({
                state,
                rows,
                values: cloneValue(values),
                reason: payload.reason || '',
            }));
        } else if (Object.prototype.hasOwnProperty.call(values, 'difference')) {
            hasDifference = normalizeNumber(values.difference) !== 0;
        }

        return createFooterResult({
            values,
            messages,
            hasDifference,
        });
    }

    return {
        compute,
        createResult: createFooterResult,
    };
}

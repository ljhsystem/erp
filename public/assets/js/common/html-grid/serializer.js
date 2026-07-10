function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
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

function normalizeRowPayload(row = {}, hooks = {}, context = {}) {
    if (typeof hooks.serializeRow === 'function') {
        return hooks.serializeRow({
            row: cloneValue(row),
            ...context,
        });
    }

    return {
        rowId: row.rowId,
        rowState: row.rowState,
        dirtyFields: Array.isArray(row.dirtyFields) ? [...row.dirtyFields] : [],
        values: cloneValue(row.values || {}),
        meta: cloneValue(row.meta || {}),
    };
}

function normalizeColumnsPayload(columns = [], state = {}, hooks = {}, context = {}) {
    if (typeof hooks.serializeColumns === 'function') {
        return hooks.serializeColumns({
            columns: cloneValue(columns),
            state: cloneValue(state),
            ...context,
        });
    }

    return columns.map((column) => ({
        key: column.key,
        visible: !((state.columns?.hidden || []).includes(column.key)),
        width: state.columns?.widths?.[column.key] ?? column.width ?? null,
        pinned: (state.columns?.pinned || []).includes(column.key),
    }));
}

export function createGridSerializer(config = {}) {
    const hooks = config.hooks && typeof config.hooks === 'object' ? config.hooks : {};
    const version = Number.isInteger(config.version) ? config.version : 1;

    function serialize(payload = {}) {
        const state = payload.state || config.state || {};
        const rows = Array.isArray(state.rows) ? state.rows : [];
        const columns = resolveColumns(config, state);
        const context = {
            reason: payload.reason || '',
            version,
        };

        const serializedRows = rows
            .map((row, rowIndex) => normalizeRowPayload(row, hooks, {
                rowIndex,
                state: cloneValue(state),
                columns: cloneValue(columns),
                ...context,
            }))
            .filter((row) => row != null);

        const meta = typeof hooks.serializeMeta === 'function'
            ? hooks.serializeMeta({
                state: cloneValue(state),
                columns: cloneValue(columns),
                rows: cloneValue(serializedRows),
                ...context,
            })
            : {
                gridId: state.gridId || '',
                columns: normalizeColumnsPayload(columns, state, hooks, context),
            };

        return {
            version,
            rows: serializedRows,
            meta: cloneValue(meta || {}),
        };
    }

    return {
        serialize,
    };
}

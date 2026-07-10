function isPlainObject(value) {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (isPlainObject(value)) {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
}

function uniqueStrings(values = []) {
    return values
        .map((value) => String(value || '').trim())
        .filter((value, index, array) => value !== '' && array.indexOf(value) === index);
}

function normalizeWidths(widths = {}, validKeys = new Set()) {
    if (!isPlainObject(widths)) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(widths)
            .map(([key, value]) => [String(key || '').trim(), Math.max(0, Number(value) || 0)])
            .filter(([key, value]) => key !== '' && validKeys.has(key) && value > 0)
    );
}

function normalizeMeta(meta = {}, validKeys = new Set()) {
    if (!isPlainObject(meta)) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(meta)
            .map(([key, value]) => [String(key || '').trim(), isPlainObject(value) ? cloneValue(value) : {}])
            .filter(([key]) => key !== '' && validKeys.has(key))
    );
}

function createInitialColumnState(schema, initialState = {}) {
    const columnKeys = Array.isArray(schema?.columnKeys) ? schema.columnKeys : [];
    const validKeys = new Set(columnKeys);
    const baseOrder = columnKeys.slice();
    const requestedOrder = Array.isArray(initialState.order) ? uniqueStrings(initialState.order).filter((key) => validKeys.has(key)) : [];
    const missingKeys = baseOrder.filter((key) => !requestedOrder.includes(key));
    const hidden = Array.isArray(initialState.hidden)
        ? uniqueStrings(initialState.hidden).filter((key) => validKeys.has(key))
        : baseOrder.filter((key) => schema.columnMap[key]?.visible === false);
    const pinned = Array.isArray(initialState.pinned)
        ? uniqueStrings(initialState.pinned).filter((key) => validKeys.has(key))
        : baseOrder.filter((key) => schema.columnMap[key]?.pinned);

    return {
        order: [...requestedOrder, ...missingKeys],
        hidden,
        pinned,
        widths: normalizeWidths(initialState.widths, validKeys),
        meta: {
            ...Object.fromEntries(baseOrder.map((key) => [key, cloneValue(schema.columnMap[key]?.meta || {})])),
            ...normalizeMeta(initialState.meta, validKeys),
        },
    };
}

function cloneColumnState(state = {}) {
    return {
        order: Array.isArray(state.order) ? [...state.order] : [],
        hidden: Array.isArray(state.hidden) ? [...state.hidden] : [],
        pinned: Array.isArray(state.pinned) ? [...state.pinned] : [],
        widths: isPlainObject(state.widths) ? { ...state.widths } : {},
        meta: isPlainObject(state.meta) ? cloneValue(state.meta) : {},
    };
}

function reorderKeys(currentOrder = [], nextOrder = [], validKeys = new Set()) {
    const requestedOrder = uniqueStrings(nextOrder).filter((key) => validKeys.has(key));
    const missingKeys = currentOrder.filter((key) => validKeys.has(key) && !requestedOrder.includes(key));
    return [...requestedOrder, ...missingKeys];
}

export function createColumnManager(config = {}) {
    const schema = config.schema;
    if (!schema || !Array.isArray(schema.columnKeys) || !isPlainObject(schema.columnMap)) {
        throw new Error('[html-grid] column-manager requires runtime schema.');
    }

    const validKeys = new Set(schema.columnKeys);
    let columnState = createInitialColumnState(schema, config.state || {});

    function assertColumnKey(key) {
        const normalized = String(key || '').trim();
        if (normalized === '' || !validKeys.has(normalized)) {
            throw new Error(`[html-grid] unknown column key "${String(key || '')}".`);
        }
        return normalized;
    }

    function replaceState(nextState = {}) {
        columnState = createInitialColumnState(schema, nextState);
        return getState();
    }

    function getState() {
        return cloneColumnState(columnState);
    }

    function getOrderedColumns() {
        return columnState.order.map((key) => schema.columnMap[key]);
    }

    function getVisibleColumns() {
        return columnState.order
            .filter((key) => !columnState.hidden.includes(key))
            .map((key) => schema.columnMap[key]);
    }

    function setOrder(nextOrder = []) {
        columnState = {
            ...columnState,
            order: reorderKeys(columnState.order, nextOrder, validKeys),
        };
        return getState();
    }

    function setVisible(key, visible = true) {
        const normalizedKey = assertColumnKey(key);
        const hidden = new Set(columnState.hidden);
        if (visible) {
            hidden.delete(normalizedKey);
        } else {
            hidden.add(normalizedKey);
        }

        columnState = {
            ...columnState,
            hidden: columnState.order.filter((columnKey) => hidden.has(columnKey)),
        };
        return getState();
    }

    function setWidth(key, width) {
        const normalizedKey = assertColumnKey(key);
        const normalizedWidth = Math.max(0, Number(width) || 0);
        const nextWidths = { ...columnState.widths };

        if (normalizedWidth > 0) {
            nextWidths[normalizedKey] = normalizedWidth;
        } else {
            delete nextWidths[normalizedKey];
        }

        columnState = {
            ...columnState,
            widths: nextWidths,
        };
        return getState();
    }

    function setPinned(key, pinned = true) {
        const normalizedKey = assertColumnKey(key);
        const pinnedKeys = new Set(columnState.pinned);
        if (pinned) {
            pinnedKeys.add(normalizedKey);
        } else {
            pinnedKeys.delete(normalizedKey);
        }

        columnState = {
            ...columnState,
            pinned: columnState.order.filter((columnKey) => pinnedKeys.has(columnKey)),
        };
        return getState();
    }

    function setMeta(key, meta = {}) {
        const normalizedKey = assertColumnKey(key);
        columnState = {
            ...columnState,
            meta: {
                ...columnState.meta,
                [normalizedKey]: isPlainObject(meta) ? cloneValue(meta) : {},
            },
        };
        return getState();
    }

    function serialize() {
        return getState();
    }

    return {
        getState,
        replaceState,
        getOrderedColumns,
        getVisibleColumns,
        setOrder,
        setVisible,
        setWidth,
        setPinned,
        setMeta,
        serialize,
    };
}

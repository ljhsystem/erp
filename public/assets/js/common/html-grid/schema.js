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

function deepFreeze(value) {
    if (Array.isArray(value)) {
        value.forEach((entry) => deepFreeze(entry));
        return Object.freeze(value);
    }

    if (isPlainObject(value)) {
        Object.values(value).forEach((entry) => deepFreeze(entry));
        return Object.freeze(value);
    }

    return value;
}

function normalizeRegistry(registry) {
    if (registry instanceof Map) {
        return new Set(Array.from(registry.keys()).map((key) => String(key || '').trim()).filter(Boolean));
    }

    if (registry instanceof Set) {
        return new Set(Array.from(registry).map((key) => String(key || '').trim()).filter(Boolean));
    }

    if (Array.isArray(registry)) {
        return new Set(registry.map((key) => String(key || '').trim()).filter(Boolean));
    }

    if (isPlainObject(registry)) {
        return new Set(Object.keys(registry).map((key) => String(key || '').trim()).filter(Boolean));
    }

    return new Set();
}

function hasRegistryValue(registry, value) {
    if (typeof value === 'function') {
        return true;
    }

    const normalized = String(value || '').trim();
    if (normalized === '') {
        return false;
    }

    return normalizeRegistry(registry).has(normalized);
}

function normalizePlugins(plugins = []) {
    const list = Array.isArray(plugins) ? plugins : [plugins];
    return list
        .map((plugin) => String(plugin || '').trim())
        .filter((plugin, index, array) => plugin !== '' && array.indexOf(plugin) === index);
}

function normalizeColumnDefinition(column = {}, index = 0, options = {}) {
    const key = String(column.key || column.field || '').trim();
    if (key === '') {
        throw new Error(`[html-grid] schema column at index ${index} requires key.`);
    }

    const editor = column.editor ?? null;
    const plugins = normalizePlugins(column.plugins ?? column.plugin ?? []);
    const formatter = column.formatter ?? null;

    if (editor !== null && editor !== '' && !hasRegistryValue(options.editors, editor)) {
        throw new Error(`[html-grid] schema column "${key}" references unknown editor "${String(editor)}".`);
    }

    plugins.forEach((pluginName) => {
        if (!hasRegistryValue(options.plugins, pluginName)) {
            throw new Error(`[html-grid] schema column "${key}" references unknown plugin "${pluginName}".`);
        }
    });

    if (formatter !== null && formatter !== '' && !hasRegistryValue(options.formatters, formatter)) {
        throw new Error(`[html-grid] schema column "${key}" references unknown formatter "${String(formatter)}".`);
    }

    const runtimeColumn = {
        key,
        label: String(column.label || column.title || key),
        type: String(column.type || 'text'),
        width: column.width == null || column.width === '' ? null : Math.max(0, Number(column.width) || 0),
        minWidth: column.minWidth == null || column.minWidth === '' ? null : Math.max(0, Number(column.minWidth) || 0),
        maxWidth: column.maxWidth == null || column.maxWidth === '' ? null : Math.max(0, Number(column.maxWidth) || 0),
        visible: column.visible !== false,
        pinned: String(column.pinned || '').trim() || null,
        editable: column.editable !== false,
        required: Boolean(column.required),
        readonly: Boolean(column.readonly),
        disabled: Boolean(column.disabled),
        sticky: Boolean(column.sticky),
        editor,
        plugins,
        formatter,
        meta: isPlainObject(column.meta) ? cloneValue(column.meta) : {},
    };

    return deepFreeze(runtimeColumn);
}

export function createRuntimeSchema(columns = [], options = {}) {
    if (!Array.isArray(columns)) {
        throw new Error('[html-grid] schema columns must be an array.');
    }

    const normalizedColumns = columns.map((column, index) => normalizeColumnDefinition(column, index, options));
    const keySet = new Set();

    normalizedColumns.forEach((column) => {
        if (keySet.has(column.key)) {
            throw new Error(`[html-grid] schema column key "${column.key}" is duplicated.`);
        }
        keySet.add(column.key);
    });

    const schema = {
        columns: normalizedColumns,
        columnKeys: normalizedColumns.map((column) => column.key),
        columnMap: Object.freeze(Object.fromEntries(normalizedColumns.map((column) => [column.key, column]))),
    };

    return deepFreeze(schema);
}


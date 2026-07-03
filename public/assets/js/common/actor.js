import { escapeHtml } from './html.js';

const UNKNOWN_ACTOR_LABEL = '(알 수 없음)';

function valueText(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

function nonEmptyValue(value) {
    const text = valueText(value);
    return text === '' ? '' : text;
}

export function actorNameField(field) {
    const key = String(field ?? '');
    return key === '' ? '' : `${key}_name`;
}

export function actorDisplay(row, field) {
    const source = row && typeof row === 'object' ? row : {};
    const key = String(field ?? '');
    if (key === '') {
        return UNKNOWN_ACTOR_LABEL;
    }

    return nonEmptyValue(source[actorNameField(key)])
        || nonEmptyValue(source[key])
        || UNKNOWN_ACTOR_LABEL;
}

export function actorExcel(row, field) {
    return actorDisplay(row, field);
}

export function actorColumn(field, title, options = {}) {
    const key = String(field ?? '');
    return {
        data: key,
        title,
        ...options,
        type: 'actor',
        render(value, type, row, meta) {
            if (typeof options.render === 'function' && type !== 'display') {
                return options.render(value, type, row, meta);
            }

            const displayValue = actorDisplay(row, key);
            return type === 'display' ? escapeHtml(displayValue) : displayValue;
        },
    };
}

export function applyActorDataTableColumn(column = {}) {
    if (!column || column.type !== 'actor') {
        return column;
    }

    const field = String(column.data ?? column.key ?? column.name ?? column.field ?? '');
    if (field === '') {
        return column;
    }

    return actorColumn(field, column.title ?? column.headerName ?? field, column);
}

export function applyActorAgGridColumn(column = {}) {
    if (!column || column.type !== 'actor') {
        return column;
    }

    const field = String(column.field ?? column.data ?? column.key ?? column.colId ?? '');
    if (field === '') {
        return column;
    }

    return {
        ...column,
        field,
        valueFormatter(params = {}) {
            return actorDisplay(params.data, field);
        },
    };
}

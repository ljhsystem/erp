import {
    getCachedDataTableMetaColumns,
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from './dataTableSettings.js';

const POLICY = Object.freeze({ NONE: 'none', OPTIONAL: 'optional', REQUIRED: 'required' });

function directLabel(control) {
    return control.closest('[data-table-settings-field], [class*="col-"]')
        ?.querySelector(':scope > label.form-label') || null;
}

function fieldContainer(control) {
    return control.closest('[data-table-settings-field], [class*="col-"]');
}

function renderRequirementMarker(label, policy) {
    if (!(label instanceof HTMLLabelElement)) return;
    label.querySelectorAll(':scope > [data-table-settings-requirement], :scope > .text-danger, :scope > .text-primary')
        .forEach(marker => {
            if (String(marker.textContent || '').trim() === '*') marker.remove();
        });
    if (![POLICY.REQUIRED, POLICY.OPTIONAL].includes(policy)) return;
    const marker = document.createElement('span');
    marker.dataset.tableSettingsRequirement = policy;
    marker.className = policy === POLICY.REQUIRED ? 'text-danger' : 'text-primary';
    marker.title = policy === POLICY.REQUIRED ? '필수 입력' : '선택 입력';
    marker.textContent = '*';
    label.append(document.createTextNode(' '), marker);
}

function replaceLabelText(label, text) {
    if (!(label instanceof HTMLLabelElement) || text === '') return;
    const firstText = Array.from(label.childNodes).find(node => node.nodeType === Node.TEXT_NODE);
    if (firstText) firstText.textContent = text;
    else label.prepend(document.createTextNode(text));
}

export function createDataTableFormSettings({
    form,
    metaDomain,
    storageKey,
    settingsOptions = {},
    stateProvider = null,
    businessPolicy = () => ({}),
} = {}) {
    const apply = () => {
        const columns = getCachedDataTableMetaColumns({ metaDomain });
        if (!form || columns.length === 0) return new Map();
        const metaByKey = new Map(columns.map(column => [String(column.key || ''), column]));
        const providedState = typeof stateProvider === 'function' ? stateProvider() : null;
        const state = providedState && typeof providedState === 'object'
            ? providedState
            : readDataTableSettingsState(storageKey, settingsOptions);
        const resolved = new Map();

        form.querySelectorAll('[name]').forEach(control => {
            const key = String(control.name || '').trim();
            const column = metaByKey.get(key);
            const label = directLabel(control);
            const container = fieldContainer(control);
            if (!column || !label || !container) return;

            const configuredPolicy = state
                ? resolveDataTableColumnRequirementPolicy(column, state)
                : (column.required ? POLICY.REQUIRED : POLICY.NONE);
            const business = businessPolicy({ key, column, control, configuredPolicy }) || {};
            const required = configuredPolicy === POLICY.REQUIRED || business.required === true;
            const policy = required
                ? POLICY.REQUIRED
                : (configuredPolicy === POLICY.OPTIONAL ? POLICY.OPTIONAL : POLICY.NONE);
            const displayName = resolveDataTableColumnDisplayName(
                column,
                state,
                column.label || column.key
            );

            replaceLabelText(label, displayName);
            renderRequirementMarker(label, policy);
            control.required = required;
            control.dataset.columnMetaRequired = required ? '1' : '0';
            resolved.set(key, { displayName, policy, required });
        });
        return resolved;
    };

    return { apply };
}

export { POLICY as DATA_TABLE_FORM_REQUIREMENT_POLICY };

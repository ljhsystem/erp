import {
    buildExcelColumnSettingsKey,
    buildExcelDownloadColumns,
    buildExcelTemplateColumns,
} from '../../common/column-meta/index.js';
import { loadExcelSettings, saveExcelSettings } from './storage.js';
import {
    ensureExcelSettingsLayout,
    renderExcelSettingsPanel,
    toggleExcelSettingsRoot,
    toggleExcelSettingsPanel,
} from './ui.js';

function notify(type, message) {
    if (window.AppCore?.notify) {
        window.AppCore.notify(type, message);
    }
}

function getDefaultColumns(domain, type) {
    return type === 'template'
        ? buildExcelTemplateColumns(domain)
        : buildExcelDownloadColumns(domain);
}

function createDefaultState(domain, type) {
    const columns = getDefaultColumns(domain, type).map((column, index) => ({
        key: column.key,
        label: column.label,
        required: column.required === true,
        visible: column.defaultSelected === true,
        order: index + 1,
    }));

    return {
        version: 1,
        domain,
        type,
        updatedAt: '',
        columns,
    };
}

function normalizeState(domain, type, state) {
    const defaults = createDefaultState(domain, type);
    const savedColumns = Array.isArray(state?.columns) ? state.columns : [];
    const savedMap = new Map(savedColumns.map((column) => [column.key, column]));

    const columns = defaults.columns.map((column, index) => {
        const saved = savedMap.get(column.key);
        return {
            ...column,
            visible: saved?.visible !== false,
            order: Number(saved?.order || index + 1),
        };
    }).sort((a, b) => a.order - b.order);

    return {
        version: 1,
        domain,
        type,
        updatedAt: String(state?.updatedAt || ''),
        columns: columns.map((column, index) => ({
            ...column,
            order: index + 1,
        })),
    };
}

function settingsKey(domain, type) {
    return buildExcelColumnSettingsKey(domain, type);
}

function loadState(domain, type) {
    const fallback = createDefaultState(domain, type);
    return normalizeState(domain, type, loadExcelSettings(settingsKey(domain, type), fallback));
}

function persistState(domain, type, state) {
    const normalized = normalizeState(domain, type, state);
    const payload = saveExcelSettings(settingsKey(domain, type), normalized);
    return normalizeState(domain, type, payload);
}

function moveColumn(state, key, direction) {
    const index = state.columns.findIndex((column) => column.key === key);
    if (index < 0) {
        return state;
    }

    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= state.columns.length) {
        return state;
    }

    const columns = [...state.columns];
    const [current] = columns.splice(index, 1);
    columns.splice(targetIndex, 0, current);

    return {
        ...state,
        columns: columns.map((column, order) => ({
            ...column,
            order: order + 1,
        })),
    };
}

function setVisibility(state, key, visible) {
    return {
        ...state,
        columns: state.columns.map((column) => {
            if (column.key !== key) {
                return column;
            }

            return {
                ...column,
                visible: column.required ? true : visible,
            };
        }),
    };
}

function selectAll(state) {
    return {
        ...state,
        columns: state.columns.map((column) => ({
            ...column,
            visible: true,
        })),
    };
}

function buildSelectedColumns(state) {
    return (Array.isArray(state?.columns) ? state.columns : [])
        .filter((column) => column.visible !== false)
        .sort((a, b) => Number(a.order || 0) - Number(b.order || 0))
        .map((column) => column.key);
}

function syncPreparedColumns(form, states) {
    if (!form) {
        return;
    }

    const templateColumns = buildSelectedColumns(states.template);
    const downloadColumns = buildSelectedColumns(states.download);

    form.dataset.excelTemplateColumns = JSON.stringify(templateColumns);
    form.dataset.excelDownloadColumns = JSON.stringify(downloadColumns);
    form.__excelPreparedColumns = {
        template: templateColumns,
        download: downloadColumns,
    };
}

function render(form, states) {
    renderExcelSettingsPanel(form, 'template', states.template.columns);
    renderExcelSettingsPanel(form, 'download', states.download.columns);
    syncPreparedColumns(form, states);
}

function bindEvents(form, domain, states) {
    if (form.__excelSettingsCoreBound === true) {
        return;
    }

    form.__excelSettingsCoreBound = true;

    form.addEventListener('click', (event) => {
        const rootToggleButton = event.target.closest('[data-excel-settings-root-toggle]');
        if (rootToggleButton) {
            toggleExcelSettingsRoot(form);
            return;
        }

        const toggleButton = event.target.closest('[data-excel-settings-toggle]');
        if (toggleButton) {
            toggleExcelSettingsPanel(form, toggleButton.dataset.excelSettingsToggle || '');
            return;
        }

        const actionButton = event.target.closest('[data-excel-settings-action]');
        if (actionButton) {
            const type = actionButton.dataset.type || '';
            const action = actionButton.dataset.excelSettingsAction || '';

            if (action === 'select-all') {
                states[type] = selectAll(states[type]);
                render(form, states);
                return;
            }

            if (action === 'reset') {
                states[type] = createDefaultState(domain, type);
                render(form, states);
                return;
            }

            if (action === 'save') {
                states[type] = persistState(domain, type, states[type]);
                render(form, states);
                notify(
                    'success',
                    type === 'template'
                        ? '\uc591\uc2dd \uc124\uc815\uc774 \uc800\uc7a5\ub418\uc5c8\uc2b5\ub2c8\ub2e4.'
                        : '\ub2e4\uc6b4\ub85c\ub4dc \uc124\uc815\uc774 \uc800\uc7a5\ub418\uc5c8\uc2b5\ub2c8\ub2e4.'
                );
            }
            return;
        }

        const moveButton = event.target.closest('[data-excel-move]');
        if (moveButton) {
            const type = moveButton.dataset.type || '';
            const key = moveButton.dataset.key || '';
            const direction = moveButton.dataset.excelMove || '';
            states[type] = moveColumn(states[type], key, direction);
            render(form, states);
        }
    });

    form.addEventListener('change', (event) => {
        const toggle = event.target.closest('[data-excel-column-toggle]');
        if (!toggle) {
            return;
        }

        const type = toggle.dataset.type || '';
        const key = toggle.dataset.key || '';
        states[type] = setVisibility(states[type], key, toggle.checked);
        render(form, states);
    });
}

export function createExcelManagerSettingsCore(config) {
    const domain = String(config?.domain || '').trim();
    const formSelector = String(config?.formSelector || '').trim();
    const form = document.querySelector(formSelector);

    if (!domain || !form) {
        return null;
    }

    const states = {
        template: loadState(domain, 'template'),
        download: loadState(domain, 'download'),
    };

    ensureExcelSettingsLayout(form);
    render(form, states);
    bindEvents(form, domain, states);

    return {
        domain,
        form,
        getState(type) {
            return states[type] || null;
        },
        getSelectedColumns(type) {
            return buildSelectedColumns(states[type] || null);
        },
        reload() {
            states.template = loadState(domain, 'template');
            states.download = loadState(domain, 'download');
            render(form, states);
        },
    };
}

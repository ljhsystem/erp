function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function panelTitle(type) {
    return type === 'template' ? '\uc591\uc2dd \uc124\uc815' : '\ub2e4\uc6b4\ub85c\ub4dc \uc124\uc815';
}

function panelDescription(type) {
    return type === 'template'
        ? '\uc5c5\ub85c\ub4dc\uc6a9 \uc591\uc2dd\uc5d0 \ud3ec\ud568\ud560 \uceec\ub7fc\uc744 \uc120\ud0dd\ud558\uc138\uc694.'
        : '\ub370\uc774\ud130 \ub2e4\uc6b4\ub85c\ub4dc\uc5d0 \ud3ec\ud568\ud560 \uceec\ub7fc\uacfc \uc21c\uc11c\ub97c \uc124\uc815\ud558\uc138\uc694.';
}

export function ensureExcelSettingsLayout(form) {
    if (!form || form.querySelector('[data-excel-settings-root]')) {
        return;
    }

    const modalBody = form.querySelector('.modal-body');
    const actionGrid = form.querySelector('.excel-action-grid');
    if (!modalBody || !actionGrid) {
        return;
    }

    const root = document.createElement('div');
    root.className = 'excel-settings-root mb-3';
    root.setAttribute('data-excel-settings-root', '1');
    root.innerHTML = `
        <button type="button"
                class="btn btn-outline-dark btn-sm excel-settings-trigger"
                data-excel-settings-root-toggle
                aria-expanded="false">
            <span>\uc5d1\uc140\uad00\ub9ac</span>
            <i class="bi bi-gear ms-1" aria-hidden="true"></i>
        </button>
        <div class="excel-settings-shell d-none mt-3" data-excel-settings-shell>
            <div class="excel-settings-heading">\uc5d1\uc140 \uc124\uc815</div>
            <div class="excel-settings-accordion">
                <div class="excel-settings-item" data-excel-settings-item="template">
                    <button type="button"
                            class="excel-settings-accordion-toggle"
                            data-excel-settings-toggle="template"
                            aria-expanded="false">
                        <span>\uc591\uc2dd \uc124\uc815</span>
                        <i class="bi bi-chevron-right" data-excel-settings-icon aria-hidden="true"></i>
                    </button>
                    <div class="d-none" data-excel-settings-panel="template"></div>
                </div>
                <div class="excel-settings-item" data-excel-settings-item="download">
                    <button type="button"
                            class="excel-settings-accordion-toggle"
                            data-excel-settings-toggle="download"
                            aria-expanded="false">
                        <span>\ub2e4\uc6b4\ub85c\ub4dc \uc124\uc815</span>
                        <i class="bi bi-chevron-right" data-excel-settings-icon aria-hidden="true"></i>
                    </button>
                    <div class="d-none" data-excel-settings-panel="download"></div>
                </div>
            </div>
        </div>
    `;

    modalBody.insertBefore(root, actionGrid);

    const dialog = form.closest('.modal')?.querySelector('.modal-dialog');
    dialog?.classList.add('modal-lg');
}

export function renderExcelSettingsPanel(form, type, columns) {
    const panel = form?.querySelector(`[data-excel-settings-panel="${type}"]`);
    if (!panel) {
        return;
    }

    const rowsHtml = columns.map((column, index) => {
        const checked = column.visible !== false ? 'checked' : '';
        const disabled = column.required ? 'disabled' : '';
        const requiredBadge = column.required
            ? '<span class="badge text-bg-primary ms-2">\ud544\uc218</span>'
            : '';

        return `
            <div class="list-group-item d-flex align-items-center gap-2" data-excel-column-row data-type="${type}" data-key="${escapeHtml(column.key)}">
                <span class="text-muted small">${index + 1}</span>
                <input class="form-check-input mt-0" type="checkbox" data-excel-column-toggle data-type="${type}" data-key="${escapeHtml(column.key)}" ${checked} ${disabled}>
                <div class="flex-grow-1">
                    <div class="fw-semibold">${escapeHtml(column.label)}${requiredBadge}</div>
                    <div class="text-muted small">${escapeHtml(column.key)}</div>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="${panelTitle(type)} \uc21c\uc11c \ubcc0\uacbd">
                    <button type="button" class="btn btn-outline-secondary" data-excel-move="up" data-type="${type}" data-key="${escapeHtml(column.key)}">\uc704</button>
                    <button type="button" class="btn btn-outline-secondary" data-excel-move="down" data-type="${type}" data-key="${escapeHtml(column.key)}">\uc544\ub798</button>
                </div>
            </div>
        `;
    }).join('');

    const selectedCount = columns.filter((column) => column.visible !== false).length;

    panel.innerHTML = `
        <div class="excel-settings-panel-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <div class="text-muted small">${panelDescription(type)}</div>
                </div>
                <div class="text-muted small">\uc120\ud0dd ${selectedCount}\uac1c / \uc804\uccb4 ${columns.length}\uac1c</div>
            </div>
            <div class="list-group">${rowsHtml}</div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-excel-settings-action="reset" data-type="${type}">\uae30\ubcf8\uac12 \ubcf5\uc6d0</button>
                <button type="button" class="btn btn-primary btn-sm" data-excel-settings-action="save" data-type="${type}">\uc800\uc7a5</button>
            </div>
        </div>
    `;
}

export function toggleExcelSettingsRoot(form) {
    const shell = form?.querySelector('[data-excel-settings-shell]');
    const trigger = form?.querySelector('[data-excel-settings-root-toggle]');
    if (!shell || !trigger) {
        return;
    }

    const shouldShow = shell.classList.contains('d-none');
    shell.classList.toggle('d-none', !shouldShow);
    trigger.setAttribute('aria-expanded', shouldShow ? 'true' : 'false');

    if (!shouldShow) {
        toggleExcelSettingsPanel(form, '');
    }
}

export function toggleExcelSettingsPanel(form, targetType) {
    ['template', 'download'].forEach((type) => {
        const panel = form?.querySelector(`[data-excel-settings-panel="${type}"]`);
        const item = form?.querySelector(`[data-excel-settings-item="${type}"]`);
        const toggleButton = form?.querySelector(`[data-excel-settings-toggle="${type}"]`);
        const icon = toggleButton?.querySelector('[data-excel-settings-icon]');
        if (!panel) {
            return;
        }

        const shouldShow = type === targetType ? panel.classList.contains('d-none') : false;
        panel.classList.toggle('d-none', !shouldShow);
        item?.classList.toggle('is-open', shouldShow);
        toggleButton?.setAttribute('aria-expanded', shouldShow ? 'true' : 'false');
        if (icon) {
            icon.classList.toggle('bi-chevron-right', !shouldShow);
            icon.classList.toggle('bi-chevron-down', shouldShow);
        }
    });
}

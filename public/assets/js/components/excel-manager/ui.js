function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function isTemplateType(type) {
    return String(type || '').trim().toLowerCase() === 'template';
}

function normalizeRequirementPolicy(value = '') {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === 'required') return 'required';
    if (normalized === 'optional') return 'optional';
    return 'none';
}

function requirementPolicySelectClass(policy = '') {
    const normalized = normalizeRequirementPolicy(policy);
    if (normalized === 'required') return 'is-required';
    if (normalized === 'optional') return 'is-optional';
    return 'is-none';
}

function panelTitle(type) {
    return isTemplateType(type) ? '업로드 양식설정' : '다운로드 설정';
}

function panelDescription(type) {
    return isTemplateType(type)
        ? '업로드 양식에 포함할 컬럼과 순서를 설정합니다.'
        : '다운로드에 포함할 컬럼과 순서를 설정합니다.';
}

function requirementStarHtml(policy = '') {
    const normalized = normalizeRequirementPolicy(policy);
    if (normalized === 'required') {
        return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
    }
    if (normalized === 'optional') {
        return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
    }
    return '';
}

function renderColumnLabel(column) {
    const displayLabel = String(column?.displayLabel || column?.label || '').trim();
    const sourceLabel = String(column?.sourceLabel || column?.key || '').trim();
    const combinedLabel = displayLabel && sourceLabel
        ? `${displayLabel}(${sourceLabel})`
        : (displayLabel || sourceLabel || String(column?.key || '').trim());
    const starPolicy = String(
        column?.requirementPolicy
        ?? column?.metaRequirementPolicy
        ?? ''
    ).trim();

    return `${escapeHtml(combinedLabel)} ${requirementStarHtml(starPolicy)}`.trim();
}

function renderDragHeader() {
    return `
        <span class="excel-settings-head-icon" aria-hidden="true">
            <i class="bi bi-arrows-move"></i>
        </span>
        <span class="visually-hidden">정렬</span>
    `;
}

function renderUsageHeader(type, columns) {
    const editableColumns = Array.isArray(columns) ? columns : [];
    const checkedCount = editableColumns.filter((column) => column.visible !== false).length;
    const allChecked = editableColumns.length > 0 && checkedCount === editableColumns.length;
    const partiallyChecked = checkedCount > 0 && checkedCount < editableColumns.length;

    return `
        <label class="excel-settings-head-toggle" aria-label="전체 선택">
            <input class="form-check-input mt-0"
                   type="checkbox"
                   data-excel-column-toggle-all
                   data-type="${escapeHtml(type)}"
                   ${allChecked ? 'checked' : ''}
                   ${partiallyChecked ? 'data-indeterminate="true"' : ''}
                   ${editableColumns.length === 0 ? 'disabled' : ''}>
        </label>
    `;
}

function renderRows(type, columns) {
    return columns.map((column, index) => {
        const checked = column.visible !== false ? 'checked' : '';
        const requirementPolicy = normalizeRequirementPolicy(column?.requirementPolicy);
        const requirementCell = isTemplateType(type)
            ? `
                <div class="excel-settings-grid-cell dt-column-settings-policy-cell">
                    <select class="form-select form-select-sm dt-requirement-policy-select ${requirementPolicySelectClass(requirementPolicy)}"
                            data-excel-column-requirement
                            data-type="${escapeHtml(type)}"
                            data-key="${escapeHtml(column.key)}">
                        <option value="none" ${requirementPolicy === 'none' ? 'selected' : ''}>사용안함</option>
                        <option value="optional" ${requirementPolicy === 'optional' ? 'selected' : ''}>선택</option>
                        <option value="required" ${requirementPolicy === 'required' ? 'selected' : ''}>필수</option>
                    </select>
                </div>
            `
            : '';

        return `
            <div class="excel-settings-grid-row ${isTemplateType(type) ? 'excel-settings-grid-row-template' : 'excel-settings-grid-row-download'}"
                 draggable="true"
                 data-excel-column-row
                 data-type="${escapeHtml(type)}"
                 data-key="${escapeHtml(column.key)}">
                <div class="excel-settings-grid-cell excel-settings-grid-cell-usage">
                    <input class="form-check-input mt-0"
                           type="checkbox"
                           data-excel-column-toggle
                           data-type="${escapeHtml(type)}"
                           data-key="${escapeHtml(column.key)}"
                           ${checked}>
                </div>
                <div class="excel-settings-grid-cell excel-settings-grid-cell-handle">
                    <button type="button"
                            class="excel-settings-drag-handle"
                            aria-label="${escapeHtml(String(column.displayLabel || column.label || column.key || '컬럼'))} 순서 변경"
                            tabindex="-1"
                            data-excel-drag-handle>
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="excel-settings-grid-cell excel-settings-grid-cell-order">
                    <span class="excel-settings-order-value">${index + 1}</span>
                </div>
                <div class="excel-settings-grid-cell excel-settings-grid-cell-label">
                    <div class="excel-settings-column-label">${renderColumnLabel(column)}</div>
                </div>
                ${requirementCell}
            </div>
        `;
    }).join('');
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
            <span>컬럼설정</span>
            <i class="bi bi-gear ms-1" aria-hidden="true"></i>
        </button>
        <div class="excel-settings-shell d-none mt-3" data-excel-settings-shell>
            <div class="excel-settings-heading">엑셀 컬럼 설정</div>
            <div class="excel-settings-accordion">
                <div class="excel-settings-item" data-excel-settings-item="template">
                    <button type="button"
                            class="excel-settings-accordion-toggle"
                            data-excel-settings-toggle="template"
                            aria-expanded="false">
                        <span>업로드 양식설정</span>
                        <i class="bi bi-chevron-right" data-excel-settings-icon aria-hidden="true"></i>
                    </button>
                    <div class="d-none" data-excel-settings-panel="template"></div>
                </div>
                <div class="excel-settings-item" data-excel-settings-item="download">
                    <button type="button"
                            class="excel-settings-accordion-toggle"
                            data-excel-settings-toggle="download"
                            aria-expanded="false">
                        <span>다운로드 설정</span>
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

    const selectedCount = columns.filter((column) => column.visible !== false).length;
    const gridClassName = isTemplateType(type)
        ? 'excel-settings-grid is-template excel-upload-settings-grid'
        : 'excel-settings-grid is-download';
    const requirementHeader = isTemplateType(type)
        ? '<div class="excel-settings-grid-head dt-column-settings-policy-head">필수구분</div>'
        : '';
    panel.innerHTML = `
        <div class="excel-settings-panel-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div class="text-muted small">${panelDescription(type)}</div>
                <div class="text-muted small">선택 ${selectedCount}개 / 전체 ${columns.length}개</div>
            </div>
            <div class="${gridClassName}" role="table" aria-label="${panelTitle(type)}">
                <div class="excel-settings-grid-header excel-settings-grid-header-download" role="row">
                    <div class="excel-settings-grid-head excel-settings-grid-cell-usage">${renderUsageHeader(type, columns)}</div>
                    <div class="excel-settings-grid-head excel-settings-grid-cell-handle">${renderDragHeader()}</div>
                    <div class="excel-settings-grid-head excel-settings-grid-cell-order">순번</div>
                    <div class="excel-settings-grid-head excel-settings-grid-cell-label">컬럼명</div>
                    ${requirementHeader}
                </div>
                <div class="excel-settings-grid-body">${renderRows(type, columns)}</div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        data-excel-settings-action="reset"
                        data-type="${type}">기본값 복원</button>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        data-excel-settings-action="save"
                        data-type="${type}">저장</button>
            </div>
        </div>
    `;

    panel.querySelectorAll('[data-indeterminate="true"]').forEach((checkbox) => {
        checkbox.indeterminate = true;
    });
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

export function resetExcelSettingsUi(form) {
    const shell = form?.querySelector('[data-excel-settings-shell]');
    const trigger = form?.querySelector('[data-excel-settings-root-toggle]');
    if (shell) {
        shell.classList.add('d-none');
    }
    if (trigger) {
        trigger.setAttribute('aria-expanded', 'false');
    }

    toggleExcelSettingsPanel(form, '');
}

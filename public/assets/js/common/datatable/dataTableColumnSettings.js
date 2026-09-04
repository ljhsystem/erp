import { createHtmlGrid } from '../html-grid/index.js';
import {
    DATA_TABLE_COLUMN_WIDTH_MAX,
    DATA_TABLE_COLUMN_WIDTH_MIN,
    DATA_TABLE_PAGE_LENGTH_OPTIONS,
    normalizeDataTableColumnWidth,
} from './dataTableViewPolicy.js';
import { cycleDataTableSortSettings } from './dataTableViewSettings.js';

const DATA_TABLE_COLUMN_WIDTH_STEP = 1;

const MODAL_ID = 'dtColumnSettingsModal';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function normalizeRequirementPolicy(value = '') {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === 'required') return 'required';
    if (normalized === 'optional') return 'optional';
    return 'none';
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

function requirementPolicySelectClass(policy = '') {
    const normalized = normalizeRequirementPolicy(policy);
    if (normalized === 'required') return 'is-required';
    if (normalized === 'optional') return 'is-optional';
    return 'is-none';
}

function ensureModal() {
    let modal = document.getElementById(MODAL_ID);
    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = MODAL_ID;
    modal.tabIndex = -1;
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div class="dt-column-settings-title-row">
                            <h5 class="modal-title">Table Settings</h5>
                            <div class="small dt-column-settings-state-source" data-dt-settings-state-source></div>
                        </div>
                        <div class="small text-muted" data-dt-settings-subtitle>컬럼 표시, 순서, 사용컬럼명, 필수구분을 설정합니다.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <div class="dt-column-settings-shell">
                        <div class="dt-column-settings-panel-body">
                            <div class="dt-column-settings-scroll">
                                <div class="dt-column-settings-sticky-region">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 dt-column-settings-toolbar">
                                        <div class="d-flex flex-wrap align-items-center justify-content-start gap-2 dt-column-settings-toolbar-actions">
                                            <div class="text-muted small" data-dt-settings-summary></div>
                                        </div>
                                        <div class="dt-column-settings-toolbar-controls">
                                            <div class="dt-view-settings" data-dt-view-settings aria-label="보기 설정"></div>
                                            <div class="btn-group btn-group-sm" role="group" aria-label="선택 컬럼 순서 변경">
                                                <button type="button" class="btn btn-outline-secondary" data-dt-settings-move-up disabled>
                                                    <i class="bi bi-arrow-up" aria-hidden="true"></i> 위로
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" data-dt-settings-move-down disabled>
                                                    <i class="bi bi-arrow-down" aria-hidden="true"></i> 아래로
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="html-grid-host html-grid-variant-settings dt-column-settings-grid" data-dt-settings-grid-host aria-label="테이블 컬럼 설정"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dt-view-settings-reset>보기 기본값 복원</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dt-settings-restore>컬럼 기본값 복원</button>
                    <button type="button" class="btn btn-primary btn-sm" data-dt-settings-save>저장</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    return modal;
}

function normalizeEntries(entries = []) {
    return entries.map((entry, index) => ({
        key: String(entry.key || '').trim(),
        title: String(entry.title || entry.key || `컬럼 ${index + 1}`).trim(),
        sourceTitle: String(entry.sourceTitle || entry.key || '').trim(),
        visible: entry.visible !== false,
        displayName: String(entry.displayName || entry.title || entry.key || `컬럼 ${index + 1}`).trim(),
        displayNameEditable: entry.displayNameEditable !== false,
        requirementPolicy: normalizeRequirementPolicy(entry.requirementPolicy),
        requirementPolicyEditable: entry.requirementPolicyEditable !== false,
        columnType: String(entry.columnType || 'virtual').trim().toLowerCase(),
        virtualType: String(entry.virtualType || entry.settingsVirtualType || '').trim().toLowerCase(),
        sourceTable: String(entry.sourceTable || entry.table || '').trim(),
        tableComment: String(entry.tableComment || '').trim(),
        sourceOrdinalPosition: Number(entry.sourceOrdinalPosition || entry.ordinalPosition || 0) || 0,
        ordinalPosition: Number(entry.ordinalPosition || 0) || 0,
        sourceRole: String(entry.sourceRole || '').trim(),
        dataType: String(entry.dataType || '').trim(),
        isNullable: String(entry.isNullable || 'YES').trim().toUpperCase() === 'NO' ? 'NO' : 'YES',
        required: false,
        hideable: entry.hideable !== false,
        movable: entry.movable !== false,
        width: Number.isFinite(Number(entry.width)) && Number(entry.width) > 0 ? Math.round(Number(entry.width)) : null,
        defaultWidth: Number.isFinite(Number(entry.defaultWidth)) && Number(entry.defaultWidth) > 0
            ? Math.round(Number(entry.defaultWidth))
            : null,
        widthResizable: entry.widthResizable !== false,
        sortDirection: ['asc', 'desc'].includes(String(entry.sortDirection || '').toLowerCase())
            ? String(entry.sortDirection).toLowerCase()
            : '',
        sortOrderable: entry.sortOrderable !== false,
    })).filter((entry) => entry.key !== '');
}

function normalizeViewSettings(value = {}, options = {}) {
    const sortSettings = Array.isArray(value?.sortSettings)
        ? value.sortSettings
            .map((item) => ({
                key: String(item?.key || '').trim(),
                dir: String(item?.dir || '').trim().toLowerCase() === 'desc' ? 'desc' : 'asc',
            }))
            .filter((item) => item.key !== '')
        : [];
    const allowedPageLengths = Array.isArray(options?.pageLengthOptions) && options.pageLengthOptions.length > 0
        ? options.pageLengthOptions
        : DATA_TABLE_PAGE_LENGTH_OPTIONS;
    const pageLength = Number(value?.pageLength);
    return {
        columnWidths: value?.columnWidths && typeof value.columnWidths === 'object'
            ? { ...value.columnWidths }
            : {},
        sortSettings,
        pageLength: allowedPageLengths.includes(pageLength) ? pageLength : allowedPageLengths[0],
        searchFormExpanded: typeof value?.searchFormExpanded === 'boolean'
            ? value.searchFormExpanded
            : Boolean(options?.searchFormExpandedDefault),
    };
}

function renderViewSettings(modal, state) {
    const panel = modal.querySelector('[data-dt-view-settings]');
    const resetButton = modal.querySelector('[data-dt-view-settings-reset]');
    if (!panel) return;
    if (!state.viewSettingsEnabled) {
        panel.hidden = true;
        if (resetButton) resetButton.hidden = true;
        return;
    }

    const pageLengthOptions = state.pageLengthOptions;
    panel.innerHTML = `
        <div class="dt-view-settings-fields">
            <label class="dt-view-settings-field dt-column-settings-search-field">
                <span class="visually-hidden">컬럼 검색</span>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" class="form-control form-control-sm"
                       data-dt-settings-column-search
                       value="${escapeHtml(state.columnSearchQuery || '')}"
                       placeholder="컬럼 검색"
                       autocomplete="off"
                       aria-label="원본컬럼명 또는 사용컬럼명 검색">
            </label>
            ${state.searchFormAvailable ? `
                <label class="dt-view-settings-field dt-view-settings-switch-field">
                    <span data-dt-view-search-status>검색영역-${state.viewSettings.searchFormExpanded ? '펼침' : '닫힘'}</span>
                    <span class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               data-dt-view-search-expanded ${state.viewSettings.searchFormExpanded ? 'checked' : ''}
                               aria-label="검색영역 ${state.viewSettings.searchFormExpanded ? '펼침' : '닫힘'}">
                    </span>
                </label>
            ` : ''}
            <label class="dt-view-settings-field">
                <span>페이지당</span>
                <select class="form-select form-select-sm" data-dt-view-page-length>
                    ${pageLengthOptions.map((value) => `<option value="${value}" ${value === state.viewSettings.pageLength ? 'selected' : ''}>${value}</option>`).join('')}
                </select>
                <span>개씩 보기</span>
            </label>
        </div>
    `;
    panel.hidden = false;
    if (resetButton) resetButton.hidden = false;
}

function normalizeColumnSearchText(value = '') {
    return String(value || '')
        .normalize('NFKC')
        .toLocaleLowerCase('ko-KR')
        .trim();
}

function applyColumnSearch(modal, state) {
    const query = normalizeColumnSearchText(state.columnSearchQuery).replace(/^%+|%+$/g, '');
    const queryTokens = query.split(/\s+/).filter(Boolean);
    const entriesByKey = new Map(state.entries.map((entry) => [entry.key, entry]));
    let visibleCount = 0;

    modal.querySelectorAll('[data-dt-settings-item]').forEach((row) => {
        const entry = entriesByKey.get(String(row.dataset.key || '').trim());
        const searchText = [
            entry?.key,
            entry?.sourceTitle,
            entry?.sourceTable,
            entry?.tableComment,
            entry?.displayName,
            entry?.title,
        ].map(normalizeColumnSearchText).join(' ');
        const compactSearchText = searchText.replace(/[\s._-]+/g, '');
        const matched = queryTokens.length === 0 || queryTokens.every((token) => {
            const normalizedToken = token.replace(/^%+|%+$/g, '');
            if (normalizedToken === '') return true;
            return searchText.includes(normalizedToken)
                || compactSearchText.includes(normalizedToken.replace(/[\s._-]+/g, ''));
        });
        row.hidden = !matched;
        if (matched) visibleCount += 1;
    });

    const host = modal.querySelector('[data-dt-settings-grid-host]');
    if (!host) return;
    let empty = host.querySelector('[data-dt-settings-search-empty]');
    if (!empty) {
        empty = document.createElement('div');
        empty.className = 'dt-column-settings-search-empty';
        empty.dataset.dtSettingsSearchEmpty = '';
        empty.textContent = '검색 결과가 없습니다.';
        host.appendChild(empty);
    }
    empty.hidden = query === '' || visibleCount > 0;
}

function readViewSettingsFromDom(modal, state) {
    return {
        ...state.viewSettings,
        pageLength: Number(modal.querySelector('[data-dt-view-page-length]')?.value || state.viewSettings.pageLength),
        searchFormExpanded: state.searchFormAvailable
            ? modal.querySelector('[data-dt-view-search-expanded]')?.checked === true
            : state.viewSettings.searchFormExpanded,
    };
}

function buildSourceTableInfo(entries = []) {
    const tableMap = new Map();
    entries.forEach((entry) => {
        const tableName = String(entry.sourceTable || '').trim();
        if (tableName === '' || entry.columnType !== 'physical') return;
        if (!tableMap.has(tableName)) {
            tableMap.set(tableName, {
                number: tableMap.size + 1,
                name: tableName,
                comment: String(entry.tableComment || '').trim(),
                count: 0,
            });
        }
        const tableInfo = tableMap.get(tableName);
        tableInfo.count += 1;
        if (tableInfo.comment === '') tableInfo.comment = String(entry.tableComment || '').trim();
    });
    return tableMap;
}

function buildSourceTableSummary(entries = []) {
    return Array.from(buildSourceTableInfo(entries).values())
        .map((tableInfo) => {
            const comment = tableInfo.comment !== '' ? tableInfo.comment : '테이블 코멘트 없음';
            return `${tableInfo.number}. ${tableInfo.name} / ${comment} (${tableInfo.count}개)`;
        })
        .join('\n');
}

function renderSelectAll(entries = [], selectedKeys = new Set()) {
    const checkedCount = entries.filter((entry) => selectedKeys.has(entry.key)).length;
    const allChecked = entries.length > 0 && checkedCount === entries.length;
    const partiallyChecked = checkedCount > 0 && checkedCount < entries.length;

    return `
        <label class="dt-column-settings-head-toggle" aria-label="순서변경 대상 전체 선택">
            <input class="form-check-input html-grid-row-selection mt-0"
                   type="checkbox"
                   data-dt-settings-select-all
                   ${allChecked ? 'checked' : ''}
                   ${partiallyChecked ? 'data-indeterminate="true"' : ''}
                   ${entries.length === 0 ? 'disabled' : ''}>
        </label>
    `;
}

function renderVisibilityAll(entries = []) {
    const toggleableEntries = entries.filter((entry) => entry.hideable !== false);
    const visibleCount = toggleableEntries.filter((entry) => entry.visible !== false).length;
    const allVisible = toggleableEntries.length > 0 && visibleCount === toggleableEntries.length;
    const partiallyVisible = visibleCount > 0 && visibleCount < toggleableEntries.length;

    return `
        <label class="dt-column-settings-head-toggle form-check form-switch m-0" aria-label="전체 컬럼 보기 설정">
            <input class="form-check-input mt-0${partiallyVisible ? ' is-mixed' : ''}"
                   type="checkbox"
                   role="switch"
                   data-dt-settings-visible-all
                   ${allVisible ? 'checked' : ''}
                   ${partiallyVisible ? 'data-indeterminate="true"' : ''}
                   ${toggleableEntries.length === 0 ? 'disabled' : ''}>
        </label>
    `;
}

function syncVisibilityAllToggle(modal, entries = []) {
    const toggle = modal.querySelector('[data-dt-settings-visible-all]');
    if (!toggle) return;
    const toggleableEntries = entries.filter((entry) => entry.hideable !== false);
    const visibleCount = toggleableEntries.filter((entry) => entry.visible !== false).length;
    toggle.checked = toggleableEntries.length > 0 && visibleCount === toggleableEntries.length;
    toggle.indeterminate = visibleCount > 0 && visibleCount < toggleableEntries.length;
    toggle.classList.toggle('is-mixed', toggle.indeterminate);
    toggle.disabled = toggleableEntries.length === 0;
    toggle.setAttribute('aria-label', toggle.checked ? '전체 컴' : (toggle.indeterminate ? '일부 컴' : '전체 꺼'));
}

function detectDragMode() {
    return 'pointer';
}

function createStaticEditor(element, value) {
    return {
        element,
        create: () => element,
        mount(host) {
            if (host && !element.parentNode) host.appendChild(element);
            return element;
        },
        focus: () => element.focus?.(),
        blur: () => element.blur?.(),
        getValue: () => value,
        setValue: () => value,
        validate: () => ({ valid: true, message: '' }),
        isDirty: () => false,
        destroy: () => element.remove?.(),
    };
}

function createDragHandleEditor(context = {}) {
    const button = context.document.createElement('button');
    button.type = 'button';
    button.className = 'text-muted reorder-handle dt-settings-drag-handle';
    button.dataset.dtSettingsDragHandle = '';
    button.disabled = context.row.values?.movable === false;
    button.tabIndex = -1;
    button.title = '드래그하여 순서를 변경합니다';
    button.setAttribute('aria-label', button.title);
    button.innerHTML = '<i class="bi bi-list" aria-hidden="true"></i>';
    return createStaticEditor(button, context.value);
}

function createOrderBadgeEditor(context = {}) {
    const badge = context.document.createElement('span');
    const systemColumn = context.row.values?.columnType !== 'physical';
    const virtualType = String(context.row.values?.virtualType || (systemColumn ? 'system' : '')).trim();
    badge.className = `dt-column-settings-order-badge${systemColumn ? ` is-${virtualType || 'other'}` : ''}`;
    if (!systemColumn) {
        badge.textContent = String(context.value || '');
    } else if (virtualType === 'calculated') {
        badge.innerHTML = '<i class="bi bi-calculator-fill" aria-hidden="true"></i>';
        badge.setAttribute('aria-label', '계산 가상컬럼');
        badge.title = '계산 가상컬럼';
    } else if (virtualType === 'system') {
        badge.innerHTML = '<i class="bi bi-gear-fill" aria-hidden="true"></i>';
        badge.setAttribute('aria-label', '시스템 가상컬럼');
        badge.title = '시스템 가상컬럼';
    } else {
        badge.innerHTML = '<i class="bi bi-puzzle-fill" aria-hidden="true"></i>';
        badge.setAttribute('aria-label', '기타 가상컬럼');
        badge.title = '기타 가상컬럼';
    }
    return createStaticEditor(badge, context.value);
}

function createSourceColumnEditor(context = {}) {
    const wrap = context.document.createElement('div');
    wrap.className = 'dt-column-settings-source';
    const label = context.document.createElement('span');
    label.className = 'dt-column-settings-source-label';
    const sourceText = String(context.value || '');
    const sourceMatch = sourceText.match(/^(\d+)-(\d+)\.(.+)$/u);
    if (sourceMatch) {
        const firstColumnCharacter = Array.from(sourceMatch[3])[0] || '';
        const prefix = context.document.createElement('span');
        prefix.className = 'dt-column-settings-source-prefix';
        prefix.textContent = `${sourceMatch[1]}-${sourceMatch[2]}.${firstColumnCharacter}`;
        const columnName = context.document.createElement('span');
        columnName.className = 'dt-column-settings-source-name';
        columnName.textContent = Array.from(sourceMatch[3]).slice(1).join('');
        label.append(prefix, columnName);
    } else {
        label.textContent = sourceText;
    }
    label.title = sourceText;
    wrap.appendChild(label);
    wrap.insertAdjacentHTML('beforeend', requirementStarHtml(context.row.values?.requirementPolicy));
    return createStaticEditor(wrap, context.value);
}

function createDisplayNameEditor(context = {}) {
    const input = context.document.createElement('input');
    input.type = 'text';
    input.className = 'html-grid-editor html-grid-editor-text';
    input.dataset.dtSettingsDisplayName = '';
    input.dataset.key = context.row.rowId;
    input.value = String(context.value || '');
    input.disabled = context.row.values?.displayNameEditable === false;
    if (input.disabled) {
        input.classList.add('dt-display-name-locked');
        input.title = '전체선택 기능 컬럼의 사용컬럼명은 변경할 수 없습니다.';
    }
    return {
        ...createStaticEditor(input, context.value),
        getValue: () => input.value,
        setValue(value) {
            input.value = String(value || '');
            return input.value;
        },
        isDirty: () => input.value !== String(context.value || ''),
    };
}

function createRequirementPolicyEditor(context = {}) {
    const select = context.document.createElement('select');
    select.className = `html-grid-editor html-grid-editor-select dt-requirement-policy-select ${requirementPolicySelectClass(context.value)}`;
    select.dataset.dtSettingsRequirementPolicy = '';
    select.dataset.key = context.row.rowId;
    select.innerHTML = `
        <option value="none">선택안함</option>
        <option value="optional">선택</option>
        <option value="required">필수</option>
    `;
    select.value = normalizeRequirementPolicy(context.value);
    select.disabled = context.row.values?.requirementPolicyEditable === false;
    if (select.disabled) {
        select.title = '시스템 가상컬럼은 필수구분을 설정할 수 없습니다.';
    }
    return {
        ...createStaticEditor(select, context.value),
        getValue: () => select.value,
        setValue(value) {
            select.value = normalizeRequirementPolicy(value);
            return select.value;
        },
        isDirty: () => select.value !== normalizeRequirementPolicy(context.value),
    };
}

function createVisibilityEditor(context = {}) {
    const wrap = context.document.createElement('div');
    wrap.className = 'form-check form-switch m-0 d-inline-flex';
    const input = context.document.createElement('input');
    input.type = 'checkbox';
    input.className = 'form-check-input';
    input.role = 'switch';
    input.dataset.dtSettingsVisible = '';
    input.dataset.key = context.row.rowId;
    input.checked = context.value !== false;
    input.disabled = context.row.values?.hideable === false;
    input.setAttribute('aria-label', `${context.row.values?.sourceTitle || context.row.rowId} 보기`);
    wrap.appendChild(input);
    return {
        ...createStaticEditor(wrap, context.value),
        getValue: () => input.checked,
        setValue(value) {
            input.checked = value !== false;
            return input.checked;
        },
        isDirty: () => input.checked !== (context.value !== false),
    };
}

function createWidthEditor(context = {}) {
    const wrap = context.document.createElement('div');
    wrap.className = 'dt-column-width-control';
    const decrease = context.document.createElement('button');
    decrease.type = 'button';
    decrease.className = 'dt-column-width-step';
    decrease.dataset.dtSettingsWidthDecrease = '';
    decrease.dataset.key = context.row.rowId;
    decrease.setAttribute('aria-label', '컬럼 너비 1px 감소');
    decrease.textContent = '◀';
    const input = context.document.createElement('input');
    input.type = 'number';
    input.className = 'html-grid-editor html-grid-editor-number dt-column-width-input';
    input.dataset.dtSettingsWidth = '';
    input.dataset.key = context.row.rowId;
    input.min = String(DATA_TABLE_COLUMN_WIDTH_MIN);
    input.max = String(DATA_TABLE_COLUMN_WIDTH_MAX);
    input.step = String(DATA_TABLE_COLUMN_WIDTH_STEP);
    input.value = Number.isFinite(Number(context.value)) && Number(context.value) > 0 ? String(Math.round(Number(context.value))) : '';
    input.disabled = context.row.values?.widthResizable === false || context.row.values?.visible === false;
    const increase = context.document.createElement('button');
    increase.type = 'button';
    increase.className = 'dt-column-width-step';
    increase.dataset.dtSettingsWidthIncrease = '';
    increase.dataset.key = context.row.rowId;
    increase.setAttribute('aria-label', '컬럼 너비 1px 증가');
    increase.textContent = '▶';
    decrease.disabled = input.disabled || Number(input.value) <= DATA_TABLE_COLUMN_WIDTH_MIN;
    increase.disabled = input.disabled || Number(input.value) >= DATA_TABLE_COLUMN_WIDTH_MAX;
    if (context.row.values?.visible === false) {
        wrap.title = '보기 설정을 켜면 기본 너비가 적용됩니다.';
    } else if (input.disabled) {
        wrap.title = '이 컬럼은 너비를 변경할 수 없습니다.';
    }
    wrap.append(decrease, input, increase);
    return {
        ...createStaticEditor(wrap, context.value),
        getValue: () => input.value,
        setValue(value) {
            input.value = Number.isFinite(Number(value)) && Number(value) > 0 ? String(Math.round(Number(value))) : '';
            return input.value;
        },
        isDirty: () => input.value !== String(context.value || ''),
    };
}

function createSortEditor(context = {}) {
    const button = context.document.createElement('button');
    const direction = ['asc', 'desc'].includes(context.value) ? context.value : '';
    button.type = 'button';
    button.className = `dt-sort-indicator${direction ? ` is-${direction}` : ''}`;
    button.dataset.dtSettingsSort = '';
    button.dataset.key = context.row.rowId;
    button.dataset.direction = direction;
    button.disabled = context.row.values?.sortOrderable === false || context.row.values?.visible === false;
    button.setAttribute('aria-label', button.disabled ? '정렬할 수 없는 컬럼' : '정렬 상태 변경');
    button.title = button.disabled ? '이 컬럼은 정렬할 수 없습니다.' : '클릭하여 정렬 상태를 변경합니다.';
    return createStaticEditor(button, direction);
}

function updateSettingsSummary(modal, entries = []) {
    const configurableEntries = entries.filter((entry) => !String(entry.key || '').startsWith('__'));
    const summary = modal.querySelector('[data-dt-settings-summary]');
    if (summary) {
        summary.textContent = `테이블 활성상태 ${configurableEntries.filter((entry) => entry.visible).length}개 / 전체컬럼 ${configurableEntries.length}개`;
    }
}

function updateStateSourceBadge(node, source = 'saved') {
    if (!node) return;
    const isDefault = source === 'default';
    node.textContent = isDefault ? '현재값: DB 기본값' : '현재값: 사용자 저장 설정';
    node.classList.toggle('is-default', isDefault);
    node.classList.toggle('is-saved', !isDefault);
}

function renderList(modal, entries = [], _dragMode = 'native', selectedKeys = new Set()) {
    const host = modal.querySelector('[data-dt-settings-grid-host]');
    if (!host) return;

    modal.__dtSettingsGrid?.destroy?.();

    updateSettingsSummary(modal, entries);

    const tableInfoMap = buildSourceTableInfo(entries);
    let physicalDisplayOrder = 0;
    const rows = entries.map((entry) => {
        const tableName = String(entry.sourceTable || '').trim();
        const tableInfo = tableInfoMap.get(tableName);
        const sourceTitle = String(entry.sourceTitle || entry.key).trim();
        const qualifiedPrefix = `${tableName}.`;
        const sourceColumnName = sourceTitle.startsWith(qualifiedPrefix)
            ? sourceTitle.slice(qualifiedPrefix.length)
            : sourceTitle;
        const sourceOrder = entry.columnType === 'physical'
            ? ++physicalDisplayOrder
            : '';
        return {
            rowId: entry.key,
            rowState: 'clean',
            values: {
                selection: '',
                handle: '',
                sourceOrder,
                sourceColumn: entry.columnType === 'physical' && tableInfo
                    ? `${tableInfo.number}-${entry.sourceOrdinalPosition}.${sourceColumnName}`
                    : sourceColumnName,
                displayName: entry.displayName || entry.title || entry.key,
                displayNameEditable: entry.displayNameEditable,
                requirementPolicy: entry.requirementPolicy,
                requirementPolicyEditable: entry.requirementPolicyEditable,
                visible: entry.visible,
                width: entry.width,
                widthResizable: entry.widthResizable,
                sortDirection: entry.sortDirection,
                sortOrderable: entry.sortOrderable,
                movable: entry.movable,
                hideable: entry.hideable,
                columnType: entry.columnType,
                virtualType: entry.virtualType,
                sourceTitle: entry.sourceTitle,
            },
        };
    });

    const grid = createHtmlGrid({
        host,
        gridId: 'data-table-column-settings',
        rows,
        columns: [
            { key: 'selection', label: '', type: 'selection', width: 40, editable: false },
            { key: 'handle', label: '이동', width: 44, editor: 'dt-drag-handle' },
            { key: 'sourceOrder', label: '순번', width: 56, editor: 'dt-order-badge' },
            { key: 'sourceColumn', label: '원본컬럼명', width: 240, editor: 'dt-source-column' },
            { key: 'displayName', label: '사용컬럼명', width: 228, editor: 'dt-display-name' },
            { key: 'requirementPolicy', label: '필수구분', width: 124, editor: 'dt-requirement-policy' },
            { key: 'visible', label: '', width: 64, editor: 'dt-visibility' },
            { key: 'width', label: '너비(px)', width: 136, editor: 'dt-width' },
            { key: 'sortDirection', label: '정렬', width: 52, editor: 'dt-sort' },
        ],
        editors: {
            'dt-drag-handle': createDragHandleEditor,
            'dt-order-badge': createOrderBadgeEditor,
            'dt-source-column': createSourceColumnEditor,
            'dt-display-name': createDisplayNameEditor,
            'dt-requirement-policy': createRequirementPolicyEditor,
            'dt-visibility': createVisibilityEditor,
            'dt-width': createWidthEditor,
            'dt-sort': createSortEditor,
        },
        capabilities: {
            addRow: false,
            deleteRow: false,
            insertRow: false,
            reorder: true,
            resize: false,
            keyboard: true,
            footer: false,
            validation: false,
            selection: true,
            multiSelection: true,
            columnHide: false,
            columnMove: false,
            columnResize: false,
            stickyHeader: true,
        },
        selection: { selectedRowIds: Array.from(selectedKeys) },
        editorFocusSelectsRow: false,
        commitEditorsBeforeRead: true,
    });
    modal.__dtSettingsGrid = grid;
    grid.render({ emptyMessage: '설정 가능한 컬럼이 없습니다.' });

    const list = host.querySelector('.html-grid-body');
    if (list) list.dataset.dtSettingsList = '';
    list?.querySelectorAll('.html-grid-body-row').forEach((row) => {
        const entry = entries.find((item) => item.key === row.dataset.rowId);
        row.dataset.dtSettingsItem = '';
        row.dataset.key = row.dataset.rowId || '';
        row.dataset.movable = entry?.movable === false ? 'false' : 'true';
        row.tabIndex = 0;
    });

    const selectionHeader = host.querySelector('.html-grid-header-cell[data-column-key="selection"] .html-grid-header-cell-content');
    if (selectionHeader) selectionHeader.innerHTML = renderSelectAll(entries, selectedKeys);
    const handleHeader = host.querySelector('.html-grid-header-cell[data-column-key="handle"] .html-grid-header-cell-content');
    if (handleHeader) {
        handleHeader.innerHTML = `
            <span class="dt-column-settings-handle-head" title="드래그핸들" aria-label="드래그핸들">
                <i class="bi bi-arrows-move" aria-hidden="true"></i>
            </span>
        `;
    }
    const visibilityHeader = host.querySelector('.html-grid-header-cell[data-column-key="visible"] .html-grid-header-cell-content');
    if (visibilityHeader) visibilityHeader.innerHTML = renderVisibilityAll(entries);
    modal.querySelectorAll('[data-dt-settings-select-all][data-indeterminate="true"]').forEach((checkbox) => {
        checkbox.indeterminate = true;
    });
    modal.querySelectorAll('[data-dt-settings-visible-all][data-indeterminate="true"]').forEach((checkbox) => {
        checkbox.indeterminate = true;
    });

    grid.on('selection:changed', ({ selection }) => {
        selectedKeys.clear();
        (selection?.selectedRowIds || []).forEach((key) => selectedKeys.add(String(key)));
        updateMoveButtonState(modal, entries, selectedKeys);
        const selectAll = modal.querySelector('[data-dt-settings-select-all]');
        if (selectAll) {
            selectAll.checked = entries.length > 0 && selectedKeys.size === entries.length;
            selectAll.indeterminate = selectedKeys.size > 0 && selectedKeys.size < entries.length;
        }
    });

    const toolbar = modal.querySelector('.dt-column-settings-toolbar');
    modal.querySelector('.dt-column-settings-scroll')?.style.setProperty('--dt-settings-toolbar-height', `${toolbar?.offsetHeight || 64}px`);
    updateMoveButtonState(modal, entries, selectedKeys);
}

function canMoveSelectedEntries(entries = [], selectedKeys = new Set(), direction = 0) {
    return entries.some((entry, index) => {
        if (!selectedKeys.has(entry.key) || entry.movable === false) return false;
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= entries.length) return false;
        const target = entries[targetIndex];
        return target?.movable !== false && !selectedKeys.has(target.key);
    });
}

function updateMoveButtonState(modal, entries = [], selectedKeys = new Set()) {
    const upButton = modal.querySelector('[data-dt-settings-move-up]');
    const downButton = modal.querySelector('[data-dt-settings-move-down]');
    if (upButton) upButton.disabled = !canMoveSelectedEntries(entries, selectedKeys, -1);
    if (downButton) downButton.disabled = !canMoveSelectedEntries(entries, selectedKeys, 1);
}

function respectsMoveGuards(before = [], after = []) {
    if (before.length !== after.length) return false;
    return before.every((entry, index) => entry.movable !== false
        || after[index]?.key === entry.key);
}

function reorderEntries(entries = [], fromKey = '', toKey = '', dropPosition = 'before') {
    if (!fromKey || !toKey || fromKey === toKey) {
        return entries;
    }

    const fromIndex = entries.findIndex((entry) => entry.key === fromKey);
    const toIndex = entries.findIndex((entry) => entry.key === toKey);
    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex || entries[fromIndex]?.movable === false) {
        return entries;
    }

    const next = entries.slice();
    const [item] = next.splice(fromIndex, 1);
    const baseIndex = fromIndex < toIndex ? toIndex - 1 : toIndex;
    const insertIndex = dropPosition === 'after' ? baseIndex + 1 : baseIndex;
    next.splice(insertIndex, 0, item);
    return respectsMoveGuards(entries, next) ? next : entries;
}

function moveSelectedEntries(entries = [], selectedKeys = new Set(), direction = 0) {
    if (!canMoveSelectedEntries(entries, selectedKeys, direction)) return entries;
    const next = entries.slice();
    const indexes = direction < 0
        ? entries.map((_entry, index) => index)
        : entries.map((_entry, index) => index).reverse();
    indexes.forEach((index) => {
        const entry = next[index];
        const targetIndex = index + direction;
        if (!selectedKeys.has(entry?.key) || entry?.movable === false) return;
        if (targetIndex < 0 || targetIndex >= next.length) return;
        const target = next[targetIndex];
        if (target?.movable === false || selectedKeys.has(target?.key)) return;
        [next[index], next[targetIndex]] = [target, entry];
    });
    return respectsMoveGuards(entries, next) ? next : entries;
}

function syncEntriesFromDom(list, entries = []) {
    const order = Array.from(list.querySelectorAll('[data-dt-settings-item]'))
        .map((item) => String(item.dataset.key || '').trim())
        .filter(Boolean);

    if (order.length === 0) {
        return entries;
    }

    const entryMap = new Map(entries.map((entry) => [entry.key, entry]));
    return order.map((key) => entryMap.get(key)).filter(Boolean);
}

function syncVisibleValuesFromDom(modal, entries = []) {
    const visibleMap = new Map(
        Array.from(modal.querySelectorAll('[data-dt-settings-visible]'))
            .map((input) => [String(input.dataset.key || '').trim(), Boolean(input.checked)])
            .filter(([key]) => key !== '')
    );

    return entries.map((entry) => ({
        ...entry,
        visible: visibleMap.has(entry.key) ? Boolean(visibleMap.get(entry.key)) : entry.visible !== false,
    }));
}

function syncEditableValuesFromDom(modal, entries = []) {
    const displayNameMap = new Map(
        Array.from(modal.querySelectorAll('[data-dt-settings-display-name]'))
            .map((input) => [String(input.dataset.key || '').trim(), String(input.value || '').trim()])
            .filter(([key]) => key !== '')
    );
    const requirementPolicyMap = new Map(
        Array.from(modal.querySelectorAll('[data-dt-settings-requirement-policy]'))
            .map((select) => [String(select.dataset.key || '').trim(), String(select.value || '').trim().toLowerCase()])
            .filter(([key]) => key !== '')
    );
    const widthMap = new Map(
        Array.from(modal.querySelectorAll('[data-dt-settings-width]'))
            .map((input) => [String(input.dataset.key || '').trim(), String(input.value || '').trim()])
            .filter(([key]) => key !== '')
    );

    return entries.map((entry) => ({
        ...entry,
        visible: Array.from(modal.querySelectorAll('[data-dt-settings-visible]'))
            .find((input) => String(input.dataset.key || '').trim() === entry.key)?.checked ?? (entry.visible !== false),
        displayName: displayNameMap.has(entry.key)
            ? String(displayNameMap.get(entry.key) || '').trim()
            : String(entry.displayName || entry.title || entry.key).trim(),
        requirementPolicy: normalizeRequirementPolicy(requirementPolicyMap.get(entry.key)),
        width: widthMap.has(entry.key)
            ? normalizeDataTableColumnWidth(widthMap.get(entry.key))
            : entry.width,
    }));
}

function syncStateEntriesFromDom(modal, entries = []) {
    const list = modal?.querySelector?.('[data-dt-settings-list]');
    if (!list) {
        return entries;
    }

    return syncEditableValuesFromDom(
        modal,
        syncVisibleValuesFromDom(
            modal,
            syncEntriesFromDom(list, entries)
        )
    );
}

function applyEntryOrderToGrid(modal, entries = []) {
    const grid = modal?.__dtSettingsGrid;
    if (!grid?.getState || !grid?.setState) return;
    const gridState = grid.getState();
    const rowMap = new Map((gridState.rows || []).map((row) => [row.rowId, row]));
    const rows = entries.map((entry) => rowMap.get(entry.key)).filter(Boolean);
    if (rows.length !== entries.length) return;
    grid.setState({ ...gridState, rows });
}

function clearDropIndicators(list) {
    if (!list) {
        return;
    }

    list.querySelectorAll('[data-dt-settings-item]').forEach((item) => {
        item.classList.remove('is-drop-before', 'is-drop-after', 'is-dragging');
        delete item.dataset.dropPosition;
    });
}

function bindJquerySortable(modal, state, rerender) {
    const list = modal.querySelector('[data-dt-settings-list]');
    const $ = window.jQuery;
    if (!list || !$?.fn?.sortable) {
        return false;
    }

    const $list = $(list);
    if ($list.data('ui-sortable')) {
        $list.sortable('destroy');
    }

    $list.sortable({
        axis: 'y',
        items: '[data-dt-settings-item][data-movable="true"]',
        handle: '.dt-settings-drag-handle',
        containment: 'parent',
        tolerance: 'pointer',
        start(_event, ui) {
            document.body.classList.add('dt-settings-dragging');
            ui.item?.addClass('is-dragging');
        },
        stop(_event, ui) {
            document.body.classList.remove('dt-settings-dragging');
            ui.item?.removeClass('is-dragging');
        },
        update() {
            const proposed = syncEditableValuesFromDom(modal, syncVisibleValuesFromDom(modal, syncEntriesFromDom(list, state.entries)));
            if (respectsMoveGuards(state.entries, proposed)) {
                applyEntryOrderToGrid(modal, proposed);
                state.entries = proposed;
            }
            rerender();
        },
    });

    return true;
}

function bindPointerDrag(modal, state, rerender) {
    const list = modal.querySelector('[data-dt-settings-list]');
    if (!list) {
        return false;
    }

    if (list.dataset.dtPointerDragBound === 'true') {
        return true;
    }

    list.dataset.dtPointerDragBound = 'true';

    const dragState = {
        active: false,
        pointerId: null,
        row: null,
        handle: null,
    };

    const finishDrag = () => {
        if (!dragState.active) {
            return;
        }

        dragState.handle?.releasePointerCapture?.(dragState.pointerId);
        dragState.row?.classList.remove('is-dragging');
        clearDropIndicators(list);
        document.body.classList.remove('dt-settings-dragging');
        const proposed = syncEditableValuesFromDom(modal, syncVisibleValuesFromDom(modal, syncEntriesFromDom(list, state.entries)));
        if (respectsMoveGuards(state.entries, proposed)) {
            applyEntryOrderToGrid(modal, proposed);
            state.entries = proposed;
        }
        dragState.active = false;
        dragState.pointerId = null;
        dragState.row = null;
        dragState.handle = null;
        rerender();
    };

    list.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('[data-dt-settings-drag-handle]');
        const row = event.target.closest('[data-dt-settings-item]');
        if (!handle || !row || row.dataset.movable !== 'true' || dragState.active) {
            return;
        }

        event.preventDefault();
        dragState.active = true;
        dragState.pointerId = event.pointerId;
        dragState.row = row;
        dragState.handle = handle;
        row.classList.add('is-dragging');
        document.body.classList.add('dt-settings-dragging');
        handle.setPointerCapture?.(event.pointerId);
    });

    list.addEventListener('pointermove', (event) => {
        if (!dragState.active || !dragState.row) {
            return;
        }

        const target = document.elementFromPoint(event.clientX, event.clientY)?.closest?.('[data-dt-settings-item]');
        if (!target || target === dragState.row || target.parentElement !== list || target.dataset.movable !== 'true') {
            return;
        }

        const rect = target.getBoundingClientRect();
        const dropPosition = event.clientY < rect.top + (rect.height / 2) ? 'before' : 'after';
        clearDropIndicators(list);
        target.dataset.dropPosition = dropPosition;
        target.classList.add(dropPosition === 'before' ? 'is-drop-before' : 'is-drop-after');

        if (dropPosition === 'before') {
            list.insertBefore(dragState.row, target);
            return;
        }

        list.insertBefore(dragState.row, target.nextElementSibling);
    });

    list.addEventListener('pointerup', finishDrag);
    list.addEventListener('pointercancel', finishDrag);

    return true;
}

export function openDataTableColumnSettings(options = {}) {
    const modal = ensureModal();
    const titleNode = modal.querySelector('.modal-title');
    const subtitleNode = modal.querySelector('[data-dt-settings-subtitle]');
    const stateSourceNode = modal.querySelector('[data-dt-settings-state-source]');
    const saveButton = modal.querySelector('[data-dt-settings-save]');
    const restoreButton = modal.querySelector('[data-dt-settings-restore]');
    const resetViewButton = modal.querySelector('[data-dt-view-settings-reset]');
    const instance = window.bootstrap?.Modal?.getOrCreateInstance(modal, { focus: false });

    const state = {
        title: 'Table Settings',
        subtitle: buildSourceTableSummary(normalizeEntries(options.entries || []))
            || String(options.subtitle || '표시 컬럼과 순서를 설정합니다.'),
        defaults: normalizeEntries(options.defaultEntries || []),
        entries: normalizeEntries(options.entries || []),
        onSave: typeof options.onSave === 'function' ? options.onSave : null,
        restoreDefaults: typeof options.restoreDefaults === 'function' ? options.restoreDefaults : null,
        stateSource: String(options.stateSource || 'saved').trim(),
        selectedKeys: new Set(),
        columnSearchQuery: '',
        pageLengthOptions: Array.isArray(options.pageLengthOptions) && options.pageLengthOptions.length > 0
            ? options.pageLengthOptions
            : DATA_TABLE_PAGE_LENGTH_OPTIONS,
        searchFormAvailable: options.searchFormAvailable === true,
        viewSettingsEnabled: options.viewSettingsEnabled === true,
        viewSettings: normalizeViewSettings(options.viewSettings, {
            pageLengthOptions: options.pageLengthOptions,
            searchFormExpandedDefault: options.searchFormExpandedDefault,
        }),
        defaultViewSettings: normalizeViewSettings(options.defaultViewSettings, {
            pageLengthOptions: options.pageLengthOptions,
            searchFormExpandedDefault: options.searchFormExpandedDefault,
        }),
        restoreViewDefaults: typeof options.restoreViewDefaults === 'function' ? options.restoreViewDefaults : null,
    };

    titleNode.textContent = state.title;
    subtitleNode.textContent = state.subtitle;
    updateStateSourceBadge(stateSourceNode, state.stateSource);

    const rerender = () => {
        const dragMode = detectDragMode();
        renderList(modal, state.entries, dragMode, state.selectedKeys);
        renderViewSettings(modal, state);
        applyColumnSearch(modal, state);
        if (dragMode === 'jquery') {
            bindJquerySortable(modal, state, rerender);
        } else {
            bindPointerDrag(modal, state, rerender);
        }
    };

    rerender();

    const cleanup = [];

    const bind = (node, type, handler) => {
        if (!node) {
            return;
        }
        node.addEventListener(type, handler);
        cleanup.push(() => node.removeEventListener(type, handler));
    };

    const syncStickyOffset = () => {
        const toolbar = modal.querySelector('.dt-column-settings-toolbar');
        const scroll = modal.querySelector('.dt-column-settings-scroll');
        if (!toolbar || !scroll) return;
        const toolbarHeight = Math.ceil(toolbar.getBoundingClientRect().height || toolbar.offsetHeight || 64);
        scroll.style.setProperty('--dt-settings-toolbar-height', `${toolbarHeight}px`);
    };
    bind(modal, 'shown.bs.modal', syncStickyOffset);
    const toolbarResizeObserver = typeof ResizeObserver === 'function'
        ? new ResizeObserver(syncStickyOffset)
        : null;
    const observedToolbar = modal.querySelector('.dt-column-settings-toolbar');
    if (toolbarResizeObserver && observedToolbar) {
        toolbarResizeObserver.observe(observedToolbar);
        cleanup.push(() => toolbarResizeObserver.disconnect());
    }

    const moveSelection = (direction) => {
        state.entries = syncStateEntriesFromDom(modal, state.entries);
        const moved = moveSelectedEntries(state.entries, state.selectedKeys, direction);
        if (moved === state.entries) return;
        applyEntryOrderToGrid(modal, moved);
        state.entries = moved;
        rerender();
        const firstSelectedKey = state.entries.find((entry) => state.selectedKeys.has(entry.key))?.key || '';
        if (firstSelectedKey !== '') {
            modal.querySelector(`[data-dt-settings-item][data-key="${CSS.escape(firstSelectedKey)}"]`)?.focus();
        }
    };

    const updateWidthButtonGuards = (input) => {
        const control = input?.closest('.dt-column-width-control');
        if (!control) return;
        const width = Number(input.value);
        control.querySelector('[data-dt-settings-width-decrease]').disabled = input.disabled
            || !Number.isFinite(width)
            || width <= DATA_TABLE_COLUMN_WIDTH_MIN;
        control.querySelector('[data-dt-settings-width-increase]').disabled = input.disabled
            || !Number.isFinite(width)
            || width >= DATA_TABLE_COLUMN_WIDTH_MAX;
    };

    const applyWidthValue = (input) => {
        const width = normalizeDataTableColumnWidth(input?.value);
        updateWidthButtonGuards(input);
        if (!Number.isFinite(width)) return;
        const key = String(input.dataset.key || '').trim();
        state.entries = syncStateEntriesFromDom(modal, state.entries).map((entry) => (
            entry.key === key ? { ...entry, width } : entry
        ));
        state.viewSettings = {
            ...state.viewSettings,
            columnWidths: { ...state.viewSettings.columnWidths, [key]: width },
        };
    };

    bind(modal.querySelector('[data-dt-settings-move-up]'), 'click', () => moveSelection(-1));
    bind(modal.querySelector('[data-dt-settings-move-down]'), 'click', () => moveSelection(1));

    bind(modal, 'input', (event) => {
        const searchInput = event.target.closest('[data-dt-settings-column-search]');
        if (!searchInput) return;
        state.columnSearchQuery = searchInput.value;
        applyColumnSearch(modal, state);
    });

    bind(modal, 'keydown', (event) => {
        if (!event.altKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        if (state.selectedKeys.size === 0) return;
        event.preventDefault();
        moveSelection(event.key === 'ArrowUp' ? -1 : 1);
    });

    bind(modal, 'change', (event) => {
        if (event.target.closest('[data-dt-view-page-length], [data-dt-view-search-expanded]')) {
            const searchSwitch = event.target.closest('[data-dt-view-search-expanded]');
            if (searchSwitch) {
                const status = searchSwitch.checked ? '펼침' : '닫힘';
                modal.querySelector('[data-dt-view-search-status]')?.replaceChildren(`검색영역-${status}`);
                searchSwitch.setAttribute('aria-label', `검색영역 ${status}`);
            }
            state.viewSettings = readViewSettingsFromDom(modal, state);
            return;
        }

        const selectAll = event.target.closest('[data-dt-settings-select-all]');
        if (selectAll) {
            state.entries = syncStateEntriesFromDom(modal, state.entries);
            state.selectedKeys = selectAll.checked
                ? new Set(state.entries.map((entry) => entry.key))
                : new Set();
            rerender();
            return;
        }

        const visibilityAll = event.target.closest('[data-dt-settings-visible-all]');
        if (visibilityAll) {
            state.entries = syncStateEntriesFromDom(modal, state.entries).map((entry) => {
                if (entry.hideable === false) return entry;
                return {
                    ...entry,
                    visible: visibilityAll.checked,
                    width: visibilityAll.checked && entry.widthResizable !== false ? entry.defaultWidth : null,
                    sortDirection: visibilityAll.checked ? entry.sortDirection : '',
                };
            });
            const columnWidths = {};
            state.entries.forEach((entry) => {
                if (entry.visible !== false && Number.isFinite(Number(entry.width)) && Number(entry.width) > 0) {
                    columnWidths[entry.key] = Math.round(Number(entry.width));
                }
            });
            state.viewSettings = {
                ...state.viewSettings,
                columnWidths,
                sortSettings: visibilityAll.checked ? state.viewSettings.sortSettings : [],
            };
            rerender();
            return;
        }

    });

    bind(modal, 'click', (event) => {
        const sortButton = event.target.closest('[data-dt-settings-sort]');
        if (sortButton && !sortButton.disabled) {
            const key = String(sortButton.dataset.key || '').trim();
            state.viewSettings = {
                ...state.viewSettings,
                sortSettings: cycleDataTableSortSettings(state.viewSettings.sortSettings, key),
            };
            const sortMap = new Map(state.viewSettings.sortSettings.map((item) => [item.key, item.dir]));
            state.entries = syncStateEntriesFromDom(modal, state.entries).map((entry) => ({
                ...entry,
                sortDirection: sortMap.get(entry.key) || '',
            }));
            modal.querySelectorAll('[data-dt-settings-sort]').forEach((button) => {
                const direction = sortMap.get(String(button.dataset.key || '').trim()) || '';
                button.dataset.direction = direction;
                button.classList.toggle('is-asc', direction === 'asc');
                button.classList.toggle('is-desc', direction === 'desc');
            });
            return;
        }

        const stepButton = event.target.closest('[data-dt-settings-width-decrease], [data-dt-settings-width-increase]');
        if (!stepButton || stepButton.disabled) return;
        const control = stepButton.closest('.dt-column-width-control');
        const input = control?.querySelector('[data-dt-settings-width]');
        if (!input || input.disabled) return;
        const direction = stepButton.matches('[data-dt-settings-width-decrease]') ? -1 : 1;
        const current = Number(input.value);
        const next = Math.min(
            DATA_TABLE_COLUMN_WIDTH_MAX,
            Math.max(DATA_TABLE_COLUMN_WIDTH_MIN, current + (direction * DATA_TABLE_COLUMN_WIDTH_STEP))
        );
        input.value = String(next);
        applyWidthValue(input);
    });

    bind(modal, 'change', (event) => {
        state.entries = syncStateEntriesFromDom(modal, state.entries);

        const checkbox = event.target.closest('[data-dt-settings-visible]');
        if (!checkbox) {
            return;
        }

        const key = checkbox.dataset.key || '';
        let nextWidth = null;
        state.entries = state.entries.map((entry) => (
            entry.key === key
                ? {
                    ...entry,
                    visible: checkbox.checked,
                    width: checkbox.checked && entry.widthResizable !== false
                        ? entry.defaultWidth
                        : null,
                }
                : entry
        ));
        nextWidth = state.entries.find((entry) => entry.key === key)?.width ?? null;
        const columnWidths = { ...state.viewSettings.columnWidths };
        delete columnWidths[key];
        if (checkbox.checked && Number.isFinite(Number(nextWidth)) && Number(nextWidth) > 0) {
            columnWidths[key] = Math.round(Number(nextWidth));
        }
        const sortSettings = checkbox.checked
            ? state.viewSettings.sortSettings
            : state.viewSettings.sortSettings.filter((item) => item.key !== key);
        state.entries = state.entries.map((entry) => (
            entry.key === key && !checkbox.checked ? { ...entry, sortDirection: '' } : entry
        ));
        state.viewSettings = { ...state.viewSettings, columnWidths, sortSettings };
        const widthInput = modal.querySelector(`[data-dt-settings-width][data-key="${CSS.escape(key)}"]`);
        if (widthInput) {
            widthInput.value = Number.isFinite(Number(nextWidth)) && Number(nextWidth) > 0
                ? String(Math.round(Number(nextWidth)))
                : '';
            const entry = state.entries.find((item) => item.key === key);
            widthInput.disabled = !checkbox.checked || entry?.widthResizable === false;
            updateWidthButtonGuards(widthInput);
        }
        const sortButton = modal.querySelector(`[data-dt-settings-sort][data-key="${CSS.escape(key)}"]`);
        if (sortButton) {
            const entry = state.entries.find((item) => item.key === key);
            sortButton.disabled = !checkbox.checked || entry?.sortOrderable === false;
            if (!checkbox.checked) {
                sortButton.dataset.direction = '';
                sortButton.classList.remove('is-asc', 'is-desc');
            }
        }
        updateSettingsSummary(modal, state.entries);
        syncVisibilityAllToggle(modal, state.entries);
    });

    bind(modal, 'input', (event) => {
        const widthInput = event.target.closest('[data-dt-settings-width]');
        if (widthInput) {
            applyWidthValue(widthInput);
            return;
        }
        const input = event.target.closest('[data-dt-settings-display-name]');
        if (!input) {
            return;
        }

        state.entries = syncStateEntriesFromDom(modal, state.entries);
        const key = input.dataset.key || '';
        state.entries = state.entries.map((entry) => (
            entry.key === key
                ? { ...entry, displayName: String(input.value || '').trim() || entry.title || entry.key }
                : entry
        ));
    });

    bind(modal, 'change', (event) => {
        const select = event.target.closest('[data-dt-settings-requirement-policy]');
        if (!select) {
            return;
        }

        state.entries = syncStateEntriesFromDom(modal, state.entries);
        const key = select.dataset.key || '';
        const nextPolicy = normalizeRequirementPolicy(select.value);
        select.classList.remove('is-none', 'is-optional', 'is-required');
        select.classList.add(requirementPolicySelectClass(nextPolicy));
        state.entries = state.entries.map((entry) => (
            entry.key === key
                ? { ...entry, requirementPolicy: nextPolicy }
                : entry
        ));
        const sourceWrap = modal.querySelector(
            `[data-row-id="${CSS.escape(key)}"][data-column-key="sourceColumn"] .dt-column-settings-source`
        );
        if (sourceWrap) {
            sourceWrap.querySelector('.column-policy-star')?.remove();
            sourceWrap.insertAdjacentHTML('beforeend', requirementStarHtml(nextPolicy));
        }
    });

    bind(restoreButton, 'click', async () => {
        if (restoreButton.disabled) return;
        restoreButton.disabled = true;
        try {
            const restoredEntries = await state.restoreDefaults?.(
                state.entries.map((entry) => ({ ...entry }))
            );
            state.defaults = normalizeEntries(restoredEntries || state.defaults);
            state.entries = normalizeEntries(state.defaults);
            state.stateSource = 'default';
            state.selectedKeys = new Set();
            updateStateSourceBadge(stateSourceNode, 'default');
            rerender();
        } catch (error) {
            console.error('[datatable-settings] restore defaults failed:', error);
            notify('error', '테이블 설정 기본값 복원 중 오류가 발생했습니다.');
        } finally {
            restoreButton.disabled = false;
        }
    });

    bind(resetViewButton, 'click', async () => {
        if (resetViewButton.disabled) return;
        resetViewButton.disabled = true;
        try {
            const restored = await state.restoreViewDefaults?.();
            state.defaultViewSettings = normalizeViewSettings(restored || state.defaultViewSettings, {
                pageLengthOptions: state.pageLengthOptions,
                searchFormExpandedDefault: options.searchFormExpandedDefault,
            });
            state.viewSettings = normalizeViewSettings(state.defaultViewSettings, {
                pageLengthOptions: state.pageLengthOptions,
                searchFormExpandedDefault: options.searchFormExpandedDefault,
            });
            const defaultSortMap = new Map(
                state.viewSettings.sortSettings.map((item) => [item.key, item.dir])
            );
            state.entries = state.entries.map((entry) => ({
                ...entry,
                width: state.viewSettings.columnWidths[entry.key] || null,
                sortDirection: defaultSortMap.get(entry.key) || '',
            }));
            rerender();
        } catch (error) {
            console.error('[datatable-settings] view restore failed:', error);
            notify('error', '보기 설정 초기화 중 오류가 발생했습니다.');
        } finally {
            resetViewButton.disabled = false;
        }
    });

    bind(saveButton, 'click', () => {
        state.entries = syncStateEntriesFromDom(modal, state.entries);
        state.viewSettings = readViewSettingsFromDom(modal, state);
        const invalidWidthEntry = state.entries.find((entry) => Number.isNaN(entry.width));
        if (invalidWidthEntry) {
            notify('warning', `너비는 ${DATA_TABLE_COLUMN_WIDTH_MIN}~${DATA_TABLE_COLUMN_WIDTH_MAX}px 사이의 숫자로 입력하거나 비워 주세요.`);
            modal.querySelector(`[data-dt-settings-width][data-key="${CSS.escape(invalidWidthEntry.key)}"]`)?.focus();
            return;
        }
        const blankDisplayEntry = state.entries.find((entry) => String(entry.displayName || '').trim() === '');
        if (blankDisplayEntry) {
            notify('warning', '사용컬럼명은 비워둘 수 없습니다.');
            const blankInput = modal.querySelector(`[data-dt-settings-display-name][data-key="${CSS.escape(blankDisplayEntry.key)}"]`);
            blankInput?.focus();
            return;
        }
        state.onSave?.(state.entries.slice(), { ...state.viewSettings });
        updateStateSourceBadge(stateSourceNode, 'saved');
        instance?.hide();
    });

    const hiddenHandler = () => {
        const list = modal.querySelector('[data-dt-settings-list]');
        const $ = window.jQuery;
        if (list && $?.fn?.sortable) {
            const $list = $(list);
            if ($list.data('ui-sortable')) {
                $list.sortable('destroy');
            }
        }
        document.body.classList.remove('dt-settings-dragging');
        modal.__dtSettingsGrid?.destroy?.();
        modal.__dtSettingsGrid = null;
        state.selectedKeys = new Set();
        cleanup.splice(0).forEach((dispose) => dispose());
        modal.removeEventListener('hidden.bs.modal', hiddenHandler);
    };

    modal.addEventListener('hidden.bs.modal', hiddenHandler);
    instance?.show();

    return modal;
}

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
                        <h5 class="modal-title">테이블 설정</h5>
                        <div class="small text-muted" data-dt-settings-subtitle>컬럼 표시, 순서, 사용컬럼명, 필수구분을 설정합니다.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <div class="excel-settings-shell">
                        <div class="excel-settings-panel-body p-0">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div class="text-muted small">한 행이 하나의 컬럼 정책입니다. 표시, 순서, 사용컬럼명, 필수구분을 같은 행에서 수정합니다.</div>
                                <div class="text-muted small" data-dt-settings-summary></div>
                            </div>
                            <div class="excel-settings-grid is-download dt-column-settings-grid" role="table" aria-label="테이블 컬럼 설정">
                                <div class="excel-settings-grid-header excel-settings-grid-header-download" role="row">
                                    <div class="excel-settings-grid-head excel-settings-grid-cell-usage" data-dt-settings-toggle-all-wrap></div>
                                    <div class="excel-settings-grid-head excel-settings-grid-cell-handle">
                                        <span class="excel-settings-head-icon" aria-hidden="true">
                                            <i class="bi bi-arrows-move"></i>
                                        </span>
                                    </div>
                                    <div class="excel-settings-grid-head excel-settings-grid-cell-order">순번</div>
                                    <div class="excel-settings-grid-head excel-settings-grid-cell-label">원본컬럼명</div>
                                    <div class="excel-settings-grid-head dt-column-settings-display-head">사용컬럼명</div>
                                    <div class="excel-settings-grid-head dt-column-settings-policy-head">필수구분</div>
                                </div>
                                <div class="excel-settings-grid-body" data-dt-settings-list></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dt-settings-restore>기본값 복원</button>
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
        requirementPolicy: normalizeRequirementPolicy(entry.requirementPolicy),
        required: false,
    })).filter((entry) => entry.key !== '');
}

function renderToggleAll(entries = []) {
    const checkedCount = entries.filter((entry) => entry.visible).length;
    const allChecked = entries.length > 0 && checkedCount === entries.length;
    const partiallyChecked = checkedCount > 0 && checkedCount < entries.length;

    return `
        <label class="excel-settings-head-toggle" aria-label="전체 선택">
            <input class="form-check-input mt-0"
                   type="checkbox"
                   data-dt-settings-toggle-all
                   ${allChecked ? 'checked' : ''}
                   ${partiallyChecked ? 'data-indeterminate="true"' : ''}
                   ${entries.length === 0 ? 'disabled' : ''}>
        </label>
    `;
}

function detectDragMode() {
    return 'pointer';
}

function renderList(modal, entries = [], dragMode = 'native') {
    const list = modal.querySelector('[data-dt-settings-list]');
    if (!list) {
        return;
    }

    const summary = modal.querySelector('[data-dt-settings-summary]');
    if (summary) {
        const selectedCount = entries.filter((entry) => entry.visible).length;
        summary.textContent = `선택 ${selectedCount}개 / 전체 ${entries.length}개`;
    }

    const toggleAllWrap = modal.querySelector('[data-dt-settings-toggle-all-wrap]');
    if (toggleAllWrap) {
        toggleAllWrap.innerHTML = renderToggleAll(entries);
    }

    list.innerHTML = entries.map((entry, index) => `
        <div class="excel-settings-grid-row dt-column-settings-item"
             ${dragMode === 'native' ? 'draggable="true"' : ''}
             data-dt-settings-item
             data-key="${escapeHtml(entry.key)}">
            <div class="excel-settings-grid-cell excel-settings-grid-cell-usage">
                <label class="mb-0 d-inline-flex">
                    <input type="checkbox"
                           class="form-check-input mt-0"
                           data-dt-settings-visible
                           data-key="${escapeHtml(entry.key)}"
                           ${entry.visible ? 'checked' : ''}>
                </label>
            </div>
            <div class="excel-settings-grid-cell excel-settings-grid-cell-handle">
                <button type="button"
                        class="excel-settings-drag-handle text-muted reorder-handle dt-settings-drag-handle"
                        ${dragMode === 'native' ? 'draggable="true"' : 'draggable="false"'}
                        data-dt-settings-drag-handle
                        tabindex="-1"
                        title="드래그하여 순서를 변경합니다"
                        aria-label="드래그하여 순서를 변경합니다">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
            </div>
            <div class="excel-settings-grid-cell excel-settings-grid-cell-order">
                <span class="excel-settings-order-value">${index + 1}</span>
            </div>
            <div class="excel-settings-grid-cell excel-settings-grid-cell-label">
                <div class="excel-settings-column-label">
                    ${escapeHtml(entry.sourceTitle || entry.key)}
                    ${requirementStarHtml(entry.requirementPolicy)}
                </div>
            </div>
            <div class="excel-settings-grid-cell dt-column-settings-display-cell">
                <input type="text"
                       class="form-control form-control-sm"
                       data-dt-settings-display-name
                       data-key="${escapeHtml(entry.key)}"
                       value="${escapeHtml(entry.displayName || entry.title || entry.key)}">
            </div>
            <div class="excel-settings-grid-cell dt-column-settings-policy-cell">
                <select class="form-select form-select-sm"
                        data-dt-settings-requirement-policy
                        data-key="${escapeHtml(entry.key)}">
                    <option value="none" ${entry.requirementPolicy === 'none' ? 'selected' : ''}>선택안함</option>
                    <option value="optional" ${entry.requirementPolicy === 'optional' ? 'selected' : ''}>선택</option>
                    <option value="required" ${entry.requirementPolicy === 'required' ? 'selected' : ''}>필수</option>
                </select>
            </div>
        </div>
    `).join('');

    list.querySelectorAll('[data-indeterminate="true"]').forEach((checkbox) => {
        checkbox.indeterminate = true;
    });
}

function reorderEntries(entries = [], fromKey = '', toKey = '', dropPosition = 'before') {
    if (!fromKey || !toKey || fromKey === toKey) {
        return entries;
    }

    const fromIndex = entries.findIndex((entry) => entry.key === fromKey);
    const toIndex = entries.findIndex((entry) => entry.key === toKey);
    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
        return entries;
    }

    const next = entries.slice();
    const [item] = next.splice(fromIndex, 1);
    const baseIndex = fromIndex < toIndex ? toIndex - 1 : toIndex;
    const insertIndex = dropPosition === 'after' ? baseIndex + 1 : baseIndex;
    next.splice(insertIndex, 0, item);
    return next;
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

    return entries.map((entry) => ({
        ...entry,
        visible: Array.from(modal.querySelectorAll('[data-dt-settings-visible]'))
            .find((input) => String(input.dataset.key || '').trim() === entry.key)?.checked ?? (entry.visible !== false),
        displayName: displayNameMap.has(entry.key)
            ? String(displayNameMap.get(entry.key) || '').trim()
            : String(entry.displayName || entry.title || entry.key).trim(),
        requirementPolicy: normalizeRequirementPolicy(requirementPolicyMap.get(entry.key)),
    }));
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
        items: '[data-dt-settings-item]',
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
            state.entries = syncEditableValuesFromDom(modal, syncVisibleValuesFromDom(modal, syncEntriesFromDom(list, state.entries)));
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
        state.entries = syncEditableValuesFromDom(modal, syncVisibleValuesFromDom(modal, syncEntriesFromDom(list, state.entries)));
        dragState.active = false;
        dragState.pointerId = null;
        dragState.row = null;
        dragState.handle = null;
        rerender();
    };

    list.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('[data-dt-settings-drag-handle]');
        const row = event.target.closest('[data-dt-settings-item]');
        if (!handle || !row || dragState.active) {
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
        if (!target || target === dragState.row || target.parentElement !== list) {
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
    const saveButton = modal.querySelector('[data-dt-settings-save]');
    const restoreButton = modal.querySelector('[data-dt-settings-restore]');
    const instance = window.bootstrap?.Modal?.getOrCreateInstance(modal, { focus: false });

    const state = {
        title: String(options.title || '테이블 설정'),
        subtitle: String(options.subtitle || '표시 컬럼과 순서를 설정합니다.'),
        defaults: normalizeEntries(options.defaultEntries || []),
        entries: normalizeEntries(options.entries || []),
        onSave: typeof options.onSave === 'function' ? options.onSave : null,
        restoreDefaults: typeof options.restoreDefaults === 'function' ? options.restoreDefaults : null,
    };

    titleNode.textContent = state.title;
    subtitleNode.textContent = state.subtitle;

    const rerender = () => {
        const dragMode = detectDragMode();
        renderList(modal, state.entries, dragMode);
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

    bind(modal, 'change', (event) => {
        const toggleAll = event.target.closest('[data-dt-settings-toggle-all]');
        if (toggleAll) {
            state.entries = state.entries.map((entry) => ({ ...entry, visible: toggleAll.checked }));
            rerender();
            return;
        }

        const checkbox = event.target.closest('[data-dt-settings-visible]');
        if (!checkbox) {
            return;
        }

        const key = checkbox.dataset.key || '';
        state.entries = state.entries.map((entry) => (
            entry.key === key
                ? { ...entry, visible: checkbox.checked }
                : entry
        ));
        rerender();
    });

    bind(modal, 'input', (event) => {
        const input = event.target.closest('[data-dt-settings-display-name]');
        if (!input) {
            return;
        }

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

        const key = select.dataset.key || '';
        state.entries = state.entries.map((entry) => (
            entry.key === key
                ? { ...entry, requirementPolicy: normalizeRequirementPolicy(select.value) }
                : entry
        ));
        rerender();
    });

    bind(restoreButton, 'click', () => {
        const restoredEntries = state.restoreDefaults?.();
        state.defaults = normalizeEntries(restoredEntries || state.defaults);
        state.entries = normalizeEntries(state.defaults);
        rerender();
    });

    bind(saveButton, 'click', () => {
        const list = modal.querySelector('[data-dt-settings-list]');
        state.entries = syncEditableValuesFromDom(
            modal,
            syncVisibleValuesFromDom(
                modal,
                syncEntriesFromDom(list, state.entries)
            )
        );
        const blankDisplayEntry = state.entries.find((entry) => String(entry.displayName || '').trim() === '');
        if (blankDisplayEntry) {
            notify('warning', '사용컬럼명은 비워둘 수 없습니다.');
            const blankInput = modal.querySelector(`[data-dt-settings-display-name][data-key="${CSS.escape(blankDisplayEntry.key)}"]`);
            blankInput?.focus();
            return;
        }
        state.onSave?.(state.entries.slice());
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
        cleanup.splice(0).forEach((dispose) => dispose());
        modal.removeEventListener('hidden.bs.modal', hiddenHandler);
    };

    modal.addEventListener('hidden.bs.modal', hiddenHandler);
    instance?.show();

    return modal;
}

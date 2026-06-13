const MODAL_ID = 'dtColumnSettingsModal';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
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
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">테이블 설정</h5>
                        <div class="small text-muted" data-dt-settings-subtitle>표시 컬럼과 순서를 설정합니다.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        필수 컬럼은 숨길 수 없습니다. 컬럼 순서 변경은 저장 후 새로고침으로 반영됩니다.
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">컬럼 목록</strong>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dt-settings-restore>기본값 복원</button>
                    </div>
                    <div class="list-group" data-dt-settings-list></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                    <button type="button" class="btn btn-primary btn-sm" data-dt-settings-save>저장</button>
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
        visible: entry.visible !== false,
        required: entry.required === true,
    })).filter((entry) => entry.key !== '');
}

function renderList(modal, entries = []) {
    const list = modal.querySelector('[data-dt-settings-list]');
    if (!list) {
        return;
    }

    list.innerHTML = entries.map((entry) => `
        <div
            class="list-group-item dt-column-settings-item"
            data-dt-settings-item
            data-key="${escapeHtml(entry.key)}"
        >
            <div class="d-flex align-items-center gap-3">
                <span
                    class="text-muted reorder-handle dt-settings-drag-handle"
                    role="button"
                    tabindex="0"
                    draggable="true"
                    title="드래그하여 순서를 변경합니다"
                    aria-label="드래그하여 순서를 변경합니다"
                ><i class="bi bi-list"></i></span>
                <label class="form-check d-flex align-items-center gap-2 mb-0 flex-grow-1">
                    <input
                        type="checkbox"
                        class="form-check-input"
                        data-dt-settings-visible
                        data-key="${escapeHtml(entry.key)}"
                        ${entry.visible ? 'checked' : ''}
                        ${entry.required ? 'disabled' : ''}
                    >
                    <span class="form-check-label">
                        ${escapeHtml(entry.title)}
                        ${entry.required ? '<span class="badge text-bg-secondary ms-2">필수</span>' : ''}
                    </span>
                </label>
            </div>
        </div>
    `).join('');
}

function reorderEntries(entries = [], fromKey = '', toKey = '') {
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
    next.splice(toIndex, 0, item);
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
            state.entries = syncEntriesFromDom(list, state.entries);
            rerender();
        },
    });

    return true;
}

function bindNativeDrag(modal, state, rerender) {
    const list = modal.querySelector('[data-dt-settings-list]');
    if (!list) {
        return false;
    }

    const dragState = { key: '' };

    list.querySelectorAll('[data-dt-settings-item]').forEach((item) => {
        item.addEventListener('dragover', (event) => {
            event.preventDefault();
        });

        item.addEventListener('drop', (event) => {
            event.preventDefault();
            const targetItem = event.currentTarget;
            const targetKey = String(targetItem?.dataset.key || '').trim();
            state.entries = reorderEntries(state.entries, dragState.key, targetKey);
            list.querySelectorAll('.is-dragging').forEach((node) => node.classList.remove('is-dragging'));
            document.body.classList.remove('dt-settings-dragging');
            dragState.key = '';
            rerender();
        });
    });

    list.querySelectorAll('.dt-settings-drag-handle').forEach((handle) => {
        handle.addEventListener('dragstart', (event) => {
            const item = event.currentTarget.closest('[data-dt-settings-item]');
            dragState.key = String(item?.dataset.key || '').trim();
            item?.classList.add('is-dragging');
            document.body.classList.add('dt-settings-dragging');
            event.dataTransfer?.setData('text/plain', dragState.key);
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
            }
        });

        handle.addEventListener('dragend', (event) => {
            event.currentTarget.closest('[data-dt-settings-item]')?.classList.remove('is-dragging');
            document.body.classList.remove('dt-settings-dragging');
            dragState.key = '';
        });
    });

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
    };

    titleNode.textContent = state.title;
    subtitleNode.textContent = state.subtitle;

    const rerender = () => {
        renderList(modal, state.entries);
        if (!bindJquerySortable(modal, state, rerender)) {
            bindNativeDrag(modal, state, rerender);
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
        const checkbox = event.target.closest('[data-dt-settings-visible]');
        if (!checkbox) {
            return;
        }

        const key = checkbox.dataset.key || '';
        state.entries = state.entries.map((entry) => (
            entry.key === key
                ? { ...entry, visible: entry.required ? true : checkbox.checked }
                : entry
        ));
        rerender();
    });

    bind(restoreButton, 'click', () => {
        state.entries = normalizeEntries(state.defaults);
        rerender();
    });

    bind(saveButton, 'click', () => {
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

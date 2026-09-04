import { initCodeModalRuntime, renderCodeSystemInfo } from './code-modal-runtime.js';

const API = {
    LIST: '/api/settings/system/code/list',
    DETAIL: '/api/settings/system/code/detail',
    GROUPS: '/api/settings/system/code/groups',
    SAVE: '/api/settings/system/code/save',
    DELETE: '/api/settings/system/code/delete',
};

const QUICK_ADD_VALUE = '__CODE_QUICK_ADD__';
const NEW_CODE_GROUP_VALUE = '__new_code_group__';

const state = {
    options: {},
    optionPromises: {},
    fieldGroups: {},
    previousValues: {},
    codeGroups: [],
    codeGroupNames: {},
    quickModal: null,
    quickModalContext: null,
    activeQuick: null,
    activeOriginal: null,
    originalModalCardCollapses: null,
    callbacks: new Set(),
    modalCleanupBound: false,
    searchFocusBound: false,
};

function normalizeSortNo(value) {
    const parsed = Number(String(value ?? '').replace(/,/g, '').trim());
    return Number.isFinite(parsed) ? parsed : Number.MAX_SAFE_INTEGER;
}

function sortCodeRowsBySortNo(rows = []) {
    return [...(Array.isArray(rows) ? rows : [])]
        .map((row, index) => ({ row, index }))
        .sort((left, right) => {
            const leftSortNo = normalizeSortNo(left.row?.sort_no);
            const rightSortNo = normalizeSortNo(right.row?.sort_no);
            if (leftSortNo !== rightSortNo) {
                return leftSortNo - rightSortNo;
            }
            return left.index - right.index;
        })
        .map((entry) => entry.row);
}

export function getCodeName(field, code) {
    const value = String(code ?? '').trim();
    if (!value) return '';

    const group = state.fieldGroups[field];
    const groups = group ? [state.options[group] || []] : Object.values(state.options);

    for (const groupOptions of groups) {
        const found = groupOptions.find((row) => row.code === value);
        if (found) return found.code_name || value;
    }

    return value;
}

export function getCodeNameByGroup(codeGroup, code) {
    const group = normalizeCodeGroup(codeGroup);
    const value = String(code ?? '').trim();
    if (!group || !value) return '';

    const found = (state.options[group] || []).find(row => row.code === value);
    return found?.code_name || value;
}

export function onCodeOptionsLoaded(callback) {
    if (typeof callback === 'function') {
        state.callbacks.add(callback);
    }
}

export async function getCodeOptions(codeGroup) {
    return fetchCodeOptions(codeGroup);
}

export async function initCodeSelectControls(root = document) {
    bindCodeSelectModalCleanup();
    ensureQuickModal();
    bindOriginalCodeModal();

    const scope = root || document;
    const selects = Array.from(scope.querySelectorAll('select[data-code-group]'));
    await Promise.all(selects.map((select) => createCodeSelect({
        selectId: ensureSelectId(select),
        codeGroup: select.dataset.codeGroup,
        selectedValue: select.value,
    })));
}

export async function createCodeSelect({ selectId, codeGroup, selectedValue = '' }) {
    bindCodeSelectModalCleanup();
    ensureQuickModal();
    bindOriginalCodeModal();

    const select = document.getElementById(selectId);
    const group = normalizeCodeGroup(codeGroup);
    if (!select || !group) return;

    select.dataset.codeGroup = group;

    if (select.name) {
        state.fieldGroups[select.name] = group;
    }

    bindCodeSelect(select, group);
    await refreshCodeSelect(select, group, selectedValue || select.value);
}

export async function refreshCodeSelect(selectOrId, codeGroup, selectedValue = '') {
    const select = typeof selectOrId === 'string' ? document.getElementById(selectOrId) : selectOrId;
    const group = normalizeCodeGroup(codeGroup || select?.dataset?.codeGroup);
    if (!select || !group) return;

    const rows = await fetchCodeOptions(group);
    const currentValue = String(selectedValue ?? select.value ?? '').trim();
    const existingEmptyOption = select.querySelector('option[value=""]');

    select.innerHTML = '';
    if (select.dataset.emptyOption !== 'false') {
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = '선택(없음)';
        emptyOption.selected = currentValue === '';
        select.appendChild(emptyOption);
    }

    rows.forEach((row) => {
        const option = document.createElement('option');
        option.value = row.code;
        option.textContent = row.code_name || row.code;
        select.appendChild(option);
    });

    const addOption = document.createElement('option');
    addOption.value = QUICK_ADD_VALUE;
    addOption.textContent = '+ 추가';
    select.appendChild(addOption);

    select.value = shouldPreserveRawCodeValue(select)
        ? currentValue
        : resolveCodeSelectValue(rows, currentValue);
    state.previousValues[select.id] = select.value || '';
    enhanceSelect2(select);
    notifyOptionsLoaded();
}

export async function openCodeQuickModal(args, legacyTargetSelectId = null) {
    if (!ensureQuickModal()) return;

    const params = typeof args === 'object'
        ? args
        : { codeGroup: args, targetSelectId: legacyTargetSelectId };

    const codeGroup = normalizeCodeGroup(params.codeGroup);
    const targetSelectId = params.targetSelectId || params.selectId || '';
    const select = targetSelectId ? document.getElementById(targetSelectId) : null;
    const quickOnly = Boolean(select?.closest('[data-code-quick-only="true"]'));

    state.activeQuick = {
        codeGroup,
        targetSelectId,
        select,
    };

    const modal = document.getElementById('codeQuickModal');
    if (!modal) return;
    const detailButton = modal.querySelector('[data-role="detail"]');
    if (detailButton) detailButton.hidden = quickOnly;

    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    const groupName = await getGroupNameForCodeGroup(codeGroup);

    setQuickCodeGroupValue(modal, codeGroup, groupName);
    setFormValue(modal, '[name="group_name"]', groupName || codeGroup);
    setFormValue(modal, '[name="code"]', '');
    setFormValue(modal, '[name="code_name"]', '');
    setFormValue(modal, '[name="note"]', '');
    setFormValue(modal, '[name="memo"]', '');
    setFormValue(modal, '[name="is_active"]', '1');
    setText(modal, '[data-role="message"]', '');

    captureQuickModalContext(modal);
    modal.addEventListener('shown.bs.modal', () => bringQuickModalToFront(modal), { once: true });
    state.quickModal = bootstrap.Modal.getOrCreateInstance(modal, { focus: false });
    state.quickModal.show();
    bringQuickModalToFront(modal);
    setTimeout(() => modal.querySelector('[name="code"]')?.focus(), 150);
}

function setQuickCodeGroupValue(modal, codeGroup, groupName = '') {
    const group = normalizeCodeGroup(codeGroup);
    const field = modal?.querySelector('[name="code_group"]');
    if (!field) return;

    if (field.tagName === 'SELECT') {
        const hasOption = Array.from(field.options).some((option) => option.value === group);
        if (group && !hasOption) {
            const option = document.createElement('option');
            option.value = group;
            option.textContent = groupName ? `${groupName} (${group})` : group;
            field.appendChild(option);
        }
    }

    field.value = group;
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

function bindCodeSelectModalCleanup() {
    if (state.modalCleanupBound) return;
    state.modalCleanupBound = true;

    document.addEventListener('hide.bs.modal', (event) => {
        closeCodeSelectsInModal(event.target);
    }, true);

    document.addEventListener('hidden.bs.modal', (event) => {
        closeCodeSelectsInModal(event.target);
    }, true);
}

function bindSelect2SearchFocus() {
    if (state.searchFocusBound || !window.jQuery) return;
    state.searchFocusBound = true;
    const focusSearch = () => {
        const search = document.querySelector('.select2-container--open .select2-search__field');
        if (!search) return false;
        search.focus?.();
        search.select?.();
        return document.activeElement === search;
    };
    window.jQuery(document).on('select2:open.codeSelectFocus', () => {
        [0, 16, 50, 120].forEach((delay) => window.setTimeout(focusSearch, delay));
        window.requestAnimationFrame?.(focusSearch);
    });
    document.addEventListener('focusin', (event) => {
        const openContainer = document.querySelector('.select2-container--open');
        if (!openContainer) return;
        const search = openContainer.querySelector('.select2-search__field');
        if (!search) return;
        const target = event.target;
        if (target === search || openContainer.contains(target)) return;
        window.setTimeout(focusSearch, 0);
    });
}

function closeCodeSelectsInModal(modal) {
    if (!modal?.querySelectorAll || !window.jQuery?.fn?.select2) return;

    modal.querySelectorAll('select[data-code-group].select2-hidden-accessible').forEach((select) => {
        try {
            window.jQuery(select).select2('close');
        } catch (error) {
        }

        select.blur?.();
    });

    modal.querySelectorAll('.select2-selection, .select2-search__field').forEach((element) => {
        element.blur?.();
    });

    const active = document.activeElement;
    if (!active || !modal.contains(active)) {
        return;
    }

    active.blur?.();

    const body = document.body;
    if (!body) {
        return;
    }

    const previousTabIndex = body.getAttribute('tabindex');
    if (!body.hasAttribute('tabindex')) {
        body.setAttribute('tabindex', '-1');
    }

    try {
        body.focus({ preventScroll: true });
    } catch (error) {
        body.focus?.();
    }

    window.setTimeout(() => {
        if (previousTabIndex === null) {
            body.removeAttribute('tabindex');
        } else {
            body.setAttribute('tabindex', previousTabIndex);
        }
    }, 0);
}

function ensureSelectId(select) {
    if (!select.id) {
        select.id = `code_select_${Math.random().toString(36).slice(2)}`;
    }
    return select.id;
}

function bindCodeSelect(select, codeGroup) {
    if (select.dataset.codeSelectBound === 'true') return;

    select.dataset.codeSelectBound = 'true';
    state.previousValues[select.id] = select.value || '';

    select.addEventListener('focus', () => {
        redirectFocusFromHiddenSelect(select);
        if (select.value !== QUICK_ADD_VALUE) {
            state.previousValues[select.id] = select.value || '';
        }
    });

    select.addEventListener('change', () => {
        if (handleQuickAddSelection(select, codeGroup)) return;
        state.previousValues[select.id] = select.value || '';
    });

    if (window.jQuery?.fn?.select2) {
        window.jQuery(select)
            .off('select2:select.codeSelect')
            .on('select2:select.codeSelect', (event) => {
                const selectedId = String(event.params?.data?.id ?? '');
                if (selectedId === QUICK_ADD_VALUE) {
                    handleQuickAddSelection(select, codeGroup, true);
                }
            })
            .off('select2:close.codeSelectFocusFix')
            .on('select2:close.codeSelectFocusFix', () => {
                window.setTimeout(() => {
                    redirectFocusFromHiddenSelect(select);
                }, 0);
            });
    }
}

function redirectFocusFromHiddenSelect(select) {
    if (!select || !window.jQuery?.fn?.select2) return;
    if (!window.jQuery(select).hasClass('select2-hidden-accessible')) return;
    if (document.activeElement !== select) return;

    const instance = window.jQuery(select).data('select2');
    const container = instance?.$container?.get?.(0) || select.nextElementSibling;
    const selection = container?.querySelector('.select2-selection');

    select.blur?.();

    if (!selection) {
        return;
    }

    if (!selection.hasAttribute('tabindex')) {
        selection.setAttribute('tabindex', '0');
    }

    try {
        selection.focus({ preventScroll: true });
    } catch (error) {
        selection.focus?.();
    }
}

function enhanceSelect2(select) {
    if (!window.jQuery?.fn?.select2 || !select) return;
    bindSelect2SearchFocus();

    const $select = window.jQuery(select);
    const modalParent = $select.closest('.modal');
    const options = {
        width: '100%',
        dropdownAutoWidth: false,
        language: 'ko',
        minimumResultsForSearch: select.dataset.codeSearchable === 'true' ? 0 : Infinity,
        dropdownCssClass: 'code-select-dropdown',
    };

    if (modalParent.length) {
        options.dropdownParent = modalParent.first();
    }

    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }

    $select.select2(options);
    $select.off('select2:open.codeSelectFocusLocal')
        .on('select2:open.codeSelectFocusLocal', () => {
            [0, 16, 50, 120].forEach((delay) => {
                window.setTimeout(() => {
                    const search = document.querySelector('.select2-container--open .select2-search__field');
                    search?.focus?.();
                    search?.select?.();
                }, delay);
            });
        })
        .off('select2:closing.codeSelectFocusFix')
        .on('select2:closing.codeSelectFocusFix', () => {
            window.setTimeout(() => {
                redirectFocusFromHiddenSelect(select);
            }, 0);
        });
}

function handleQuickAddSelection(select, codeGroup, force = false) {
    if (!select || (!force && select.value !== QUICK_ADD_VALUE)) {
        return false;
    }

    if (select.dataset.codeQuickAddOpening === 'true') {
        return true;
    }

    select.dataset.codeQuickAddOpening = 'true';
    const previousValue = state.previousValues[select.id] || '';
    restoreSelectValue(select, previousValue);

    window.setTimeout(() => {
        openCodeQuickModal({ codeGroup, targetSelectId: select.id });
        delete select.dataset.codeQuickAddOpening;
    }, 0);

    return true;
}

function restoreSelectValue(select, value) {
    select.value = value || '';

    if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
        window.jQuery(select).val(value || '').trigger('change.select2');
    }
}

async function fetchCodeOptions(codeGroup) {
    const group = normalizeCodeGroup(codeGroup);
    if (!group) return [];
    if (Array.isArray(state.options[group])) {
        return state.options[group];
    }
    if (state.optionPromises[group]) {
        return state.optionPromises[group];
    }

    state.optionPromises[group] = (async () => {
        const response = await fetch(`${API.LIST}?code_group=${encodeURIComponent(group)}`, { cache: 'no-store' });
        const json = await response.json();
        const rows = sortCodeRowsBySortNo(Array.isArray(json) ? json : (json.data || []));

        state.options[group] = rows
            .map((row) => ({
                id: String(row.id ?? '').trim(),
                code: String(row.code ?? '').trim(),
                code_name: String(row.code_name ?? row.code ?? '').trim(),
                group_name: String(row.group_name ?? '').trim(),
                sort_no: normalizeSortNo(row.sort_no),
                is_active: Number(row.is_active ?? 1),
                extra_data: row.extra_data ?? row.extraData ?? row.extra ?? null,
            }))
            .filter((row) => row.code && row.is_active === 1);

        const groupName = state.options[group].find((row) => row.group_name)?.group_name || '';
        if (groupName) {
            state.codeGroupNames[group] = groupName;
        }

        return state.options[group];
    })().finally(() => {
        delete state.optionPromises[group];
    });

    return state.optionPromises[group];
}

async function fetchCodeRows(codeGroup) {
    const group = normalizeCodeGroup(codeGroup);
    const response = await fetch(`${API.LIST}?code_group=${encodeURIComponent(group)}&filters=[]`, { cache: 'no-store' });
    const json = await response.json();
    return Array.isArray(json?.data) ? json.data : [];
}

async function fetchCodeDetail(id) {
    const response = await fetch(`${API.DETAIL}?id=${encodeURIComponent(id)}`, { cache: 'no-store' });
    const json = await response.json();
    return json?.success ? json.data : null;
}

async function fetchCodeGroups() {
    try {
        const response = await fetch(API.GROUPS, { cache: 'no-store' });
        const json = await response.json();
        const rows = Array.isArray(json) ? json : (json.data || []);
        state.codeGroups = rows
            .map((row) => normalizeCodeGroup(row.code_group ?? row))
            .filter(Boolean)
            .sort();

        rows.forEach((row) => {
            const codeGroup = normalizeCodeGroup(row?.code_group ?? row);
            const groupName = String(row?.group_name || '').trim();
            if (codeGroup && groupName) {
                state.codeGroupNames[codeGroup] = groupName;
            }
        });
    } catch (error) {
        state.codeGroups = Object.keys(state.options).sort();
    }

    return state.codeGroups;
}

function ensureQuickModal() {
    let modal = document.getElementById('codeQuickModal');
    if (!modal) {
        window.alert('코드관리 빠른 추가 모달을 찾을 수 없습니다.');
        return false;
        modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'codeQuickModal';
        modal.tabIndex = -1;
        modal.innerHTML = `
            <div class="modal-dialog modal-md">
                <div class="modal-content">
                    <form id="codeQuickForm">
                        <div class="modal-header">
                            <h5 class="modal-title">기준정보 빠른 추가</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">코드그룹</label>
                                    <input type="text" class="form-control form-control-sm" name="code_group" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">그룹명</label>
                                    <input type="text" class="form-control form-control-sm" name="group_name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">사용여부</label>
                                    <select class="form-select form-select-sm" name="is_active">
                                        <option value="1">사용</option>
                                        <option value="0">미사용</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">코드 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm text-uppercase" name="code" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">코드명 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" name="code_name" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">비고</label>
                                    <input type="text" class="form-control form-control-sm" name="note">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">메모</label>
                                    <textarea class="form-control form-control-sm" name="memo" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="small text-danger mt-2" data-role="message"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-role="detail">상세정보</button>
                            <button type="submit" class="btn btn-success btn-sm">저장</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    state.quickModal = bootstrap.Modal.getOrCreateInstance(modal, { focus: false });
    bindQuickCodeModal(modal);
    return true;
}

function captureQuickModalContext(modal) {
    const parentModal = Array.from(document.querySelectorAll('.modal.show'))
        .filter((candidate) => candidate !== modal)
        .at(-1);
    const scrollContainer = parentModal?.querySelector('.modal-body');

    state.quickModalContext = {
        parentModalId: String(parentModal?.id || ''),
        parentScrollTop: Number(scrollContainer?.scrollTop || 0),
        bodyPaddingRight: document.body.style.paddingRight || '',
        bodyOverflow: document.body.style.overflow || '',
    };
}

function bringQuickModalToFront(modal) {
    if (!modal) return;
    const modalZIndex = 20000;
    const apply = () => {
        modal.style.setProperty('z-index', String(modalZIndex), 'important');
        modal.querySelector('.modal-dialog')?.style.setProperty('z-index', String(modalZIndex + 1), 'important');
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops[backdrops.length - 1]?.style.setProperty('z-index', String(modalZIndex - 10), 'important');
    };

    apply();
    [0, 16, 50, 120].forEach((delay) => window.setTimeout(apply, delay));
}

function restoreQuickModalContext(modal) {
    const context = state.quickModalContext;
    state.quickModalContext = null;
    modal.style.removeProperty('z-index');
    modal.querySelector('.modal-dialog')?.style.removeProperty('z-index');
    if (!context || !document.querySelector('.modal.show')) return;

    document.body.classList.add('modal-open');
    document.body.style.paddingRight = context.bodyPaddingRight;
    document.body.style.overflow = context.bodyOverflow;

    const parentModal = context.parentModalId
        ? document.getElementById(context.parentModalId)
        : null;
    const scrollContainer = parentModal?.querySelector('.modal-body');
    if (scrollContainer) {
        window.requestAnimationFrame(() => {
            scrollContainer.scrollTop = context.parentScrollTop;
        });
    }

}

function bindQuickCodeModal(modal) {
    if (!modal || modal.dataset.codeQuickBound === 'true') return;

    modal.addEventListener('hidden.bs.modal', () => restoreQuickModalContext(modal));
    modal.querySelector('[name="code"]')?.addEventListener('input', function () {
        this.value = normalizeCode(this.value);
    });

    modal.querySelector('[data-role="detail"]')?.addEventListener('click', () => {
        if (!state.activeQuick?.codeGroup) return;
        const formData = new FormData(modal.querySelector('form'));
        const initialValues = {
            group_name: String(formData.get('group_name') || '').trim(),
            code: normalizeCode(formData.get('code')),
            code_name: String(formData.get('code_name') || '').trim(),
            is_active: String(formData.get('is_active') || '1'),
            note: String(formData.get('note') || '').trim(),
            memo: String(formData.get('memo') || '').trim(),
        };
        const codeGroup = state.activeQuick.codeGroup;
        const targetSelectId = state.activeQuick.targetSelectId;
        modal.addEventListener('hidden.bs.modal', () => {
            void openOriginalCodeModal({ codeGroup, targetSelectId, createNew: true, initialValues });
        }, { once: true });
        state.quickModal.hide();
    });

    modal.querySelector('form')?.addEventListener('submit', saveQuickCode);
    modal.dataset.codeQuickBound = 'true';
}

function bindOriginalCodeModal() {
    const form = document.getElementById('codeForm');
    const modalEl = document.getElementById('codeModal');
    if (!form || !modalEl || form.dataset.codeSelectBound === 'true') return;

    form.dataset.codeSelectBound = 'true';
    bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    state.originalModalCardCollapses = initCodeModalRuntime(modalEl);
    modalEl.addEventListener('hidden.bs.modal', () => restoreQuickModalContext(modalEl));

    form.addEventListener('submit', saveOriginalCode);
    document.getElementById('btnDeleteCode')?.addEventListener('click', deleteOriginalCode);
    document.getElementById('modal_code_code')?.addEventListener('input', function () {
        this.value = normalizeCode(this.value);
    });
    document.getElementById('modal_code_group')?.addEventListener('change', function () {
        if (this.value === NEW_CODE_GROUP_VALUE) {
            showOriginalGroupInput('');
            return;
        }
        syncOriginalGroupName(this.value);
    });
    document.getElementById('modal_code_group_input')?.addEventListener('input', function () {
        this.value = normalizeCodeGroup(this.value);
        syncOriginalGroupName(this.value);
    });
    document.getElementById('btnBackCodeGroupSelect')?.addEventListener('click', () => {
        showOriginalGroupSelect(state.activeOriginal?.codeGroup || '');
    });
}

async function saveQuickCode(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const message = form.querySelector('[data-role="message"]');
    const submit = form.querySelector('[type="submit"]');
    const formData = new FormData(form);
    formData.set('code', normalizeCode(formData.get('code')));
    formData.set('code_group', normalizeCodeGroup(formData.get('code_group')));
    formData.set('group_name', String(formData.get('group_name') || '').trim());

    if (!formData.get('group_name')) {
        message.textContent = '그룹명을 확인할 수 없습니다.';
        return;
    }

    submit.disabled = true;
    message.textContent = '';

    try {
        const json = await postForm(API.SAVE, formData);
        if (!json.success) {
            message.textContent = json.message || '저장에 실패했습니다.';
            return;
        }

        const code = String(formData.get('code') || '').trim();
        await refreshActiveSelect(state.activeQuick, code);
        state.quickModal.hide();
        window.AppCore?.notify?.('success', '기준정보가 추가되었습니다.');
    } catch (error) {
        message.textContent = error.message || '저장 중 오류가 발생했습니다.';
    } finally {
        submit.disabled = false;
    }
}

async function openOriginalCodeModal({ codeGroup, targetSelectId, createNew = false, initialValues = {} }) {
    bindOriginalCodeModal();

    const modalEl = document.getElementById('codeModal');
    if (!modalEl) {
        window.AppCore?.notify?.('warning', '기준정보 모달을 찾을 수 없습니다.');
        return;
    }

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    const select = targetSelectId ? document.getElementById(targetSelectId) : null;
    const selectedCode = createNew ? '' : (select?.value || '');
    const group = normalizeCodeGroup(codeGroup);
    const rows = await fetchCodeRows(group);
    const currentRow = rows.find((row) => String(row.code || '') === selectedCode);
    const detail = currentRow?.id ? await fetchCodeDetail(currentRow.id) : null;

    state.activeOriginal = {
        codeGroup: group,
        targetSelectId,
        select,
        selectedCode,
    };

    await renderOriginalGroupOptions(group);

    if (detail) {
        fillOriginalCodeForm(detail);
        setText(document, '#codeModalLabel', '기준정보 수정');
        document.getElementById('btnDeleteCode').style.display = '';
    } else {
        resetOriginalCodeForm(group, initialValues);
        setText(document, '#codeModalLabel', '기준정보 등록');
        document.getElementById('btnDeleteCode').style.display = 'none';
    }

    captureQuickModalContext(modalEl);
    bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).show();
    bringQuickModalToFront(modalEl);
}

async function renderOriginalGroupOptions(selectedGroup = '') {
    const select = document.getElementById('modal_code_group');
    if (!select) return;

    const groups = await fetchCodeGroups();
    const merged = Array.from(new Set([...groups, normalizeCodeGroup(selectedGroup)].filter(Boolean))).sort();

    select.innerHTML = '<option value="">선택(없음)</option>';
    merged.forEach((group) => {
        const option = document.createElement('option');
        option.value = group;
        option.textContent = state.codeGroupNames[group] ? `${state.codeGroupNames[group]} (${group})` : group;
        select.appendChild(option);
    });

    const separator = document.createElement('option');
    separator.disabled = true;
    separator.textContent = '──────────';
    select.appendChild(separator);

    const newOption = document.createElement('option');
    newOption.value = NEW_CODE_GROUP_VALUE;
    newOption.textContent = '+ 신규 코드그룹';
    select.appendChild(newOption);

    showOriginalGroupSelect(selectedGroup);
}

function resetOriginalCodeForm(codeGroup = '', values = {}) {
    const form = document.getElementById('codeForm');
    form?.reset();

    setValue('modal_code_id', '');
    setValue('modal_code_code', values.code || '');
    setValue('modal_code_group_name', values.group_name || getCachedGroupName(codeGroup));
    setValue('modal_code_code_name', values.code_name || '');
    setValue('modal_code_note', values.note || '');
    setValue('modal_code_memo', values.memo || '');
    setValue('modal_code_extra_data', values.extra_data || '{}');
    setValue('modal_code_is_active', String(values.is_active ?? '1'));
    showOriginalGroupSelect(codeGroup);
    renderCodeSystemInfo();
    state.originalModalCardCollapses?.reset?.();
}

function fillOriginalCodeForm(data = {}) {
    resetOriginalCodeForm(data.code_group || state.activeOriginal?.codeGroup || '');

    if (data.code_group && data.group_name) {
        state.codeGroupNames[normalizeCodeGroup(data.code_group)] = String(data.group_name).trim();
    }

    setValue('modal_code_id', data.id || '');
    setValue('modal_code_code', data.code || '');
    setValue('modal_code_group_name', data.group_name || getCachedGroupName(data.code_group || state.activeOriginal?.codeGroup || ''));
    setValue('modal_code_code_name', data.code_name || '');
    setValue('modal_code_note', data.note || '');
    setValue('modal_code_memo', data.memo || '');
    setValue('modal_code_extra_data', data.extra_data || '{}');
    setValue('modal_code_is_active', String(data.is_active ?? '1'));
    showOriginalGroupSelect(data.code_group || state.activeOriginal?.codeGroup || '');
    renderCodeSystemInfo(data);
}

function showOriginalGroupSelect(value = '') {
    const select = document.getElementById('modal_code_group');
    const input = document.getElementById('modal_code_group_input');
    const inputWrap = document.getElementById('modal_code_group_input_wrap');

    if (inputWrap) inputWrap.classList.add('d-none');
    if (select) {
        select.classList.remove('d-none');
        select.value = normalizeCodeGroup(value);
    }
    if (input) input.value = '';
    syncOriginalGroupName(value);
}

function showOriginalGroupInput(value = '') {
    const select = document.getElementById('modal_code_group');
    const input = document.getElementById('modal_code_group_input');
    const inputWrap = document.getElementById('modal_code_group_input_wrap');

    if (select) select.classList.add('d-none');
    if (inputWrap) inputWrap.classList.remove('d-none');
    if (input) {
        input.value = normalizeCodeGroup(value);
        input.focus();
    }
    syncOriginalGroupName(value);
}

function getCachedGroupName(codeGroup) {
    return state.codeGroupNames[normalizeCodeGroup(codeGroup)] || '';
}

async function getGroupNameForCodeGroup(codeGroup) {
    const group = normalizeCodeGroup(codeGroup);
    if (!group) return '';

    if (state.codeGroupNames[group]) {
        return state.codeGroupNames[group];
    }

    const rows = await fetchCodeRows(group);
    const groupName = String(rows.find((row) => row.group_name)?.group_name || '').trim();
    if (groupName) {
        state.codeGroupNames[group] = groupName;
    }

    return groupName;
}

function syncOriginalGroupName(codeGroup) {
    const input = document.getElementById('modal_code_group_name');
    if (!input) return;

    const groupName = getCachedGroupName(codeGroup);
    input.value = groupName;
}

function getOriginalCodeGroupValue() {
    const inputWrap = document.getElementById('modal_code_group_input_wrap');
    if (inputWrap && !inputWrap.classList.contains('d-none')) {
        return document.getElementById('modal_code_group_input')?.value || '';
    }

    return document.getElementById('modal_code_group')?.value || '';
}

async function saveOriginalCode(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const submit = form.querySelector('[type="submit"]');
    const formData = new FormData(form);
    const codeGroup = normalizeCodeGroup(getOriginalCodeGroupValue());
    const groupName = String(formData.get('group_name') || '').trim();
    const code = normalizeCode(formData.get('code'));
    const codeName = String(formData.get('code_name') || '').trim();
    const extraData = String(formData.get('extra_data') || '').trim();

    if (!codeGroup || !groupName || !code || !codeName) {
        window.AppCore?.notify?.('warning', '코드그룹, 그룹명, 코드, 코드명은 필수입니다.');
        return;
    }

    if (extraData) {
        try {
            JSON.parse(extraData);
        } catch (error) {
            window.AppCore?.notify?.('warning', '추가 속성은 올바른 JSON 형식이어야 합니다.');
            return;
        }
    }

    formData.set('code_group', codeGroup);
    formData.set('group_name', groupName);
    formData.set('code', code);

    if (submit) submit.disabled = true;

    try {
        const json = await postForm(API.SAVE, formData);
        if (!json.success) {
            window.AppCore?.notify?.('error', json.message || '저장에 실패했습니다.');
            return;
        }

        await refreshActiveSelect(state.activeOriginal, code);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('codeModal')).hide();
        window.AppCore?.notify?.('success', '저장 완료');
    } finally {
        if (submit) submit.disabled = false;
    }
}

async function deleteOriginalCode() {
    const id = document.getElementById('modal_code_id')?.value || '';
    if (!id || !confirm('삭제하시겠습니까?')) return;

    const formData = new FormData();
    formData.set('id', id);

    const json = await postForm(API.DELETE, formData);
    if (!json.success) {
        window.AppCore?.notify?.('error', json.message || '삭제에 실패했습니다.');
        return;
    }

    await refreshActiveSelect(state.activeOriginal, '');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('codeModal')).hide();
    window.AppCore?.notify?.('success', '삭제 완료');
}

async function refreshActiveSelect(active, selectedCode = '') {
    const select = active?.select || (active?.targetSelectId ? document.getElementById(active.targetSelectId) : null);
    const codeGroup = normalizeCodeGroup(active?.codeGroup || select?.dataset?.codeGroup);
    if (!select || !codeGroup) return;

    delete state.options[codeGroup];
    await refreshCodeSelect(select, codeGroup, selectedCode);
    select.value = selectedCode;
    state.previousValues[select.id] = selectedCode;
    select.dispatchEvent(new Event('change', { bubbles: true }));

    if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
        window.jQuery(select).val(selectedCode).trigger('change.select2');
    }
}

async function postForm(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        body: formData,
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(json.message || `요청에 실패했습니다. (${response.status})`);
    }
    return json;
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}

function setFormValue(root, selector, value) {
    const el = root.querySelector(selector);
    if (el) el.value = value ?? '';
}

function setText(root, selector, value) {
    const el = root.querySelector(selector);
    if (el) el.textContent = value ?? '';
}

function notifyOptionsLoaded() {
    state.callbacks.forEach((callback) => {
        try {
            callback(state.options);
        } catch (error) {
            console.error('[code-select] callback failed', error);
        }
    });
}

function normalizeCodeGroup(value) {
    return String(value || '').trim().replace(/\s+/g, '').toUpperCase();
}

function normalizeCode(value) {
    return String(value || '').trim().toUpperCase();
}

function normalizeCodeLookupValue(value) {
    return String(value ?? '').trim().replace(/\s+/g, ' ').toUpperCase();
}

function resolveCodeSelectValue(rows = [], value = '') {
    const raw = String(value ?? '').trim();
    if (raw === '') return '';

    const exactCode = rows.find((row) => String(row.code || '').trim() === raw);
    if (exactCode) return exactCode.code;

    const normalized = normalizeCodeLookupValue(raw);
    const byCode = rows.find((row) => normalizeCodeLookupValue(row.code) === normalized);
    if (byCode) return byCode.code;

    const byName = rows.find((row) => normalizeCodeLookupValue(row.code_name) === normalized);
    return byName?.code || raw;
}

function shouldPreserveRawCodeValue(select) {
    return select?.dataset?.preserveRawCodeValue === 'true';
}

// Path: PROJECT_ROOT . '/public/assets/js/common/code-select.js'

const API = {
    LIST: '/api/settings/base-info/code/list',
    DETAIL: '/api/settings/base-info/code/detail',
    GROUPS: '/api/settings/base-info/code/groups',
    SAVE: '/api/settings/base-info/code/save',
    DELETE: '/api/settings/base-info/code/delete'
};

const QUICK_ADD_VALUE = '__CODE_QUICK_ADD__';
const NEW_CODE_GROUP_VALUE = '__new_code_group__';

const state = {
    options: {},
    fieldGroups: {},
    previousValues: {},
    codeGroups: [],
    quickModal: null,
    activeQuick: null,
    activeOriginal: null,
    callbacks: new Set()
};

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

export function onCodeOptionsLoaded(callback) {
    if (typeof callback === 'function') {
        state.callbacks.add(callback);
    }
}

export async function initCodeSelectControls(root = document) {
    ensureQuickModal();
    bindOriginalCodeModal();

    const scope = root || document;
    const selects = Array.from(scope.querySelectorAll('select[data-code-group]'));
    await Promise.all(selects.map((select) => createCodeSelect({
        selectId: ensureSelectId(select),
        codeGroup: select.dataset.codeGroup,
        selectedValue: select.value
    })));
}

export async function createCodeSelect({ selectId, codeGroup, selectedValue = '' }) {
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
    await refreshCodeSelect(selectId, group, selectedValue || select.value);
}

export async function refreshCodeSelect(selectId, codeGroup, selectedValue = '') {
    const select = typeof selectId === 'string' ? document.getElementById(selectId) : selectId;
    const group = normalizeCodeGroup(codeGroup || select?.dataset?.codeGroup);
    if (!select || !group) return;

    const rows = await fetchCodeOptions(group);
    const currentValue = String(selectedValue ?? select.value ?? '').trim();

    select.innerHTML = '<option value="">선택</option>';

    rows.forEach((row) => {
        const option = document.createElement('option');
        option.value = row.code;
        option.textContent = row.code_name || row.code;
        select.appendChild(option);
    });

    if (rows.length > 0) {
        const separator = document.createElement('option');
        separator.disabled = true;
        separator.textContent = '──────────';
        select.appendChild(separator);
    }

    const addOption = document.createElement('option');
    addOption.value = QUICK_ADD_VALUE;
    addOption.textContent = '+ 기준추가';
    select.appendChild(addOption);

    select.value = currentValue;
    state.previousValues[select.id] = select.value || '';
    notifyOptionsLoaded();
}

export async function openCodeQuickModal(args, legacyTargetSelectId = null) {
    ensureQuickModal();

    const params = typeof args === 'object'
        ? args
        : { codeGroup: args, targetSelectId: legacyTargetSelectId };

    const codeGroup = normalizeCodeGroup(params.codeGroup);
    const targetSelectId = params.targetSelectId || params.selectId || '';
    const select = targetSelectId ? document.getElementById(targetSelectId) : null;

    state.activeQuick = {
        codeGroup,
        targetSelectId,
        select
    };

    const modal = document.getElementById('codeQuickModal');
    modal.querySelector('[name="code_group"]').value = codeGroup;
    modal.querySelector('[name="code"]').value = '';
    modal.querySelector('[name="code_name"]').value = '';
    modal.querySelector('[name="note"]').value = '';
    modal.querySelector('[name="memo"]').value = '';
    modal.querySelector('[name="is_active"]').value = '1';
    modal.querySelector('[data-role="message"]').textContent = '';

    state.quickModal.show();
    setTimeout(() => modal.querySelector('[name="code"]')?.focus(), 150);
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
        if (select.value !== QUICK_ADD_VALUE) {
            state.previousValues[select.id] = select.value || '';
        }
    });

    select.addEventListener('change', () => {
        if (select.value === QUICK_ADD_VALUE) {
            const previousValue = state.previousValues[select.id] || '';
            select.value = previousValue;
            openCodeQuickModal({ codeGroup, targetSelectId: select.id });
            return;
        }

        state.previousValues[select.id] = select.value || '';
    });
}

async function fetchCodeOptions(codeGroup) {
    const group = normalizeCodeGroup(codeGroup);
    if (!group) return [];

    const response = await fetch(`${API.LIST}?code_group=${encodeURIComponent(group)}`, { cache: 'no-store' });
    const json = await response.json();
    const rows = Array.isArray(json) ? json : (json.data || []);

    state.options[group] = rows
        .map((row) => ({
            id: String(row.id ?? '').trim(),
            code: String(row.code ?? '').trim(),
            code_name: String(row.code_name ?? row.code ?? '').trim()
        }))
        .filter((row) => row.code);

    return state.options[group];
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
    } catch (error) {
        state.codeGroups = Object.keys(state.options).sort();
    }

    return state.codeGroups;
}

function ensureQuickModal() {
    if (document.getElementById('codeQuickModal')) {
        state.quickModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('codeQuickModal'), { focus: false });
        return;
    }

    const modal = document.createElement('div');
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
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-role="detail">세부정보</button>
                        <button type="submit" class="btn btn-success btn-sm">저장</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    state.quickModal = new bootstrap.Modal(modal, { focus: false });

    modal.querySelector('[name="code"]').addEventListener('input', function () {
        this.value = normalizeCode(this.value);
    });

    modal.querySelector('[data-role="detail"]').addEventListener('click', async () => {
        if (!state.activeQuick?.codeGroup) return;
        state.quickModal.hide();
        await openOriginalCodeModal({
            codeGroup: state.activeQuick.codeGroup,
            targetSelectId: state.activeQuick.targetSelectId
        });
    });

    modal.querySelector('form').addEventListener('submit', saveQuickCode);
}

function bindOriginalCodeModal() {
    const form = document.getElementById('codeForm');
    if (!form || form.dataset.codeSelectBound === 'true') return;

    form.dataset.codeSelectBound = 'true';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('codeModal'), { focus: false });

    form.addEventListener('submit', saveOriginalCode);
    document.getElementById('btnDeleteCode')?.addEventListener('click', deleteOriginalCode);
    document.getElementById('modal_code_code')?.addEventListener('input', function () {
        this.value = normalizeCode(this.value);
    });
    document.getElementById('modal_code_group')?.addEventListener('change', function () {
        if (this.value === NEW_CODE_GROUP_VALUE) {
            showOriginalGroupInput('');
        }
    });
    document.getElementById('modal_code_group_input')?.addEventListener('input', function () {
        this.value = normalizeCodeGroup(this.value);
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
    } finally {
        submit.disabled = false;
    }
}

async function openOriginalCodeModal({ codeGroup, targetSelectId }) {
    bindOriginalCodeModal();

    const modalEl = document.getElementById('codeModal');
    if (!modalEl) {
        window.AppCore?.notify?.('warning', '기준정보 모달을 찾을 수 없습니다.');
        return;
    }

    const select = targetSelectId ? document.getElementById(targetSelectId) : null;
    const selectedCode = select?.value || '';
    const group = normalizeCodeGroup(codeGroup);
    const rows = await fetchCodeRows(group);
    const currentRow = rows.find((row) => String(row.code || '') === selectedCode);
    const detail = currentRow?.id ? await fetchCodeDetail(currentRow.id) : null;

    state.activeOriginal = {
        codeGroup: group,
        targetSelectId,
        select,
        selectedCode
    };

    await renderOriginalGroupOptions(group);

    if (detail) {
        fillOriginalCodeForm(detail);
        document.getElementById('codeModalLabel').textContent = '기준정보 수정';
        document.getElementById('btnDeleteCode').style.display = '';
    } else {
        resetOriginalCodeForm(group);
        document.getElementById('codeModalLabel').textContent = '기준정보 등록';
        document.getElementById('btnDeleteCode').style.display = 'none';
    }

    bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).show();
}

async function renderOriginalGroupOptions(selectedGroup = '') {
    const select = document.getElementById('modal_code_group');
    if (!select) return;

    const groups = await fetchCodeGroups();
    const merged = Array.from(new Set([...groups, normalizeCodeGroup(selectedGroup)].filter(Boolean))).sort();

    select.innerHTML = '<option value="">선택</option>';
    merged.forEach((group) => {
        const option = document.createElement('option');
        option.value = group;
        option.textContent = group;
        select.appendChild(option);
    });

    const separator = document.createElement('option');
    separator.disabled = true;
    separator.textContent = '──────────';
    select.appendChild(separator);

    const newOption = document.createElement('option');
    newOption.value = NEW_CODE_GROUP_VALUE;
    newOption.textContent = '+ 신규코드생성';
    select.appendChild(newOption);

    showOriginalGroupSelect(selectedGroup);
}

function resetOriginalCodeForm(codeGroup = '') {
    const form = document.getElementById('codeForm');
    form?.reset();

    setValue('modal_code_id', '');
    setValue('modal_code_code', '');
    setValue('modal_code_code_name', '');
    setValue('modal_code_note', '');
    setValue('modal_code_memo', '');
    setValue('modal_code_extra_data', '{}');
    setValue('modal_code_is_active', '1');
    showOriginalGroupSelect(codeGroup);
}

function fillOriginalCodeForm(data = {}) {
    resetOriginalCodeForm(data.code_group || state.activeOriginal?.codeGroup || '');

    setValue('modal_code_id', data.id || '');
    setValue('modal_code_code', data.code || '');
    setValue('modal_code_code_name', data.code_name || '');
    setValue('modal_code_note', data.note || '');
    setValue('modal_code_memo', data.memo || '');
    setValue('modal_code_extra_data', data.extra_data || '{}');
    setValue('modal_code_is_active', String(data.is_active ?? '1'));
    showOriginalGroupSelect(data.code_group || state.activeOriginal?.codeGroup || '');
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
    const code = normalizeCode(formData.get('code'));
    const codeName = String(formData.get('code_name') || '').trim();
    const extraData = String(formData.get('extra_data') || '').trim();

    if (!codeGroup || !code || !codeName) {
        window.AppCore?.notify?.('warning', '코드그룹, 코드, 코드명은 필수입니다.');
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

    await refreshCodeSelect(select.id, codeGroup, selectedCode);
    select.value = selectedCode;
    state.previousValues[select.id] = selectedCode;
    select.dispatchEvent(new Event('change', { bubbles: true }));
}

async function postForm(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        body: formData
    });
    return response.json();
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
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

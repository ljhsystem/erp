import { formatNumber } from '/public/assets/js/common/format.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import { initCodeSelectControls, getCodeName } from '/public/assets/js/pages/main/settings/system/code-select.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import { writeSystemUserSettingsStorage } from '/public/assets/js/common/user-settings/systemUserSettingsStorage.js';

const ACCOUNT_COLUMN_MAP = {
    sort_no: { label: '순번', visible: true, className: 'text-center' },
    account_code: { label: '계정코드', visible: true },
    account_name: { label: '계정과목명', visible: true },
    parent_name: { label: '상위계정', visible: true },
    parent_id: { label: '상위계정ID', visible: false },
    account_group: { label: '계정구분', visible: true, className: 'text-center' },
    normal_balance: { label: '정상잔액', visible: true, className: 'text-center' },
    level: { label: '레벨', visible: true, className: 'text-center' },
    is_posting: { label: '전표입력', visible: true, className: 'text-center' },
    allow_sub_account: { label: '보조계정', visible: true, className: 'text-center' },
    is_active: { label: '상태', visible: true, className: 'text-center' },
    note: { label: '비고', visible: false },
    memo: { label: '메모', visible: false },
    created_at: { label: '생성일시', visible: false },
    created_by: { label: '생성자', visible: false, type: 'actor' },
    updated_at: { label: '수정일시', visible: false },
    updated_by: { label: '수정자', visible: false, type: 'actor' },
    deleted_at: { label: '삭제일시', visible: false },
    deleted_by: { label: '삭제자', visible: false, type: 'actor' },
    has_sub_account: { label: '보조계정등록', visible: false, className: 'text-center' },
    id: { label: 'ID', visible: false }
};
Object.assign(ACCOUNT_COLUMN_MAP, {
    account_category: { label: '계정카테고리', visible: false },
    account_level: { label: '계정레벨', visible: false, className: 'text-center' },
    is_postable: { label: '전표가능', visible: false, className: 'text-center' },
    status: { label: '상태코드', visible: false },
    full_path: { label: '전체경로', visible: false },
    path_ids: { label: '경로 ID', visible: false },
    tree_sort: { label: '트리정렬값', visible: false },
});
const ACCOUNT_COLUMN_ORDER = [
    'id',
    'sort_no',
    'account_code',
    'account_name',
    'parent_id',
    'account_group',
    'account_category',
    'normal_balance',
    'level',
    'account_level',
    'is_posting',
    'is_postable',
    'allow_sub_account',
    'status',
    'full_path',
    'path_ids',
    'tree_sort',
    'note',
    'memo',
    'is_active',
    'created_at',
    'created_by',
    'updated_at',
    'updated_by',
    'deleted_at',
    'deleted_by',
    'parent_name',
    'has_sub_account',
];
const DATE_OPTIONS = [
    { value: 'created_at', label: '생성일자' },
    { value: 'updated_at', label: '수정일자' }
];
const ACCOUNT_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.ledger.account.account-table.v1';
const ACCOUNT_SUB_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.ledger.account.sub-account-table.v1';
const ACCOUNT_USER_SETTING_PAGE_KEY = 'account-subject-main';
const ACCOUNT_SUB_USER_SETTING_PAGE_KEY = 'account-subject-sub';
const ACCOUNT_META_DOMAIN = 'account-subject-main';
const ACCOUNT_SUB_META_DOMAIN = 'account-subject-sub';
const NEW_PARENT_ACCOUNT_VALUE = '__new_parent_account__';
const SUB_ACCOUNT_CODE_GROUP = 'REF_TARGET';
const SUB_ACCOUNT_SELECT_NAME = 'ledger_sub_ref_target';
const ACCOUNT_MODAL_FIELD_POLICIES = Object.freeze([
    { selector: '#modal_account_code', key: 'account_code' },
    { selector: '#modal_account_name', key: 'account_name' },
    { selector: '#modal_parent_id', key: 'parent_id' },
    { selector: '#modal_account_group', key: 'account_group' },
    { selector: '#modal_normal_balance_debit', key: 'normal_balance', labelSelector: '.col-md-4 > label.form-label' },
    { selector: '#modal_allow_sub_account_toggle', key: 'allow_sub_account' },
    { selector: '#modal_is_posting_toggle', key: 'is_posting' },
    { selector: '#modal_is_active_toggle', key: 'is_active' },
    { selector: '#modal_note', key: 'note' },
    { selector: '#modal_memo', key: 'memo' },
]);

let accountPolicyBound = false;

function sanitizeTableSettingsState(storageKey, userSettingPageKey, normalizer = null) {
    try {
        const parsed = readDataTableSettingsState(storageKey, {
            userSettingPageKey,
        });
        if (!parsed || typeof parsed !== 'object') return;

        let changed = false;
        const nextState = { ...parsed };

        [
            'columnWidths',
            'pageLength',
            'sortSettings',
            'currentPage',
            'searchFormExpanded',
            'searchFormState',
            'requiredColumns',
            'columnWidth',
        ].forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(nextState, key)) {
                delete nextState[key];
                changed = true;
            }
        });

        if (typeof normalizer === 'function') {
            const normalizedState = normalizer(nextState);
            if (normalizedState && normalizedState !== nextState) {
                Object.assign(nextState, normalizedState);
                changed = true;
            }
        }

        if (changed) {
            writeSystemUserSettingsStorage(storageKey, nextState, {
                userSettingPageKey,
                settingType: 'TABLE',
            });
        }
    } catch (error) {
        console.warn('[account-subject] table settings sanitize failed:', error);
    }
}

function sanitizeAccountTableSettingsState() {
    sanitizeTableSettingsState(ACCOUNT_TABLE_SETTINGS_STORAGE_KEY, ACCOUNT_USER_SETTING_PAGE_KEY);
}

function sanitizeSubAccountTableSettingsState() {
    sanitizeTableSettingsState(
        ACCOUNT_SUB_TABLE_SETTINGS_STORAGE_KEY,
        ACCOUNT_SUB_USER_SETTING_PAGE_KEY,
        (state) => {
            const currentOrder = Array.isArray(state?.columnOrder) ? state.columnOrder : [];
            if (currentOrder.length === 0 || !currentOrder.includes('__actions')) {
                return null;
            }

            const nextOrder = currentOrder.filter((key) => key !== '__actions');
            nextOrder.push('__actions');

            const unchanged = nextOrder.length === currentOrder.length
                && nextOrder.every((key, index) => key === currentOrder[index]);

            if (unchanged) {
                return null;
            }

            return {
                ...state,
                columnOrder: nextOrder,
            };
        }
    );
}

function currentAccountPolicyState() {
    return readDataTableSettingsState(ACCOUNT_TABLE_SETTINGS_STORAGE_KEY, {
        userSettingPageKey: ACCOUNT_USER_SETTING_PAGE_KEY,
    }) || {};
}

function accountFieldLabel(key, fallback = '') {
    const normalizedKey = String(key || '').trim();
    return resolveDataTableColumnDisplayName(
        { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
        currentAccountPolicyState(),
        normalizedKey || fallback
    );
}

function accountFieldRequirement(key) {
    const normalizedKey = String(key || '').trim();
    return resolveDataTableColumnRequirementPolicy(
        { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
        currentAccountPolicyState()
    );
}

function accountFieldStarMarkup(key) {
    const policy = accountFieldRequirement(key);
    if (policy === 'required') {
        return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
    }
    if (policy === 'optional') {
        return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
    }
    return '';
}

function findAccountModalLabel(fieldConfig, root = document) {
    const selector = String(fieldConfig?.selector || '').trim();
    if (selector === '') return null;

    const field = root.querySelector(selector);
    if (!field) return null;

    const explicitLabelSelector = String(fieldConfig?.labelSelector || '').trim();
    if (explicitLabelSelector !== '') {
        const explicitLabel = field.closest('.col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-auto, .col-12, .mb-3')
            ?.querySelector(explicitLabelSelector)
            || root.querySelector(explicitLabelSelector);
        if (explicitLabel) return explicitLabel;
    }

    if (field.id) {
        const byFor = root.querySelector(`label[for="${field.id}"]`);
        if (byFor) return byFor;
    }

    const group = field.closest('.mb-3, .form-check, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-auto, .col-12');
    if (group) {
        const label = group.querySelector('label.form-label, label.form-check-label');
        if (label) return label;
    }

    return field.closest('label.form-label, label.form-check-label') || null;
}

function applyAccountModalPolicyLabels(root = document) {
    ACCOUNT_MODAL_FIELD_POLICIES.forEach((field) => {
        const labelEl = findAccountModalLabel(field, root);
        if (!labelEl) return;

        const displayName = accountFieldLabel(field.key, field.key);
        const starMarkup = accountFieldStarMarkup(field.key);
        labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
    });
}

function bindAccountPolicySync() {
    if (accountPolicyBound) return;
    accountPolicyBound = true;
    sanitizeAccountTableSettingsState();

    document.addEventListener('datatable-settings:updated', (event) => {
        const storageKey = String(event?.detail?.storageKey || '').trim();
        if (storageKey && storageKey !== ACCOUNT_TABLE_SETTINGS_STORAGE_KEY) {
            return;
        }

        sanitizeAccountTableSettingsState();
        applyAccountModalPolicyLabels(document);
    });
}

function focusAccountPolicyField(key, selector) {
    if (key === 'parent_id' && isParentAccountInputMode()) {
        document.getElementById('modal_new_parent_code')?.focus();
        return;
    }

    const field = document.querySelector(selector);
    if (!field) return;
    if (typeof field.focus === 'function') {
        field.focus();
    }
}

function collectAccountModalValues() {
    return {
        account_code: normalizeAccountCodeValue($('#modal_account_code').val()),
        account_name: String($('#modal_account_name').val() || '').trim(),
        parent_id: isParentAccountInputMode()
            ? normalizeAccountCodeValue($('#modal_new_parent_code').val())
            : String($('#modal_parent_id').val() || '').trim(),
        account_group: String($('#modal_account_group').val() || '').trim(),
        normal_balance: String($('input[name="normal_balance"]:checked').val() || '').trim(),
        allow_sub_account: String($('#modal_allow_sub_account').val() || '').trim(),
        is_posting: String($('#modal_is_posting').val() || '').trim(),
        is_active: String($('#modal_is_active').val() || '').trim(),
        note: String($('#modal_note').val() || '').trim(),
        memo: String($('#modal_memo').val() || '').trim(),
    };
}

function validateAccountRequiredPolicies() {
    const values = collectAccountModalValues();

    for (const field of ACCOUNT_MODAL_FIELD_POLICIES) {
        if (accountFieldRequirement(field.key) !== 'required') {
            continue;
        }

        if (String(values[field.key] ?? '').trim() !== '') {
            continue;
        }

        notify('warning', `${accountFieldLabel(field.key, field.key)} 항목을 입력해 주세요.`);
        focusAccountPolicyField(field.key, field.selector);
        return false;
    }

    return true;
}

document.addEventListener('trash:detail-render', async (event) => {
    const detail = event.detail || {};
    if (detail.type !== 'account') return;

    const detailEl = detail.modal?.querySelector('#account-trash-detail');
    const row = detail.data || {};
    if (!detailEl) return;

    detailEl.innerHTML = '<div class="text-muted py-3">상세 정보를 불러오는 중입니다.</div>';

    try {
        const query = row.account_code
            ? `code=${encodeURIComponent(row.account_code)}`
            : (row.id ? `id=${encodeURIComponent(row.id)}` : '');
        const json = query ? await fetchJson(`${API.DETAIL}?${query}`) : null;
        renderTrashDetail(detailEl, json?.success ? (json.data || row) : row);
    } catch (err) {
        console.error('[ledger-account] trash detail failed:', err);
        renderTrashDetail(detailEl, row);
    }
});


function initAccountCodeFormat() {
    [
        document.getElementById('modal_account_code'),
        document.getElementById('modal_new_parent_code')
    ].forEach((input) => {
        if (!input || input.dataset.accountCodeFormatBound === 'true') return;

        input.addEventListener('input', () => {
            input.value = formatAccountCodeInput(input.value);
        });

        input.addEventListener('blur', () => {
            input.value = formatAccountCodeDisplay(input.value);
        });

        input.dataset.accountCodeFormatBound = 'true';
    });
}


function normalizeAccountCodeValue(value) {
    return String(value ?? '').replace(/,/g, '').trim();
}

function isNumericAccountCode(value) {
    return /^\d+$/.test(normalizeAccountCodeValue(value));
}

function formatAccountCodeDisplay(value) {
    const normalized = normalizeAccountCodeValue(value);
    if (!normalized || !isNumericAccountCode(normalized)) {
        return normalized;
    }

    return formatNumber(normalized);
}

function formatAccountCodeInput(value) {
    const raw = String(value ?? '').trim();
    const withoutCommas = normalizeAccountCodeValue(raw);

    if (withoutCommas === '') {
        return '';
    }

    if (!/^\d+$/.test(withoutCommas)) {
        return withoutCommas.toUpperCase();
    }

    return formatAccountCodeDisplay(withoutCommas.slice(0, 6));
}

function getAccountLevel(row = {}) {
    const level = Number(row.account_level ?? row.level ?? 1);
    return Number.isFinite(level) && level > 0 ? level : 1;
}

function isPostableAccount(row = {}) {
    const postable = String(row.is_postable ?? '').toUpperCase();
    if (postable === 'Y') return true;
    if (postable === 'N') return false;
    return Number(row.is_posting ?? 0) === 1;
}

function getTreeClass(level) {
    if (level <= 1) return 'tree-level-1';
    if (level === 2) return 'tree-level-2';
    if (level === 3) return 'tree-level-3';
    return 'tree-level-leaf';
}

function renderPostableBadge(row = {}) {
    if (isPostableAccount(row)) {
        return '<span class="account-postable-badge is-postable"><i class="bi bi-pencil-square"></i> 전표</span>';
    }
    return '<span class="account-postable-badge is-group"><i class="bi bi-folder2"></i> 그룹</span>';
}
function renderAccountTreeCell(row = {}, value = '') {
    const level = getAccountLevel(row);
    const depth = Math.max(level - 1, 0);
    const padding = Math.min(depth * 20, 240);
    const isPostable = isPostableAccount(row);
    const branch = level > 1 ? '<span class="account-tree-branch">&#9492;</span>' : '';
    const icon = isPostable
        ? '<i class="bi bi-file-earmark-text account-tree-icon is-postable"></i>'
        : '<i class="bi bi-folder2-open account-tree-icon is-group"></i>';
    const badge = isPostable
        ? '<span class="account-tree-chip is-postable">전표입력</span>'
        : '<span class="account-tree-chip is-group">그룹계정</span>';

    return `
        <div class="account-tree-cell ${getTreeClass(level)}" style="padding-left:${padding}px" title="${escapeHtml(row.full_path || value || '')}">
            ${branch}
            ${icon}
            <span class="account-tree-title">${escapeHtml(value ?? '')}</span>
            ${badge}
        </div>
    `;
}

function renderTrashDetail(detailEl, data = {}) {
    detailEl.innerHTML = `
        <h5 class="mb-3">${escapeHtml(data.account_name ?? '')}</h5>
        <table class="table table-sm">
            <tr><th width="140">순번</th><td>${escapeHtml(data.sort_no ?? '')}</td></tr>
            <tr><th>계정코드</th><td>${escapeHtml(data.account_code ?? '')}</td></tr>
            <tr><th>계정과목명</th><td>${escapeHtml(data.account_name ?? '')}</td></tr>
            <tr><th>상위계정</th><td>${escapeHtml(data.parent_name ?? '')}</td></tr>
            <tr><th>계정구분</th><td>${escapeHtml(data.account_group ?? '')}</td></tr>
            <tr><th>정상잔액</th><td>${escapeHtml(data.normal_balance === 'credit' ? '대변' : '차변')}</td></tr>
            <tr><th>전표입력</th><td>${Number(data.is_posting ?? 0) === 1 ? '가능' : '불가'}</td></tr>
            <tr><th>보조계정</th><td>${Number(data.allow_sub_account ?? 0) === 1 ? '사용' : '미사용'}</td></tr>
            <tr><th>상태</th><td>${Number(data.is_active ?? 0) === 1 ? '사용' : '미사용'}</td></tr>
            <tr><th>비고</th><td>${escapeHtml(data.note ?? '')}</td></tr>
            <tr><th>메모</th><td>${escapeHtml(data.memo ?? '')}</td></tr>
            <tr><th>생성일시</th><td>${escapeHtml(data.created_at ?? '')}</td></tr>
            <tr><th>생성자</th><td>${escapeHtml(actorDisplay(data, 'created_by'))}</td></tr>
            <tr><th>수정일시</th><td>${escapeHtml(data.updated_at ?? '')}</td></tr>
            <tr><th>수정자</th><td>${escapeHtml(actorDisplay(data, 'updated_by'))}</td></tr>
            <tr><th>삭제일시</th><td>${escapeHtml(data.deleted_at ?? '')}</td></tr>
            <tr><th>삭제자</th><td>${escapeHtml(actorDisplay(data, 'deleted_by'))}</td></tr>
        </table>
    `;
}
function updateCount(count) {
    const el = document.getElementById('accountCount');
    if (el) el.textContent = `총 ${count ?? 0}건`;
}
function renderSubAccountCodeSelect(className, selectedValue = '', extraClass = '', rowKey = '') {
    const selectClass = [className, extraClass].filter(Boolean).join(' ');
    return `
        <select class="form-select form-select-sm ${selectClass}"
                name="${SUB_ACCOUNT_SELECT_NAME}"
                data-code-group="${SUB_ACCOUNT_CODE_GROUP}"
                data-row-key="${escapeHtml(rowKey)}"
                data-selected="${escapeHtml(selectedValue || '')}">
            <option value="" ${selectedValue ? '' : 'selected'}>선택(없음)</option>
            ${selectedValue ? `<option value="${escapeHtml(selectedValue)}" selected>${escapeHtml(getSubAccountCodeName(selectedValue, selectedValue))}</option>` : ''}
        </select>
    `;
}

function renderRequiredSelect(className, selectedValue = 0, rowKey = '') {
    const hasValue = selectedValue === 0 || selectedValue === 1 || selectedValue === '0' || selectedValue === '1';
    const value = hasValue ? Number(selectedValue) : '';
    return `
        <select class="form-select form-select-sm ${className}" data-row-key="${escapeHtml(rowKey)}">
            <option value="" ${value === '' ? 'selected' : ''}>선택하세요</option>
            <option value="1" ${value === 1 ? 'selected' : ''}>필수</option>
            <option value="0" ${value === 0 ? 'selected' : ''}>선택</option>
        </select>
    `;
}

async function initSubAccountCodeSelects(root = document) {
    const selects = Array.from(root.querySelectorAll(`select[data-code-group="${SUB_ACCOUNT_CODE_GROUP}"]`));
    await initCodeSelectControls(root);
    selects.forEach((select) => {
        const selected = select.dataset.selected || select.value || '';
        const emptyOption = select.querySelector('option[value=""]');
        if (!emptyOption) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = '선택(없음)';
            select.insertBefore(option, select.firstChild);
        } else {
            emptyOption.textContent = '선택(없음)';
        }

        if (selected) {
            select.value = selected;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            select.value = '';
        }

        if (window.jQuery && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).trigger('change.select2');
        }
    });
}
function getSubAccountCodeName(code, fallback = '') {
    const value = String(code || '').trim();
    if (!value) return '';
    return getCodeName(SUB_ACCOUNT_SELECT_NAME, value) || fallback || value;
}

function notify(type, message) {
    if (window.AppCore?.notify) {
        window.AppCore.notify(type, message);
        return;
    }

    if (type === 'error' || type === 'warning') {
        alert(message);
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

export {
    ACCOUNT_COLUMN_MAP,
    ACCOUNT_COLUMN_ORDER,
    DATE_OPTIONS,
    ACCOUNT_TABLE_SETTINGS_STORAGE_KEY,
    ACCOUNT_SUB_TABLE_SETTINGS_STORAGE_KEY,
    ACCOUNT_USER_SETTING_PAGE_KEY,
    ACCOUNT_SUB_USER_SETTING_PAGE_KEY,
    ACCOUNT_META_DOMAIN,
    ACCOUNT_SUB_META_DOMAIN,
    NEW_PARENT_ACCOUNT_VALUE,
    SUB_ACCOUNT_CODE_GROUP,
    sanitizeAccountTableSettingsState,
    sanitizeSubAccountTableSettingsState,
    currentAccountPolicyState,
    accountFieldLabel,
    accountFieldRequirement,
    accountFieldStarMarkup,
    applyAccountModalPolicyLabels,
    bindAccountPolicySync,
    focusAccountPolicyField,
    collectAccountModalValues,
    validateAccountRequiredPolicies,
    initAccountCodeFormat,
    normalizeAccountCodeValue,
    isNumericAccountCode,
    formatAccountCodeDisplay,
    formatAccountCodeInput,
    getAccountLevel,
    isPostableAccount,
    renderPostableBadge,
    renderAccountTreeCell,
    renderTrashDetail,
    updateCount,
    renderSubAccountCodeSelect,
    renderRequiredSelect,
    initSubAccountCodeSelects,
    getSubAccountCodeName,
    notify,
    escapeHtml,
};

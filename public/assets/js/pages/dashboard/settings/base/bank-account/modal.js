import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';

export function createBankAccountModalModule({
    API,
    formModule,
    formatAccountNumber,
    state,
}) {
    let accountPolicyBound = false;

    const ACCOUNT_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.dashboard.settings.base-info.bank-account.account-table.v1';
    const ACCOUNT_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#accountForm [name="account_name"]', key: 'account_name', fallback: '계좌명' },
        { selector: '#modal_bank_name', key: 'bank_name', fallback: '은행명' },
        { selector: '#accountForm [name="account_number"]', key: 'account_number', fallback: '계좌번호' },
        { selector: '#accountForm [name="account_holder"]', key: 'account_holder', fallback: '예금주' },
        { selector: '#modal_account_type', key: 'account_type', fallback: '계좌구분' },
        { selector: '#modal_account_currency', key: 'currency', fallback: '통화' },
        { selector: '#accountForm [name="is_active"]', key: 'is_active', fallback: '사용여부' },
        { selector: '#accountForm [name="note"]', key: 'note', fallback: '비고' },
        { selector: '#accountForm [name="memo"]', key: 'memo', fallback: '메모' },
        { selector: '#modal_bank_file', key: 'bank_file', fallback: '통장사본' },
    ]);

    function currentAccountPolicyState() {
        return readDataTableSettingsState(ACCOUNT_TABLE_SETTINGS_STORAGE_KEY) || {};
    }

    function accountFieldLabel(key, _fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentAccountPolicyState(),
            normalizedKey
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

    function isAccountFilePolicyKey(key) {
        return String(key || '').trim() === 'bank_file';
    }

    function isAccountFieldVisible(field) {
        if (!field) return false;
        if (field.type === 'hidden') return false;
        if (field.disabled) return false;
        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        return true;
    }

    function shouldValidateAccountPolicyField(field) {
        const selector = String(field?.selector || '').trim();
        if (!selector) return false;
        const input = document.querySelector(selector);
        return isAccountFieldVisible(input);
    }

    function hasAccountBankFileValue() {
        const input = formModule.getBankBookInputEl?.();
        const deleteFlag = formModule.getDeleteBankBookEl?.();
        const drop = document.getElementById('bankBookUpload') || document.getElementById('bankCopyUpload');
        const hasExisting = String(drop?.dataset?.original || '0') === '1';
        return (input?.files?.length || 0) > 0 || (hasExisting && String(deleteFlag?.value || '0') !== '1');
    }

    function collectAccountDetailValues(form, formData) {
        const values = {};

        ACCOUNT_MODAL_FIELD_POLICIES.forEach((field) => {
            const key = String(field?.key || '').trim();
            const selector = String(field?.selector || '').trim();
            if (!key || !selector || isAccountFilePolicyKey(key)) return;

            const input = form?.querySelector(selector) || document.querySelector(selector);
            if (!input) return;

            const fieldName = String(input.name || key).trim();
            values[key] = formData.get(fieldName) ?? input.value ?? '';
        });

        return values;
    }

    function validateAccountRequiredPolicies(fields = [], values = {}) {
        for (const field of fields) {
            const key = String(field?.key || '').trim();
            if (!key || accountFieldRequirement(key) !== 'required') {
                continue;
            }
            if (!shouldValidateAccountPolicyField(field)) {
                continue;
            }

            const label = accountFieldLabel(key, field?.fallback || key);
            if (isAccountFilePolicyKey(key)) {
                if (!hasAccountBankFileValue()) {
                    return `${label} 항목은 필수입니다.`;
                }
                continue;
            }

            const value = values[key];
            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return `${label} 항목은 필수입니다.`;
                }
                continue;
            }

            if (String(value ?? '').trim() === '') {
                return `${label} 항목은 필수입니다.`;
            }
        }

        return '';
    }

    function findAccountModalLabel(fieldSelector, root = document) {
        const field = root.querySelector(fieldSelector);
        if (!field) return null;

        if (field.id) {
            const labelByFor = root.querySelector(`label[for="${field.id}"]`);
            if (labelByFor) return labelByFor;
        }

        const column = field.closest('div[class*="col-"]');
        if (column) {
            const label = column.querySelector('label.form-label');
            if (label) return label;
        }

        return field.closest('label.form-label') || null;
    }

    function applyAccountModalPolicyLabels(root = document) {
        ACCOUNT_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findAccountModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = accountFieldLabel(field.key, field.fallback);
            const starMarkup = accountFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function bindAccountPolicySync() {
        if (accountPolicyBound) return;
        accountPolicyBound = true;

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== ACCOUNT_TABLE_SETTINGS_STORAGE_KEY) return;
            applyAccountModalPolicyLabels(document);
        });
    }

    function initModal() {
        const modalEl = document.getElementById('accountModal');
        if (!modalEl) return;

        state.accountModal = new bootstrap.Modal(modalEl, { focus: false });

        const excelEl = document.getElementById('accountExcelModal');
        if (excelEl) {
            state.excelModal = new bootstrap.Modal(excelEl);
        }

        bindAccountPolicySync();
        applyAccountModalPolicyLabels(document);

        modalEl.addEventListener('hidden.bs.modal', () => {
            const form = document.getElementById('accountForm');
            if (form) form.reset();

            const idEl = formModule.getIdEl();
            if (idEl) idEl.value = '';

            const deleteBtn = document.getElementById('btnDeleteAccount');
            if (deleteBtn) deleteBtn.style.display = 'none';

            window.isNewAccount = false;

            const titleEl = document.querySelector('#accountModal .modal-title');
            if (titleEl) {
                titleEl.textContent = '계좌 정보';
            }

            formModule.resetBankBookUI();
            applyAccountModalPolicyLabels(document);
        });

        formModule.bindDateIconPicker();

        modalEl.addEventListener('shown.bs.modal', () => {
            formModule.bindAdminDateInputs();
            applyAccountModalPolicyLabels(document);
        });
    }

    async function fetchAccountDetail(id) {
        const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.message || '계좌 상세 조회에 실패했습니다.');
        }
        return json.data;
    }

    async function openAccountEditModal(accountId) {
        if (!accountId) return;

        window.isNewAccount = false;

        const form = document.getElementById('accountForm');
        if (form) form.reset();

        const titleEl = document.querySelector('#accountModal .modal-title');
        if (titleEl) {
            titleEl.textContent = '계좌 정보 수정';
        }

        const deleteBtn = document.getElementById('btnDeleteAccount');
        if (deleteBtn) deleteBtn.style.display = '';

        const idEl = formModule.getIdEl();
        if (idEl) idEl.value = accountId;

        const delFile = formModule.getDeleteBankBookEl();
        const fileInput = formModule.getBankBookInputEl();
        if (delFile) delFile.value = '0';
        if (fileInput) fileInput.value = '';

        formModule.resetBankBookUI();
        applyAccountModalPolicyLabels(document);
        state.accountModal?.show();

        try {
            const [data] = await Promise.all([
                fetchAccountDetail(accountId),
                formModule.prepareAccountModalControls(),
            ]);

            const nextIdEl = formModule.getIdEl();
            if (nextIdEl) nextIdEl.value = data.id;
            fillModal(data);
        } catch (error) {
            console.error(error);
            formModule.notify('error', error.message || '서버 오류');
        }
    }

    function fillModal(data) {
        Object.keys(data).forEach((key) => {
            if (key === 'id' || key === 'bank_file') return;

            const byId =
                document.getElementById(`account_${key}`) ||
                document.getElementById(`modal_${key}`);

            const byName = document.querySelector(`#accountForm [name="${key}"]`);
            const el = byId || byName;
            if (!el) return;

            if (el.type === 'file') {
                el.value = '';
                return;
            }

            if (key === 'account_number') {
                el.value = data[key] ?? '';
                return;
            }

            if (el.tagName === 'SELECT') {
                setSelectValueByCodeOrText(el, data[key] ?? '');
                return;
            }

            el.value = data[key] ?? '';
        });

        const accountNumberInput = document.querySelector('#accountForm [name="account_number"][data-format="account_number"]');
        if (accountNumberInput) {
            accountNumberInput.value = formatAccountNumber(accountNumberInput.value, '');
            formModule.bindAccountBankFormatting();
        }

        formModule.renderBankBook(data);
        applyAccountModalPolicyLabels(document);
    }

    function setSelectValueByCodeOrText(select, value) {
        const raw = String(value ?? '').trim();
        const normalized = raw.toUpperCase();
        const option = Array.from(select.options || []).find((item) => {
            const optionValue = String(item.value ?? '').trim();
            const optionText = String(item.textContent ?? '').trim();
            return optionValue === raw
                || optionValue.toUpperCase() === normalized
                || optionText === raw
                || optionText.toUpperCase() === normalized;
        });

        select.value = option?.value ?? raw;
        if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).trigger('change.select2');
        }
    }

    function bindModalEvents($, getTable) {
        $(document).off('submit', '#accountForm');
        $(document).on('submit', '#accountForm', function (event) {
            event.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const requiredMessage = validateAccountRequiredPolicies(
                ACCOUNT_MODAL_FIELD_POLICIES,
                collectAccountDetailValues(form, formData)
            );
            if (requiredMessage) {
                formModule.notify('warning', requiredMessage);
                return;
            }

            const errorMessage = formModule.validateAccountForm(formData);
            if (errorMessage) {
                formModule.notify('warning', errorMessage);
                return;
            }

            formData.set('currency', String(formData.get('currency') || '').trim().toUpperCase() || 'KRW');
            formData.set('account_number', formModule.unformatAccountNumberValue(formData.get('account_number') || ''));

            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            $.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            })
                .done((res) => {
                    if (!res.success) {
                        formModule.notify('error', res.message || '저장에 실패했습니다.');
                        return;
                    }

                    state.accountModal?.hide();
                    getTable()?.ajax.reload(null, false);
                    formModule.notify('success', '저장되었습니다.');
                })
                .fail(() => {
                    formModule.notify('error', '서버 오류');
                })
                .always(() => {
                    if (btn) btn.disabled = false;
                });
        });

        $('#btnDeleteAccount').off('click').on('click', () => {
            const id = formModule.getIdEl()?.value || '';
            if (!id || !confirm('삭제하시겠습니까?')) return;

            $.post(API.DELETE, { id })
                .done((res) => {
                    if (res.success) {
                        formModule.notify('success', '삭제했습니다.');
                        getTable()?.ajax.reload(null, false);
                        state.accountModal?.hide();
                    } else {
                        formModule.notify('error', res.message || '삭제에 실패했습니다.');
                    }
                })
                .fail(() => {
                    formModule.notify('error', '서버 오류');
                });
        });
    }

    return {
        initModal,
        fetchAccountDetail,
        openAccountEditModal,
        bindModalEvents,
    };
}

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { checkBusinessStatus } from '/public/assets/js/common/biz_api.js';
import {
    formatBizNumber,
    formatCorpNumber,
    formatDateDisplay,
    formatAmount,
    formatMobile,
    unformatAmount,
    formatPhone,
    onlyNumber,
} from '/public/assets/js/common/format.js';

const QUICK_CREATE_MODAL_ID = 'journalQuickCreateModal';
let sharedTodayPicker = null;

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    return response.json();
}

function waitForModalHidden(modalEl, modal) {
    return new Promise((resolve) => {
        const handleHidden = () => {
            modalEl.removeEventListener('hidden.bs.modal', handleHidden);
            resolve();
        };

        modalEl.addEventListener('hidden.bs.modal', handleHidden, { once: true });
        modal.hide();
    });
}

function formatPickerDate(date) {
    if (!date) {
        return '';
    }

    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function ensureSharedTodayPicker() {
    if (sharedTodayPicker) {
        return sharedTodayPicker;
    }

    const container = document.getElementById('journal-today-picker')
        || document.getElementById('today-picker');
    if (!container) {
        return null;
    }

    sharedTodayPicker = AdminPicker.create({
        type: 'today',
        container,
    });

    sharedTodayPicker.subscribe((_, date) => {
        const input = sharedTodayPicker.__target;
        if (!input || !date) {
            return;
        }

        input.value = formatPickerDate(date);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        sharedTodayPicker.close();
    });

    return sharedTodayPicker;
}

function openSharedDatePicker(input) {
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const picker = ensureSharedTodayPicker();
    if (!picker) {
        return;
    }

    picker.__target = input;

    if (typeof picker.clearDate === 'function') {
        picker.clearDate();
    }

    const currentValue = String(input.value || '').trim();
    if (currentValue) {
        const currentDate = new Date(currentValue.replaceAll('.', '-'));
        if (!Number.isNaN(currentDate.getTime()) && typeof picker.setDate === 'function') {
            picker.setDate(currentDate);
        }
    }

    picker.open({ anchor: input });
}

function bindModalDateInputs(modalEl) {
    modalEl.querySelectorAll('.admin-date').forEach((input) => {
        if (input.dataset.dateInputFormatBound === 'true') {
            return;
        }

        input.dataset.dateInputFormatBound = 'true';
        input.addEventListener('input', () => {
            input.value = formatDateInputValue(input.value);
        });
    });

    modalEl.querySelectorAll('.date-icon').forEach((icon) => {
        if (icon.dataset.dateIconBound === 'true') {
            return;
        }

        icon.dataset.dateIconBound = 'true';
        icon.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const wrapper = icon.closest('.date-input, .date-input-wrap');
            const input = wrapper?.querySelector('input');
            openSharedDatePicker(input);
        });
    });
}

function formatDateInputValue(value) {
    const digits = onlyNumber(value).slice(0, 8);

    if (digits.length <= 4) {
        return digits;
    }

    if (digits.length <= 6) {
        return `${digits.slice(0, 4)}-${digits.slice(4)}`;
    }

    return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
}

function setSelect2SingleValue(selectEl, value, text = '') {
    if (!(selectEl instanceof HTMLSelectElement)) {
        return;
    }

    const normalizedValue = String(value ?? '').trim();
    if (normalizedValue === '') {
        if (window.jQuery) {
            window.jQuery(selectEl).val(null).trigger('change');
        } else {
            selectEl.value = '';
        }
        return;
    }

    let option = Array.from(selectEl.options).find((item) => item.value === normalizedValue);
    if (!option) {
        option = new Option(text || normalizedValue, normalizedValue, true, true);
        selectEl.add(option);
    } else {
        option.selected = true;
        if (text) {
            option.text = text;
        }
    }

    if (window.jQuery) {
        window.jQuery(selectEl).val(normalizedValue).trigger('change');
    } else {
        selectEl.value = normalizedValue;
    }
}

function destroyScopedSelect2(selectEl) {
    if (!selectEl || !window.jQuery) {
        return;
    }

    const $select = window.jQuery(selectEl);
    if ($select.hasClass('select2-hidden-accessible')) {
        $select.off('.journalProject');
        $select.select2('destroy');
    }
}

function initProjectModalSelect2(modalEl) {
    if (!window.jQuery) {
        return;
    }

    const employeeEl = modalEl.querySelector('#modal_employee_id');
    if (employeeEl && employeeEl.dataset.projectSelect2Bound !== 'true') {
        AdminPicker.select2Ajax(employeeEl, {
            url: '/api/settings/organization/employee/search-picker',
            placeholder: '담당직원 검색',
            minimumInputLength: 0,
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                };
            },
            processResults(json) {
                const rows = json?.results ?? json?.data ?? [];

                return {
                    results: rows
                        .map((row) => ({
                            id: String(row.id ?? ''),
                            text: row.text ?? row.employee_name ?? row.username ?? row.id,
                            raw: row,
                        }))
                        .filter((item) => item.id !== ''),
                };
            },
        });

        employeeEl.dataset.projectSelect2Bound = 'true';
    }

    const clientEl = modalEl.querySelector('#modal_client_id');
    if (clientEl && clientEl.dataset.projectSelect2Bound !== 'true') {
        AdminPicker.select2Ajax(clientEl, {
            url: '/api/settings/base-info/client/search-picker',
            placeholder: '거래처 검색',
            minimumInputLength: 0,
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                };
            },
            processResults(json) {
                const rows = json?.results ?? json?.data ?? [];

                return {
                    results: rows
                        .map((row) => ({
                            id: String(row.id ?? ''),
                            text: row.text ?? row.client_name ?? row.name ?? row.id,
                            raw: row,
                        }))
                        .filter((item) => item.id !== ''),
                };
            },
        });

        window.jQuery(clientEl).off('select2:select.journalProject');
        window.jQuery(clientEl).on('select2:select.journalProject', function (event) {
            const item = event.params?.data;
            if (!item) {
                return;
            }

            window.jQuery(this).val(String(item.id)).trigger('change');
        });

        clientEl.dataset.projectSelect2Bound = 'true';
    }
}

function resetProjectModalSelect2(modalEl) {
    const employeeEl = modalEl.querySelector('#modal_employee_id');
    const clientEl = modalEl.querySelector('#modal_client_id');

    destroyScopedSelect2(employeeEl);
    destroyScopedSelect2(clientEl);

    if (employeeEl) {
        employeeEl.innerHTML = '<option value=""></option>';
        delete employeeEl.dataset.projectSelect2Bound;
    }

    if (clientEl) {
        clientEl.innerHTML = '<option value=""></option>';
        delete clientEl.dataset.projectSelect2Bound;
    }
}

function validateProjectFormData(formData, notify) {
    const projectName = String(formData.get('project_name') || '').trim();
    const contractDate = String(formData.get('contract_date') || '').trim();
    const startDate = String(formData.get('start_date') || '').trim();
    const completionDate = String(formData.get('completion_date') || '').trim();
    const amount = String(formData.get('initial_contract_amount') || '').trim();

    if (!projectName) {
        notify('warning', '프로젝트명은 필수입니다.');
        return false;
    }

    const datePattern = /^\d{4}-\d{2}-\d{2}$/;
    if (contractDate && !datePattern.test(contractDate)) {
        notify('warning', '계약일자는 YYYY-MM-DD 형식이어야 합니다.');
        return false;
    }

    if (startDate && !datePattern.test(startDate)) {
        notify('warning', '착공일자는 YYYY-MM-DD 형식이어야 합니다.');
        return false;
    }

    if (completionDate && !datePattern.test(completionDate)) {
        notify('warning', '준공일자는 YYYY-MM-DD 형식이어야 합니다.');
        return false;
    }

    if (startDate && completionDate && startDate > completionDate) {
        notify('warning', '준공일자는 착공일자보다 빠를 수 없습니다.');
        return false;
    }

    if (amount && !/^-?\d+$/.test(amount)) {
        notify('warning', '최초 계약금액은 숫자만 입력할 수 있습니다.');
        return false;
    }

    return true;
}

function setFieldValue(field, value) {
    if (!field || field.type === 'file') {
        return;
    }

    if (field.type === 'checkbox') {
        field.checked = Number(value) === 1 || value === true || value === '1';
        return;
    }

    field.value = value ?? '';
}

function fillFormFields(form, data, skipNames = []) {
    const skip = new Set(skipNames);

    Array.from(form.elements).forEach((field) => {
        const name = field.name;
        if (!name || skip.has(name)) {
            return;
        }

        if (!Object.prototype.hasOwnProperty.call(data, name)) {
            return;
        }

        setFieldValue(field, data[name]);
    });
}

function applyFormatByType(input) {
    if (!input) {
        return;
    }

    const formatType = String(input.dataset.format || '').trim();
    if (!formatType) {
        return;
    }

    if (formatType === 'biz') {
        input.value = formatBizNumber(input.value);
        return;
    }

    if (formatType === 'corp') {
        const rawValue = onlyNumber(input.value);
        input.dataset.real = rawValue;
        input.value = formatCorpNumber(rawValue);
        return;
    }

    if (formatType === 'mobile') {
        input.value = formatMobile(input.value);
        return;
    }

    if (formatType === 'phone' || formatType === 'fax') {
        input.value = formatPhone(input.value);
        return;
    }

    if (formatType === 'amount') {
        input.value = formatAmount(input.value);
    }
}

function bindScopedFormatters(root) {
    if (root.dataset.formattersBound === 'true') {
        root.querySelectorAll('input[data-format]').forEach((input) => {
            applyFormatByType(input);
        });
        return;
    }

    root.addEventListener('input', (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        applyFormatByType(input);
    });

    root.querySelectorAll('input[data-format]').forEach((input) => {
        applyFormatByType(input);
    });

    root.dataset.formattersBound = 'true';
}

function maskRrn(value) {
    const rawValue = onlyNumber(value);
    if (!rawValue) {
        return '';
    }

    const formatted = formatCorpNumber(rawValue);
    const [front, back = ''] = formatted.split('-');
    if (!back) {
        return front;
    }

    return `${front}-${'*'.repeat(back.length)}`;
}

function ensureQuickCreateModal() {
    let modalEl = document.getElementById(QUICK_CREATE_MODAL_ID);
    if (modalEl) {
        return modalEl;
    }

    modalEl = document.createElement('div');
    modalEl.className = 'modal fade';
    modalEl.id = QUICK_CREATE_MODAL_ID;
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form data-role="quick-create-form">
                    <div class="modal-header">
                        <h5 class="modal-title" data-role="quick-create-title">빠른 등록</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                    </div>
                    <div class="modal-body" data-role="quick-create-body"></div>
                    <div class="modal-footer">
                        <div class="me-auto small text-danger" data-role="quick-create-message"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                        <button type="button" class="btn btn-outline-primary btn-sm btn-detail-open" data-role="quick-create-detail" data-target="">상세입력</button>
                        <button type="submit" class="btn btn-success btn-sm" data-role="quick-create-submit">저장</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.body.appendChild(modalEl);
    return modalEl;
}

function renderQuickField(field, values = {}) {
    const value = values[field.name] ?? field.value ?? '';
    const required = field.required ? ' required' : '';
    const placeholder = field.placeholder ? ` placeholder="${escapeHtml(field.placeholder)}"` : '';

    if (field.type === 'select') {
        const options = (field.options || [])
            .map((option) => {
                const selected = String(option.value) === String(value) ? ' selected' : '';
                return `<option value="${escapeHtml(option.value)}"${selected}>${escapeHtml(option.label)}</option>`;
            })
            .join('');

        return `
            <div class="mb-3">
                <label class="form-label">${escapeHtml(field.label)}</label>
                <select class="form-select form-select-sm" name="${escapeHtml(field.name)}"${required}>
                    ${options}
                </select>
            </div>
        `;
    }

    const formatAttr = field.format ? ` data-format="${escapeHtml(field.format)}"` : '';

    return `
        <div class="mb-3">
            <label class="form-label">${escapeHtml(field.label)}</label>
            <input type="text"
                   class="form-control form-control-sm"
                   name="${escapeHtml(field.name)}"
                   value="${escapeHtml(value)}"${placeholder}${required}${formatAttr}>
        </div>
    `;
}

function ensureQuickCreateConfigs() {
    return {
        client: {
            quickTitle: '거래처 빠른 등록',
            templateId: 'journal-client-modal-template',
            detailModalId: 'clientModal',
            deleteButtonSelector: '#btnDeleteClient',
            saveUrl: '/api/settings/base-info/client/save',
            detailUrl(value) {
                return `/api/settings/base-info/client/detail?id=${encodeURIComponent(value)}`;
            },
            deleteUrl: '/api/settings/base-info/client/delete',
            quickFields: [
                { name: 'client_name', label: '거래처명', required: true, placeholder: '거래처명을 입력하세요' },
                { name: 'ceo_name', label: '대표자명', placeholder: '대표자명을 입력하세요' },
                { name: 'phone', label: '전화번호', placeholder: '전화번호를 입력하세요', format: 'phone' },
            ],
            extendQuickFormData(formData) {
                formData.set('mode', 'CREATE');
                formData.set('is_active', '1');
            },
            resolveSelection(json, values) {
                return {
                    value: String(json?.id ?? ''),
                    text: String(values.client_name || ''),
                };
            },
            fillDetailForm(modalEl, data) {
                const form = modalEl.querySelector('form');
                if (!form) {
                    return;
                }

                fillFormFields(form, data);

                const rrnInput = form.querySelector('[name="rrn"]');
                if (rrnInput) {
                    const rawValue = onlyNumber(data.rrn || '');
                    rrnInput.dataset.real = rawValue;
                    rrnInput.value = rawValue ? maskRrn(rawValue) : '';
                }

                ['delete_business_certificate', 'delete_rrn_image', 'delete_bank_file'].forEach((name) => {
                    const field = form.querySelector(`[name="${name}"]`);
                    if (field) {
                        field.value = '0';
                    }
                });

                const deleteButton = modalEl.querySelector('#btnDeleteClient');
                if (deleteButton) {
                    deleteButton.style.display = data.id ? 'inline-block' : 'none';
                }
            },
            bindDetailModal(modalEl, context, notify) {
                const form = modalEl.querySelector('#client-edit-form');
                const deleteButton = modalEl.querySelector('#btnDeleteClient');
                const rrnInput = form?.querySelector('[name="rrn"]');

                bindScopedFormatters(modalEl);
                if (window.KakaoAddress?.bind && !window.KakaoAddress.__journalBound) {
                    window.KakaoAddress.bind();
                    window.KakaoAddress.__journalBound = true;
                }

                const fileBindings = [
                    {
                        trigger: '#dropZoneBiz',
                        input: '#modal_business_certificate',
                        text: '#dropZoneTextBiz',
                        deleteFlag: '#delete_business_certificate',
                    },
                    {
                        trigger: '#dropZoneRrn',
                        input: '#modal_rrn_image',
                        text: '#dropZoneTextRrn',
                        deleteFlag: '#delete_rrn_image',
                    },
                    {
                        trigger: '#bankCopyUpload',
                        input: '#modal_bank_file',
                        text: '#bankCopyText',
                        deleteFlag: '#delete_bank_file',
                    },
                ];

                fileBindings.forEach((binding) => {
                    const triggerEl = modalEl.querySelector(binding.trigger);
                    const inputEl = modalEl.querySelector(binding.input);
                    const textEl = modalEl.querySelector(binding.text);
                    const deleteFlagEl = modalEl.querySelector(binding.deleteFlag);

                    if (!triggerEl || !inputEl) {
                        return;
                    }

                    triggerEl.addEventListener('click', () => {
                        inputEl.click();
                    });

                    inputEl.addEventListener('change', () => {
                        const fileName = inputEl.files?.[0]?.name || '';

                        if (textEl && fileName) {
                            textEl.textContent = fileName;
                        }

                        if (deleteFlagEl && fileName) {
                            deleteFlagEl.value = '0';
                        }
                    });
                });

                const bizStatusButton = modalEl.querySelector('#btnCheckBizStatus');
                bizStatusButton?.addEventListener('click', async () => {
                    const businessInput = form?.querySelector('[name="business_number"]');
                    const businessNumber = onlyNumber(businessInput?.value || '');

                    if (!businessNumber) {
                        notify('warning', '사업자등록번호를 먼저 입력해 주세요.');
                        return;
                    }

                    try {
                        const json = await checkBusinessStatus(businessNumber);
                        notify('info', json?.message || json?.status || '사업자 상태를 확인했습니다.');
                    } catch (error) {
                        console.error('[journal-basic-info] biz status check failed', error);
                        notify('error', '사업자 상태 조회에 실패했습니다.');
                    }
                });

                modalEl.querySelectorAll('.toggle-rrn').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (!rrnInput) {
                            return;
                        }

                        const rawValue = onlyNumber(rrnInput.dataset.real || rrnInput.value || '');
                        const showRaw = rrnInput.dataset.visible === '1';

                        rrnInput.dataset.visible = showRaw ? '0' : '1';
                        rrnInput.value = showRaw ? maskRrn(rawValue) : formatCorpNumber(rawValue);
                    });
                });

                form?.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const formData = new FormData(form);
                    formData.set('mode', 'CREATE');

                    const businessNumber = form.querySelector('[name="business_number"]');
                    if (businessNumber) {
                        formData.set('business_number', onlyNumber(businessNumber.value));
                    }

                    if (rrnInput) {
                        formData.set('rrn', onlyNumber(rrnInput.dataset.real || rrnInput.value));
                    }

                    const saveButton = form.querySelector('button[type="submit"]');
                    if (saveButton) {
                        saveButton.disabled = true;
                    }

                    try {
                        const json = await fetchJson('/api/settings/base-info/client/save', {
                            method: 'POST',
                            body: formData,
                        });

                        if (!json.success) {
                            notify('error', json.message || '거래처 저장에 실패했습니다.');
                            return;
                        }

                        const idField = form.querySelector('[name="id"]');
                        if (idField && json.id) {
                            idField.value = json.id;
                        }

                        await context.onSaved?.({
                            type: 'client',
                            value: String(json.id ?? idField?.value ?? ''),
                            text: String(form.querySelector('[name="client_name"]')?.value || ''),
                            sourceEl: context.sourceEl,
                            json,
                            values: Object.fromEntries(formData.entries()),
                        });

                        notify('success', '거래처 저장이 완료되었습니다.');
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    } catch (error) {
                        console.error('[journal-basic-info] client detail save failed', error);
                        notify('error', '거래처 저장 중 오류가 발생했습니다.');
                    } finally {
                        if (saveButton) {
                            saveButton.disabled = false;
                        }
                    }
                });

                deleteButton?.addEventListener('click', async () => {
                    const idField = form?.querySelector('[name="id"]');
                    const id = String(idField?.value || '').trim();

                    if (!id) {
                        return;
                    }

                    if (!window.confirm('거래처를 삭제하시겠습니까?')) {
                        return;
                    }

                    try {
                        const formData = new FormData();
                        formData.set('id', id);

                        const json = await fetchJson('/api/settings/base-info/client/delete', {
                            method: 'POST',
                            body: formData,
                        });

                        if (!json.success) {
                            notify('error', json.message || '거래처 삭제에 실패했습니다.');
                            return;
                        }

                        await context.onDeleted?.({
                            type: 'client',
                            value: id,
                            sourceEl: context.sourceEl,
                        });

                        notify('success', '거래처가 삭제되었습니다.');
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    } catch (error) {
                        console.error('[journal-basic-info] client detail delete failed', error);
                        notify('error', '거래처 삭제 중 오류가 발생했습니다.');
                    }
                });
            },
        },
        account: {
            quickTitle: '계정과목 빠른 등록',
            templateId: 'journal-account-modal-template',
            detailModalId: 'accountModal',
            deleteButtonSelector: '#btnDeleteAccount',
            saveUrl: '/api/ledger/account/save',
            detailUrl(value) {
                return `/api/ledger/account/detail?code=${encodeURIComponent(value)}`;
            },
            deleteUrl: '/api/ledger/account/soft-delete',
            quickFields: [
                { name: 'account_code', label: '계정코드', required: true, placeholder: '계정코드를 입력하세요' },
                { name: 'account_name', label: '계정과목명', required: true, placeholder: '계정과목명을 입력하세요' },
                {
                    name: 'account_group',
                    label: '계정구분',
                    type: 'select',
                    required: true,
                    options: [
                        { value: '', label: '선택' },
                        { value: '자산', label: '자산' },
                        { value: '부채', label: '부채' },
                        { value: '자본', label: '자본' },
                        { value: '수익', label: '수익' },
                        { value: '비용', label: '비용' },
                    ],
                },
            ],
            extendQuickFormData(formData) {
                formData.set('mode', 'CREATE');
                formData.set('normal_balance', 'debit');
                formData.set('is_posting', '1');
                formData.set('is_active', '1');
                formData.set('sub_policies', '[]');
            },
            resolveSelection(_json, values) {
                const accountCode = String(values.account_code || '').trim();
                const accountName = String(values.account_name || '').trim();

                return {
                    value: accountCode,
                    text: accountCode && accountName ? `${accountCode} - ${accountName}` : accountCode,
                };
            },
            fillDetailForm(modalEl, data) {
                const form = modalEl.querySelector('form');
                if (!form) {
                    return;
                }

                fillFormFields(form, data, ['sub_policies']);

                const policyState = modalEl.__policyState;
                if (policyState) {
                    policyState.items = Array.isArray(data.sub_policies)
                        ? data.sub_policies.map((policy) => ({
                            sub_account_type: String(policy.sub_account_type ?? 'partner'),
                            is_required: Number(policy.is_required ?? 0),
                            is_multiple: Number(policy.is_multiple ?? 0),
                            custom_group_code: String(policy.custom_group_code ?? ''),
                        }))
                        : [];
                    policyState.render();
                }

                const allowField = form.querySelector('[name="allow_sub_account"]');
                if (allowField) {
                    allowField.value = String(data.allow_sub_account_computed ?? data.allow_sub_account ?? 0);
                }
            },
            bindDetailModal(modalEl, context, notify) {
                const form = modalEl.querySelector('#account-edit-form');
                const deleteButton = modalEl.querySelector('#btnDeleteAccount');
                const subPolicyBody = modalEl.querySelector('#sub-policy-tbody');
                const addPolicyButton = modalEl.querySelector('#btnAddSubPolicy');
                const allowField = modalEl.querySelector('#modal_allow_sub_account');
                const allowLabel = modalEl.querySelector('#modal_allow_sub_account_label');
                const parentIdField = modalEl.querySelector('#modal_parent_id');
                const parentNameField = modalEl.querySelector('#modal_parent_name');
                const selectParentButton = modalEl.querySelector('#btnSelectParent');
                const clearParentButton = modalEl.querySelector('#btnClearParent');

                const pickerLayer = document.createElement('div');
                pickerLayer.className = 'is-hidden';
                document.body.appendChild(pickerLayer);

                const parentPicker = AdminPicker.create({
                    type: 'account',
                    container: pickerLayer,
                });

                const policyState = {
                    items: [],
                    render() {
                        if (!subPolicyBody) {
                            return;
                        }

                        if (!policyState.items.length) {
                            subPolicyBody.innerHTML = `
                                <tr class="sub-policy-empty">
                                    <td colspan="5" class="text-center text-muted">등록된 보조정책이 없습니다.</td>
                                </tr>
                            `;
                            updateAllowSubAccountDisplay();
                            return;
                        }

                        subPolicyBody.innerHTML = policyState.items.map((policy, index) => {
                            const isCustom = policy.sub_account_type === 'custom';

                            return `
                                <tr>
                                    <td>
                                        <select class="form-select form-select-sm policy-type-select" data-index="${index}">
                                            <option value="partner"${policy.sub_account_type === 'partner' ? ' selected' : ''}>partner</option>
                                            <option value="project"${policy.sub_account_type === 'project' ? ' selected' : ''}>project</option>
                                            <option value="custom"${policy.sub_account_type === 'custom' ? ' selected' : ''}>custom</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input policy-required-check" data-index="${index}"${Number(policy.is_required) ? ' checked' : ''}>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input policy-multiple-check" data-index="${index}"${Number(policy.is_multiple) ? ' checked' : ''}>
                                    </td>
                                    <td>
                                        <input type="text"
                                               class="form-control form-control-sm policy-custom-group-input"
                                               data-index="${index}"
                                               value="${escapeHtml(policy.custom_group_code || '')}"
                                               placeholder="custom 타입에서만 사용"
                                               ${isCustom ? '' : 'disabled'}>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-policy" data-index="${index}">삭제</button>
                                    </td>
                                </tr>
                            `;
                        }).join('');

                        updateAllowSubAccountDisplay();
                    },
                };

                modalEl.__policyState = policyState;

                function updateAllowSubAccountDisplay() {
                    const hasPolicy = policyState.items.length > 0;
                    const value = hasPolicy ? '1' : String(allowField?.value || '0');
                    const label = hasPolicy ? '정책 사용' : (value === '1' ? '사용' : '미사용');

                    if (allowField) {
                        allowField.value = value;
                    }

                    if (allowLabel) {
                        allowLabel.value = label;
                    }
                }

                function serializePolicies() {
                    return policyState.items
                        .map((policy) => ({
                            sub_account_type: String(policy.sub_account_type || '').trim(),
                            is_required: Number(policy.is_required) ? 1 : 0,
                            is_multiple: Number(policy.is_multiple) ? 1 : 0,
                            custom_group_code: String(policy.custom_group_code || '').trim(),
                        }))
                        .filter((policy) => policy.sub_account_type !== '');
                }

                parentPicker.subscribe((eventName, row) => {
                    if (eventName !== 'select' || !row) {
                        return;
                    }

                    if (parentIdField) {
                        parentIdField.value = row.id ?? '';
                    }

                    if (parentNameField) {
                        parentNameField.value = row.account_name
                            ? `${row.account_code} - ${row.account_name}`
                            : (row.account_code ?? '');
                    }

                    parentPicker.close();
                });

                selectParentButton?.addEventListener('click', (event) => {
                    event.preventDefault();
                    parentPicker.open({ anchor: selectParentButton });
                });

                clearParentButton?.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (parentIdField) {
                        parentIdField.value = '';
                    }
                    if (parentNameField) {
                        parentNameField.value = '';
                    }
                });

                addPolicyButton?.addEventListener('click', () => {
                    policyState.items.push({
                        sub_account_type: 'partner',
                        is_required: 0,
                        is_multiple: 0,
                        custom_group_code: '',
                    });
                    policyState.render();
                });

                modalEl.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('.btn-remove-policy');
                    if (!removeButton) {
                        return;
                    }

                    const index = Number(removeButton.dataset.index);
                    if (!Number.isInteger(index)) {
                        return;
                    }

                    policyState.items.splice(index, 1);
                    policyState.render();
                });

                modalEl.addEventListener('change', (event) => {
                    const index = Number(event.target.dataset.index);
                    if (!Number.isInteger(index) || !policyState.items[index]) {
                        return;
                    }

                    if (event.target.classList.contains('policy-type-select')) {
                        policyState.items[index].sub_account_type = event.target.value;
                        if (event.target.value !== 'custom') {
                            policyState.items[index].custom_group_code = '';
                        }
                        policyState.render();
                        return;
                    }

                    if (event.target.classList.contains('policy-required-check')) {
                        policyState.items[index].is_required = event.target.checked ? 1 : 0;
                        return;
                    }

                    if (event.target.classList.contains('policy-multiple-check')) {
                        policyState.items[index].is_multiple = event.target.checked ? 1 : 0;
                    }
                });

                modalEl.addEventListener('input', (event) => {
                    const input = event.target;
                    if (!input.classList.contains('policy-custom-group-input')) {
                        return;
                    }

                    const index = Number(input.dataset.index);
                    if (!Number.isInteger(index) || !policyState.items[index]) {
                        return;
                    }

                    policyState.items[index].custom_group_code = input.value.trim();
                });

                form?.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const formData = new FormData(form);
                    formData.set('mode', 'CREATE');
                    formData.set('sub_policies', JSON.stringify(serializePolicies()));
                    formData.set('allow_sub_account', policyState.items.length > 0 ? '1' : String(allowField?.value || '0'));

                    const saveButton = form.querySelector('button[type="submit"]');
                    if (saveButton) {
                        saveButton.disabled = true;
                    }

                    try {
                        const json = await fetchJson('/api/ledger/account/save', {
                            method: 'POST',
                            body: formData,
                        });

                        if (!json.success) {
                            notify('error', json.message || '계정과목 저장에 실패했습니다.');
                            return;
                        }

                        const idField = form.querySelector('[name="id"]');
                        if (idField && json.id) {
                            idField.value = json.id;
                        }

                        const accountCode = String(form.querySelector('[name="account_code"]')?.value || '').trim();
                        const accountName = String(form.querySelector('[name="account_name"]')?.value || '').trim();

                        await context.onSaved?.({
                            type: 'account',
                            value: accountCode,
                            text: accountCode && accountName ? `${accountCode} - ${accountName}` : accountCode,
                            sourceEl: context.sourceEl,
                            json,
                            values: Object.fromEntries(formData.entries()),
                        });

                        notify('success', '계정과목 저장이 완료되었습니다.');
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    } catch (error) {
                        console.error('[journal-basic-info] account detail save failed', error);
                        notify('error', '계정과목 저장 중 오류가 발생했습니다.');
                    } finally {
                        if (saveButton) {
                            saveButton.disabled = false;
                        }
                    }
                });

                deleteButton?.addEventListener('click', async () => {
                    const id = String(form?.querySelector('[name="id"]')?.value || '').trim();
                    const accountCode = String(form?.querySelector('[name="account_code"]')?.value || '').trim();

                    if (!id) {
                        return;
                    }

                    if (!window.confirm('계정과목을 삭제하시겠습니까?')) {
                        return;
                    }

                    try {
                        const formData = new FormData();
                        formData.set('id', id);

                        const json = await fetchJson('/api/ledger/account/soft-delete', {
                            method: 'POST',
                            body: formData,
                        });

                        if (!json.success) {
                            notify('error', json.message || '계정과목 삭제에 실패했습니다.');
                            return;
                        }

                        await context.onDeleted?.({
                            type: 'account',
                            value: accountCode,
                            sourceEl: context.sourceEl,
                        });

                        notify('success', '계정과목이 삭제되었습니다.');
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    } catch (error) {
                        console.error('[journal-basic-info] account detail delete failed', error);
                        notify('error', '계정과목 삭제 중 오류가 발생했습니다.');
                    }
                });

                modalEl.addEventListener('hidden.bs.modal', () => {
                    parentPicker.close?.();
                    pickerLayer.remove();
                }, { once: true });
            },
        },
        project: {
            quickTitle: '프로젝트 빠른 등록',
            templateId: 'journal-project-modal-template',
            detailModalId: 'projectModal',
            deleteButtonSelector: '#btnDeleteProject',
            saveUrl: '/api/settings/base-info/project/save',
            detailUrl(value) {
                return `/api/settings/base-info/project/detail?id=${encodeURIComponent(value)}`;
            },
            deleteUrl: '/api/settings/base-info/project/delete',
            quickFields: [
                { name: 'project_name', label: '프로젝트명', required: true, placeholder: '프로젝트명을 입력해 주세요.' },
                { name: 'construction_name', label: '공사명', placeholder: '공사명을 입력해 주세요.' },
            ],
            extendQuickFormData(formData) {
                formData.set('is_active', '1');
            },
            resolveSelection(json, values) {
                return {
                    value: String(json?.id ?? json?.data?.id ?? ''),
                    text: String(values.project_name || ''),
                };
            },
            fillDetailForm(modalEl, data) {
                const form = modalEl.querySelector('form');
                if (!form) {
                    return;
                }

                fillFormFields(form, data);

                [
                    'permit_date',
                    'contract_date',
                    'start_date',
                    'completion_date',
                    'bid_notice_date',
                ].forEach((name) => {
                    const field = form.querySelector(`[name="${name}"]`);
                    if (field) {
                        field.value = formatDateDisplay(data[name]);
                    }
                });

                const amountInput = modalEl.querySelector('#modal_initial_contract_amount');
                if (amountInput) {
                    amountInput.value = formatAmount(data.initial_contract_amount ?? '');
                }

                const employeeText = data.employee_name ?? data.employee_id ?? '';
                const clientText = data.linked_client_name ?? data.client_name ?? data.client_id ?? '';
                setSelect2SingleValue(modalEl.querySelector('#modal_employee_id'), data.employee_id, employeeText);
                setSelect2SingleValue(modalEl.querySelector('#modal_client_id'), data.client_id, clientText);

                const deleteButton = modalEl.querySelector('#btnDeleteProject');
                if (deleteButton) {
                    deleteButton.style.display = data.id ? 'inline-block' : 'none';
                }
            },
            bindDetailModal(modalEl, context, notify) {
                const form = modalEl.querySelector('#project-edit-form');
                const deleteButton = modalEl.querySelector('#btnDeleteProject');
                const amountInput = modalEl.querySelector('#modal_initial_contract_amount');

                bindScopedFormatters(modalEl);
                bindModalDateInputs(modalEl);
                initProjectModalSelect2(modalEl);

                if (window.KakaoAddress?.bind && !window.KakaoAddress.__journalBound) {
                    window.KakaoAddress.bind();
                    window.KakaoAddress.__journalBound = true;
                }

                form?.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const formData = new FormData(form);
                    if (amountInput) {
                        formData.set('initial_contract_amount', unformatAmount(amountInput.value));
                    }

                    if (!validateProjectFormData(formData, notify)) {
                        return;
                    }

                    const saveButton = form.querySelector('button[type="submit"]');
                    if (saveButton) {
                        saveButton.disabled = true;
                    }

                    try {
                        const json = await fetchJson('/api/settings/base-info/project/save', {
                            method: 'POST',
                            body: formData,
                        });

                        if (!json.success) {
                            notify('error', json.message || '프로젝트 저장에 실패했습니다.');
                            return;
                        }

                        const id = String(json.id ?? formData.get('id') ?? '');
                        const idField = form.querySelector('[name="id"]');
                        if (idField && id) {
                            idField.value = id;
                        }

                        await context.onSaved?.({
                            type: 'project',
                            value: id,
                            text: String(form.querySelector('[name="project_name"]')?.value || ''),
                            sourceEl: context.sourceEl,
                            json,
                            values: Object.fromEntries(formData.entries()),
                        });

                        notify('success', '프로젝트 저장이 완료되었습니다.');
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    } catch (error) {
                        console.error('[journal-basic-info] project detail save failed', error);
                        notify('error', '프로젝트 저장 중 오류가 발생했습니다.');
                    } finally {
                        if (saveButton) {
                            saveButton.disabled = false;
                        }
                    }
                });

                deleteButton?.addEventListener('click', async () => {
                    const id = String(form?.querySelector('[name="id"]')?.value || '').trim();
                    if (!id) {
                        return;
                    }

                    if (!window.confirm('프로젝트를 삭제하시겠습니까?')) {
                        return;
                    }

                    try {
                        const formData = new FormData();
                        formData.set('id', id);

                        const json = await fetchJson('/api/settings/base-info/project/delete', {
                            method: 'POST',
                            body: formData,
                        });

                        if (!json.success) {
                            notify('error', json.message || '프로젝트 삭제에 실패했습니다.');
                            return;
                        }

                        await context.onDeleted?.({
                            type: 'project',
                            value: id,
                            sourceEl: context.sourceEl,
                        });

                        notify('success', '프로젝트가 삭제되었습니다.');
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    } catch (error) {
                        console.error('[journal-basic-info] project detail delete failed', error);
                        notify('error', '프로젝트 삭제 중 오류가 발생했습니다.');
                    }
                });

                modalEl.addEventListener('hidden.bs.modal', () => {
                    resetProjectModalSelect2(modalEl);
                }, { once: true });
            },
        },
    };
}

export function createJournalBasicInfoBridge({ notify }) {
    const safeNotify = typeof notify === 'function'
        ? notify
        : (_type, message) => window.alert(message);

    const configs = ensureQuickCreateConfigs();
    const quickModalEl = ensureQuickCreateModal();
    const quickModal = new bootstrap.Modal(quickModalEl, { focus: false });
    const quickForm = quickModalEl.querySelector('[data-role="quick-create-form"]');
    const quickTitleEl = quickModalEl.querySelector('[data-role="quick-create-title"]');
    const quickBodyEl = quickModalEl.querySelector('[data-role="quick-create-body"]');
    const quickMessageEl = quickModalEl.querySelector('[data-role="quick-create-message"]');
    const quickDetailButton = quickModalEl.querySelector('[data-role="quick-create-detail"]');
    const quickSubmitButton = quickModalEl.querySelector('[data-role="quick-create-submit"]');

    let activeQuickContext = null;
    let activeDetailRoot = null;

    function setQuickMessage(message = '') {
        quickMessageEl.textContent = message;
    }

    function clearQuickData() {
        delete window.__quickData;
        delete window.__quickDataType;
    }

    function collectQuickFormValues() {
        const formData = new FormData(quickForm);
        return Object.fromEntries(formData.entries());
    }

    function stashQuickData(type, values) {
        window.__quickData = { ...values };
        window.__quickDataType = type;
    }

    function getQuickData(type, fallback = null) {
        if (fallback && typeof fallback === 'object') {
            return fallback;
        }

        if (!window.__quickData || window.__quickDataType !== type) {
            return null;
        }

        return window.__quickData;
    }

    function prepareDetailModalForCreate(modalEl, config) {
        const form = modalEl.querySelector('form');
        form?.reset();

        const idField = form?.querySelector('[name="id"]');
        if (idField) {
            idField.value = '';
        }

        const deleteButton = config.deleteButtonSelector
            ? modalEl.querySelector(config.deleteButtonSelector)
            : null;
        if (deleteButton) {
            deleteButton.style.display = 'none';
        }
    }

    function applyQuickDataToDetailModal(modalEl, type, config, quickData = null) {
        const form = modalEl.querySelector('form');
        const data = getQuickData(type, quickData);
        if (!form || !data) {
            return;
        }

        const fields = Array.isArray(config.quickFields) ? config.quickFields : [];
        fields.forEach((fieldConfig) => {
            const sourceName = String(fieldConfig?.name || '').trim();
            const targetName = String(fieldConfig?.detailName || sourceName).trim();
            if (!sourceName || !targetName) {
                return;
            }

            const nextValue = data[sourceName];
            if (nextValue == null || String(nextValue).trim() === '') {
                return;
            }

            const field = form.elements.namedItem(targetName);
            const isRadioGroup = typeof RadioNodeList !== 'undefined' && field instanceof RadioNodeList;
            if (!field || isRadioGroup) {
                return;
            }

            const currentValue = field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')
                ? (field.checked ? '1' : '')
                : String(field.value || '').trim();

            if (currentValue !== '') {
                return;
            }

            if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
                field.checked = nextValue === true || nextValue === '1' || nextValue === 1;
            } else {
                field.value = nextValue;
            }

            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });

        clearQuickData();
    }

    function resetQuickModal() {
        quickForm.reset();
        quickBodyEl.innerHTML = '';
        setQuickMessage('');
        activeQuickContext = null;
        quickSubmitButton.disabled = false;
        quickDetailButton.disabled = false;
        quickDetailButton.dataset.target = '';
    }

    async function openDetailModal(type, context = {}) {
        const config = configs[type];
        if (!config) {
            throw new Error(`Unsupported basic info type: ${type}`);
        }

        if (activeDetailRoot) {
            activeDetailRoot.remove();
            activeDetailRoot = null;
        }

        const template = document.getElementById(config.templateId);
        if (!(template instanceof HTMLTemplateElement)) {
            safeNotify('error', '상세 모달 템플릿을 찾을 수 없습니다.');
            return;
        }

        activeDetailRoot = document.createElement('div');
        activeDetailRoot.dataset.journalDetailModal = type;
        activeDetailRoot.appendChild(template.content.cloneNode(true));
        document.body.appendChild(activeDetailRoot);

        const targetId = String(context.targetId || config.detailModalId || '').trim();
        const modalEl = targetId
            ? activeDetailRoot.querySelector(`#${targetId}`)
            : activeDetailRoot.querySelector('.modal');
        if (!modalEl) {
            safeNotify('error', '상세 모달을 초기화할 수 없습니다.');
            activeDetailRoot.remove();
            activeDetailRoot = null;
            return;
        }

        const modal = new bootstrap.Modal(modalEl, { focus: false });

        modalEl.addEventListener('hidden.bs.modal', () => {
            activeDetailRoot?.remove();
            activeDetailRoot = null;
            clearQuickData();
        }, { once: true });

        config.bindDetailModal?.(modalEl, context, safeNotify);
        modalEl.addEventListener('shown.bs.modal', () => {
            applyQuickDataToDetailModal(modalEl, type, config, context.quickData);
        }, { once: true });

        if (!context.value) {
            prepareDetailModalForCreate(modalEl, config);
            modal.show();
            return;
        }

        try {
            const json = await fetchJson(config.detailUrl(context.value));
            if (!json.success || !json.data) {
                safeNotify('error', json.message || '상세 데이터를 불러오지 못했습니다.');
                modal.dispose();
                activeDetailRoot?.remove();
                activeDetailRoot = null;
                return;
            }

            config.fillDetailForm?.(modalEl, json.data);
            modal.show();
        } catch (error) {
            console.error('[journal-basic-info] detail load failed', error);
            safeNotify('error', '상세 데이터를 불러오는 중 오류가 발생했습니다.');
            modal.dispose();
            activeDetailRoot?.remove();
            activeDetailRoot = null;
            clearQuickData();
        }
    }

    async function persistQuickCreate() {
        if (!activeQuickContext) {
            return;
        }

        const { type, config, context } = activeQuickContext;
        setQuickMessage('');
        quickSubmitButton.disabled = true;
        quickDetailButton.disabled = true;

        const formData = new FormData(quickForm);
        config.extendQuickFormData?.(formData);

        try {
            const json = await fetchJson(config.saveUrl, {
                method: 'POST',
                body: formData,
            });

            if (!json.success) {
                setQuickMessage(json.message || '빠른 등록에 실패했습니다.');
                return;
            }

            const values = Object.fromEntries(formData.entries());
            const selection = config.resolveSelection(json, values);

            if (!selection.value) {
                setQuickMessage('생성된 항목의 식별자를 확인할 수 없습니다.');
                return;
            }

            await context.onSaved?.({
                type,
                value: selection.value,
                text: selection.text,
                sourceEl: context.sourceEl,
                json,
                values,
            });

            quickModal.hide();
        } catch (error) {
            console.error('[journal-basic-info] quick create failed', error);
            setQuickMessage('빠른 등록 중 오류가 발생했습니다.');
        } finally {
            quickSubmitButton.disabled = false;
            quickDetailButton.disabled = false;
        }
    }

    quickForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        await persistQuickCreate();
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.btn-detail-open');
        if (!button || button !== quickDetailButton || !activeQuickContext) {
            return;
        }

        const targetId = String(button.dataset.target || '').trim();
        if (!targetId) {
            console.error('[journal-basic-info] detail modal target missing');
            return;
        }

        const { type, context } = activeQuickContext;
        const quickData = collectQuickFormValues();

        stashQuickData(type, quickData);

        try {
            await waitForModalHidden(quickModalEl, quickModal);
            await openDetailModal(type, {
                ...context,
                targetId,
                quickData,
            });
        } catch (error) {
            console.error('[journal-basic-info] quick detail open failed', error);
            clearQuickData();
            safeNotify('error', '상세 입력 모달을 여는 중 오류가 발생했습니다.');
        }
    });

    quickModalEl.addEventListener('hidden.bs.modal', resetQuickModal);

    return {
        bindDateInputs(modalEl) {
            bindModalDateInputs(modalEl);
        },
        openQuickCreate(type, context = {}) {
            const config = configs[type];
            if (!config) {
                safeNotify('error', '지원하지 않는 빠른 등록 유형입니다.');
                return;
            }

            activeQuickContext = {
                type,
                config,
                context,
            };

            quickTitleEl.textContent = config.quickTitle;
            quickBodyEl.innerHTML = config.quickFields
                .map((field) => renderQuickField(field, context.initialValues || {}))
                .join('');
            setQuickMessage('');
            quickDetailButton.dataset.target = config.detailModalId || '';

            bindScopedFormatters(quickModalEl);
            quickModal.show();
        },
        openDetailModal,
    };
}

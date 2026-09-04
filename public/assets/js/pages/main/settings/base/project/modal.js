import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import { bindModalCardCollapses } from '/public/assets/js/common/modal-card-collapse.js';
import { confirmDialog } from '/public/assets/js/common/confirm-dialog.js';
import { runDeleteProgress } from '/public/assets/js/common/delete-progress.js';

export function createProjectModalModule({
    AdminPicker,
    API,
    openClientQuickCreate,
    formatDateDisplay,
    formatAmount,
    formModule,
    state,
}) {
    let employeeSelect2Inited = false;
    let siteAgentSelect2Inited = false;
    let clientSelect2Inited = false;
    let projectPolicyBound = false;

    const PROJECT_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.main.settings.base-info.project.project-table.v1';
    const PROJECT_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#modal_project_name', key: 'project_name', fallback: '프로젝트명' },
        { selector: '#modal_construction_name', key: 'construction_name', fallback: '공사명/계약명' },
        { selector: '#modal_employee_id', key: 'employee_id', fallback: '담당직원' },
        { selector: '#modal_is_active', key: 'is_active', fallback: '진행상황' },
        { selector: '#modal_client_name', key: 'client_name', fallback: '발주처명' },
        { selector: '#modal_client_type', key: 'client_type', fallback: '발주처구분' },
        { selector: '#modal_bid_type', key: 'bid_type', fallback: '입찰방법' },
        { selector: '#modal_site_agent', key: 'site_agent', fallback: '현장대리인' },
        { selector: '#modal_project_client_id', key: 'client_id', fallback: '거래처' },
        { selector: '#modal_director', key: 'director', fallback: '감리원 / 소장' },
        { selector: '#modal_manager', key: 'manager', fallback: '담당자' },
        { selector: '#modal_contract_method', key: 'contract_method', fallback: '계약방식' },
        { selector: '#modal_housing_type', key: 'housing_type', fallback: '공사유형' },
        { selector: '#modal_contract_type', key: 'contract_type', fallback: '도급종류' },
        { selector: '#modal_work_type', key: 'work_type', fallback: '공종' },
        { selector: '#modal_work_subtype', key: 'work_subtype', fallback: '공종 세부구분' },
        { selector: '#modal_business_type', key: 'business_type', fallback: '업종(주업종)' },
        { selector: '#modal_work_detail_type', key: 'work_detail_type', fallback: '세부 공사종류(주력분야)' },
        { selector: '#modal_site_region_city', key: 'site_region_city', fallback: '시/도' },
        { selector: '#modal_site_region_district', key: 'site_region_district', fallback: '시/군/구' },
        { selector: '#modal_site_region_address', key: 'site_region_address', fallback: '주소' },
        { selector: '#modal_site_region_address_detail', key: 'site_region_address_detail', fallback: '상세주소' },
        { selector: '#modal_permit_date', key: 'permit_date', fallback: '허가일자' },
        { selector: '#modal_contract_date', key: 'contract_date', fallback: '계약일자' },
        { selector: '#modal_start_date', key: 'start_date', fallback: '착공일자' },
        { selector: '#modal_completion_date', key: 'completion_date', fallback: '준공일자' },
        { selector: '#modal_bid_notice_date', key: 'bid_notice_date', fallback: '입찰공고일' },
        { selector: '#modal_initial_contract_amount', key: 'initial_contract_amount', fallback: '최초 계약금액' },
        { selector: '#modal_permit_agency', key: 'permit_agency', fallback: '허가기관' },
        { selector: '#modal_authorized_company_seal', key: 'authorized_company_seal', fallback: '사용인감명' },
        { selector: '#modal_note', key: 'note', fallback: '비고' },
        { selector: '#modal_memo', key: 'memo', fallback: '메모' },
    ]);

    function currentProjectPolicyState() {
        return readDataTableSettingsState(PROJECT_TABLE_SETTINGS_STORAGE_KEY, {
            userSettingPageKey: 'project',
        }) || {};
    }

    function projectFieldLabel(key, fallback = '') {
        const normalizedKey = String(key || '').trim();
        const resolved = resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentProjectPolicyState(),
            fallback
        );
        return resolved && resolved !== normalizedKey ? resolved : (fallback || normalizedKey);
    }

    function projectFieldRequirement(key) {
        const normalizedKey = String(key || '').trim();
        if (normalizedKey === 'project_name') return 'required';
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentProjectPolicyState()
        );
    }

    function projectFieldStarMarkup(key) {
        const policy = projectFieldRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function isProjectFieldVisible(field) {
        if (!field) return false;
        if (field.type === 'hidden') return false;
        if (field.disabled) return false;
        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        return true;
    }

    function shouldValidateProjectPolicyField(field) {
        const selector = String(field?.selector || '').trim();
        if (!selector) return false;
        const input = document.querySelector(selector);
        return isProjectFieldVisible(input);
    }

    function collectProjectDetailValues(form, formData) {
        const values = {};

        PROJECT_MODAL_FIELD_POLICIES.forEach((field) => {
            const key = String(field?.key || '').trim();
            const selector = String(field?.selector || '').trim();
            if (!key || !selector) return;

            const input = form?.querySelector(selector) || document.querySelector(selector);
            if (!input) return;

            const fieldName = String(input.name || key).trim();
            values[key] = formData.get(fieldName) ?? input.value ?? '';
        });

        return values;
    }

    function validateProjectRequiredPolicies(fields = [], values = {}) {
        for (const field of fields) {
            const key = String(field?.key || '').trim();
            if (!key || projectFieldRequirement(key) !== 'required') {
                continue;
            }
            if (!shouldValidateProjectPolicyField(field)) {
                continue;
            }

            const value = values[key];
            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return `${projectFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
                }
                continue;
            }

            if (String(value ?? '').trim() === '') {
                return `${projectFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
            }
        }

        return '';
    }

    function findProjectModalLabel(fieldSelector, root = document) {
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

    function applyProjectModalPolicyLabels(root = document) {
        PROJECT_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findProjectModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = projectFieldLabel(field.key, field.fallback);
            const starMarkup = projectFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function bindProjectPolicySync() {
        if (projectPolicyBound) return;
        projectPolicyBound = true;

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== PROJECT_TABLE_SETTINGS_STORAGE_KEY) return;
            applyProjectModalPolicyLabels(document);
        });
    }

    function initModal() {
        const modalEl = document.getElementById('projectModal');
        if (!modalEl) return;

        state.projectModal = new bootstrap.Modal(modalEl, { focus: false });
        const excelModalEl = document.getElementById('projectExcelUploadModal');
        if (excelModalEl) state.excelModal = new bootstrap.Modal(excelModalEl);

        bindProjectPolicySync();
        bindModalCardCollapses(modalEl, { resetOnShow: true });
        applyProjectModalPolicyLabels(document);
        renderProjectSystemInfo();

        modalEl.addEventListener('hidden.bs.modal', () => {
            document.getElementById('project-edit-form')?.reset();
            resetProjectModalSelect2();
            const amountInput = document.getElementById('modal_initial_contract_amount');
            if (amountInput) amountInput.value = '';
            renderProjectSystemInfo();
            applyProjectModalPolicyLabels(document);
        });

        formModule.bindDateIconPicker();
        modalEl.addEventListener('shown.bs.modal', () => {
            formModule.bindAdminDateInputs();
            initProjectModalSelect2();
            applyProjectModalPolicyLabels(document);
        });
    }

    function initProjectModalSelect2() {
        initEmployeeSelect2();
        initSiteAgentSelect2();
        initClientSelect2();
    }

    function initEmployeeSelect2() {
        const el = document.getElementById('modal_employee_id');
        if (!el || employeeSelect2Inited) return;
        const $el = window.jQuery(el);
        if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
        AdminPicker.select2Ajax(el, {
            url: API.EMPLOYEE_SEARCH,
            placeholder: '담당직원 검색',
            minimumInputLength: 0,
            dropdownParent: window.jQuery('#projectModal'),
            width: '100%',
            dataBuilder(params) {
                return { q: params.term || '', limit: 20 };
            },
            processResults(json) {
                const rows = json?.results ?? json?.data ?? [];
                return {
                    results: rows.map((row) => ({
                            id: String(row.id ?? ''),
                            text: row.text ?? row.employee_name ?? row.username ?? row.id,
                            raw: row,
                        })).filter((item) => item.id !== ''),
                };
            },
        });
        $el.off('select2:select.projectEmployee');
        employeeSelect2Inited = true;
    }

    function initSiteAgentSelect2() {
        const el = document.getElementById('modal_site_agent');
        if (!el || siteAgentSelect2Inited) return;
        const $el = window.jQuery(el);
        if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
        AdminPicker.select2Ajax(el, {
            url: API.EMPLOYEE_SEARCH,
            placeholder: '현장대리인 검색',
            minimumInputLength: 0,
            dropdownParent: window.jQuery('#projectModal'),
            width: '100%',
            dataBuilder(params) {
                return { q: params.term || '', limit: 20 };
            },
            processResults(json) {
                const rows = json?.results ?? json?.data ?? [];
                return {
                    results: rows.map((row) => ({
                            id: String(row.id ?? ''),
                            text: row.text ?? row.employee_name ?? row.username ?? row.id,
                            raw: row,
                        })).filter((item) => item.id !== ''),
                };
            },
        });
        $el.off('select2:select.projectSiteAgent');
        siteAgentSelect2Inited = true;
    }

    function initClientSelect2() {
        const el = document.getElementById('modal_project_client_id');
        if (!el || clientSelect2Inited) return;
        const $el = window.jQuery(el);
        if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
        AdminPicker.select2Ajax(el, {
            url: API.CLIENT_SEARCH,
            placeholder: '거래처 검색',
            includeCommonAdd: true,
            minimumInputLength: 0,
            dropdownParent: window.jQuery('#projectModal'),
            width: '100%',
            dataBuilder(params) {
                return { q: params.term || '', limit: 20 };
            },
            processResults(json) {
                const rows = json?.results ?? json?.data ?? [];
                return {
                    results: rows.map((row) => ({
                        id: String(row.id ?? ''),
                        text: row.text ?? row.client_name ?? '',
                        raw: row,
                    })).filter((item) => item.id !== ''),
                };
            },
        });
        $el.off('select2:select.projectClient').on('select2:select.projectClient', function (event) {
            const item = event.params?.data;
            if (item) window.jQuery(this).val(String(item.id)).trigger('change');
        });
        el.removeEventListener?.('picker:add', el.__projectClientPickerAdd);
        el.__projectClientPickerAdd = () => {
            window.jQuery(el).val(null).trigger('change');
            window.jQuery(el).select2('close');
            openProjectClientQuickCreate('');
        };
        el.addEventListener('picker:add', el.__projectClientPickerAdd);
        clientSelect2Inited = true;
    }

    function resetProjectModalSelect2() {
        const $employee = window.jQuery('#modal_employee_id');
        const $siteAgent = window.jQuery('#modal_site_agent');
        const $client = window.jQuery('#modal_project_client_id');

        if ($employee.hasClass('select2-hidden-accessible')) {
            $employee.off('.projectEmployee');
            $employee.select2('destroy');
        }
        if ($siteAgent.hasClass('select2-hidden-accessible')) {
            $siteAgent.off('.projectSiteAgent');
            $siteAgent.select2('destroy');
        }
        if ($client.hasClass('select2-hidden-accessible')) {
            $client.off('.projectClient');
            $client.select2('destroy');
        }

        [['modal_employee_id'], ['modal_site_agent'], ['modal_project_client_id']].forEach(([id]) => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '<option value=""></option>';
        });

        employeeSelect2Inited = false;
        siteAgentSelect2Inited = false;
        clientSelect2Inited = false;
    }

    function setProjectEmployeeSelect2(data) {
        const employeeId = String(data.employee_id ?? '').trim();
        const $el = window.jQuery('#modal_employee_id');
        if (!employeeId) return $el.val(null).trigger('change');
        const employeeText = data.employee_name ?? employeeId;
        $el.find(`option[value="${employeeId}"]`).remove();
        $el.append(new Option(employeeText, employeeId, true, true));
        $el.val(employeeId).trigger('change');
    }

    function setProjectSiteAgentSelect2(data) {
        const siteAgent = String(data.site_agent ?? '').trim();
        const $el = window.jQuery('#modal_site_agent');
        if (!siteAgent) return $el.val(null).trigger('change');
        const siteAgentText = data.site_agent_name ?? data.site_agent_employee_name ?? siteAgent;
        $el.find(`option[value="${siteAgent}"]`).remove();
        $el.append(new Option(siteAgentText, siteAgent, true, true));
        $el.val(siteAgent).trigger('change');
    }

    function setProjectClientSelect2(data) {
        const clientId = String(data.client_id ?? '').trim();
        const $el = window.jQuery('#modal_project_client_id');
        if (!clientId) return $el.val(null).trigger('change');
        const clientText = data.linked_client_name ?? data.client_name ?? clientId;
        $el.find(`option[value="${clientId}"]`).remove();
        $el.append(new Option(clientText, clientId, true, true));
        $el.val(clientId).trigger('change');
    }

    function fillModal(data) {
        Object.keys(data).forEach((key) => {
            if (['id', 'employee_id', 'site_agent', 'client_id'].includes(key)) return;
            const el = document.getElementById(`modal_${key}`);
            if (!el) return;
            let value = data[key] ?? '';
            if (['permit_date', 'contract_date', 'start_date', 'completion_date', 'bid_notice_date'].includes(key)) {
                value = formatDateDisplay(value);
            }
            if (key === 'initial_contract_amount') {
                value = formatAmount(value);
            }
            el.value = value;
            if (['bid_type', 'contract_method', 'housing_type', 'contract_type', 'work_type'].includes(key)
                && window.jQuery?.fn?.select2
                && window.jQuery(el).hasClass('select2-hidden-accessible')) {
                window.jQuery(el).val(value || null).trigger('change.select2');
            }
        });

        setTimeout(() => {
            setProjectEmployeeSelect2(data);
            setProjectSiteAgentSelect2(data);
            setProjectClientSelect2(data);
            applyProjectModalPolicyLabels(document);
        }, 50);
    }

    function openProjectClientQuickCreate(defaultName = '') {
        openClientQuickCreate({
            select: document.getElementById('modal_project_client_id'),
            initialValues: { client_name: defaultName },
            onSuccess() {
                window.AppCore?.notify?.('success', '거래처가 등록되었습니다.');
            },
            getOptionText(values) {
                return values.client_name || '';
            },
        });
    }

    function openCreateModal() {
        const form = document.getElementById('project-edit-form');
        if (form) form.reset();
        resetProjectModalSelect2();
        window.jQuery('#modal_project_id').val('');
        window.jQuery('#btnDeleteProject').hide();
        window.isNewProject = true;
        const titleEl = document.getElementById('projectModalLabel');
        if (titleEl) titleEl.textContent = '프로젝트 신규 등록';
        const amountInput = document.getElementById('modal_initial_contract_amount');
        if (amountInput) amountInput.value = '';
        renderProjectSystemInfo();
        applyProjectModalPolicyLabels(document);
        state.projectModal?.show();
        void formModule.prepareProjectModalControls().catch((error) => {
            console.error('[project] modal controls prepare failed', error);
            formModule.notify('error', '프로젝트 입력 항목 준비 중 오류가 발생했습니다.');
        });
    }

    async function fetchProjectDetail(id) {
        const json = await window.AppAjax.fetchJson(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
        if (!json.success || !json.data) throw new Error(json.message || '프로젝트 상세 조회에 실패했습니다.');
        return json.data;
    }

    async function openProjectEditModal(projectId) {
        if (!projectId) return;
        window.isNewProject = false;
        document.getElementById('project-edit-form')?.reset();
        resetProjectModalSelect2();
        document.getElementById('projectModalLabel').textContent = '프로젝트 정보 수정';
        window.jQuery('#btnDeleteProject').show();
        window.jQuery('#modal_project_id').val(projectId);
        applyProjectModalPolicyLabels(document);
        state.projectModal?.show();
        try {
            const [data] = await Promise.all([fetchProjectDetail(projectId), formModule.prepareProjectModalControls()]);
            window.jQuery('#modal_project_id').val(data.id ?? '');
            fillModal(data);
            renderProjectSystemInfo(data);
        } catch (error) {
            console.error(error);
            formModule.notify('error', error.message || '프로젝트 상세 조회 중 오류가 발생했습니다.');
        }
    }

    function bindModalEvents($, getTable) {
        $(document).off('submit', '#project-edit-form');
        $(document).on('submit', '#project-edit-form', async function (event) {
            event.preventDefault();
            this.querySelectorAll('.admin-date').forEach((input) => {
                input.value = formModule.normalizeDateInputValue(input.value);
            });
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const amountInput = document.getElementById('modal_initial_contract_amount');
            if (amountInput) formData.set('initial_contract_amount', String(amountInput.value || '').replace(/,/g, ''));
            const requiredMessage = validateProjectRequiredPolicies(
                PROJECT_MODAL_FIELD_POLICIES,
                collectProjectDetailValues(this, formData)
            );
            if (requiredMessage) return formModule.notify('warning', requiredMessage);
            const validationMessage = formModule.validateProjectForm(formData);
            if (validationMessage) return formModule.notify('warning', validationMessage);
            if (submitButton) submitButton.disabled = true;
            try {
                const res = await window.AppAjax.fetchJson(API.SAVE, {
                method: 'POST',
                body: formData,
                });
                if (res.success) {
                    state.projectModal?.hide();
                    getTable()?.ajax.reload(null, false);
                    formModule.notify('success', '저장이 완료되었습니다.');
                } else {
                    formModule.notify('error', res.message || '저장에 실패했습니다.');
                }
            } catch (error) {
                console.error(error);
                formModule.notify('error', error.message || '저장 중 오류가 발생했습니다.');
            } finally {
                if (submitButton) submitButton.disabled = false;
            }
        });

        $('#btnDeleteProject').off('click').on('click', async function () {
            const id = $('#modal_project_id').val();
            if (!id || !await confirmDialog({ title: '프로젝트 삭제', message: '프로젝트를 휴지통으로 이동하시겠습니까?', confirmText: '삭제', confirmClass: 'btn-danger' })) return;
            try {
                await runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '프로젝트를 휴지통으로 이동 중', trashChanged: true }, async () => {
                    const res = await window.AppAjax.postForm(API.DELETE, { id });
                    if (!res.success) throw new Error(res.message || '삭제에 실패했습니다.');
                    formModule.notify('success', '삭제가 완료되었습니다.');
                    await new Promise(resolve => getTable()?.ajax.reload(() => resolve(), false));
                    state.projectModal?.hide();
                });
            } catch (error) {
                console.error(error);
                formModule.notify('error', error.message || '삭제 중 오류가 발생했습니다.');
            }
        });
    }

    function renderProjectSystemInfo(data = {}) {
        const container = document.getElementById('projectSystemInfoFields');
        if (!container) return;
        const fields = [
            ['id', 'ID'], ['sort_no', '순번'], ['created_at', '생성일시', 'datetime'],
            ['created_by_name', '생성자'], ['updated_at', '수정일시', 'datetime'],
            ['updated_by_name', '수정자'], ['deleted_at', '삭제일시', 'datetime'], ['deleted_by_name', '삭제자'],
        ];
        container.replaceChildren(...fields.map(([key, labelText, type]) => {
            const item = document.createElement('div');
            item.className = 'project-system-info-field';
            const label = document.createElement('span');
            label.className = 'project-system-info-label';
            label.textContent = labelText;
            const value = document.createElement('span');
            value.className = 'project-system-info-value';
            const raw = type === 'datetime' ? formatDateDisplay(data[key]) : data[key];
            value.textContent = raw === null || raw === undefined || raw === '' ? '-' : String(raw);
            item.append(label, value);
            return item;
        }));
    }

    return {
        initModal,
        openCreateModal,
        openProjectEditModal,
        bindModalEvents,
        fetchProjectDetail,
        fillModal,
    };
}

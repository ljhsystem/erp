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

    function initModal() {
        const modalEl = document.getElementById('projectModal');
        if (!modalEl) return;

        state.projectModal = new bootstrap.Modal(modalEl, { focus: false });
        const excelModalEl = document.getElementById('projectExcelUploadModal');
        if (excelModalEl) state.excelModal = new bootstrap.Modal(excelModalEl);

        modalEl.addEventListener('hidden.bs.modal', () => {
            document.getElementById('project-edit-form')?.reset();
            resetProjectModalSelect2();
            const amountInput = document.getElementById('modal_initial_contract_amount');
            if (amountInput) amountInput.value = '';
        });

        formModule.bindDateIconPicker();
        modalEl.addEventListener('shown.bs.modal', () => {
            formModule.bindAdminDateInputs();
            initProjectModalSelect2();
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
                    results: [
                        { id: '__none__', text: '선택(없음)', isNone: true },
                        ...rows.map((row) => ({
                            id: String(row.id ?? ''),
                            text: row.text ?? row.employee_name ?? row.username ?? row.id,
                            raw: row,
                        })).filter((item) => item.id !== ''),
                    ],
                };
            },
        });
        $el.off('select2:select.projectEmployee').on('select2:select.projectEmployee', function (event) {
            if (event.params?.data?.id === '__none__') window.jQuery(this).val(null).trigger('change');
        });
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
                    results: [
                        { id: '__none__', text: '선택(없음)', isNone: true },
                        ...rows.map((row) => ({
                            id: String(row.id ?? ''),
                            text: row.text ?? row.employee_name ?? row.username ?? row.id,
                            raw: row,
                        })).filter((item) => item.id !== ''),
                    ],
                };
            },
        });
        $el.off('select2:select.projectSiteAgent').on('select2:select.projectSiteAgent', function (event) {
            if (event.params?.data?.id === '__none__') window.jQuery(this).val(null).trigger('change');
        });
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
        state.projectModal?.show();
        void formModule.prepareProjectModalControls().catch((error) => {
            console.error('[project] modal controls prepare failed', error);
            formModule.notify('error', '프로젝트 입력 항목 준비 중 오류가 발생했습니다.');
        });
    }

    async function fetchProjectDetail(id) {
        const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
        const json = await res.json();
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
        state.projectModal?.show();
        try {
            const [data] = await Promise.all([fetchProjectDetail(projectId), formModule.prepareProjectModalControls()]);
            window.jQuery('#modal_project_id').val(data.id ?? '');
            fillModal(data);
        } catch (error) {
            console.error(error);
            formModule.notify('error', error.message || '프로젝트 상세 조회 중 오류가 발생했습니다.');
        }
    }

    function bindModalEvents($, getTable) {
        $(document).off('submit', '#project-edit-form');
        $(document).on('submit', '#project-edit-form', function (event) {
            event.preventDefault();
            this.querySelectorAll('.admin-date').forEach((input) => {
                input.value = formModule.normalizeDateInputValue(input.value);
            });
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const amountInput = document.getElementById('modal_initial_contract_amount');
            if (amountInput) formData.set('initial_contract_amount', String(amountInput.value || '').replace(/,/g, ''));
            const validationMessage = formModule.validateProjectForm(formData);
            if (validationMessage) return formModule.notify('warning', validationMessage);
            if (submitButton) submitButton.disabled = true;
            $.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            }).done((res) => {
                if (res.success) {
                    state.projectModal?.hide();
                    getTable()?.ajax.reload(null, false);
                    formModule.notify('success', '저장이 완료되었습니다.');
                } else {
                    formModule.notify('error', res.message || '저장에 실패했습니다.');
                }
            }).fail((error) => {
                console.error(error);
                formModule.notify('error', '서버 오류가 발생했습니다.');
            }).always(() => {
                if (submitButton) submitButton.disabled = false;
            });
        });

        $('#btnDeleteProject').off('click').on('click', function () {
            const id = $('#modal_project_id').val();
            if (!id || !confirm('삭제하시겠습니까?')) return;
            $.post(API.DELETE, { id }).done((res) => {
                if (res.success) {
                    formModule.notify('success', '삭제가 완료되었습니다.');
                    getTable()?.ajax.reload(null, false);
                    state.projectModal?.hide();
                } else {
                    formModule.notify('error', res.message || '삭제에 실패했습니다.');
                }
            });
        });
    }

    return {
        initModal,
        openCreateModal,
        openProjectEditModal,
        bindModalEvents,
        fetchProjectDetail,
    };
}

import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import {
    formatDate,
    formatDateInputValue,
    normalizeDateInputValue,
    normalizeStartEnd,
} from './form.js';

export function createWorkTeamModalModule({
    AdminPicker,
    api,
    notify,
    openClientQuickCreate,
    reloadTable,
}) {
    let workTeamModal = null;
    let excelModal = null;
    let todayPicker = null;
    let clientSelect2Inited = false;
    let modalControlsPromise = null;
    let workTeamPolicyBound = false;
    let openCreateContext = null;

    const WORK_TEAM_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.dashboard.settings.base-info.work-team.work-team-table.v1';
    const WORK_TEAM_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#modal-work-team-team-name', key: 'team_name', fallback: '팀명' },
        { selector: '#modal-work-team-is-active', key: 'is_active', fallback: '상태' },
        { selector: '#modal-work-team-team-leader-client-id', key: 'team_leader_client_id', fallback: '팀장' },
        { selector: '#modal-work-team-note', key: 'note', fallback: '비고' },
        { selector: '#modal-work-team-memo', key: 'memo', fallback: '메모' },
    ]);

    function currentWorkTeamPolicyState() {
        return readDataTableSettingsState(WORK_TEAM_TABLE_SETTINGS_STORAGE_KEY) || {};
    }

    function workTeamFieldLabel(key, _fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentWorkTeamPolicyState(),
            normalizedKey
        );
    }

    function workTeamFieldRequirement(key) {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentWorkTeamPolicyState()
        );
    }

    function workTeamFieldStarMarkup(key) {
        const policy = workTeamFieldRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function isWorkTeamFieldVisible(field) {
        if (!field) return false;
        if (field.type === 'hidden') return false;
        if (field.disabled) return false;
        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        return true;
    }

    function shouldValidateWorkTeamPolicyField(field) {
        const selector = String(field?.selector || '').trim();
        if (!selector) return false;
        const input = document.querySelector(selector);
        return isWorkTeamFieldVisible(input);
    }

    function collectWorkTeamDetailValues(form, formData) {
        const values = {};

        WORK_TEAM_MODAL_FIELD_POLICIES.forEach((field) => {
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

    function validateWorkTeamRequiredPolicies(fields = [], values = {}) {
        for (const field of fields) {
            const key = String(field?.key || '').trim();
            if (!key || workTeamFieldRequirement(key) !== 'required') {
                continue;
            }
            if (!shouldValidateWorkTeamPolicyField(field)) {
                continue;
            }

            const value = values[key];
            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return `${workTeamFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
                }
                continue;
            }

            if (String(value ?? '').trim() === '') {
                return `${workTeamFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
            }
        }

        return '';
    }

    function findWorkTeamModalLabel(fieldSelector, root = document) {
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

    function applyWorkTeamModalPolicyLabels(root = document) {
        WORK_TEAM_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findWorkTeamModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = workTeamFieldLabel(field.key, field.fallback);
            const starMarkup = workTeamFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function bindWorkTeamPolicySync() {
        if (workTeamPolicyBound) return;
        workTeamPolicyBound = true;

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== WORK_TEAM_TABLE_SETTINGS_STORAGE_KEY) return;
            applyWorkTeamModalPolicyLabels(document);
        });
    }

    function initModal() {
        const modalEl = document.getElementById('workTeamModal');
        if (modalEl) {
            workTeamModal = new bootstrap.Modal(modalEl, { focus: false });
            bindWorkTeamPolicySync();
            applyWorkTeamModalPolicyLabels(document);
            modalEl.addEventListener('hidden.bs.modal', () => {
                resetForm();
                applyWorkTeamModalPolicyLabels(document);
            });
            modalEl.addEventListener('shown.bs.modal', () => {
                deferModalControls();
                applyWorkTeamModalPolicyLabels(document);
            });
        }

        const excelModalEl = document.getElementById('workTeamExcelModal');
        if (excelModalEl) {
            excelModal = new bootstrap.Modal(excelModalEl);
        }
    }

    function getWorkTeamModal() {
        return workTeamModal;
    }

    function getExcelModal() {
        return excelModal;
    }

    function initExcelDataset() {
        const excelForm = document.getElementById('workTeamExcelForm');
        if (!excelForm) return;
        excelForm.dataset.templateUrl = api.EXCEL_TEMPLATE;
        excelForm.dataset.downloadUrl = api.EXCEL_DOWNLOAD;
        excelForm.dataset.uploadUrl = api.EXCEL_UPLOAD;
    }

    function preloadModalControls() {
        if (!modalControlsPromise) {
            modalControlsPromise = prepareModalControls().catch((error) => {
                console.error('[work-team] modal controls preload failed', error);
                modalControlsPromise = null;
            });
        }
        return modalControlsPromise;
    }

    function prepareModalControls() {
        if (!modalControlsPromise) {
            modalControlsPromise = Promise.resolve().then(() => {
                initClientSelect2();
            }).catch((error) => {
                modalControlsPromise = null;
                throw error;
            });
        }
        return modalControlsPromise;
    }

    function deferModalControls() {
        const run = () => {
            prepareModalControls().catch((error) => {
                console.error('[work-team] modal controls prepare failed', error);
                notify('error', '입력 항목 준비 중 오류가 발생했습니다.');
            });
        };

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(() => window.setTimeout(run, 0));
            return;
        }

        window.setTimeout(run, 0);
    }

    async function fetchDetail(teamId) {
        const res = await fetch(`${api.DETAIL}?id=${encodeURIComponent(teamId)}`);
        const json = await res.json();
        if (!json.success || !json.data) {
            throw new Error(json.message || '상세 조회에 실패했습니다.');
        }
        return json.data;
    }

    async function openEditModalByRow(rowData) {
        if (!rowData?.id) return;
        resetForm();
        document.getElementById('workTeamModalLabel').textContent = '팀관리 수정';
        document.getElementById('btnDeleteWorkTeam').style.display = '';
        document.getElementById('modal-work-team-id').value = rowData.id;
        applyWorkTeamModalPolicyLabels(document);
        workTeamModal?.show();

        try {
            const [data] = await Promise.all([fetchDetail(rowData.id), prepareModalControls()]);
            fillForm(data);
            setTeamLeaderSelect2(data);
        } catch (error) {
            console.error(error);
            notify('error', error.message || '서버 오류가 발생했습니다.');
        }
    }

    function openCreateModal(options = {}) {
        resetForm();
        openCreateContext = options && typeof options === 'object' ? options : null;
        document.getElementById('workTeamModalLabel').textContent = '팀관리 등록';
        document.getElementById('btnDeleteWorkTeam').style.display = 'none';
        setTeamLeaderSelect2({});
        if (openCreateContext?.initialValues && typeof openCreateContext.initialValues === 'object') {
            fillForm(openCreateContext.initialValues);
            setTeamLeaderSelect2(openCreateContext.initialValues);
        }
        applyWorkTeamModalPolicyLabels(document);
        workTeamModal?.show();
        deferModalControls();
    }

    function resetForm() {
        const form = document.getElementById('workTeamForm');
        if (form) form.reset();
        const idEl = document.getElementById('modal-work-team-id');
        if (idEl) idEl.value = '';
        const deleteButton = document.getElementById('btnDeleteWorkTeam');
        if (deleteButton) deleteButton.style.display = 'none';
        setTeamLeaderSelect2({});
    }

    function fillForm(data) {
        Object.entries(data).forEach(([key, value]) => {
            const domKey = String(key).replace(/_/g, '-');
            const el = document.getElementById(`modal-work-team-${domKey}`);
            if (!el) return;
            el.value = value ?? '';
        });
        applyWorkTeamModalPolicyLabels(document);
    }

    function initClientSelect2() {
        const el = document.getElementById('modal-work-team-team-leader-client-id');
        if (!el || clientSelect2Inited) return;
        const $el = window.jQuery(el);

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        AdminPicker.select2Ajax(el, {
            url: api.CLIENT_SEARCH,
            placeholder: '팀장 거래처 검색',
            includeCommonAdd: true,
            minimumInputLength: 0,
            dropdownParent: window.jQuery('#workTeamModal'),
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

        $el.off('select2:select.workTeamClient');
        $el.on('select2:select.workTeamClient', function (event) {
            const item = event.params?.data;
            if (!item) return;
            window.jQuery(this).val(String(item.id)).trigger('change');
        });

        el.removeEventListener?.('picker:add', el.__workTeamClientPickerAdd);
        el.__workTeamClientPickerAdd = () => {
            window.jQuery(el).val(null).trigger('change');
            window.jQuery(el).select2('close');
            openClientQuickCreate('');
        };
        el.addEventListener('picker:add', el.__workTeamClientPickerAdd);

        clientSelect2Inited = true;
    }

    function setTeamLeaderSelect2(data) {
        const clientId = String(data.team_leader_client_id ?? '').trim();
        const $el = window.jQuery('#modal-work-team-team-leader-client-id');
        if (!$el.length) return;

        if (!clientId) {
            $el.val(null).trigger('change');
            return;
        }

        const text = data.team_leader_client_name ?? clientId;
        $el.find(`option[value="${clientId}"]`).remove();
        $el.append(new Option(text, clientId, true, true));
        $el.val(clientId).trigger('change');
    }

    function bindModalEvents() {
        window.jQuery(document).off('submit', '#workTeamForm');
        window.jQuery(document).on('submit', '#workTeamForm', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            const requiredMessage = validateWorkTeamRequiredPolicies(
                WORK_TEAM_MODAL_FIELD_POLICIES,
                collectWorkTeamDetailValues(this, formData)
            );
            if (requiredMessage) {
                notify('warning', requiredMessage);
                return;
            }
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            window.jQuery.ajax({
                url: api.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            })
                .done((res) => {
                    if (res.success) {
                        openCreateContext?.onSaved?.(res, Object.fromEntries(formData.entries()));
                        workTeamModal?.hide();
                        reloadTable();
                        notify('success', res.message || '저장되었습니다.');
                    } else {
                        notify('error', res.message || '저장에 실패했습니다.');
                    }
                })
                .fail(() => notify('error', '서버 오류가 발생했습니다.'))
                .always(() => {
                    openCreateContext = null;
                    if (submitButton) submitButton.disabled = false;
                });
        });

        window.jQuery('#btnDeleteWorkTeam').off('click');
        window.jQuery('#btnDeleteWorkTeam').on('click', function () {
            const id = window.jQuery('#modal-work-team-id').val();
            if (!id || !window.confirm('삭제하시겠습니까?')) return;

            window.jQuery.post(api.DELETE, { id })
                .done((res) => {
                    if (res.success) {
                        workTeamModal?.hide();
                        reloadTable();
                        notify('success', res.message || '삭제했습니다.');
                    } else {
                        notify('error', res.message || '삭제에 실패했습니다.');
                    }
                })
                .fail(() => {
                    notify('error', '삭제 중 오류가 발생했습니다.');
                });
        });
    }

    function initAdminDatePicker() {
        if (todayPicker) return todayPicker;
        const container = document.getElementById('today-picker');
        if (!container) return null;

        todayPicker = AdminPicker.create({ type: 'today', container });
        todayPicker.subscribe((_, date) => {
            const input = todayPicker.__target;
            if (!input || !date) return;
            input.value = formatDate(date);
            normalizeStartEnd(input.name === 'dateStart' ? 'start' : 'end');
            todayPicker.close();
        });
        return todayPicker;
    }

    function bindAdminDateInputs() {
        document.querySelectorAll('.admin-date').forEach((input) => {
            if (input.dataset.dateInputBound === '1') return;
            input.dataset.dateInputBound = '1';
            input.addEventListener('input', () => {
                input.value = formatDateInputValue(input.value);
            });
            input.addEventListener('blur', () => {
                input.value = normalizeDateInputValue(input.value, notify);
            });
        });
    }

    function bindDateIconPicker() {
        if (document.__workTeamDateIconPickerBound) return;
        document.__workTeamDateIconPickerBound = true;

        document.addEventListener('click', function (event) {
            const icon = event.target.closest('.date-icon');
            if (!icon) return;
            const wrap = icon.closest('.date-input, .date-input-wrap');
            const input = wrap ? wrap.querySelector('input.admin-date, input[name="dateStart"], input[name="dateEnd"]') : null;
            if (!input) return;
            event.preventDefault();
            event.stopPropagation();
            openDatePickerForInput(input);
        }, true);
    }

    function openDatePickerForInput(input) {
        const picker = initAdminDatePicker();
        if (!picker) return;
        picker.__target = input;
        if (typeof picker.clearDate === 'function') picker.clearDate();
        input.value = normalizeDateInputValue(input.value, notify);
        if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            const date = new Date(input.value);
            if (!Number.isNaN(date.getTime())) {
                picker.setDate(date);
            }
        }
        picker.open({ anchor: input });
    }

    return {
        initModal,
        initExcelDataset,
        preloadModalControls,
        bindModalEvents,
        initAdminDatePicker,
        bindAdminDateInputs,
        bindDateIconPicker,
        openCreateModal,
        openEditModalByRow,
        fetchDetail,
        getExcelModal,
    };
}

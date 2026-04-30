import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { openClientQuickCreate } from '/public/assets/js/pages/dashboard/settings/base/client.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    const API = {
        LIST: '/api/settings/base-info/work-team/list',
        DETAIL: '/api/settings/base-info/work-team/detail',
        SAVE: '/api/settings/base-info/work-team/save',
        DELETE: '/api/settings/base-info/work-team/delete',
        CLIENT_SEARCH: '/api/settings/base-info/client/search-picker',
        CLIENT_SAVE: '/api/settings/base-info/client/save',
        TRASH: '/api/settings/base-info/work-team/trash',
        RESTORE: '/api/settings/base-info/work-team/restore',
        RESTORE_BULK: '/api/settings/base-info/work-team/restore-bulk',
        RESTORE_ALL: '/api/settings/base-info/work-team/restore-all',
        PURGE: '/api/settings/base-info/work-team/purge',
        PURGE_BULK: '/api/settings/base-info/work-team/purge-bulk',
        PURGE_ALL: '/api/settings/base-info/work-team/purge-all',
        REORDER: '/api/settings/base-info/work-team/reorder',
        EXCEL_UPLOAD: '/api/settings/base-info/work-team/excel-upload',
        EXCEL_DOWNLOAD: '/api/settings/base-info/work-team/excel',
        EXCEL_TEMPLATE: '/api/settings/base-info/work-team/template'
    };

    const WORK_TEAM_COLUMN_MAP = {
        sort_no: { label: '순번', visible: true, className: 'text-center' },
        team_name: { label: '팀명', visible: true },
        team_leader_client_name: { label: '팀장', visible: true },
        team_leader_client_id: { label: '팀장 거래처 ID', visible: false },
        note: { label: '비고', visible: true },
        memo: { label: '메모', visible: false },
        is_active: { label: '상태', visible: true, className: 'text-center' },
        created_at: { label: '등록일시', visible: false },
        created_by_name: { label: '등록자', visible: false },
        updated_at: { label: '수정일시', visible: false },
        updated_by_name: { label: '수정자', visible: false },
        deleted_at: { label: '삭제일시', visible: false },
        deleted_by_name: { label: '삭제자', visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '등록일시' },
        { value: 'updated_at', label: '수정일시' }
    ];

    let workTeamTable = null;
    let workTeamModal = null;
    let excelModal = null;
    let todayPicker = null;
    let clientSelect2Inited = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('jQuery not loaded');
            return;
        }

        initWorkTeamPage(window.jQuery);
    });

    function initWorkTeamPage($) {
        initModal();
        initAdminDatePicker();
        initExcelDataset();
        initDataTable($);
        bindRowReorder(workTeamTable, {
            api: API.REORDER,
            onSuccess() {
                AppCore?.notify?.('success', '팀 순번이 저장되었습니다.');
                workTeamTable?.ajax.reload(null, false);
            },
            onError(json) {
                AppCore?.notify?.('error', json?.message || '팀 순번 저장에 실패했습니다.');
                workTeamTable?.ajax.reload(null, false);
            }
        });
        bindTableEvents($);
        bindModalEvents($);
        initClientSelect2();
        bindAdminDateInputs();
        bindDateIconPicker();
        bindExcelEvents();
        bindTrashEvents();
    }

    function initModal() {
        const modalEl = document.getElementById('workTeamModal');
        if (!modalEl) return;

        workTeamModal = new bootstrap.Modal(modalEl, { focus: false });

        modalEl.addEventListener('hidden.bs.modal', resetForm);

        const excelModalEl = document.getElementById('workTeamExcelModal');
        if (excelModalEl) {
            excelModal = new bootstrap.Modal(excelModalEl);
        }
    }

    function initExcelDataset() {
        const excelForm = document.getElementById('workTeamExcelForm');
        if (!excelForm) return;

        excelForm.dataset.templateUrl = API.EXCEL_TEMPLATE;
        excelForm.dataset.downloadUrl = API.EXCEL_DOWNLOAD;
        excelForm.dataset.uploadUrl = API.EXCEL_UPLOAD;
    }

    function initDataTable($) {
        workTeamTable = createDataTable({
            tableSelector: '#work-team-table',
            api: API.LIST,
            columns: buildColumns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            autoWidth: false,
            buttons: [
                {
                    text: '엑셀관리',
                    className: 'btn btn-success btn-sm',
                    action: function () {
                        excelModal?.show();
                    }
                },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal
                },
                {
                    text: '새 팀',
                    className: 'btn btn-warning btn-sm',
                    action: openCreateModal
                }
            ]
        });

        window.workTeamTable = workTeamTable;

        if (workTeamTable) {
            workTeamTable.on('init.dt draw.dt xhr.dt', () => {
                updateCount(workTeamTable.page.info()?.recordsDisplay ?? 0);
            });

            SearchForm({
                table: workTeamTable,
                apiList: API.LIST,
                tableId: 'workTeam',
                defaultSearchField: 'team_name',
                dateOptions: DATE_OPTIONS
            });

            bindTableHighlight('#work-team-table', workTeamTable);
        }
    }

    function buildColumns() {
        const columns = [{
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'col-reorder reorder-handle no-sort no-colvis text-center',
            headerClassName: 'col-reorder no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>'
        }];

        Object.entries(WORK_TEAM_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                className: config.className || '',
                headerClassName: config.className || '',
                defaultContent: '',
                render(data) {
                    if (data === null || data === undefined) return '';

                    if (field === 'is_active') {
                        return Number(data) === 1
                            ? '<span class="badge bg-success">사용</span>'
                            : '<span class="badge bg-secondary">미사용</span>';
                    }

                    return data;
                }
            });
        });

        return columns;
    }

    function bindTableEvents($) {
        $('#work-team-table tbody').on('dblclick', 'tr', async function () {
            const row = workTeamTable.row(this).data();
            if (!row?.id) return;

            try {
                const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(row.id)}`);
                const json = await res.json();

                if (!json.success || !json.data) {
                    AppCore?.notify?.('error', json.message || '작업팀 상세 조회에 실패했습니다.');
                    return;
                }

                openEditModal(json.data);
            } catch (error) {
                console.error(error);
                AppCore?.notify?.('error', '서버 오류가 발생했습니다.');
            }
        });
    }

    function bindModalEvents($) {
        $(document).off('submit', '#workTeamForm');
        $(document).on('submit', '#workTeamForm', function (event) {
            event.preventDefault();

            const formData = new FormData(this);
            const teamName = String(formData.get('team_name') || '').trim();

            if (!teamName) {
                AppCore?.notify?.('warning', '팀명은 필수입니다.');
                return;
            }

            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            $.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done((res) => {
                if (res.success) {
                    workTeamModal?.hide();
                    workTeamTable?.ajax.reload(null, false);
                    AppCore?.notify?.('success', '저장 완료');
                } else {
                    AppCore?.notify?.('error', res.message || '저장 실패');
                }
            })
            .fail(() => AppCore?.notify?.('error', '서버 오류가 발생했습니다.'))
            .always(() => {
                if (submitButton) submitButton.disabled = false;
            });
        });

        $('#btnDeleteWorkTeam').on('click', function () {
            const id = $('#modal_work_team_id').val();
            if (!id || !confirm('삭제하시겠습니까?')) return;

            $.post(API.DELETE, { id })
                .done((res) => {
                    if (res.success) {
                        workTeamModal?.hide();
                        workTeamTable?.ajax.reload(null, false);
                        AppCore?.notify?.('success', '삭제 완료');
                    } else {
                        AppCore?.notify?.('error', res.message || '삭제 실패');
                    }
                });
        });
    }

    function openCreateModal() {
        resetForm();
        document.getElementById('workTeamModalLabel').textContent = '작업팀 등록';
        document.getElementById('btnDeleteWorkTeam').style.display = 'none';
        setTeamLeaderSelect2({});
        workTeamModal?.show();
    }

    function openEditModal(data) {
        resetForm();
        document.getElementById('workTeamModalLabel').textContent = '작업팀 수정';
        document.getElementById('btnDeleteWorkTeam').style.display = '';
        fillForm(data);
        setTeamLeaderSelect2(data);
        workTeamModal?.show();
    }

    function resetForm() {
        const form = document.getElementById('workTeamForm');
        if (form) form.reset();
        document.getElementById('modal_work_team_id').value = '';
        document.getElementById('btnDeleteWorkTeam').style.display = 'none';
        setTeamLeaderSelect2({});
    }

    function fillForm(data) {
        Object.entries(data).forEach(([key, value]) => {
            const el = document.getElementById(`modal_work_team_${key}`);
            if (!el) return;
            el.value = value ?? '';
        });
    }

    function initClientSelect2() {
        const el = document.getElementById('modal_work_team_team_leader_client_id');
        if (!el || clientSelect2Inited) return;

        const $el = window.jQuery(el);

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        AdminPicker.select2Ajax(el, {
            url: API.CLIENT_SEARCH,
            placeholder: '팀장 거래처 검색',
            minimumInputLength: 0,
            dropdownParent: window.jQuery('#workTeamModal'),
            width: '100%',
            templateResult(item) {
                if (!item.id) return item.text;
                if (item.isQuickCreate) {
                    return window.jQuery(
                        '<div class="select2-action-option"><span class="fw-semibold text-primary">+ 신규 거래처 추가</span></div>'
                    );
                }
                if (item.isNone) return item.text;
                return item.text;
            },
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20
                };
            },
            processResults(json, params) {
                const rows = json?.results ?? json?.data ?? [];
                const term = String(params?.term ?? '').trim();

                return {
                    results: [
                        { id: '__none__', text: '선택(없음)', isNone: true },
                        ...rows.map(row => ({
                            id: String(row.id ?? ''),
                            text: row.text ?? row.client_name ?? '',
                            raw: row
                        })).filter(item => item.id !== ''),
                        {
                            id: '__quick_client__',
                            text: '+ 신규 거래처 추가',
                            isQuickCreate: true,
                            term
                        }
                    ]
                };
            }
        });

        $el.off('select2:select.workTeamClient');
        $el.on('select2:select.workTeamClient', function (event) {
            const item = event.params?.data;
            if (!item) return;

            if (item?.id === '__none__') {
                window.jQuery(this).val(null).trigger('change');
                return;
            }

            if (item.id === '__quick_client__') {
                window.jQuery(this).val(null).trigger('change');
                window.jQuery(this).select2('close');
                openWorkTeamClientQuickCreate(item.term || '');
                return;
            }

            window.jQuery(this).val(String(item.id)).trigger('change');
        });

        clientSelect2Inited = true;
    }

    function setTeamLeaderSelect2(data) {
        const clientId = String(data.team_leader_client_id ?? '').trim();
        const $el = window.jQuery('#modal_work_team_team_leader_client_id');

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

    function openWorkTeamClientQuickCreate(defaultName = '') {
        openClientQuickCreate({
            select: document.getElementById('modal_work_team_team_leader_client_id'),
            initialValues: {
                client_name: defaultName
            },
            openDetail: openWorkTeamClientDetailModal,
            onSuccess() {
                AppCore?.notify?.('success', '거래처가 등록되었습니다.');
            },
            getOptionText(values) {
                return values.client_name || '';
            }
        });
    }

    async function openWorkTeamClientDetailModal(values = {}) {
        const template = document.getElementById('work-team-client-modal-template');
        if (!(template instanceof HTMLTemplateElement)) {
            AppCore?.notify?.('error', '거래처 상세 모달 템플릿을 찾을 수 없습니다.');
            return;
        }

        const root = document.createElement('div');
        root.dataset.workTeamClientDetailRoot = '1';
        root.appendChild(template.content.cloneNode(true));
        document.body.appendChild(root);

        const modalEl = root.querySelector('#clientModal');
        const form = root.querySelector('#client-edit-form');

        if (!modalEl || !form) {
            root.remove();
            AppCore?.notify?.('error', '거래처 상세 모달을 초기화할 수 없습니다.');
            return;
        }

        const titleEl = modalEl.querySelector('#clientModalLabel');
        if (titleEl) titleEl.textContent = '거래처 신규 등록';

        const deleteBtn = modalEl.querySelector('#btnDeleteClient');
        if (deleteBtn) deleteBtn.style.display = 'none';

        fillFormValue(form, 'client_name', values.client_name);
        fillFormValue(form, 'ceo_name', values.ceo_name);
        fillFormValue(form, 'phone', values.phone);
        fillFormValue(form, 'is_active', '1');

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
            backdrop: 'static',
            focus: false
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            root.remove();
        }, { once: true });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(form);
            const clientName = String(formData.get('client_name') || '').trim();

            if (!clientName) {
                AppCore?.notify?.('warning', '거래처명을 입력하세요.');
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            try {
                const response = await fetch(API.CLIENT_SAVE, {
                    method: 'POST',
                    body: formData
                });
                const json = await response.json();

                if (!json.success || !json.id) {
                    AppCore?.notify?.('error', json.message || '거래처 등록에 실패했습니다.');
                    return;
                }

                const $client = window.jQuery('#modal_work_team_team_leader_client_id');
                $client.find(`option[value="${json.id}"]`).remove();
                $client.append(new Option(clientName, json.id, true, true));
                $client.val(json.id).trigger('change');

                AppCore?.notify?.('success', '거래처가 등록되었습니다.');
                modal.hide();
            } catch (error) {
                console.error(error);
                AppCore?.notify?.('error', '서버 오류가 발생했습니다.');
            } finally {
                if (submitButton) submitButton.disabled = false;
            }
        }, { once: true });

        modal.show();
    }

    function fillFormValue(form, name, value) {
        if (value == null || String(value).trim() === '') return;

        const field = form.elements.namedItem(name);
        if (!field || field instanceof RadioNodeList) return;

        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function initAdminDatePicker() {
        if (todayPicker) return todayPicker;

        const container = document.getElementById('today-picker');
        if (!container) return null;

        todayPicker = AdminPicker.create({
            type: 'today',
            container
        });

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
        document.querySelectorAll('.admin-date').forEach(input => {
            if (input.dataset.dateInputBound === '1') return;
            input.dataset.dateInputBound = '1';

            input.addEventListener('input', () => {
                input.value = formatDateInputValue(input.value);
            });

            input.addEventListener('blur', () => {
                input.value = normalizeDateInputValue(input.value);
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

        if (typeof picker.clearDate === 'function') {
            picker.clearDate();
        }

        input.value = normalizeDateInputValue(input.value);

        if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            const date = new Date(input.value);
            if (!Number.isNaN(date.getTime())) {
                picker.setDate(date);
            }
        }

        picker.open({ anchor: input });
    }

    function formatDateInputValue(value) {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 8);

        if (digits.length <= 4) return digits;
        if (digits.length <= 6) return `${digits.slice(0, 4)}-${digits.slice(4)}`;

        return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
    }

    function normalizeDateInputValue(value) {
        const formatted = formatDateInputValue(value);
        const match = formatted.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (!match) return formatted;

        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const date = new Date(year, month - 1, day);

        if (
            date.getFullYear() !== year ||
            date.getMonth() !== month - 1 ||
            date.getDate() !== day
        ) {
            AppCore?.notify?.('warning', '올바른 날짜를 입력하세요.');
            return '';
        }

        return formatted;
    }

    function formatDate(date) {
        if (!date) return '';

        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');

        return `${y}-${m}-${d}`;
    }

    function normalizeStartEnd(type) {
        const start = document.querySelector('input[name="dateStart"]');
        const end = document.querySelector('input[name="dateEnd"]');

        if (!start || !end) return;
        if (!start.value || !end.value) return;

        if (type === 'start' && start.value > end.value) {
            end.value = start.value;
        }

        if (type === 'end' && end.value < start.value) {
            start.value = end.value;
        }
    }

    function updateCount(count) {
        const el = document.getElementById('workTeamCount');
        if (el) el.textContent = `총 ${count ?? 0}건`;
    }

    function openTrashModal() {
        const modalEl = document.getElementById('workTeamTrashModal');
        if (!modalEl) return;

        modalEl.dataset.listUrl = API.TRASH;
        modalEl.dataset.restoreUrl = API.RESTORE;
        modalEl.dataset.restoreBulkUrl = API.RESTORE_BULK;
        modalEl.dataset.restoreAllUrl = API.RESTORE_ALL;
        modalEl.dataset.deleteUrl = API.PURGE;
        modalEl.dataset.deleteBulkUrl = API.PURGE_BULK;
        modalEl.dataset.deleteAllUrl = API.PURGE_ALL;

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function bindTrashEvents() {
        window.TrashColumns = window.TrashColumns || {};
        window.TrashColumns.workTeam = function (row) {
            return `
                <td>${row.sort_no ?? ''}</td>
                <td>${escapeHtml(row.team_name ?? '')}</td>
                <td>${escapeHtml(row.team_leader_client_name ?? '')}</td>
                <td>${row.deleted_at ?? ''}</td>
                <td>${escapeHtml(row.deleted_by_name ?? row.deleted_by ?? '')}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">영구삭제</button>
                </td>
            `;
        };

        document.addEventListener('trash:detail-render', function (event) {
            const { data, modal } = event.detail;
            if (modal.dataset.type !== 'workTeam') return;

            const detailBox = modal.querySelector('.trash-detail');
            if (!detailBox) return;

            detailBox.innerHTML = `
                <div class="p-3">
                    <h6 class="mb-3">작업팀 상세</h6>
                    <div><b>팀명:</b> ${escapeHtml(data.team_name ?? '')}</div>
                    <div><b>팀장:</b> ${escapeHtml(data.team_leader_client_name ?? '')}</div>
                    <div><b>상태:</b> ${Number(data.is_active) === 1 ? '사용' : '미사용'}</div>
                    <div><b>비고:</b> ${escapeHtml(data.note ?? '')}</div>
                    <div><b>메모:</b> ${escapeHtml(data.memo ?? '')}</div>
                </div>
            `;
        });

        document.addEventListener('trash:changed', (event) => {
            if (event.detail?.type === 'workTeam') {
                workTeamTable?.ajax.reload(null, false);
            }
        });
    }

    function bindExcelEvents() {
        document.addEventListener('excel:uploaded', () => {
            workTeamTable?.ajax.reload(null, false);
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();

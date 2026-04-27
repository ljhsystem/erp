import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    const API = {
        LIST: '/api/settings/system/code/list',
        DETAIL: '/api/settings/system/code/detail',
        GROUPS: '/api/settings/system/code/groups',
        SAVE: '/api/settings/system/code/save',
        DELETE: '/api/settings/system/code/delete',
        TRASH: '/api/settings/system/code/trash',
        RESTORE: '/api/settings/system/code/restore',
        RESTORE_BULK: '/api/settings/system/code/restore-bulk',
        RESTORE_ALL: '/api/settings/system/code/restore-all',
        PURGE: '/api/settings/system/code/purge',
        PURGE_BULK: '/api/settings/system/code/purge-bulk',
        PURGE_ALL: '/api/settings/system/code/purge-all',
        REORDER: '/api/settings/system/code/reorder',
        EXCEL_UPLOAD: '/api/settings/system/code/excel-upload',
        EXCEL_DOWNLOAD: '/api/settings/system/code/excel',
        EXCEL_TEMPLATE: '/api/settings/system/code/template'
    };

    const CODE_COLUMN_MAP = {
        sort_no: { label: '순번', visible: true, className: 'text-center' },
        code_group: { label: '코드그룹', visible: true },
        code: { label: '코드', visible: true },
        code_name: { label: '코드명', visible: true },
        note: { label: '비고', visible: true },
        memo: { label: '메모', visible: false },
        is_active: { label: '상태', visible: true, className: 'text-center' },
        extra_data: { label: '추가속성', visible: false },
        created_at: { label: '생성일', visible: false },
        created_by_name: { label: '생성자', visible: false },
        updated_at: { label: '수정일', visible: false },
        updated_by_name: { label: '수정자', visible: false },
        deleted_at: { label: '삭제일', visible: false },
        deleted_by_name: { label: '삭제자', visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '등록일시' },
        { value: 'updated_at', label: '수정일시' }
    ];
    const NEW_CODE_GROUP_VALUE = '__new_code_group__';

    let codeTable = null;
    let codeModal = null;
    let excelModal = null;
    let todayPicker = null;
    let codeGroups = [];

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('jQuery not loaded');
            return;
        }

        initCodePage(window.jQuery);
    });

    function initCodePage($) {
        initModal();
        initAdminDatePicker();
        initExcelDataset();
        loadCodeGroups();
        initDataTable($);
        bindRowReorder(codeTable, {
            api: API.REORDER,
            onSuccess() {
                AppCore?.notify?.('success', '기준정보 순번이 저장되었습니다.');
                codeTable?.ajax.reload(null, false);
            },
            onError(json) {
                AppCore?.notify?.('error', json?.message || '기준정보 순번 저장에 실패했습니다.');
                codeTable?.ajax.reload(null, false);
            }
        });
        bindTableEvents($);
        bindModalEvents($);
        bindAdminDateInputs();
        bindDateIconPicker();
        bindExcelEvents();
        bindTrashEvents();
    }

    function initModal() {
        const modalEl = document.getElementById('codeModal');
        if (!modalEl) return;

        codeModal = new bootstrap.Modal(modalEl, { focus: false });
        modalEl.addEventListener('hidden.bs.modal', resetForm);

        const excelModalEl = document.getElementById('codeExcelModal');
        if (excelModalEl) {
            excelModal = new bootstrap.Modal(excelModalEl);
        }
    }

    function initExcelDataset() {
        const excelForm = document.getElementById('codeExcelForm');
        if (!excelForm) return;

        excelForm.dataset.templateUrl = API.EXCEL_TEMPLATE;
        excelForm.dataset.downloadUrl = API.EXCEL_DOWNLOAD;
        excelForm.dataset.uploadUrl = API.EXCEL_UPLOAD;
    }

    function initDataTable($) {
        codeTable = createDataTable({
            tableSelector: '#code-table',
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
                    text: '새 기준정보',
                    className: 'btn btn-warning btn-sm',
                    action: openCreateModal
                }
            ]
        });

        window.codeTable = codeTable;

        if (codeTable) {
            codeTable.on('init.dt draw.dt xhr.dt', () => {
                updateCount(codeTable.page.info()?.recordsDisplay ?? 0);
            });

            codeTable.on('xhr.dt', (event, settings, json) => {
                const rows = Array.isArray(json?.data) ? json.data : [];
                mergeCodeGroups(rows.map((row) => row.code_group));
            });

            SearchForm({
                table: codeTable,
                apiList: API.LIST,
                tableId: 'code',
                defaultSearchField: 'code_name',
                dateOptions: DATE_OPTIONS
            });

            bindTableHighlight('#code-table', codeTable);
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

        Object.entries(CODE_COLUMN_MAP).forEach(([field, config]) => {
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

                    return escapeHtml(data);
                }
            });
        });

        return columns;
    }

    function bindTableEvents($) {
        $('#code-table tbody')
            .on('dblclick', 'tr', async function () {
                const row = codeTable.row(this).data();
                if (!row?.id) return;
                await openEditById(row.id);
            });
    }

    async function openEditById(id) {
        try {
            const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
            const json = await res.json();

            if (!json.success || !json.data) {
                AppCore?.notify?.('error', json.message || '기준정보 상세 조회에 실패했습니다.');
                return;
            }

            openEditModal(json.data);
        } catch (error) {
            console.error(error);
            AppCore?.notify?.('error', '서버 오류가 발생했습니다.');
        }
    }

    function bindModalEvents($) {
        $(document).off('submit', '#codeForm');
        $(document).on('submit', '#codeForm', function (event) {
            event.preventDefault();

            const formData = new FormData(this);
            const codeGroup = normalizeCodeGroup(getModalCodeGroupValue());
            const code = String(formData.get('code') || '').trim();
            const codeName = String(formData.get('code_name') || '').trim();

            if (!codeGroup || !code || !codeName) {
                AppCore?.notify?.('warning', '코드그룹, 코드, 코드명은 필수입니다.');
                return;
            }

            if (!isValidCodeGroup(codeGroup)) {
                AppCore?.notify?.('warning', '코드그룹은 영문과 _만 사용할 수 있습니다.');
                return;
            }

            formData.set('code_group', codeGroup);
            formData.set('code', code.toUpperCase());

            const extraData = String(formData.get('extra_data') || '').trim();
            if (extraData) {
                try {
                    JSON.parse(extraData);
                } catch (error) {
                    AppCore?.notify?.('warning', '추가 속성은 올바른 JSON 형식이어야 합니다.');
                    return;
                }
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
                    codeModal?.hide();
                    codeTable?.ajax.reload(null, false);
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

        $('#btnDeleteCode').on('click', function () {
            const id = $('#modal_code_id').val();
            if (!id || !confirm('삭제하시겠습니까?')) return;

            deleteCodeById($, id, true);
        });

        $('#modal_code_code').on('input', function () {
            this.value = this.value.toUpperCase();
        });

        $('#modal_code_group, #modal_code_group_input').on('input change', function () {
            syncCodeGroupInputs(this);
        });

        $('#btnBackCodeGroupSelect').on('click', function () {
            showCodeGroupSelect('');
        });

    }

    function openCreateModal() {
        resetForm();
        document.getElementById('codeModalLabel').textContent = '기준정보 등록';
        setModalCodeGroup('');
        document.getElementById('btnDeleteCode').style.display = 'none';
        codeModal?.show();
    }

    function openEditModal(data) {
        resetForm();
        document.getElementById('codeModalLabel').textContent = '기준정보 수정';
        document.getElementById('btnDeleteCode').style.display = '';
        fillForm(data);
        codeModal?.show();
    }

    function resetForm() {
        const form = document.getElementById('codeForm');
        if (form) form.reset();

        document.getElementById('modal_code_id').value = '';
        setModalCodeGroup('');
        document.getElementById('btnDeleteCode').style.display = 'none';
    }

    function fillForm(data) {
        Object.entries(data).forEach(([key, value]) => {
            const el = document.getElementById(`modal_code_${key}`);
            if (!el) return;
            el.value = value ?? '';
        });

        setModalCodeGroup(data.code_group ?? '');
    }

    function setModalCodeGroup(value) {
        const normalized = normalizeCodeGroup(value);
        const select = document.getElementById('modal_code_group');
        const input = document.getElementById('modal_code_group_input');

        if (!normalized) {
            showCodeGroupSelect('');
            return;
        }

        const hasOption = select
            ? Array.from(select.options).some((option) => option.value === normalized)
            : false;

        if (hasOption) {
            showCodeGroupSelect(normalized);
            return;
        }

        showCodeGroupInput(normalized);
    }

    function getModalCodeGroupValue() {
        const inputWrap = document.getElementById('modal_code_group_input_wrap');
        const inputValue = document.getElementById('modal_code_group_input')?.value || '';
        const selectValue = document.getElementById('modal_code_group')?.value || '';

        if (inputWrap && !inputWrap.classList.contains('d-none')) {
            return inputValue;
        }

        return selectValue === NEW_CODE_GROUP_VALUE ? '' : selectValue;
    }

    async function loadCodeGroups() {
        try {
            const res = await fetch(`${API.GROUPS}?_=${Date.now()}`, { cache: 'no-store' });
            const json = await res.json();
            const groups = Array.isArray(json.data) ? json.data : [];

            mergeCodeGroups(groups);
        } catch (error) {
            console.error('[code] groups load failed', error);
        }
    }

    function mergeCodeGroups(groups) {
        const merged = new Set(codeGroups);

        groups.forEach((group) => {
            const value = normalizeCodeGroup(group);
            if (value) merged.add(value);
        });

        codeGroups = Array.from(merged).sort();
        renderGroupOptions(document.getElementById('modal_code_group'), codeGroups, '선택');
    }

    function renderGroupOptions(select, groups, firstLabel) {
        if (!select) return;

        const currentValue = select.value;
        select.innerHTML = '';

        const first = document.createElement('option');
        first.value = '';
        first.textContent = firstLabel;
        select.appendChild(first);

        groups.forEach((group) => {
            const value = normalizeCodeGroup(group);
            if (!value) return;

            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        });

        const create = document.createElement('option');
        create.value = NEW_CODE_GROUP_VALUE;
        create.textContent = '+ 신규 코드그룹 생성';
        select.appendChild(create);

        if (currentValue && Array.from(select.options).some((option) => option.value === currentValue)) {
            select.value = currentValue;
        } else {
            select.value = '';
        }
    }

    function syncCodeGroupInputs(source) {
        const select = document.getElementById('modal_code_group');
        const input = document.getElementById('modal_code_group_input');

        if (!select || !input) return;

        if (source === select && select.value === NEW_CODE_GROUP_VALUE) {
            showCodeGroupInput('');
            return;
        }

        if (source === select && select.value) {
            input.value = '';
            return;
        }

        if (source === input) {
            input.value = normalizeCodeGroup(input.value);
        }
    }

    function showCodeGroupSelect(value = '') {
        const select = document.getElementById('modal_code_group');
        const input = document.getElementById('modal_code_group_input');
        const inputWrap = document.getElementById('modal_code_group_input_wrap');

        if (inputWrap) inputWrap.classList.add('d-none');
        if (select) {
            select.classList.remove('d-none');
            select.disabled = false;
            select.required = true;
            select.value = value;
        }
        if (input) {
            input.required = false;
            input.value = '';
        }
    }

    function showCodeGroupInput(value = '') {
        const select = document.getElementById('modal_code_group');
        const input = document.getElementById('modal_code_group_input');
        const inputWrap = document.getElementById('modal_code_group_input_wrap');

        if (select) {
            select.classList.add('d-none');
            select.disabled = true;
            select.required = false;
            select.value = '';
        }
        if (inputWrap) inputWrap.classList.remove('d-none');
        if (input) {
            input.required = true;
            input.value = normalizeCodeGroup(value);
            input.focus();
        }
    }

    function normalizeCodeGroup(value) {
        return String(value || '').trim().replace(/\s+/g, '').toUpperCase();
    }

    function isValidCodeGroup(value) {
        return /^[A-Z_]+$/.test(value);
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

            normalizeStartEnd(
                input.name === 'dateStart' ? 'start' : 'end'
            );

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
        if (document.__codeDateIconPickerBound) return;
        document.__codeDateIconPickerBound = true;

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

    function deleteCodeById($, id, closeModal) {
        $.post(API.DELETE, { id })
            .done((res) => {
                if (res.success) {
                    if (closeModal) codeModal?.hide();
                    codeTable?.ajax.reload(null, false);
                    AppCore?.notify?.('success', '삭제 완료');
                } else {
                    AppCore?.notify?.('error', res.message || '삭제 실패');
                }
            })
            .fail(() => AppCore?.notify?.('error', '서버 오류가 발생했습니다.'));
    }

    function bindExcelEvents() {
        document.addEventListener('excel:uploaded', () => {
            codeTable?.ajax.reload(null, false);
        });
    }

    function updateCount(count) {
        const el = document.getElementById('codeCount');
        if (el) el.textContent = `총 ${count ?? 0}건`;
    }

    function openTrashModal() {
        const modalEl = document.getElementById('codeTrashModal');
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
        window.TrashColumns.code = function (row) {
            return `
                <td>${row.sort_no ?? ''}</td>
                <td>${escapeHtml(row.code ?? '')}</td>
                <td>${escapeHtml(row.code_name ?? '')}</td>
                <td>${Number(row.is_active) === 1 ? '사용' : '미사용'}</td>
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
            if (modal.dataset.type !== 'code') return;

            const detailBox = modal.querySelector('.trash-detail');
            if (!detailBox) return;

            detailBox.innerHTML = `
                <div class="p-3">
                    <h6 class="mb-3">기준정보 상세</h6>
                    <div><b>순번:</b> ${escapeHtml(data.sort_no ?? '')}</div>
                    <div><b>코드:</b> ${escapeHtml(data.code ?? '')}</div>
                    <div><b>코드명:</b> ${escapeHtml(data.code_name ?? '')}</div>
                    <div><b>상태:</b> ${Number(data.is_active) === 1 ? '사용' : '미사용'}</div>
                    <div><b>비고:</b> ${escapeHtml(data.note ?? '')}</div>
                    <div><b>메모:</b> ${escapeHtml(data.memo ?? '')}</div>
                </div>
            `;
        });

        document.addEventListener('trash:changed', (event) => {
            if (event.detail?.type === 'code') {
                codeTable?.ajax.reload(null, false);
            }
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

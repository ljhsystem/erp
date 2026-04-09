// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/settings/base/bank.account.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, updateTableHeight, forceTableHeightSync, bindTableHighlight } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    console.log('[base-bank-account.js] loaded');

    /* =========================
       API / 상수
    ========================= */
    const API = {
        LIST: "/api/settings/base-info/bank-account/list",
        DETAIL: "/api/settings/base-info/bank-account/detail",

        SAVE: "/api/settings/base-info/bank-account/save",
        DELETE: "/api/settings/base-info/bank-account/delete",

        TRASH: "/api/settings/base-info/bank-account/trash",
        RESTORE: "/api/settings/base-info/bank-account/restore",
        RESTORE_BULK: "/api/settings/base-info/bank-account/restore-bulk",
        RESTORE_ALL: "/api/settings/base-info/bank-account/restore-all",

        PURGE: "/api/settings/base-info/bank-account/purge",
        PURGE_BULK: "/api/settings/base-info/bank-account/purge-bulk",
        PURGE_ALL: "/api/settings/base-info/bank-account/purge-all",

        REORDER: "/api/settings/base-info/bank-account/reorder",

        EXCEL_UPLOAD: "/api/settings/base-info/bank-account/excel-upload",
        EXCEL_DOWNLOAD: "/api/settings/base-info/bank-account/excel",
        EXCEL_TEMPLATE: "/api/settings/base-info/bank-account/template"
    };

    /* =========================
       계좌 컬럼 한글 매핑
    ========================= */
    const ACCOUNT_COLUMN_MAP = {
        code             : { label: "코드",     visible: true  },
        alias            : { label: "별칭",     visible: true  },
        account_name     : { label: "계좌명",   visible: true  },
        bank_name        : { label: "은행명",   visible: true  },
        account_number   : { label: "계좌번호", visible: true  },
        account_holder   : { label: "예금주",   visible: true  },
        currency         : { label: "통화",     visible: false },
        bank_book_file   : { label: "통장사본", visible: false },
        note             : { label: "비고",     visible: true  },

        is_active        : { label: "사용여부", visible: false },

        created_at       : { label: "생성일시", visible: false },
        created_by_name  : { label: "생성자",   visible: false },
        updated_at       : { label: "수정일시", visible: false },
        updated_by_name  : { label: "수정자",   visible: false },
        deleted_at       : { label: "삭제일시", visible: false },
        deleted_by_name  : { label: "삭제자",   visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '등록일자' },
        { value: 'updated_at', label: '수정일자' }
    ];

    let accountTable = null;
    let accountModal = null;
    let excelModal = null;
    let todayPicker = null;
    let globalBound = false;

    /* ============================================================
       DOM READY
    ============================================================ */
    document.addEventListener('DOMContentLoaded', async () => {
        if (!window.jQuery) {
            console.error('jQuery not loaded');
            return;
        }

        const $ = window.jQuery;
        initAccountPage($);
    });

    /* ============================================================
       PAGE INIT
    ============================================================ */
    function initAccountPage($) {

        initModal();
        initAdminDatePicker();
        initBankBookUpload();
        initExcelDataset();

        initDataTable($);

        bindTableEvents($);
        bindModalEvents($);
        bindAdminDateInputs();
        bindTableLayoutEvents(accountTable, '#account-table');
        bindUIEvents();
        bindExcelEvents();
        bindTrashEvents();
        bindGlobalEvents();
    }

    /* ============================================================
       EXCEL DATASET
    ============================================================ */
    function initExcelDataset() {

        const excelForm = document.getElementById('accountExcelForm');
        if (!excelForm) return;

        excelForm.dataset.templateUrl = API.EXCEL_TEMPLATE;
        excelForm.dataset.downloadUrl = API.EXCEL_DOWNLOAD;
        excelForm.dataset.uploadUrl   = API.EXCEL_UPLOAD;
    }

    /* ============================================================
       MODAL
    ============================================================ */
    function initModal() {

        const modalEl = document.getElementById('accountModal');
        if (!modalEl) return;

        accountModal = new bootstrap.Modal(modalEl, { focus: false });

        const excelEl = document.getElementById('accountExcelModal');
        if (excelEl) {
            excelModal = new bootstrap.Modal(excelEl);
        }

        modalEl.addEventListener('hidden.bs.modal', () => {

            const form = document.getElementById('accountForm');
            if (form) form.reset();

            const idEl = getIdEl();
            if (idEl) idEl.value = '';

            const codeEl = document.getElementById('modal_code');
            if (codeEl) codeEl.value = '';

            const deleteBtn = document.getElementById('btnDeleteAccount');
            if (deleteBtn) deleteBtn.style.display = 'none';

            resetBankBookUI();
        });

        document.querySelectorAll('.date-icon').forEach(icon => {
            icon.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();

                const wrap = icon.closest('.date-input, .date-input-wrap');
                const input = wrap ? wrap.querySelector('input') : null;
                if (!input) return;

                const picker = initAdminDatePicker();
                if (!picker) return;

                picker.__target = input;

                if (typeof picker.clearDate === 'function') {
                    picker.clearDate();
                }

                const v = input.value;
                if (v) {
                    const d = new Date(v);
                    if (!isNaN(d)) picker.setDate(d);
                }

                picker.open({ anchor: input });
            });
        });

        modalEl.addEventListener('shown.bs.modal', () => {
            bindAdminDateInputs();
        });
    }

    /* ============================================================
       TABLE LAYOUT
    ============================================================ */
    function bindTableLayoutEvents(table, tableSelector) {
        if (!table) return;

        window.addEventListener('resize', () => {
            updateTableHeight(table, tableSelector);
        });

        document.addEventListener('sidebar:toggled', () => {
            updateTableHeight(table, tableSelector);

            setTimeout(() => {
                forceTableHeightSync(table, tableSelector);
            }, 340);
        });
    }

    /* ============================================================
       UI EVENTS
    ============================================================ */
    function bindUIEvents() {

        const btnRemoveBankBook =
            document.getElementById('btnDeleteBankBook') ||
            document.getElementById('btnRemoveBankBook');

        if (btnRemoveBankBook) {
            btnRemoveBankBook.addEventListener('click', function () {
                if (!confirm('통장사본을 삭제하시겠습니까?')) return;

                const input = getBankBookInputEl();
                const del = getDeleteBankBookEl();
                const list = getBankBookListEl();
                const drop = getBankBookDropEl();
                const text = getBankBookTextEl();

                if (input) input.value = '';
                if (del) del.value = '1';

                if (list) {
                    list.dataset.original = '0';
                    list.innerHTML = `
                        <div class="file-item">
                            <span>📄 <strong>통장사본</strong></span>
                            <div class="file-status text-danger">
                                통장사본이 삭제됩니다. 저장 시 반영됩니다.
                            </div>
                        </div>
                    `;
                }

                if (drop) drop.dataset.original = '0';

                if (text) {
                    text.innerHTML = `
                        여기로 파일을 끌어다 놓거나 클릭하여 업로드
                        <br>
                        (PDF, JPG, PNG)
                    `;
                }
            });
        }
    }

    /* ============================================================
       EXCEL
    ============================================================ */
    function bindExcelEvents() {
        document.addEventListener('excel:uploaded', () => {
            if (accountTable) {
                accountTable.ajax.reload(null, false);
            }
        });
    }

    /* ============================================================
       GLOBAL
    ============================================================ */
    function bindGlobalEvents() {
        if (globalBound) return;
        globalBound = true;

        document.addEventListener('input', onGlobalInput);
    }

    function onGlobalInput(e) {
        const type = e.target.dataset.format;
        if (!type) return;

        if (type === 'currency') {
            e.target.value = String(e.target.value || '').toUpperCase().slice(0, 10);
        }
    }

    /* ============================================================
       TRASH
    ============================================================ */
    function bindTrashEvents() {

        document.addEventListener('trash:detail-render', function(e) {

            const { data, modal } = e.detail;
            if (modal.dataset.type !== 'account') return;

            const detailBox = modal.querySelector('.trash-detail');
            if (!detailBox) return;

            let html = `
                <div class="p-3">
                    <h6 class="mb-3">계좌 상세</h6>
            `;

            Object.entries(ACCOUNT_COLUMN_MAP).forEach(([key, config]) => {
                const value = data[key];
                if (value === null || value === undefined || value === '') return;

                let displayValue = value;

                if (key === 'bank_book_file') {
                    displayValue = value ? '등록됨' : '';
                }

                html += `<div><b>${config.label}:</b> ${displayValue}</div>`;
            });

            html += `</div>`;
            detailBox.innerHTML = html;
        });

        window.TrashColumns = window.TrashColumns || {};

        window.TrashColumns.account = function(row) {
            return `
                <td>${row.code ?? ''}</td>
                <td>${row.alias ?? ''}</td>
                <td>${row.account_name ?? ''}</td>
                <td>${row.bank_name ?? ''}</td>
                <td>${row.account_number ?? ''}</td>
                <td>${row.deleted_at ?? ''}</td>
                <td>${row.deleted_by_name ?? row.deleted_by ?? ''}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">영구삭제</button>
                </td>
            `;
        };

        document.addEventListener('trash:changed', (e) => {
            const { type } = e.detail || {};
            if (type === 'account') {
                if (accountTable) {
                    accountTable.ajax.reload(null, false);
                }
            }
        });
    }

    /* ============================================================
       DATE PICKER
    ============================================================ */
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
            input.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();

                const picker = initAdminDatePicker();
                if (!picker) return;

                picker.__target = input;

                if (typeof picker.clearDate === 'function') {
                    picker.clearDate();
                }

                const v = input.value;
                if (v) {
                    const d = new Date(v);
                    if (!isNaN(d)) picker.setDate(d);
                }

                picker.open({ anchor: input });
            });
        });
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
        const end   = document.querySelector('input[name="dateEnd"]');

        if (!start || !end) return;
        if (!start.value || !end.value) return;

        if (type === 'start' && start.value > end.value) {
            end.value = start.value;
        }

        if (type === 'end' && end.value < start.value) {
            start.value = end.value;
        }
    }

    /* ============================================================
       DATATABLE
    ============================================================ */
    function initDataTable($) {

        const columns = buildAccountColumns();

        accountTable = createDataTable({
            tableSelector: '#account-table',
            api: API.LIST,
            columns: columns,
            defaultOrder: [[1, "asc"]],
            pageLength: 10,
            buttons: [
                {
                    text: "엑셀관리",
                    className: "btn btn-success btn-sm",
                    action: function () {
                        if (excelModal) {
                            excelModal.show();
                        }
                    }
                },
                {
                    text: "휴지통",
                    className: "btn btn-danger btn-sm",
                    action: function () {
                        const trashModalEl = document.getElementById('accountTrashModal');
                        if (!trashModalEl) return;

                        trashModalEl.dataset.listUrl        = API.TRASH;
                        trashModalEl.dataset.restoreUrl     = API.RESTORE;
                        trashModalEl.dataset.restoreBulkUrl = API.RESTORE_BULK;
                        trashModalEl.dataset.restoreAllUrl  = API.RESTORE_ALL;

                        trashModalEl.dataset.deleteUrl      = API.PURGE;
                        trashModalEl.dataset.deleteBulkUrl  = API.PURGE_BULK;
                        trashModalEl.dataset.deleteAllUrl   = API.PURGE_ALL;

                        const modal = new bootstrap.Modal(trashModalEl);
                        modal.show();
                    }
                },
                {
                    text: "새 계좌",
                    className: "btn btn-warning btn-sm",
                    action: function () {

                        const form = document.getElementById('accountForm');
                        if (form) form.reset();

                        const idEl = getIdEl();
                        const codeEl = document.getElementById('modal_code');

                        if (idEl) idEl.value = '';
                        if (codeEl) codeEl.value = '';

                        const deleteBtn = document.getElementById('btnDeleteAccount');
                        if (deleteBtn) deleteBtn.style.display = 'none';

                        const createdAtEl =
                            document.getElementById('account_created_at') ||
                            document.getElementById('modal_created_at');

                        if (createdAtEl && !createdAtEl.value) {
                            const d = new Date();
                            createdAtEl.value = d.toISOString().slice(0, 10);
                        }

                        resetBankBookUI();

                        if (accountModal) {
                            accountModal.show();
                        }
                    }
                }
            ]
        });

        window.accountTable = accountTable;

        if (accountTable) {
            console.log('✅ Account DataTable 생성 완료');

            SearchForm({
                table: accountTable,
                apiList: API.LIST,
                tableId: 'account',
                defaultSearchField: 'account_name',
                dateOptions: DATE_OPTIONS
            });

            updateTableHeight(accountTable, '#account-table');
            bindTableHighlight('#account-table', accountTable);
        }

        bindRowReorder(accountTable, { api: API.REORDER });
    }

    /* ============================================================
       TABLE EVENTS
    ============================================================ */
    function bindTableEvents($) {

        $(document).on('focus', '#modal_code', function() {
            AppCore?.notify?.('info', '코드를 입력하지 않아도 저장 시 자동 생성됩니다.');
        });

        $('#account-table tbody').on('dblclick', 'tr', async function () {

            const row = accountTable.row(this).data();
            if (!row) return;

            try {
                const res = await fetch(API.DETAIL + '?id=' + row.id);
                const json = await res.json();

                if (!json.success) {
                    AppCore?.notify?.('error', '상세조회 실패');
                    return;
                }

                const data = json.data;

                const deleteBtn = document.getElementById('btnDeleteAccount');
                if (deleteBtn) deleteBtn.style.display = '';

                const idEl = getIdEl();
                if (idEl) idEl.value = data.id;

                const delFile = getDeleteBankBookEl();
                const fileInput = getBankBookInputEl();

                if (delFile) delFile.value = '0';
                if (fileInput) fileInput.value = '';

                fillModal(data);
                accountModal.show();

            } catch (e) {
                console.error(e);
                AppCore?.notify?.('error', '서버 오류');
            }
        });

        $('#account-table tbody').on('click', 'td', function () {
            const cell = accountTable.cell(this);
            const value = cell.data();
            const colIndex = cell.index().column;
            const field = accountTable.column(colIndex).dataSrc();

            if (!field) return;

            const $first = $('.search-condition').first();
            $first.find('select').val(field);
            $first.find('input').val(value);
        });
    }

    /* ============================================================
       MODAL SAVE / DELETE
    ============================================================ */
    function bindModalEvents($) {

        $(document).off('submit', '#accountForm');

        $(document).on('submit', '#accountForm', function (e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);

            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            $.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(res => {
                if (!res.success) {
                    AppCore?.notify?.('error', res.message || '저장 실패');
                    return;
                }

                accountModal.hide();
                accountTable.ajax.reload(null, false);

                AppCore?.notify?.('success', '저장 완료');
            })
            .fail(() => {
                AppCore?.notify?.('error', '서버 오류');
            })
            .always(() => {
                if (btn) btn.disabled = false;
            });
        });

        $('#btnDeleteAccount').off('click').on('click', function () {

            const id = getIdEl()?.value || '';
            if (!id || !confirm('삭제하시겠습니까?')) return;

            $.post(API.DELETE, { id })
                .done(res => {
                    if (res.success) {
                        AppCore?.notify?.('success', '삭제 완료');
                        accountTable.ajax.reload(null, false);
                        accountModal.hide();
                    } else {
                        alert(res.message || '삭제 실패');
                    }
                });
        });
    }

    /* ============================================================
       UTIL
    ============================================================ */
    function fillModal(data) {

        Object.keys(data).forEach(key => {

            if (key === 'id') return;
            if (key === 'bank_book_file') return;

            const byId =
                document.getElementById('account_' + key) ||
                document.getElementById('modal_' + key);

            const byName = document.querySelector(`#accountForm [name="${key}"]`);

            const el = byId || byName;
            if (!el) return;

            el.value = data[key] ?? '';
        });

        renderBankBook(data);
    }

    function buildAccountColumns() {

        const columns = [];

        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            width: "40px",
            className: "reorder-handle no-colvis text-center",
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>'
        });

        Object.entries(ACCOUNT_COLUMN_MAP).forEach(([field, config]) => {

            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                defaultContent: "",
                render: function(data, type) {

                    if (data == null) return "";
                    if (type !== 'display') return data;

                    if (field === 'bank_book_file') {
                        return data ? '등록됨' : '';
                    }

                    if (field === 'is_active') {
                        return String(data) === '1'
                            ? '<span class="badge bg-success">사용</span>'
                            : '<span class="badge bg-secondary">미사용</span>';
                    }

                    return data;
                }
            });
        });

        return columns;
    }

    /* ============================================================
       FILE: 통장사본
    ============================================================ */
    function initBankBookUpload() {

        const drop = getBankBookDropEl();
        const input = getBankBookInputEl();
        const text = getBankBookTextEl();

        if (!drop || !input || !text) return;

        if (!drop.dataset.original) {
            drop.dataset.original = "0";
        }

        function renderFile(file) {
            const hasOriginal = drop.dataset.original === "1";
            const message = hasOriginal
                ? "저장 시 기존 통장사본이 교체됩니다."
                : "저장 시 통장사본이 등록됩니다.";

            const shortName = shortenFileName(file.name, 20);

            text.innerHTML = `
                📄 <strong>통장사본</strong>
                <br>
                (${shortName})
                <br>
                <span class="text-primary">${message}</span>
            `;
        }

        drop.addEventListener('click', () => input.click());

        input.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) return;
            renderFile(file);
        });

        drop.addEventListener('dragover', e => {
            e.preventDefault();
        });

        drop.addEventListener('drop', e => {
            e.preventDefault();

            const file = e.dataTransfer.files[0];
            if (!file) return;

            input.files = e.dataTransfer.files;
            renderFile(file);
        });
    }

    function renderBankBook(data) {

        const list = getBankBookListEl();
        const text = getBankBookTextEl();
        const drop = getBankBookDropEl();

        if (!list && !text) return;

        const filePath = data.bank_book_file || '';

        if (list) list.innerHTML = '';

        if (filePath) {

            const fileName = filePath.split('/').pop();
            const path = encodeURIComponent(filePath);

            if (drop) drop.dataset.original = '1';

            if (list) {
                list.innerHTML = `
                    <div class="file-item">
                        <span>📄 <strong>통장사본</strong> (${fileName})</span>
                        <div class="file-actions">
                            <a href="/api/file/preview?path=${path}" target="_blank">미리보기</a>
                            <span class="file-divider">|</span>
                            <a href="javascript:void(0)" id="btnDeleteBankBookInline">삭제</a>
                        </div>
                    </div>
                `;

                const inlineDelete = document.getElementById('btnDeleteBankBookInline');
                if (inlineDelete) {
                    inlineDelete.onclick = function() {
                        if (!confirm('통장사본을 삭제하시겠습니까?')) return;

                        const input = getBankBookInputEl();
                        const del = getDeleteBankBookEl();

                        if (input) input.value = '';
                        if (del) del.value = '1';
                        if (drop) drop.dataset.original = '0';

                        list.innerHTML = `
                            <div class="file-item">
                                <span>📄 <strong>통장사본</strong> (${fileName})</span>
                                <div class="file-status text-danger">
                                    통장사본이 삭제됩니다. 저장 시 반영됩니다.
                                </div>
                            </div>
                        `;
                    };
                }
            }

            if (text) {
                text.innerHTML = `
                    통장사본 등록됨
                    <br>
                    <span class="text-primary">클릭 또는 드롭으로 교체 가능</span>
                `;
            }

        } else {
            if (drop) drop.dataset.original = '0';

            if (text) {
                text.innerHTML = `
                    여기로 파일을 끌어다 놓거나 클릭하여 업로드
                    <br>
                    (PDF, JPG, PNG)
                `;
            }

            if (list) {
                list.innerHTML = '';
            }
        }
    }

    function resetBankBookUI() {

        const input = getBankBookInputEl();
        const del = getDeleteBankBookEl();
        const list = getBankBookListEl();
        const text = getBankBookTextEl();
        const drop = getBankBookDropEl();

        if (input) input.value = '';
        if (del) del.value = '0';
        if (list) list.innerHTML = '';
        if (drop) drop.dataset.original = '0';

        if (text) {
            text.innerHTML = `
                여기로 파일을 끌어다 놓거나 클릭하여 업로드
                <br>
                (PDF, JPG, PNG)
            `;
        }
    }

    function getIdEl() {
        return (
            document.getElementById('account_id') ||
            document.getElementById('modal_account_id') ||
            document.querySelector('#accountForm [name="id"]')
        );
    }

    function getBankBookInputEl() {
        return (
            document.getElementById('modal_bank_book_file') ||
            document.getElementById('modal_bank_file') ||
            document.querySelector('#accountForm [name="bank_book_file"]')
        );
    }

    function getDeleteBankBookEl() {
        return (
            document.getElementById('delete_bank_book_file') ||
            document.getElementById('delete_bank_file') ||
            document.querySelector('#accountForm [name="delete_bank_book_file"]')
        );
    }

    function getBankBookListEl() {
        return (
            document.getElementById('bankBookList') ||
            document.getElementById('bankBookPreview')
        );
    }

    function getBankBookTextEl() {
        return (
            document.getElementById('bankBookText') ||
            document.getElementById('bankCopyText')
        );
    }

    function getBankBookDropEl() {
        return (
            document.getElementById('bankBookUpload') ||
            document.getElementById('bankCopyUpload')
        );
    }

    function shortenFileName(name, max = 20) {
        if (!name) return '';

        const lastDot = name.lastIndexOf('.');
        if (lastDot <= 0) {
            return name.length <= max
                ? name
                : name.substring(0, Math.max(1, max - 3)) + '...';
        }

        const ext = name.substring(lastDot);
        const base = name.substring(0, lastDot);

        if (name.length <= max) return name;

        const keep = Math.max(1, max - ext.length - 3);
        return base.substring(0, keep) + '...' + ext;
    }

})();
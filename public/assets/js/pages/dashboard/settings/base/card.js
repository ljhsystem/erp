// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/settings/base/card.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, updateTableHeight, forceTableHeightSync, bindTableHighlight } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    console.log('[base-card.js] loaded');

    /* =========================
       API
    ========================= */
    const API = {
        LIST: "/api/settings/base-info/card/list",
        DETAIL: "/api/settings/base-info/card/detail",

        SAVE: "/api/settings/base-info/card/save",
        DELETE: "/api/settings/base-info/card/delete",

        TRASH: "/api/settings/base-info/card/trash",
        RESTORE: "/api/settings/base-info/card/restore",
        RESTORE_BULK: "/api/settings/base-info/card/restore-bulk",
        RESTORE_ALL: "/api/settings/base-info/card/restore-all",

        PURGE: "/api/settings/base-info/card/purge",
        PURGE_BULK: "/api/settings/base-info/card/purge-bulk",
        PURGE_ALL: "/api/settings/base-info/card/purge-all",

        REORDER: "/api/settings/base-info/card/reorder",

        EXCEL_UPLOAD: "/api/settings/base-info/card/excel-upload",
        EXCEL_DOWNLOAD: "/api/settings/base-info/card/excel",
        EXCEL_TEMPLATE: "/api/settings/base-info/card/template"
    };

    /* =========================
       카드 컬럼 매핑
    ========================= */
    const CARD_COLUMN_MAP = {
        code:             { label: "코드",       visible: true  },
        alias:            { label: "별칭",       visible: true  },
        card_name:        { label: "카드명",     visible: true  },
        card_company:     { label: "카드사",     visible: true  },
        card_number:      { label: "카드번호",   visible: true  },
        card_holder:      { label: "소유자",     visible: true  },
        billing_day:      { label: "결제일",     visible: false },
        expiry_date:      { label: "만기일",     visible: true  },
        status:           { label: "상태",       visible: true  },
        card_image:       { label: "카드이미지", visible: false },
        note:             { label: "비고",       visible: true  },

        is_active:        { label: "사용여부",   visible: false },

        created_at:       { label: "생성일시",   visible: false },
        created_by_name:  { label: "생성자",     visible: false },
        updated_at:       { label: "수정일시",   visible: false },
        updated_by_name:  { label: "수정자",     visible: false },
        deleted_at:       { label: "삭제일시",   visible: false },
        deleted_by_name:  { label: "삭제자",     visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '등록일자' },
        { value: 'updated_at', label: '수정일자' },
        { value: 'expiry_date', label: '만기일' }
    ];

    let cardTable = null;
    let cardModal = null;
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
        initCardPage($);
    });

    /* ============================================================
       PAGE INIT
    ============================================================ */
    function initCardPage($) {

        initModal();
        initAdminDatePicker();
        initCardImageUpload();
        initExcelDataset();

        initDataTable($);

        bindTableEvents($);
        bindModalEvents($);
        bindAdminDateInputs();
        bindTableLayoutEvents(cardTable, '#card-table');
        bindUIEvents();
        bindExcelEvents();
        bindTrashEvents();
        bindGlobalEvents();
    }

    /* ============================================================
       EXCEL DATASET
    ============================================================ */
    function initExcelDataset() {
        const excelForm = document.getElementById('cardExcelForm');
        if (!excelForm) return;

        excelForm.dataset.templateUrl = API.EXCEL_TEMPLATE;
        excelForm.dataset.downloadUrl = API.EXCEL_DOWNLOAD;
        excelForm.dataset.uploadUrl   = API.EXCEL_UPLOAD;
    }

    /* ============================================================
       MODAL
    ============================================================ */
    function initModal() {

        const modalEl = document.getElementById('cardModal');
        if (!modalEl) return;

        cardModal = new bootstrap.Modal(modalEl, { focus: false });

        const excelEl = document.getElementById('cardExcelModal');
        if (excelEl) {
            excelModal = new bootstrap.Modal(excelEl);
        }

        modalEl.addEventListener('hidden.bs.modal', () => {

            const form =
                document.getElementById('cardForm') ||
                document.getElementById('card-edit-form');

            if (form) form.reset();

            const idEl =
                document.getElementById('modal_card_id') ||
                form?.querySelector('[name="id"]');

            if (idEl) idEl.value = '';

            const codeEl =
                document.getElementById('modal_code') ||
                form?.querySelector('[name="code"]');

            if (codeEl) codeEl.value = '';

            const deleteBtn = document.getElementById('btnDeleteCard');
            if (deleteBtn) deleteBtn.style.display = 'none';

            const titleEl =
                document.getElementById('cardModalLabel') ||
                modalEl.querySelector('.modal-title');
            if (titleEl) titleEl.textContent = '카드 정보';

            resetCardImageUI();
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

        const btnRemoveCardImage =
            document.getElementById('btnDeleteCardImage') ||
            document.getElementById('btnRemoveCardImage');

        if (btnRemoveCardImage) {
            btnRemoveCardImage.addEventListener('click', function () {
                if (!confirm('카드 이미지를 삭제하시겠습니까?')) return;

                const input =
                    document.getElementById('modal_card_image') ||
                    document.getElementById('modal_card_file');

                const del =
                    document.getElementById('delete_card_image') ||
                    document.getElementById('delete_card_file');

                if (input) input.value = '';
                if (del) del.value = '1';

                const list =
                    document.getElementById('cardImageList') ||
                    document.getElementById('cardPreview');

                if (list) {
                    list.dataset.original = '0';
                    list.innerHTML = `
                        <div class="file-item">
                            <span>📄 <strong>카드 이미지</strong></span>
                            <div class="file-status text-danger">
                                카드 이미지가 삭제됩니다. 저장 시 반영됩니다.
                            </div>
                        </div>
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
            if (cardTable) {
                cardTable.ajax.reload(null, false);
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

        if (type === 'day') {
            let v = String(e.target.value || '').replace(/\D/g, '');
            if (v !== '') {
                const n = Math.max(1, Math.min(31, Number(v)));
                e.target.value = String(n);
            }
        }
    }

    /* ============================================================
       TRASH
    ============================================================ */
    function bindTrashEvents() {

        document.addEventListener('trash:detail-render', function(e) {

            const { data, modal } = e.detail;
            if (modal.dataset.type !== 'card') return;

            const detailBox = modal.querySelector('.trash-detail');
            if (!detailBox) return;

            let html = `
                <div class="p-3">
                    <h6 class="mb-3">카드 상세</h6>
            `;

            Object.entries(CARD_COLUMN_MAP).forEach(([key, config]) => {
                const value = data[key];
                if (value === null || value === undefined || value === '') return;

                let displayValue = value;

                if (key === 'card_image') {
                    displayValue = value ? '등록됨' : '';
                }

                html += `<div><b>${config.label}:</b> ${displayValue}</div>`;
            });

            html += `</div>`;
            detailBox.innerHTML = html;
        });

        window.TrashColumns = window.TrashColumns || {};

        window.TrashColumns.card = function(row) {
            return `
                <td>${row.code ?? ''}</td>
                <td>${row.alias ?? ''}</td>
                <td>${row.card_name ?? ''}</td>
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
            if (type === 'card') {
                if (cardTable) {
                    cardTable.ajax.reload(null, false);
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

        const columns = buildCardColumns();

        cardTable = createDataTable({
            tableSelector: '#card-table',
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
                        const trashModalEl = document.getElementById('cardTrashModal');
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
                    text: "새 카드",
                    className: "btn btn-warning btn-sm",
                    action: function () {

                        const form =
                            document.getElementById('cardForm') ||
                            document.getElementById('card-edit-form');

                        if (form) form.reset();

                        const idEl =
                            document.getElementById('modal_card_id') ||
                            form?.querySelector('[name="id"]');

                        const codeEl =
                            document.getElementById('modal_code') ||
                            form?.querySelector('[name="code"]');

                        if (idEl) idEl.value = '';
                        if (codeEl) codeEl.value = '';

                        const deleteBtn = document.getElementById('btnDeleteCard');
                        if (deleteBtn) deleteBtn.style.display = 'none';

                        window.isNewCard = true;

                        const titleEl =
                            document.getElementById('cardModalLabel') ||
                            document.querySelector('#cardModal .modal-title');

                        if (titleEl) {
                            titleEl.textContent = '카드 신규 등록';
                        }

                        const expiryEl =
                            document.getElementById('modal_expiry_date') ||
                            form?.querySelector('[name="expiry_date"]');

                        if (expiryEl && !expiryEl.value) {
                            const d = new Date();
                            expiryEl.value = d.toISOString().slice(0, 10);
                        }

                        resetCardImageUI();

                        if (cardModal) {
                            cardModal.show();
                        }
                    }
                }
            ]
        });

        window.cardTable = cardTable;

        if (cardTable) {
            console.log('✅ Card DataTable 생성 완료');

            SearchForm({
                table: cardTable,
                apiList: API.LIST,
                tableId: 'card',
                defaultSearchField: 'card_name',
                dateOptions: DATE_OPTIONS
            });

            updateTableHeight(cardTable, '#card-table');
            bindTableHighlight('#card-table', cardTable);
        }

        bindRowReorder(cardTable, { api: API.REORDER });
    }

    /* ============================================================
       TABLE EVENTS
    ============================================================ */
    function bindTableEvents($) {

        $(document).on('focus', '#modal_code', function() {
            if (window.isNewCard) {
                AppCore?.notify?.('info', '코드를 입력하지 않아도 저장 시 자동 생성됩니다.');
            }
        });

        $('#card-table tbody').on('dblclick', 'tr', async function () {

            const row = cardTable.row(this).data();
            if (!row) return;

            try {
                const res = await fetch(API.DETAIL + '?id=' + row.id);
                const json = await res.json();

                if (!json.success) {
                    AppCore?.notify?.('error', '상세조회 실패');
                    return;
                }

                const data = json.data;

                window.isNewCard = false;

                const deleteBtn = document.getElementById('btnDeleteCard');
                if (deleteBtn) deleteBtn.style.display = '';

                const idEl =
                    document.getElementById('modal_card_id') ||
                    document.querySelector('#cardForm [name="id"]') ||
                    document.querySelector('#card-edit-form [name="id"]');

                if (idEl) idEl.value = data.id;

                const delImage =
                    document.getElementById('delete_card_image') ||
                    document.getElementById('delete_card_file');
                const imageInput =
                    document.getElementById('modal_card_image') ||
                    document.getElementById('modal_card_file');

                if (delImage) delImage.value = '0';
                if (imageInput) imageInput.value = '';

                fillModal(data);

                const titleEl =
                    document.getElementById('cardModalLabel') ||
                    document.querySelector('#cardModal .modal-title');

                if (titleEl) titleEl.textContent = '카드 수정';

                cardModal.show();

            } catch (e) {
                console.error(e);
                AppCore?.notify?.('error', '서버 오류');
            }
        });

        $('#card-table tbody').on('click', 'td', function () {
            const cell = cardTable.cell(this);
            const value = cell.data();
            const colIndex = cell.index().column;
            const field = cardTable.column(colIndex).dataSrc();

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

        $(document).off('submit', '#cardForm');
        $(document).off('submit', '#card-edit-form');

        $(document).on('submit', '#cardForm, #card-edit-form', function (e) {
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

                cardModal.hide();
                cardTable.ajax.reload(null, false);

                AppCore?.notify?.('success', '저장 완료');
            })
            .fail(() => {
                AppCore?.notify?.('error', '서버 오류');
            })
            .always(() => {
                if (btn) btn.disabled = false;
            });
        });

        $('#btnDeleteCard').off('click').on('click', function () {

            const id =
                $('#modal_card_id').val() ||
                $('#cardForm [name="id"]').val() ||
                $('#card-edit-form [name="id"]').val();

            if (!id || !confirm('삭제하시겠습니까?')) return;

            $.post(API.DELETE, { id })
                .done(res => {
                    if (res.success) {
                        AppCore?.notify?.('success', '삭제 완료');
                        cardTable.ajax.reload(null, false);
                        cardModal.hide();
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

        const form =
            document.getElementById('cardForm') ||
            document.getElementById('card-edit-form');

        Object.keys(data).forEach(key => {

            if (key === 'id') return;
            if (key === 'card_image' || key === 'card_file') return;

            const byId = document.getElementById('modal_' + key);
            const byName = form?.querySelector(`[name="${key}"]`);
            const el = byId || byName;

            if (!el) return;

            let value = data[key] ?? '';

            if (key === 'billing_day') {
                value = value ? String(value) : '';
            }

            el.value = value;
        });

        renderCardImage(data);
    }

    function buildCardColumns() {

        const columns = [];

        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            width: "40px",
            className: "reorder-handle no-colvis text-center",
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>'
        });

        Object.entries(CARD_COLUMN_MAP).forEach(([field, config]) => {

            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                defaultContent: "",
                render: function(data, type) {

                    if (data == null) return "";
                    if (type !== 'display') return data;

                    if (field === 'card_number') {
                        return maskCardNumber(data);
                    }

                    if (field === 'card_image') {
                        return data ? '등록됨' : '';
                    }

                    if (field === 'status') {
                        return renderStatusBadge(data);
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

    function maskCardNumber(cardNumber) {
        const raw = String(cardNumber || '').replace(/\D/g, '');
        if (!raw) return '';

        if (raw.length <= 4) return raw;

        const last4 = raw.slice(-4);
        return `****-****-****-${last4}`;
    }

    function renderStatusBadge(status) {
        const s = String(status || '').toLowerCase();

        if (s === 'active' || s === '사용중') {
            return '<span class="badge bg-success">사용중</span>';
        }
        if (s === 'expired' || s === '만기') {
            return '<span class="badge bg-secondary">만기</span>';
        }
        if (s === 'stopped' || s === '정지') {
            return '<span class="badge bg-danger">정지</span>';
        }
        return status || '';
    }

    /* ============================================================
       CARD IMAGE
    ============================================================ */
    function initCardImageUpload() {

        const drop =
            document.getElementById('cardImageUpload') ||
            document.getElementById('dropZoneCard');

        const input =
            document.getElementById('modal_card_image') ||
            document.getElementById('modal_card_file');

        const text =
            document.getElementById('cardImageText') ||
            document.getElementById('dropZoneTextCard');

        if (!drop || !input || !text) return;

        if (!drop.dataset.original) {
            drop.dataset.original = "0";
        }

        function renderFile(file) {
            const hasOriginal = drop.dataset.original === "1";
            const message = hasOriginal
                ? "저장 시 기존 카드 이미지가 교체됩니다."
                : "저장 시 카드 이미지가 등록됩니다.";

            const shortName = shortenFileName(file.name, 20);

            text.innerHTML = `
                📄 <strong>카드 이미지</strong>
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

    function renderCardImage(data) {

        const list =
            document.getElementById('cardImageList') ||
            document.getElementById('cardPreview');

        const text =
            document.getElementById('cardImageText') ||
            document.getElementById('dropZoneTextCard');

        const drop =
            document.getElementById('cardImageUpload') ||
            document.getElementById('dropZoneCard');

        if (!list && !text) return;

        const filePath = data.card_image || data.card_file || '';

        if (list) list.innerHTML = '';

        if (filePath) {

            const fileName = filePath.split('/').pop();
            const path = encodeURIComponent(filePath);

            if (drop) drop.dataset.original = '1';

            if (list) {
                list.innerHTML = `
                    <div class="file-item">
                        <span>📄 <strong>카드 이미지</strong> (${fileName})</span>
                        <div class="file-actions">
                            <a href="/api/file/preview?path=${path}" target="_blank">미리보기</a>
                            <span class="file-divider">|</span>
                            <a href="javascript:void(0)" id="btnDeleteCardImageInline">삭제</a>
                        </div>
                    </div>
                `;

                const inlineDelete = document.getElementById('btnDeleteCardImageInline');
                if (inlineDelete) {
                    inlineDelete.onclick = function() {
                        if (!confirm('카드 이미지를 삭제하시겠습니까?')) return;

                        const input =
                            document.getElementById('modal_card_image') ||
                            document.getElementById('modal_card_file');

                        const del =
                            document.getElementById('delete_card_image') ||
                            document.getElementById('delete_card_file');

                        if (input) input.value = '';
                        if (del) del.value = '1';
                        if (drop) drop.dataset.original = '0';

                        list.innerHTML = `
                            <div class="file-item">
                                <span>📄 <strong>카드 이미지</strong> (${fileName})</span>
                                <div class="file-status text-danger">
                                    카드 이미지가 삭제됩니다. 저장 시 반영됩니다.
                                </div>
                            </div>
                        `;
                    };
                }
            }

            if (text) {
                text.innerHTML = `
                    카드 이미지 등록됨
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
                    (JPG, PNG, WEBP)
                `;
            }

            if (list) {
                list.innerHTML = '';
            }
        }
    }

    function resetCardImageUI() {

        const input =
            document.getElementById('modal_card_image') ||
            document.getElementById('modal_card_file');

        const del =
            document.getElementById('delete_card_image') ||
            document.getElementById('delete_card_file');

        const list =
            document.getElementById('cardImageList') ||
            document.getElementById('cardPreview');

        const text =
            document.getElementById('cardImageText') ||
            document.getElementById('dropZoneTextCard');

        const drop =
            document.getElementById('cardImageUpload') ||
            document.getElementById('dropZoneCard');

        if (input) input.value = '';
        if (del) del.value = '0';
        if (list) list.innerHTML = '';
        if (drop) drop.dataset.original = '0';

        if (text) {
            text.innerHTML = `
                여기로 파일을 끌어다 놓거나 클릭하여 업로드
                <br>
                (JPG, PNG, WEBP)
            `;
        }
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
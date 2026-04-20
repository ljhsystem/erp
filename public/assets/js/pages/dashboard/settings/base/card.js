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
        code             : { label: "코드",       visible: true  },
    
        card_name        : { label: "카드명",     visible: true  },
        client_name      : { label: "카드사",     visible: true  },
        card_number      : { label: "카드번호",   visible: true  },
        card_type        : { label: "카드유형",   visible: true  },
    
        account_name     : { label: "연결계좌",   visible: true  },
        account_id       : { label: "계좌ID",     visible: false },
    
        expiry_year      : { label: "유효기간(년)", visible: false },
        expiry_month     : { label: "유효기간(월)", visible: false },
    
        currency         : { label: "통화",       visible: true  },
        limit_amount     : { label: "한도",       visible: true  },
    
        card_file        : { label: "카드이미지", visible: false },
    
        note             : { label: "비고",       visible: true  },
        memo             : { label: "메모",       visible: false },
    
        is_active        : { label: "사용여부",   visible: false },
    
        created_at       : { label: "생성일시",   visible: false },
        created_by_name  : { label: "생성자",     visible: false },
        updated_at       : { label: "수정일시",   visible: false },
        updated_by_name  : { label: "수정자",     visible: false },
        deleted_at       : { label: "삭제일시",   visible: false },
        deleted_by_name  : { label: "삭제자",     visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '등록일자' },
        { value: 'updated_at', label: '수정일자' }
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

        let selectInitialized = false;

        modalEl.addEventListener('shown.bs.modal', () => {
        
            if (!selectInitialized) {
                initSelectPickers();
                selectInitialized = true;
            }
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

        const btnRemoveCardFile =
            document.getElementById('btnDeleteCardFile') ||
            document.getElementById('btnRemoveCardFile');
    
        if (btnRemoveCardFile) {
            btnRemoveCardFile.addEventListener('click', function () {
                if (!confirm('카드 이미지를 삭제하시겠습니까?')) return;
    
                const input = getCardFileInputEl();
                const del = getDeleteCardFileEl();
                const list = getCardFileListEl();
                const drop = getCardFileDropEl();
                const text = getCardFileTextEl();
    
                if (input) input.value = '';
                if (del) del.value = '1';
    
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
                <td>${row.card_name ?? ''}</td>
                <td>${row.client_name ?? ''}</td>
                <td>${row.card_number ?? ''}</td>
                <td>${row.account_name ?? ''}</td>
                <td>${row.currency ?? ''}</td>
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

                        AdminPicker.clearSelect2('#cardClientSelect', true);
                        AdminPicker.clearSelect2('#cardAccountSelect', true);

                        const expiryYearEl = form?.querySelector('[name="expiry_year"]');
                        const expiryMonthEl = form?.querySelector('[name="expiry_month"]');
                        const currencyEl = form?.querySelector('[name="currency"]');
                        const isActiveEl = form?.querySelector('[name="is_active"]');
                        const cardTypeEl = form?.querySelector('[name="card_type"]');
                        
                        if (expiryYearEl) expiryYearEl.value = '';
                        if (expiryMonthEl) expiryMonthEl.value = '';
                        if (currencyEl && !currencyEl.value) currencyEl.value = 'KRW';
                        if (isActiveEl) isActiveEl.value = '1';
                        if (cardTypeEl) cardTypeEl.value = 'corporate';

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

            cardTable.on('init.dt', () => {
                updateCardCount(cardTable.page.info()?.recordsDisplay ?? 0);
            });

            cardTable.on('draw.dt', () => {
                updateCardCount(cardTable.page.info()?.recordsDisplay ?? 0);
            });

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

    function updateCardCount(count) {
        const el = document.getElementById('cardCount');
        if (!el) return;
        el.textContent = `총 ${count ?? 0}건`;
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

                const delFile = getDeleteCardFileEl();
                const fileInput = getCardFileInputEl();
                
                if (delFile) delFile.value = '0';
                if (fileInput) fileInput.value = '';

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
            const cardName = String(formData.get('card_name') || '').trim();
            const cardNumber = String(formData.get('card_number') || '').trim();
            const currency = String(formData.get('currency') || '').trim().toUpperCase();
            const expiryYear = String(formData.get('expiry_year') || '').trim();
            const expiryMonth = String(formData.get('expiry_month') || '').trim();
            const limitAmount = String(formData.get('limit_amount') || '').trim();

            if (!cardName) {
                AppCore?.notify?.('warning', '카드명은 필수입니다.');
                return;
            }

            if (currency && !/^[A-Z]{3}$/.test(currency)) {
                AppCore?.notify?.('warning', '통화 코드는 3자리 영문으로 입력해주세요.');
                return;
            }

            if (cardNumber && !/^[0-9-]+$/.test(cardNumber)) {
                AppCore?.notify?.('warning', '카드번호는 숫자와 하이픈만 입력할 수 있습니다.');
                return;
            }

            if (expiryYear && !/^\d{4}$/.test(expiryYear)) {
                AppCore?.notify?.('warning', '유효기간(년)은 4자리 숫자로 입력해주세요.');
                return;
            }

            if (expiryMonth && !/^(0?[1-9]|1[0-2])$/.test(expiryMonth)) {
                AppCore?.notify?.('warning', '유효기간(월)은 1부터 12 사이로 입력해주세요.');
                return;
            }

            if (limitAmount && Number(limitAmount) < 0) {
                AppCore?.notify?.('warning', '한도금액은 0 이상이어야 합니다.');
                return;
            }

            formData.set('currency', currency || 'KRW');

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
                        AppCore?.notify?.('error', res.message || '삭제 실패');
                    }
                })
                .fail(() => {
                    AppCore?.notify?.('error', '서버 오류');
                });
        });
    }

    /* ============================================================
       UTIL
    ============================================================ */
    function fillModal(data) {

        const form = document.getElementById('cardForm');
        if (!form) return;
    
        /* 기본값 세팅 */
        Object.keys(data).forEach(key => {
    
            if (key === 'id') return;
            if (key === 'card_file') return;
    
            const el = form.querySelector(`[name="${key}"]`);
            if (!el) return;
    
            if (el.type === 'file') {
                el.value = '';
                return;
            }
    
            let value = data[key] ?? '';
    
            if (key === 'limit_amount' && value !== '') {
                value = Number(value);
            }
    
            el.value = value;
        });
    
        /* 🔥 Select2 값 세팅 (반복문 밖으로 이동) */
        setSelect2Initial('#cardClientSelect', data.client_id, data.client_name);
        setSelect2Initial('#cardAccountSelect', data.account_id, data.account_name);
    
        renderCardFile(data);
    }

    function setSelect2Initial(selector, id, text) {

        if (!id) return;
    
        const el = document.querySelector(selector);
        if (!el) return;
    
        const option = new Option(text || '', id, true, true);
        el.append(option);
    
        AdminPicker.setSelect2Value(selector, id);
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
                    
                    if (field === 'card_file') {
                        if (!data) return '';
                        const path = encodeURIComponent(data);
                        return `
                            <a href="/api/file/preview?path=${path}" target="_blank">
                                📄 보기
                            </a>
                        `;
                    }
                    
                    if (field === 'card_type') {
                        if (data === 'corporate') return '법인';
                        if (data === 'personal') return '개인';
                        if (data === 'virtual') return '가상';
                        return data;
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


    /* ============================================================
       CARD IMAGE
    ============================================================ */
    function initCardImageUpload() {

        const drop = getCardFileDropEl();
        const input = getCardFileInputEl();
        const text = getCardFileTextEl();
    
        if (!drop || !input || !text) return;
    
        if (!drop.dataset.original) {
            drop.dataset.original = "0";
        }
    
        function renderFile(file) {
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            const allowed = ['pdf', 'jpg', 'jpeg', 'png'];

            if (!allowed.includes(ext)) {
                AppCore?.notify?.('warning', '카드 이미지는 PDF, JPG, PNG 파일만 업로드할 수 있습니다.');
                input.value = '';
                return false;
            }

            if (file.size > 10 * 1024 * 1024) {
                AppCore?.notify?.('warning', '카드 이미지 파일은 10MB 이하만 업로드할 수 있습니다.');
                input.value = '';
                return false;
            }

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

            return true;
        }
    
        drop.addEventListener('click', () => input.click());
    
        input.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) return;
            if (!renderFile(file)) {
                input.value = '';
            }
        });
    
        drop.addEventListener('dragover', e => {
            e.preventDefault();
        });
    
        drop.addEventListener('drop', e => {
            e.preventDefault();
    
            const file = e.dataTransfer.files[0];
            if (!file) return;

            input.files = e.dataTransfer.files;
            if (!renderFile(file)) {
                input.value = '';
            }
        });
    }

    function renderCardFile(data) {

        const list = getCardFileListEl();
        const text = getCardFileTextEl();
        const drop = getCardFileDropEl();
    
        if (!text) return;
    
        const filePath = data.card_file || '';
    
        if (list) list.innerHTML = '';
    
        if (filePath) {
    
            const path = encodeURIComponent(filePath);
    
            if (drop) {
                drop.dataset.original = "1";
            }
    
            text.innerHTML = `
                <div class="file-status">
                    <div class="upload-guide">
                        여기로 파일을 끌어다 놓거나 클릭하여 업로드
                        <br>
                        (PDF, JPG, PNG)
                    </div>
                    <div class="file-line">
                        📄 <strong>카드 이미지 등록됨</strong>
                    </div>
                    <div class="file-links">
                        <a href="javascript:void(0)"
                           id="btnOpenCardFile"
                           class="file-link-open disabled">
                           미리보기
                        </a>
                        <span class="file-divider">|</span>
                        <a href="javascript:void(0)"
                           id="btnDeleteCardFileInline"
                           class="file-link-delete disabled">
                           삭제
                        </a>
                    </div>
                </div>
            `;
    
            const btnOpen = document.getElementById('btnOpenCardFile');
            const btnDelete = document.getElementById('btnDeleteCardFileInline');
    
            if (btnOpen) {
                btnOpen.classList.remove('disabled');
                btnOpen.href = "/api/file/preview?path=" + path;
                btnOpen.target = "_blank";
    
                btnOpen.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }
    
            if (btnDelete) {
                btnDelete.classList.remove('disabled');
    
                btnDelete.onclick = function (e) {
    
                    e.stopPropagation();
    
                    if (!confirm('카드 이미지를 삭제하시겠습니까?')) return;
    
                    const input = getCardFileInputEl();
                    const del = getDeleteCardFileEl();
    
                    if (input) input.value = '';
                    if (del) del.value = '1';
                    if (drop) drop.dataset.original = "0";
    
                    text.innerHTML = `
                        <div class="upload-guide">
                            여기로 파일을 끌어다 놓거나 클릭하여 업로드
                            <br>
                            (PDF, JPG, PNG)
                        </div>
                        <div class="file-status text-danger">
                            ⚠ 카드 이미지가 삭제됩니다. 저장 시 반영됩니다.
                        </div>
                    `;
                };
            }
    
        } else {
    
            if (drop) {
                drop.dataset.original = "0";
            }
    
            text.innerHTML = `
                여기로 파일을 끌어다 놓거나 클릭하여 업로드
                <br>
                (PDF, JPG, PNG)
            `;
        }
    }

    function resetCardImageUI() {

        const input = getCardFileInputEl();
        const del = getDeleteCardFileEl();
        const list = getCardFileListEl();
        const text = getCardFileTextEl();
        const drop = getCardFileDropEl();
    
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

    function getCardFileInputEl() {
        return (
            document.getElementById('modal_card_file') ||
            document.querySelector('#cardForm [name="card_file"]')
        );
    }
    
    function getDeleteCardFileEl() {
        return (
            document.getElementById('delete_card_file') ||
            document.querySelector('#cardForm [name="delete_card_file"]')
        );
    }
    
    function getCardFileListEl() {
        return (
            document.getElementById('cardFileList') ||
            document.getElementById('cardPreview')
        );
    }
    
    function getCardFileTextEl() {
        return (
            document.getElementById('cardUploadText') ||
            document.getElementById('cardImageText')
        );
    }
    
    function getCardFileDropEl() {
        return (
            document.getElementById('cardUpload') ||
            document.getElementById('cardImageUpload')
        );
    }
    function initSelectPickers() {

        /* 카드사 */
        AdminPicker.select2Ajax('#cardClientSelect', {
            url: '/api/settings/base-info/client/search-picker',
            minimumInputLength: 0,
    
            dataBuilder(params) {
                return {
                    q: params.term || ''
                };
            },
    
            processResults(data) {
                
                const rows = data?.data ?? [];
    
                return {
                    results: rows.map(row => ({
                        id: row.id,
                        text: row.client_name
                    }))
                };
            }
        });
    
        /* 결제계좌 */
        AdminPicker.select2Ajax('#cardAccountSelect', {
            url: '/api/settings/base-info/bank-account/search-picker',
            minimumInputLength: 0,
    
            dataBuilder(params) {
                return {
                    q: params.term || ''
                };
            },
    
            processResults(data) {     
                const rows = data?.data ?? [];
    
                return {
                    results: rows.map(row => ({
                        id: row.id,
                        text: `${row.account_name} (${row.bank_name})`
                    }))
                };
            }
        });
    }

})();

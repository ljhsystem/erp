// Path: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/base/card.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { formatAmount, initNumberInputs, parseNumber } from '/public/assets/js/common/format.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    const API = {
        LIST: '/api/settings/base-info/card/list',
        DETAIL: '/api/settings/base-info/card/detail',
        SAVE: '/api/settings/base-info/card/save',
        DELETE: '/api/settings/base-info/card/delete',
        TRASH: '/api/settings/base-info/card/trash',
        RESTORE: '/api/settings/base-info/card/restore',
        RESTORE_BULK: '/api/settings/base-info/card/restore-bulk',
        RESTORE_ALL: '/api/settings/base-info/card/restore-all',
        PURGE: '/api/settings/base-info/card/purge',
        PURGE_BULK: '/api/settings/base-info/card/purge-bulk',
        PURGE_ALL: '/api/settings/base-info/card/purge-all',
        REORDER: '/api/settings/base-info/card/reorder',
        EXCEL_UPLOAD: '/api/settings/base-info/card/excel-upload',
        EXCEL_DOWNLOAD: '/api/settings/base-info/card/excel',
        EXCEL_TEMPLATE: '/api/settings/base-info/card/template'
    };

    const CARD_COLUMN_MAP = {
        sort_no: { label: '순번', visible: true, width: '70px', className: 'text-center' },
        card_name: { label: '카드명', visible: true, width: '180px' },
        client_name: { label: '카드사', visible: true, width: '150px' },
        card_number: { label: '카드번호', visible: true, width: '170px' },
        card_type: { label: '카드유형', visible: true, width: '110px' },
        account_name: { label: '결제계좌', visible: true, width: '170px' },
        account_id: { label: '계좌ID', visible: false, width: '220px' },
        expiry_year: { label: '유효기간(년)', visible: false, width: '110px', className: 'text-center' },
        expiry_month: { label: '유효기간(월)', visible: false, width: '110px', className: 'text-center' },
        currency: { label: '통화', visible: true, width: '80px', className: 'text-center' },
        limit_amount: { label: '한도금액', visible: true, width: '130px', className: 'text-end' },
        card_file: { label: '카드이미지', visible: false, width: '120px', className: 'text-center' },
        note: { label: '비고', visible: true, width: '200px' },
        memo: { label: '메모', visible: false, width: '220px' },
        is_active: { label: '상태', visible: true, width: '90px', className: 'text-center' },
        created_at: { label: '등록일시', visible: false, width: '150px' },
        created_by_name: { label: '등록자', visible: false, width: '140px' },
        updated_at: { label: '수정일시', visible: false, width: '150px' },
        updated_by_name: { label: '수정자', visible: false, width: '140px' },
        deleted_at: { label: '삭제일시', visible: false, width: '150px' },
        deleted_by_name: { label: '삭제자', visible: false, width: '140px' }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '등록일자' },
        { value: 'updated_at', label: '수정일자' }
    ];

    const CARD_TYPE_LABELS = {
        corporate: '법인카드',
        personal: '개인카드',
        virtual: '가상카드'
    };

    let cardTable = null;
    let cardModal = null;
    let excelModal = null;
    let todayPicker = null;
    let selectInitialized = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('jQuery not loaded');
            return;
        }

        initCardPage(window.jQuery);
    });

    function initCardPage($) {
        initModal();
        initNumberInputs('#cardForm .number-input');
        initAdminDatePicker();
        bindAdminDateInputs();
        bindDateIconPicker();
        initCardImageUpload();
        initExcelDataset();
        initDataTable($);
        bindTableEvents($);
        bindModalEvents($);
        bindUIEvents();
        bindExcelEvents();
        bindTrashEvents();
        bindGlobalEvents();
    }

    function initExcelDataset() {
        const excelForm = document.getElementById('cardExcelForm');
        if (!excelForm) return;

        excelForm.dataset.templateUrl = API.EXCEL_TEMPLATE;
        excelForm.dataset.downloadUrl = API.EXCEL_DOWNLOAD;
        excelForm.dataset.uploadUrl = API.EXCEL_UPLOAD;
    }

    function initModal() {
        const modalEl = document.getElementById('cardModal');
        if (!modalEl) return;

        cardModal = new bootstrap.Modal(modalEl, { focus: false });

        const excelEl = document.getElementById('cardExcelModal');
        if (excelEl) {
            excelModal = new bootstrap.Modal(excelEl);
        }

        modalEl.addEventListener('hidden.bs.modal', () => {
            resetCardForm();
            setModalTitle('카드 정보');
            window.isNewCard = false;
        });

        modalEl.addEventListener('shown.bs.modal', () => {
            initNumberInputs('#cardForm .number-input');

            if (!selectInitialized) {
                initSelectPickers();
                selectInitialized = true;
            }
        });
    }

    function resetCardForm() {
        const form = getForm();
        if (form) form.reset();

        const idEl = getIdEl();
        if (idEl) idEl.value = '';

        const deleteBtn = document.getElementById('btnDeleteCard');
        if (deleteBtn) deleteBtn.style.display = 'none';

        AdminPicker.clearSelect2('#cardClientSelect', true);
        AdminPicker.clearSelect2('#cardAccountSelect', true);

        const currencyEl = form?.querySelector('[name="currency"]');
        const activeEl = form?.querySelector('[name="is_active"]');
        const cardTypeEl = form?.querySelector('[name="card_type"]');
        const deleteFileEl = getDeleteCardFileEl();

        if (currencyEl) currencyEl.value = 'KRW';
        if (activeEl) activeEl.value = '1';
        if (cardTypeEl) cardTypeEl.value = 'corporate';
        if (deleteFileEl) deleteFileEl.value = '0';

        resetCardImageUI();
    }

    function bindUIEvents() {
        const btnRemoveCardFile = document.getElementById('btnDeleteCardFile') || document.getElementById('btnRemoveCardFile');
        if (!btnRemoveCardFile || btnRemoveCardFile.dataset.bound === '1') return;

        btnRemoveCardFile.dataset.bound = '1';
        btnRemoveCardFile.addEventListener('click', () => {
            markCardFileDeleted();
        });
    }

    function bindExcelEvents() {
        document.addEventListener('excel:uploaded', () => {
            cardTable?.ajax.reload(null, false);
        });
    }

    function bindGlobalEvents() {
        if (document.__cardGlobalBound) return;
        document.__cardGlobalBound = true;

        document.addEventListener('input', (e) => {
            const input = e.target;
            if (!input?.matches?.('[name="expiry_year"], [name="expiry_month"], [data-format="currency"]')) return;

            if (input.name === 'expiry_year') {
                input.value = String(input.value || '').replace(/\D/g, '').slice(0, 4);
            }

            if (input.name === 'expiry_month') {
                input.value = String(input.value || '').replace(/\D/g, '').slice(0, 2);
            }

            if (input.dataset.format === 'currency') {
                input.value = String(input.value || '').replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 3);
            }
        });
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
        if (document.__cardDateIconPickerBound) return;
        document.__cardDateIconPickerBound = true;

        document.addEventListener('click', (e) => {
            const icon = e.target.closest('.date-icon');
            if (!icon) return;

            const wrap = icon.closest('.date-input, .date-input-wrap');
            const input = wrap ? wrap.querySelector('input.admin-date, input[name="dateStart"], input[name="dateEnd"]') : null;
            if (!input) return;

            e.preventDefault();
            e.stopPropagation();
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
        if (!start || !end || !start.value || !end.value) return;

        if (type === 'start' && start.value > end.value) end.value = start.value;
        if (type === 'end' && end.value < start.value) start.value = end.value;
    }

    function initDataTable($) {
        const columns = buildCardColumns();

        cardTable = createDataTable({
            tableSelector: '#card-table',
            api: API.LIST,
            columns,
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            autoWidth: false,
            buttons: [
                {
                    text: '엑셀관리',
                    className: 'btn btn-success btn-sm',
                    action: () => excelModal?.show()
                },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal
                },
                {
                    text: '새 카드',
                    className: 'btn btn-warning btn-sm',
                    action: openCreateModal
                }
            ]
        });

        window.cardTable = cardTable;

        if (cardTable) {
            cardTable.on('init.dt draw.dt', () => {
                updateCardCount(cardTable.page.info()?.recordsDisplay ?? 0);
            });

            SearchForm({
                table: cardTable,
                apiList: API.LIST,
                tableId: 'card',
                defaultSearchField: 'card_name',
                dateOptions: DATE_OPTIONS,
                normalizeFilters: normalizeCardFilters
            });

            bindTableHighlight('#card-table', cardTable);
        }

        bindRowReorder(cardTable, { api: API.REORDER });
    }

    function openTrashModal() {
        const trashModalEl = document.getElementById('cardTrashModal');
        if (!trashModalEl) return;

        trashModalEl.dataset.listUrl = API.TRASH;
        trashModalEl.dataset.restoreUrl = API.RESTORE;
        trashModalEl.dataset.restoreBulkUrl = API.RESTORE_BULK;
        trashModalEl.dataset.restoreAllUrl = API.RESTORE_ALL;
        trashModalEl.dataset.deleteUrl = API.PURGE;
        trashModalEl.dataset.deleteBulkUrl = API.PURGE_BULK;
        trashModalEl.dataset.deleteAllUrl = API.PURGE_ALL;

        new bootstrap.Modal(trashModalEl).show();
    }

    function openCreateModal() {
        resetCardForm();
        window.isNewCard = true;
        setModalTitle('카드 신규 등록');
        cardModal?.show();
    }

    function updateCardCount(count) {
        const el = document.getElementById('cardCount');
        if (!el) return;
        el.textContent = `총 ${count ?? 0}건`;
    }

    function normalizeCardFilters(filters) {
        return (filters || []).map(filter => {
            if (filter?.field === 'is_active') {
                const value = normalizeActiveValue(filter.value);
                return value === '' ? null : { field: 'is_active', value };
            }

            if (filter?.field === 'card_type') {
                const value = normalizeCardTypeValue(filter.value);
                return value === '' ? filter : { field: 'card_type', value };
            }

            return filter;
        }).filter(Boolean);
    }

    function normalizeActiveValue(value) {
        const raw = String(value ?? '').trim().toLowerCase();
        if (['1', '사용', '사용중', '활성', 'active', 'y', 'yes', 'true'].includes(raw)) return '1';
        if (['0', '미사용', '비활성', 'inactive', 'n', 'no', 'false'].includes(raw)) return '0';
        return '';
    }

    function bindTableEvents($) {
        $('#card-table tbody').on('dblclick', 'tr', async function () {
            const row = cardTable.row(this).data();
            if (!row) return;

            try {
                const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(row.id)}`);
                const json = await res.json();

                if (!json.success || !json.data) {
                    AppCore?.notify?.('error', json.message || '카드 상세 조회에 실패했습니다.');
                    return;
                }

                window.isNewCard = false;
                setModalTitle('카드 정보 수정');

                const deleteBtn = document.getElementById('btnDeleteCard');
                if (deleteBtn) deleteBtn.style.display = '';

                const idEl = getIdEl();
                if (idEl) idEl.value = json.data.id;

                const delFile = getDeleteCardFileEl();
                const fileInput = getCardFileInputEl();
                if (delFile) delFile.value = '0';
                if (fileInput) fileInput.value = '';

                fillModal(json.data);
                cardModal?.show();
            } catch (err) {
                console.error(err);
                AppCore?.notify?.('error', '서버 오류가 발생했습니다.');
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

    function bindModalEvents($) {
        $(document).off('submit', '#cardForm');

        $(document).on('submit', '#cardForm', function (e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const cardName = String(formData.get('card_name') || '').trim();
            const cardNumber = String(formData.get('card_number') || '').trim();
            const currency = String(formData.get('currency') || '').trim().toUpperCase();
            const expiryYear = String(formData.get('expiry_year') || '').trim();
            const expiryMonth = String(formData.get('expiry_month') || '').trim().padStart(2, '0');
            const limitAmountRaw = String(formData.get('limit_amount') || '').trim();
            const limitAmount = limitAmountRaw === '' ? 0 : parseNumber(limitAmountRaw);

            if (!cardName) {
                AppCore?.notify?.('warning', '카드명은 필수입니다.');
                return;
            }

            if (cardNumber && !/^[0-9-]+$/.test(cardNumber)) {
                AppCore?.notify?.('warning', '카드번호는 숫자와 하이픈만 입력할 수 있습니다.');
                return;
            }

            if (!/^[A-Z]{3}$/.test(currency || 'KRW')) {
                AppCore?.notify?.('warning', '통화 코드는 3자리 영문으로 입력하세요.');
                return;
            }

            if (expiryYear && !/^\d{4}$/.test(expiryYear)) {
                AppCore?.notify?.('warning', '유효기간 년도는 4자리 숫자로 입력하세요.');
                return;
            }

            if (expiryMonth && !/^(0[1-9]|1[0-2])$/.test(expiryMonth)) {
                AppCore?.notify?.('warning', '유효기간 월은 01부터 12까지 입력하세요.');
                return;
            }

            if (limitAmount < 0) {
                AppCore?.notify?.('warning', '한도금액은 0 이상이어야 합니다.');
                return;
            }

            formData.set('card_type', normalizeCardTypeValue(formData.get('card_type')) || 'corporate');
            formData.set('currency', currency || 'KRW');
            formData.set('expiry_month', expiryMonth === '00' ? '' : expiryMonth);
            formData.set('limit_amount', String(limitAmount));

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
                        AppCore?.notify?.('error', res.message || '저장에 실패했습니다.');
                        return;
                    }

                    cardModal?.hide();
                    cardTable?.ajax.reload(null, false);
                    AppCore?.notify?.('success', '저장되었습니다.');
                })
                .fail(() => {
                    AppCore?.notify?.('error', '서버 오류가 발생했습니다.');
                })
                .always(() => {
                    if (btn) btn.disabled = false;
                });
        });

        $('#btnDeleteCard').off('click').on('click', () => {
            const id = getIdEl()?.value || '';
            if (!id || !confirm('삭제하시겠습니까?')) return;

            $.post(API.DELETE, { id })
                .done(res => {
                    if (res.success) {
                        AppCore?.notify?.('success', '삭제되었습니다.');
                        cardTable?.ajax.reload(null, false);
                        cardModal?.hide();
                    } else {
                        AppCore?.notify?.('error', res.message || '삭제에 실패했습니다.');
                    }
                })
                .fail(() => {
                    AppCore?.notify?.('error', '서버 오류가 발생했습니다.');
                });
        });
    }

    function fillModal(data) {
        const form = getForm();
        if (!form) return;

        Object.keys(data).forEach(key => {
            if (key === 'id' || key === 'card_file') return;

            const el = form.querySelector(`[name="${key}"]`);
            if (!el || el.type === 'file') return;

            let value = data[key] ?? '';

            if (key === 'card_type') {
                value = normalizeCardTypeValue(value);
            }

            if (key === 'limit_amount' && value !== '') {
                value = formatAmount(value);
            }

            el.value = value;
        });

        setSelect2Initial('#cardClientSelect', data.client_id, data.client_name);
        setSelect2Initial('#cardAccountSelect', data.account_id, data.account_name);
        renderCardFile(data);
    }

    function normalizeCardTypeValue(value) {
        const normalized = String(value ?? '').trim().toLowerCase();

        if (['법인', '법인카드', 'corporate'].includes(normalized)) return 'corporate';
        if (['개인', '개인카드', 'personal'].includes(normalized)) return 'personal';
        if (['가상', '가상카드', 'virtual'].includes(normalized)) return 'virtual';

        return normalized;
    }

    function setSelect2Initial(selector, id, text) {
        if (!id) return;

        const el = document.querySelector(selector);
        if (!el) return;

        const option = new Option(text || id, id, true, true);
        el.append(option);
        AdminPicker.setSelect2Value(selector, id);
    }

    function buildCardColumns() {
        const columns = [{
            title: '<i class="bi bi-arrows-move"></i>',
            width: '40px',
            className: 'reorder-handle no-sort no-colvis text-center',
            headerClassName: 'no-sort text-center',
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>'
        }];

        Object.entries(CARD_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                width: config.width,
                className: config.className || '',
                defaultContent: '',
                render: function (data, type) {
                    if (data == null) return '';
                    if (type !== 'display') return data;

                    if (field === 'card_file') {
                        if (!data) return '';
                        const path = encodeURIComponent(data);
                        return `<a href="/api/file/preview?path=${path}" target="_blank">보기</a>`;
                    }

                    if (field === 'limit_amount') {
                        return formatAmount(data);
                    }

                    if (field === 'card_type') {
                        return CARD_TYPE_LABELS[normalizeCardTypeValue(data)] || data;
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

    function initCardImageUpload() {
        const drop = getCardFileDropEl();
        const input = getCardFileInputEl();
        const text = getCardFileTextEl();
        if (!drop || !input || !text || drop.dataset.uploadBound === '1') return;

        drop.dataset.uploadBound = '1';
        drop.dataset.original = drop.dataset.original || '0';

        const renderFile = (file) => {
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

            const message = drop.dataset.original === '1'
                ? '저장하면 기존 카드 이미지가 교체됩니다.'
                : '저장하면 카드 이미지가 등록됩니다.';

            text.innerHTML = `
                <strong>카드 이미지</strong><br>
                (${shortenFileName(file.name, 20)})<br>
                <span class="text-primary">${message}</span>
            `;

            return true;
        };

        drop.addEventListener('click', () => input.click());

        input.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) return;
            renderFile(file);
        });

        drop.addEventListener('dragover', e => e.preventDefault());
        drop.addEventListener('drop', e => {
            e.preventDefault();

            const file = e.dataTransfer.files[0];
            if (!file) return;

            input.files = e.dataTransfer.files;
            renderFile(file);
        });
    }

    function renderCardFile(data) {
        const text = getCardFileTextEl();
        const drop = getCardFileDropEl();
        const filePath = data.card_file || '';
        if (!text) return;

        if (filePath) {
            const path = encodeURIComponent(filePath);
            if (drop) drop.dataset.original = '1';

            text.innerHTML = `
                <div class="upload-guide">파일을 끌어놓거나 클릭하여 업로드<br>(PDF, JPG, PNG)</div>
                <div class="file-line"><strong>카드 이미지 등록됨</strong></div>
                <div class="file-links">
                    <a href="/api/file/preview?path=${path}" target="_blank" class="file-link-open">미리보기</a>
                    <span class="file-divider">|</span>
                    <a href="javascript:void(0)" id="btnDeleteCardFileInline" class="file-link-delete">삭제</a>
                </div>
            `;

            const btnDelete = document.getElementById('btnDeleteCardFileInline');
            if (btnDelete) {
                btnDelete.onclick = (e) => {
                    e.stopPropagation();
                    markCardFileDeleted();
                };
            }
            return;
        }

        resetCardImageUI();
    }

    function markCardFileDeleted() {
        if (!confirm('카드 이미지를 삭제하시겠습니까?')) return;

        const input = getCardFileInputEl();
        const del = getDeleteCardFileEl();
        const drop = getCardFileDropEl();
        const text = getCardFileTextEl();

        if (input) input.value = '';
        if (del) del.value = '1';
        if (drop) drop.dataset.original = '0';
        if (text) {
            text.innerHTML = `
                <div class="upload-guide">파일을 끌어놓거나 클릭하여 업로드<br>(PDF, JPG, PNG)</div>
                <div class="file-status text-danger">카드 이미지가 삭제됩니다. 저장해야 반영됩니다.</div>
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
        if (text) text.innerHTML = '파일을 끌어놓거나 클릭하여 업로드<br>(PDF, JPG, PNG)';
    }

    function shortenFileName(name, max = 20) {
        if (!name) return '';

        const lastDot = name.lastIndexOf('.');
        if (lastDot <= 0) {
            return name.length <= max ? name : `${name.substring(0, Math.max(1, max - 3))}...`;
        }

        const ext = name.substring(lastDot);
        const base = name.substring(0, lastDot);
        if (name.length <= max) return name;

        const keep = Math.max(1, max - ext.length - 3);
        return `${base.substring(0, keep)}...${ext}`;
    }

    function bindTrashEvents() {
        document.addEventListener('trash:detail-render', (e) => {
            const { data, modal } = e.detail;
            if (modal.dataset.type !== 'card') return;

            const detailBox = modal.querySelector('.trash-detail');
            if (!detailBox) return;

            let html = '<div class="p-3"><h6 class="mb-3">카드 상세</h6>';

            Object.entries(CARD_COLUMN_MAP).forEach(([key, config]) => {
                const value = data[key];
                if (value === null || value === undefined || value === '') return;

                let displayValue = value;
                if (key === 'card_type') displayValue = CARD_TYPE_LABELS[normalizeCardTypeValue(value)] || value;
                if (key === 'is_active') displayValue = String(value) === '1' ? '사용' : '미사용';
                if (key === 'limit_amount') displayValue = formatAmount(value);
                if (key === 'card_file') displayValue = value ? '등록됨' : '';

                html += `<div><b>${config.label}:</b> ${displayValue}</div>`;
            });

            html += '</div>';
            detailBox.innerHTML = html;
        });

        window.TrashColumns = window.TrashColumns || {};
        window.TrashColumns.card = function (row) {
            return `
                <td>${row.sort_no ?? ''}</td>
                <td>${row.card_name ?? ''}</td>
                <td>${row.client_name ?? ''}</td>
                <td>${row.card_number ?? ''}</td>
                <td>${CARD_TYPE_LABELS[normalizeCardTypeValue(row.card_type)] || row.card_type || ''}</td>
                <td>${row.account_name ?? ''}</td>
                <td>${row.currency ?? ''}</td>
                <td>${String(row.is_active) === '1' ? '사용' : '미사용'}</td>
                <td>${row.deleted_at ?? ''}</td>
                <td>${row.deleted_by_name ?? row.deleted_by ?? ''}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">영구삭제</button>
                </td>
            `;
        };

        document.addEventListener('trash:changed', (e) => {
            if (e.detail?.type === 'card') {
                cardTable?.ajax.reload(null, false);
            }
        });
    }

    function initSelectPickers() {
        const modalParent = window.jQuery ? window.jQuery('#cardModal') : null;
        if (modalParent && modalParent.length === 0) return;

        AdminPicker.select2Ajax('#cardClientSelect', {
            url: '/api/settings/base-info/client/search-picker',
            placeholder: '카드사 검색',
            minimumInputLength: 0,
            dropdownParent: modalParent,
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                    is_active: 1
                };
            },
            processResults(data) {
                const rows = data?.results ?? data?.data ?? [];

                return {
                    results: rows.map(row => ({
                        id: String(row.id ?? ''),
                        text: row.text || row.client_name || row.company_name || ''
                    })).filter(row => row.id !== '')
                };
            }
        });

        AdminPicker.select2Ajax('#cardAccountSelect', {
            url: '/api/settings/base-info/bank-account/search-picker',
            placeholder: '결제계좌 검색',
            minimumInputLength: 0,
            dropdownParent: modalParent,
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20
                };
            },
            processResults(data) {
                const rows = data?.results ?? data?.data ?? [];

                return {
                    results: rows.map(row => ({
                        id: String(row.id ?? ''),
                        text: row.text || `${row.account_name || ''}${row.bank_name ? ` (${row.bank_name})` : ''}`
                    })).filter(row => row.id !== '')
                };
            }
        });
    }

    function getForm() {
        return document.getElementById('cardForm');
    }

    function getIdEl() {
        return document.getElementById('modal_card_id') || document.querySelector('#cardForm [name="id"]');
    }

    function getCardFileInputEl() {
        return document.getElementById('modal_card_file') || document.querySelector('#cardForm [name="card_file"]');
    }

    function getDeleteCardFileEl() {
        return document.getElementById('delete_card_file') || document.querySelector('#cardForm [name="delete_card_file"]');
    }

    function getCardFileListEl() {
        return document.getElementById('cardFileList') || document.getElementById('cardPreview');
    }

    function getCardFileTextEl() {
        return document.getElementById('cardUploadText') || document.getElementById('cardImageText');
    }

    function getCardFileDropEl() {
        return document.getElementById('cardUpload') || document.getElementById('cardImageUpload');
    }

    function setModalTitle(title) {
        const titleEl = document.getElementById('cardModalLabel') || document.querySelector('#cardModal .modal-title');
        if (titleEl) titleEl.textContent = title;
    }
})();

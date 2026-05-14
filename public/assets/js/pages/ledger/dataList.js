import { createDataTable } from '/public/assets/js/components/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { openVoucherRecommendationModal } from '/public/assets/js/pages/ledger/voucherRecommendationModal.js';
import '/public/assets/js/components/trash-manager.js';

(() => {
    'use strict';

    const API = {
        fields: '/api/import/fields',
        rows: '/api/import/evidences',
        trash: '/api/import/evidences/trash',
        saveSeedRow: '/api/import/evidence/save',
        changeStatus: '/api/import/evidences/status',
        deleteRows: '/api/import/evidences/delete',
        restoreRows: '/api/import/evidences/restore',
        purgeRows: '/api/import/evidences/purge',
        purgeAll: '/api/import/evidences/purge-all',
        createTransactions: '/api/import/create-transactions',
    };

    const selectedIds = new Set();
    let seedTable = null;
    let isCreating = false;
    let editModal = null;
    let editingRow = null;
    let importFields = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        console[type === 'error' ? 'error' : 'warn'](message);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            cache: 'no-store',
            ...options,
            headers: {
                ...(options.headers || {}),
            },
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '요청 처리에 실패했습니다.');
        }
        return json;
    }

    async function loadImportFields() {
        if (importFields.length > 0) return importFields;
        const json = await fetchJson(API.fields);
        importFields = Array.isArray(json.data) ? json.data : [];
        return importFields;
    }

    function fieldMetaMap() {
        return importFields.reduce((map, field) => {
            map.set(String(field.value || ''), {
                key: String(field.value || ''),
                label: String(field.label || field.value || ''),
                group: String(field.group || '기타'),
            });
            return map;
        }, new Map());
    }

    function mapped(row) {
        return row?.mapped_payload || {};
    }

    function numericValue(value) {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : null;
        }
        const normalized = String(value ?? '')
            .trim()
            .replace(/[,\s₩원]/g, '');
        if (!normalized || normalized === '-' || normalized.toLowerCase() === 'nan') {
            return null;
        }
        const number = Number(normalized);
        return Number.isFinite(number) ? number : null;
    }

    function formatNumber(value) {
        const number = numericValue(value);
        return number === null ? '-' : number.toLocaleString('ko-KR');
    }

    function importSourceLabel(value) {
        const key = String(value || '').toUpperCase();
        return {
            TAX: '홈택스',
            HOMETAX: '홈택스',
            CARD: '카드사',
            CARD_COMPANY: '카드사',
            BANK: '은행',
            SHOPPING: '쇼핑몰',
            TRADE: '수입/무역',
            IMPORT: '수입/무역',
            MANUAL: '수기입력',
        }[key] || value || '-';
    }

    function importTypeLabel(value) {
        const key = String(value || '').toUpperCase();
        return {
            TAX_INVOICE: '세금계산서',
            CASH_RECEIPT: '현금영수증',
            CARD_HOMETAX: '카드(홈택스)',
            CARD_STATEMENT: '카드(카드사)',
            CARD_APPROVAL: '카드(카드사)',
            BANK_TRANSACTION: '입출금',
            SHOPPING_ORDER: '주문',
            IMPORT_INVOICE: '수입인보이스',
        }[key] || value || '-';
    }

    function directionLabel(value) {
        const key = String(value || '').toUpperCase();
        return {
            PURCHASE: '매입',
            SALES: '매출',
            IN: '입금',
            OUT: '출금',
            BANK: '입출금',
            GENERAL: '일반',
        }[key] || value || '-';
    }

    function statusBadge(status) {
        const meta = {
            READY: ['READY', 'text-bg-success', '수정 및 거래 생성 가능'],
            PROCESSED: ['PROCESSED', 'text-bg-primary', '거래 생성 완료'],
            ERROR: ['ERROR', 'text-bg-danger', '수정 필요'],
            VERIFY_ONLY: ['VERIFY_ONLY', 'text-bg-info', '세무 검증/대사 전용'],
            PROCESSING: ['PROCESSING', 'text-bg-warning', '처리 중'],
            DUPLICATED: ['DUPLICATED', 'text-bg-secondary', '중복 의심'],
            UNCHANGED: ['UNCHANGED', 'text-bg-light text-dark border', '동일 원본'],
            UPDATED: ['UPDATED', 'text-bg-info', 'Seed 갱신됨'],
            DELETED: ['DELETED', 'text-bg-dark', '휴지통 Seed Data'],
        }[status] || [status || '-', 'text-bg-secondary', status || '-'];
        return `<span class="badge ${meta[1]}" title="${escapeHtml(meta[2])}">${escapeHtml(meta[0])}</span>`;
    }

    function labelBadge(label) {
        return `<span class="badge text-bg-light border text-dark">${escapeHtml(label || '-')}</span>`;
    }

    function normalizedStatus(row) {
        if (row?.deleted_at) return 'DELETED';
        return String(row?.process_status || row?.status || '').trim().toUpperCase();
    }

    function rowClient(row) {
        const data = mapped(row);
        return data.client_company_name
            || data.client_business_number
            || data.supplier_company_name
            || data.customer_company_name
            || data.company_name
            || data.business_number
            || '';
    }

    function selectedReadyIds() {
        return Array.from(selectedIds).filter((id) => {
            const row = seedTable?.rows().data().toArray().find((item) => item.id === id);
            return normalizedStatus(row) === 'READY';
        });
    }

    function selectedProcessedRows() {
        return Array.from(selectedIds).map((id) => seedTable?.rows().data().toArray().find((item) => item.id === id))
            .filter((row) => normalizedStatus(row) === 'PROCESSED' && String(row?.transaction_id || '').trim() !== '');
    }

    function isSelectableForBulk(row) {
        const status = normalizedStatus(row);
        return status !== 'DELETED';
    }

    function updateButtons() {
        const hasSelection = selectedIds.size > 0;
        const readyCount = selectedReadyIds().length;
        const processedCount = selectedProcessedRows().length;
        const wrapper = seedTable?.table().container();

        wrapper?.querySelector('.btn-create-recommended-vouchers')?.toggleAttribute('disabled', isCreating || processedCount === 0);
        wrapper?.querySelector('.btn-open-voucher-check')?.toggleAttribute('disabled', processedCount === 0);
        wrapper?.querySelector('.btn-status-selected-seeds')?.toggleAttribute('disabled', !hasSelection);
        wrapper?.querySelector('.btn-delete-selected-seeds')?.toggleAttribute('disabled', !hasSelection);

        const selectAll = wrapper?.querySelector('#seedSelectAll');
        if (selectAll) {
            const selectableRows = seedTable?.rows({ search: 'applied' }).data().toArray().filter(isSelectableForBulk) || [];
            const selectedCount = selectableRows.filter((row) => selectedIds.has(row.id)).length;
            selectAll.checked = selectableRows.length > 0 && selectableRows.length === selectedCount;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < selectableRows.length;
        }
    }

    function reloadRows() {
        selectedIds.clear();
        seedTable?.ajax.reload(() => updateButtons(), false);
    }

    function syncVisibleRowCheckboxes() {
        const wrapper = seedTable?.table().container();
        wrapper?.querySelectorAll('.seed-row-check').forEach((checkbox) => {
            const id = checkbox.dataset.id || '';
            checkbox.checked = id !== '' && selectedIds.has(id);
        });
    }

    function fieldInputType(key, value) {
        if (String(value ?? '').length > 80 || /note|memo|address|description/.test(key)) return 'textarea';
        return 'text';
    }

    function renderEditField(key, value, meta) {
        const type = fieldInputType(key, value);
        const label = meta?.label || key;
        const escapedKey = escapeHtml(key);
        const escapedValue = escapeHtml(value ?? '');
        const input = type === 'textarea'
            ? `<textarea class="form-control form-control-sm seed-edit-input" rows="2" data-key="${escapedKey}">${escapedValue}</textarea>`
            : `<input type="${type}" class="form-control form-control-sm seed-edit-input" data-key="${escapedKey}" value="${escapedValue}">`;

        return `
            <label class="seed-edit-field">
                <span class="form-label small mb-1">${escapeHtml(label)} <span class="text-muted">(${escapedKey})</span></span>
                ${input}
            </label>
        `;
    }

    function renderEditFields(row) {
        const container = document.getElementById('seedRowEditFields');
        if (!container) return;

        const payload = mapped(row);
        const metas = fieldMetaMap();
        const keys = Array.from(new Set([
            ...importFields.map((field) => String(field.value || '')).filter((key) => Object.prototype.hasOwnProperty.call(payload, key)),
            ...Object.keys(payload),
        ])).filter(Boolean);

        if (keys.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-4">수정할 표준 필드가 없습니다.</div>';
            return;
        }

        const groups = new Map();
        keys.forEach((key) => {
            const meta = metas.get(key) || { key, label: key, group: '기타' };
            if (!groups.has(meta.group)) groups.set(meta.group, []);
            groups.get(meta.group).push(renderEditField(key, payload[key], meta));
        });

        container.innerHTML = Array.from(groups.entries()).map(([group, fields]) => `
            <section class="mb-3">
                <h6 class="fw-semibold border-bottom pb-2 mb-2">${escapeHtml(group)}</h6>
                <div class="row g-2">
                    ${fields.map((field) => `<div class="col-12 col-md-6 col-xl-4">${field}</div>`).join('')}
                </div>
            </section>
        `).join('');
    }

    async function editSeedRow(row) {
        if (!row?.id) return;
        await loadImportFields();
        editingRow = row;
        const isReady = normalizedStatus(row) === 'READY';
        document.getElementById('seedRowEditId').value = row.id;
        document.getElementById('seedRowEditSubtitle').textContent = [
            row.source_type_name || importSourceLabel(row.source_type),
            row.import_type_name || importTypeLabel(row.import_type || row.seed_source_type),
            rowClient(row) || '-',
            normalizedStatus(row),
        ].join(' / ');
        renderEditFields(row);
        document.querySelectorAll('#seedRowEditFields .seed-edit-input').forEach((input) => {
            input.disabled = !isReady;
        });
        const saveBtn = document.getElementById('seedRowEditSaveBtn');
        const deleteBtn = document.getElementById('seedRowEditDeleteBtn');
        if (saveBtn) saveBtn.disabled = !isReady;
        if (deleteBtn) deleteBtn.disabled = normalizedStatus(row) === 'PROCESSED' || Boolean(row.deleted_at);
        editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('seedRowEditModal'), { focus: false });
        editModal.show();
    }

    function collectEditPayload() {
        const next = { ...mapped(editingRow) };
        document.querySelectorAll('#seedRowEditFields .seed-edit-input').forEach((input) => {
            const key = input.dataset.key || '';
            if (!key) return;
            next[key] = input.value;
        });
        return next;
    }

    async function saveEditingRow() {
        if (!editingRow?.id) return;
        const parsed = collectEditPayload();
        const json = await fetchJson(API.saveSeedRow, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: editingRow.id, parsed_json: parsed }),
        });
        notify('success', json.message || 'Seed Data가 수정되었습니다.');
        editModal?.hide();
        editingRow = null;
        reloadRows();
    }

    async function postSelected(url, extraPayload = {}) {
        const ids = Array.from(selectedIds);
        if (ids.length === 0) {
            notify('warning', 'Seed Data를 선택하세요.');
            return null;
        }

        return fetchJson(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seed_row_ids: ids, ...extraPayload }),
        });
    }

    function buildColumns() {
        return [
            {
                data: null,
                title: '<input type="checkbox" class="form-check-input" id="seedSelectAll" aria-label="READY Seed 전체 선택">',
                orderable: false,
                searchable: false,
                className: 'text-center no-colvis no-export',
                render(row) {
                    const disabled = !isSelectableForBulk(row);
                    const checked = selectedIds.has(row.id) ? 'checked' : '';
                    return `<input type="checkbox" class="form-check-input seed-row-check" data-id="${escapeHtml(row.id)}" ${checked} ${disabled ? 'disabled' : ''} aria-label="Seed 선택">`;
                },
            },
            { data: 'process_status', title: '상태', className: 'text-nowrap', render: (_value, _type, row) => statusBadge(normalizedStatus(row)) },
            { data: 'source_type', title: '자료출처', className: 'text-nowrap', render: (value, _type, row) => labelBadge(row.source_type_name || importSourceLabel(value)) },
            { data: 'import_type', title: '자료유형', className: 'text-nowrap', render: (value, _type, row) => labelBadge(row.import_type_name || importTypeLabel(value || row.seed_source_type)) },
            { data: 'mapped_payload.transaction_direction', title: '거래구분', className: 'text-nowrap', render: (value) => escapeHtml(directionLabel(value || '')) },
            {
                data: 'client_name',
                title: '거래처',
                render(value, _type, row) {
                    value = value || rowClient(row);
                    return `<span title="${escapeHtml(value)}">${escapeHtml(value || '-')}</span>`;
                },
            },
            { data: 'mapped_payload.transaction_date', title: '작성일자', className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
            { data: 'mapped_payload.supply_amount', title: '공급가', className: 'text-end text-nowrap', render: (value) => formatNumber(value) },
            { data: 'mapped_payload.vat_amount', title: '부가세', className: 'text-end text-nowrap', render: (value) => formatNumber(value) },
            { data: 'mapped_payload.total_amount', title: '합계금액', className: 'text-end text-nowrap', render: (value) => formatNumber(value) },
            { data: 'mapped_payload.description', title: '적요', render: (value) => `<span title="${escapeHtml(value || '')}">${escapeHtml(value || '-')}</span>` },
            { data: 'transaction_id', title: '거래번호', className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
            { data: 'processed_at', title: '처리일시', className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
        ];
    }

    function openTrashModal() {
        const modal = document.getElementById('seedRowsTrashModal');
        if (!modal) {
            notify('warning', 'Seed Data 휴지통 모달을 찾을 수 없습니다.');
            return;
        }
        modal.dataset.listUrl = API.trash;
        modal.dataset.restoreUrl = API.restoreRows;
        modal.dataset.deleteUrl = API.purgeRows;
        modal.dataset.deleteAllUrl = API.purgeAll;
        bootstrap.Modal.getOrCreateInstance(modal, { focus: false }).show();
    }

    function bindEvents() {
        const tableEl = document.getElementById('seedRowsTable');

        tableEl?.addEventListener('change', (event) => {
            const checkbox = event.target.closest('.seed-row-check');
            if (!checkbox) return;

            const id = checkbox.dataset.id || '';
            if (!id) return;
            if (checkbox.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
            updateButtons();
        });

        document.addEventListener('change', (event) => {
            const selectAll = event.target.closest('#seedSelectAll');
            if (!selectAll) return;
            if (!seedTable?.table().container()?.contains(selectAll)) return;

            const rows = seedTable?.rows({ search: 'applied' }).data().toArray() || [];
            rows.forEach((row) => {
                if (!isSelectableForBulk(row)) return;
                if (selectAll.checked) {
                    selectedIds.add(row.id);
                } else {
                    selectedIds.delete(row.id);
                }
            });
            syncVisibleRowCheckboxes();
            updateButtons();
        });

        tableEl?.addEventListener('dblclick', (event) => {
            if (event.target.closest('a, button, input, select, textarea')) return;
            const row = seedTable.row(event.target.closest('tr')).data();
            void editSeedRow(row).catch((error) => notify('error', error.message));
        });

        document.addEventListener('trash:changed', (event) => {
            if (event.detail?.type === 'seedRows') {
                reloadRows();
            }
        });

        document.addEventListener('trash:detail-render', (event) => {
            if (event.detail?.type !== 'seedRows') return;
            const detailEl = event.detail.modal?.querySelector('.trash-detail');
            const row = event.detail.data || {};
            if (!detailEl) return;
            detailEl.innerHTML = `
                <div class="small">
                    <dl class="row mb-0">
                        <dt class="col-4">자료출처</dt><dd class="col-8">${escapeHtml(row.source_type_name || importSourceLabel(row.source_type))}</dd>
                        <dt class="col-4">자료유형</dt><dd class="col-8">${escapeHtml(row.import_type_name || importTypeLabel(row.import_type || row.seed_source_type))}</dd>
                        <dt class="col-4">상태</dt><dd class="col-8">${statusBadge(normalizedStatus(row))}</dd>
                        <dt class="col-4">거래처</dt><dd class="col-8">${escapeHtml(rowClient(row) || '-')}</dd>
                        <dt class="col-4">합계금액</dt><dd class="col-8">${escapeHtml(formatNumber(mapped(row).total_amount))}</dd>
                        <dt class="col-4">적요</dt><dd class="col-8">${escapeHtml(mapped(row).description || '-')}</dd>
                        <dt class="col-4">파일명</dt><dd class="col-8">${escapeHtml(row.file_name || '-')}</dd>
                        <dt class="col-4">삭제일시</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
                    </dl>
                </div>
            `;
        });

        document.getElementById('seedRowEditSaveBtn')?.addEventListener('click', () => {
            void saveEditingRow().catch((error) => notify('error', error.message));
        });

        document.getElementById('seedRowEditDeleteBtn')?.addEventListener('click', async () => {
            if (!editingRow?.id) return;
            if (!window.confirm('Seed Data를 휴지통으로 이동할까요?')) return;
            const json = await fetchJson(API.deleteRows, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ seed_row_ids: [editingRow.id] }),
            });
            notify('success', json.message || 'Seed Data가 휴지통으로 이동되었습니다.');
            editModal?.hide();
            editingRow = null;
            reloadRows();
        });
    }

    async function createSelectedTransactions(button) {
        const ids = selectedReadyIds();
        if (ids.length === 0) {
            notify('warning', '거래 생성할 READY Seed Data를 선택하세요.');
            return;
        }

        isCreating = true;
        updateButtons();
        const originalText = button?.textContent || '선택 거래 생성';
        if (button) button.textContent = '생성 중';
        try {
            const json = await fetchJson(API.createTransactions, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ seed_row_ids: ids }),
            });
            notify('success', json.message || '선택 Seed Data의 거래 생성이 완료되었습니다.');
        } finally {
            isCreating = false;
            if (button) button.textContent = originalText;
            reloadRows();
        }
    }

    async function createRecommendedVouchers(button) {
        const rows = selectedProcessedRows();
        if (rows.length === 0) {
            notify('warning', '추천전표를 생성할 PROCESSED Seed Data를 선택하세요.');
            return;
        }

        isCreating = true;
        updateButtons();
        const originalText = button?.textContent || '추천전표생성';
        if (button) button.textContent = '검토 중';
        try {
            if (rows.length > 1) {
                notify('warning', '추천분개검토는 현재 1건씩 처리합니다. 첫 번째 선택 거래를 엽니다.');
            }
            const opened = await openVoucherRecommendationModal({
                transactionId: rows[0].transaction_id,
                onSaved(json) {
                    notify('success', json.message || 'draft 전표가 저장되었습니다.');
                    reloadRows();
                },
                onClosed() {
                    isCreating = false;
                    if (button) button.textContent = originalText;
                    updateButtons();
                },
            });
            if (!opened) {
                isCreating = false;
                if (button) button.textContent = originalText;
                updateButtons();
            }
        } catch (error) {
            isCreating = false;
            if (button) button.textContent = originalText;
            updateButtons();
            notify('error', error.message);
        }
    }

    function initTable() {
        seedTable = createDataTable({
            tableSelector: '#seedRowsTable',
            api: API.rows,
            density: 'compact',
            pageLength: 100,
            defaultOrder: [[6, 'desc']],
            searchTableId: 'seedRows',
            columns: buildColumns(),
            buttons: [
                {
                    text: '추천전표생성',
                    className: 'btn btn-outline-success btn-sm btn-create-recommended-vouchers',
                    action: (_event, _dt, node) => {
                        void createRecommendedVouchers(node?.get(0)).catch((error) => notify('error', error.message));
                    },
                },
                {
                    text: '전표확인',
                    className: 'btn btn-outline-primary btn-sm btn-open-voucher-check',
                    action: () => {
                        window.location.href = '/ledger/journal';
                    },
                },
                {
                    text: '선택 상태변경',
                    className: 'btn btn-outline-secondary btn-sm btn-status-selected-seeds',
                    action: async () => {
                        const status = window.prompt('변경할 상태를 입력하세요. (READY, ERROR, DUPLICATED)', 'READY');
                        if (status === null) return;
                        const json = await postSelected(API.changeStatus, { process_status: status.trim().toUpperCase() });
                        if (!json) return;
                        notify('success', json.message || '상태가 변경되었습니다.');
                        reloadRows();
                    },
                },
                {
                    text: '선택 삭제',
                    className: 'btn btn-outline-danger btn-sm btn-delete-selected-seeds',
                    action: async () => {
                        if (!window.confirm('선택한 Seed Data를 휴지통으로 이동할까요? PROCESSED 행은 제외됩니다.')) return;
                        const json = await postSelected(API.deleteRows);
                        if (!json) return;
                        notify('success', json.message || '선택 Seed Data가 휴지통으로 이동되었습니다.');
                        reloadRows();
                    },
                },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal,
                },
            ],
        });

        seedTable.on('draw.dt xhr.dt', updateButtons);

        SearchForm({
            table: seedTable,
            apiList: API.rows,
            tableId: 'seedRows',
            defaultSearchField: 'client_name',
            initialCollapsed: true,
            dateOptions: [
                { value: 'mapped_payload.transaction_date', label: '작성일자' },
                { value: 'created_at', label: '생성일시' },
                { value: 'processed_at', label: '처리일시' },
                { value: 'updated_at', label: '수정일시' },
            ],
            excludeFields: [''],
        });

        bindEvents();
        updateButtons();
    }

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.seedRows = function (row = {}) {
        return `
            <td>${statusBadge(normalizedStatus(row))}</td>
            <td>${escapeHtml(row.source_type_name || importSourceLabel(row.source_type))}</td>
            <td>${escapeHtml(row.import_type_name || importTypeLabel(row.import_type || row.seed_source_type))}</td>
            <td>${escapeHtml(rowClient(row) || '-')}</td>
            <td class="text-end">${escapeHtml(formatNumber(mapped(row).total_amount))}</td>
            <td>${escapeHtml(mapped(row).transaction_date || '-')}</td>
            <td>${escapeHtml(row.deleted_at || '-')}</td>
            <td>
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id)}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id)}">삭제</button>
            </td>
        `;
    };

    initTable();
})();

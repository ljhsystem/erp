import { actorColumn } from '/public/assets/js/common/actor.js';
import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { manageButtonRenderer } from '/public/assets/js/common/table/renderers/index.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { importTypeLabel as sharedImportTypeLabel } from '/public/assets/js/pages/ledger/shared/labels.js';
import { confirmDialog } from '/public/assets/js/common/confirm-dialog.js';
import { runDeleteProgress } from '/public/assets/js/common/delete-progress.js';
import '/public/assets/js/components/trash-manager.js';

(() => {
    'use strict';

    const API = {
        list: '/api/ledger/evidence-metadata/list',
        detail: '/api/ledger/evidence-metadata/detail',
        save: '/api/ledger/evidence-metadata/save',
        delete: '/api/ledger/evidence-metadata/delete',
        trash: '/api/ledger/evidence-metadata/trash',
        restore: '/api/ledger/evidence-metadata/restore',
        purge: '/api/ledger/evidence-metadata/purge',
        reorder: '/api/ledger/evidence-metadata/reorder',
        sourceColumns: '/api/ledger/evidence-metadata/source-columns',
        recommend: '/api/ledger/evidence-metadata/recommend',
        options: '/api/ledger/evidence-metadata/options',
    };
    const STORAGE_KEY = 'datatable.settings.ledger.evidence-metadata.evidence-metadata-table.v2';
    const PAGE_KEY = 'ledger.evidence-metadata';
    const META_DOMAIN = 'evidence-metadata';
    const EVIDENCE_TYPE_LABELS = { DATA: '자료증빙', FUND: '자금증빙', BOTH: '자료·자금 공통' };

    const tableElement = document.getElementById('evidence-metadata-table');
    const form = document.getElementById('evidenceMetadataForm');
    const modalElement = document.getElementById('evidenceMetadataModal');
    const sourceTableInput = document.getElementById('evidenceMetadataSourceTable');
    const sourceTableDisplay = document.getElementById('evidenceMetadataSourceTableDisplay');
    const importTypeSelect = document.getElementById('evidenceMetadataImportType');
    const importTypeDisplay = document.getElementById('evidenceMetadataImportTypeDisplay');
    const deleteButton = document.getElementById('evidenceMetadataDeleteBtn');
    const countElement = document.getElementById('evidenceMetadataCount');
    const mappingContainer = document.getElementById('evidenceMetadataMappingFields');
    const adjustmentContainer = document.getElementById('evidenceMetadataAdjustmentRows');
    const addAdjustmentButton = document.getElementById('evidenceMetadataAddAdjustment');
    const typeModalElement = document.getElementById('evidencePolicyTypeModal');
    const typeSelect = document.getElementById('evidencePolicyTypeSelect');
    const typeConfirm = document.getElementById('evidencePolicyTypeConfirm');
    if (!tableElement || !form || !modalElement || !window.bootstrap?.Modal) return;

    const modal = new window.bootstrap.Modal(modalElement);
    const typeModal = typeModalElement ? new window.bootstrap.Modal(typeModalElement) : null;
    let table = null;
    let importTypes = [];
    let mappingFields = [];
    let sourceColumns = [];

    document.addEventListener('DOMContentLoaded', () => void boot().catch(showError));
    document.addEventListener('trash:changed', (event) => {
        if (event.detail?.type === 'evidenceMetadata') {
            void loadOptions().catch(showError);
            table?.ajax.reload(null, false);
        }
    });
    document.addEventListener('trash:detail-render', (event) => {
        if (event.detail?.type !== 'evidenceMetadata') return;
        const row = event.detail.data || {};
        const detail = event.detail.modal?.querySelector('.trash-detail');
        if (!detail) return;
        detail.innerHTML = `<dl class="row small mb-0">
            <dt class="col-4">자료유형</dt><dd class="col-8">${escapeHtml(formatImportTypeDisplay(row.import_type))}</dd>
            <dt class="col-4">원본테이블</dt><dd class="col-8">${escapeHtml(row.source_table || '-')}</dd>
            <dt class="col-4">증빙유형</dt><dd class="col-8">${escapeHtml(EVIDENCE_TYPE_LABELS[row.evidence_type] || row.evidence_type || '-')}</dd>
            <dt class="col-4">삭제일시</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
            <dt class="col-4">삭제자</dt><dd class="col-8">${escapeHtml(row.deleted_by_name || row.deleted_by || '-')}</dd>
        </dl>`;
    });

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.evidenceMetadata = (row = {}) => `
        <td>${escapeHtml(formatImportTypeDisplay(row.import_type))}</td>
        <td>${escapeHtml(row.source_table || '')}</td>
        <td>${escapeHtml(EVIDENCE_TYPE_LABELS[row.evidence_type] || row.evidence_type || '')}</td>
        <td>${escapeHtml(row.deleted_at || '')}</td>
        <td>${escapeHtml(row.deleted_by_name || row.deleted_by || '')}</td>
        <td class="text-center">
            <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id || '')}">복원</button>
            <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id || '')}">영구삭제</button>
        </td>`;

    function showError(error) {
        const message = error instanceof Error ? error.message : '요청 처리 중 오류가 발생했습니다.';
        if (typeof window.notify === 'function') {
            window.notify('error', message);
            return;
        }
        window.alert(message);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function boot() {
        await loadOptions();
        await initTable();
        bindEvents();
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
            ...options,
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '요청 처리 중 오류가 발생했습니다.');
        }
        return json;
    }

    async function loadOptions() {
        const optionsJson = await fetchJson(API.options);
        importTypes = Array.isArray(optionsJson.data?.import_types) ? optionsJson.data.import_types : [];
        const basisFields = Array.isArray(optionsJson.data?.basis_fields) ? optionsJson.data.basis_fields : [];
        mappingFields = basisFields.map((field) => String(field.semantic_key || '')).filter(Boolean);
        renderBasisFields(basisFields);
        fillImportTypeSelect(typeSelect, false, true);
    }

    function fillSelect(select, rows, valueKey, labelResolver, emptyLabel = '선택') {
        if (!select) return;
        const selected = select.value;
        select.replaceChildren(new Option(emptyLabel, ''));
        rows.forEach((row) => select.add(new Option(labelResolver(row), String(row[valueKey] ?? ''))));
        select.value = selected;
    }

    function fillImportTypeSelect(select, optional = false, availableOnly = false) {
        const rows = availableOnly ? importTypes.filter((row) => row.is_registered !== true) : importTypes;
        fillSelect(select, rows, 'code', (row) => `${row.code_name || row.code} (${row.code})`, optional ? '사용 안 함' : '선택');
    }

    function renderBasisFields(fields) {
        if (!mappingContainer) return;
        mappingContainer.replaceChildren();
        const groups = new Map();
        fields.forEach((field) => {
            const group = String(field.group || '기준 설정');
            if (!groups.has(group)) groups.set(group, []);
            groups.get(group).push(field);
        });
        groups.forEach((groupFields, groupLabel) => {
            const group = document.createElement('div');
            group.className = 'mapping-group';
            const title = document.createElement('h6');
            title.className = 'mapping-group-title';
            title.textContent = groupLabel;
            const row = document.createElement('div');
            row.className = 'row g-3';
            groupFields.forEach((field) => {
                const semanticKey = String(field.semantic_key || '');
                const column = document.createElement('div');
                column.className = 'col-md-4';
                const label = document.createElement('label');
                label.className = 'form-label';
                label.htmlFor = `evidenceMetadata_${semanticKey}`;
                label.textContent = String(field.label || semanticKey);
                const description = document.createElement('div');
                description.className = 'mapping-description';
                description.textContent = String(field.description || '');
                const select = document.createElement('select');
                select.className = 'form-select form-select-sm metadata-column-select';
                select.id = `evidenceMetadata_${semanticKey}`;
                select.name = `mappings[${semanticKey}]`;
                column.append(label, description, select);
                row.append(column);
            });
            group.append(title, row);
            mappingContainer.append(group);
        });
    }

    function columns() {
        return [
            {
                data: null,
                title: '<i class="bi bi-arrows-move"></i>',
                className: 'reorder-handle no-sort no-colvis text-center',
                headerClassName: 'no-colvis text-center',
                orderable: false,
                searchable: false,
                defaultContent: '<i class="bi bi-list"></i>',
                settingsKey: '__reorder',
                width: '44px',
                widthResizable: true,
            },
            { data: 'sort_no', title: '순번', className: 'dt-sequence-column text-center text-nowrap', width: 60, settingsKey: 'sort_no' },
            {
                data: 'import_type',
                title: '자료유형',
                settingsKey: 'import_type',
                render: (value, type) => type === 'display' ? escapeHtml(formatImportTypeDisplay(value)) : value,
            },
            { data: 'source_table', title: '원본테이블', settingsKey: 'source_table' },
            { data: 'evidence_type', title: '사용영역', settingsKey: 'evidence_type', render: (value, type) => type === 'display' ? (EVIDENCE_TYPE_LABELS[value] || value || '') : value },
            { data: 'health_message', title: '정책상태', settingsKey: 'health_status', render: (value, type, row) => type === 'display' ? `<span class="badge ${row.health_status === 'NORMAL' ? 'text-bg-success' : 'text-bg-danger'}">${escapeHtml(value || '확인 필요')}</span>` : value },
            { data: 'created_at', title: '등록일시', settingsKey: 'created_at' },
            actorColumn('created_by', '등록자', { visible: true }),
            { data: 'updated_at', title: '수정일시', settingsKey: 'updated_at' },
            actorColumn('updated_by', '수정자', { visible: true }),
            {
                data: null,
                title: '관리',
                className: 'dt-action-column text-center no-colvis',
                headerClassName: 'dt-action-column text-center no-colvis',
                orderable: false,
                searchable: false,
                settingsKey: '__actions',
                render: (_value, type, row) => type === 'display' ? manageButtonRenderer(row, { escapeHtml }) : '',
            },
        ];
    }

    async function initTable() {
        table = await createDataTable({
            tableSelector: '#evidence-metadata-table',
            api: API.list,
            deleteApi: API.delete,
            bulkDelete: true,
            columns: columns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            searchTableId: 'evidenceMetadata',
            tableSettings: {
                enabled: true,
                pageKey: PAGE_KEY,
                tableKey: 'evidence-metadata-table',
                storageKey: STORAGE_KEY,
                userSettingPageKey: PAGE_KEY,
                metaDomain: META_DOMAIN,
                tableLabel: '증빙정책 목록',
                title: '증빙정책 테이블 설정',
                defaultVisibleColumns: [
                    'sort_no',
                    'import_type',
                    'source_table',
                    'evidence_type',
                    'health_status',
                    'created_at',
                    'created_by',
                    'updated_at',
                    'updated_by',
                ],
            },
            buttons: [
                { text: '휴지통', className: 'btn btn-danger btn-sm', action: openTrash },
                { text: '신규등록', className: 'btn btn-warning btn-sm', action: openCreate },
            ],
        });

        table.on('draw.dt xhr.dt', updateCount);
        SearchForm({ table, apiList: API.list, tableId: 'evidenceMetadata', defaultSearchField: 'import_type', dateOptions: [] });
        bindRowReorder(table, {
            api: API.reorder,
            onSuccess() {
                window.notify?.('success', '증빙정책 순서가 저장되었습니다.');
                table?.ajax.reload(null, false);
            },
            onError(json) {
                showError(new Error(json?.message || '증빙정책 순서 저장에 실패했습니다.'));
                table?.ajax.reload(null, false);
            },
        });
    }

    function openTrash() {
        const element = document.getElementById('evidenceMetadataTrashModal');
        if (!element) return;
        element.dataset.listUrl = API.trash;
        element.dataset.restoreUrl = API.restore;
        element.dataset.deleteUrl = API.purge;
        element.dataset.deleteAllUrl = '';
        window.bootstrap.Modal.getOrCreateInstance(element).show();
    }

    function updateCount() {
        const count = table?.page?.info?.()?.recordsDisplay || 0;
        if (countElement) countElement.textContent = `총 ${Number(count).toLocaleString('ko-KR')}건`;
    }

    function bindEvents() {
        typeConfirm?.addEventListener('click', () => void confirmNewPolicyType().catch(showError));
        addAdjustmentButton?.addEventListener('click', () => addAdjustmentRow());
        adjustmentContainer?.addEventListener('click', (event) => {
            const removeButton = event.target.closest('.metadata-adjustment-remove');
            if (!removeButton) return;
            removeButton.closest('.metadata-adjustment-row')?.remove();
            reindexAdjustmentRows();
        });
        form.addEventListener('submit', (event) => void save(event).catch(showError));
        deleteButton?.addEventListener('click', () => void deleteCurrent().catch(showError));

        window.jQuery(tableElement).on('click', '.seed-row-edit-btn', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const row = table.row(event.currentTarget.closest('tr')).data();
            if (row?.id) void openEdit(row.id).catch(showError);
        });
        window.jQuery(tableElement).on('dblclick', 'tbody tr', (event) => {
            if (event.target.closest('button, input, .reorder-handle')) return;
            const row = table.row(event.currentTarget).data();
            if (row?.id) void openEdit(row.id).catch(showError);
        });
    }

    function resetForm() {
        form.reset();
        document.getElementById('evidenceMetadataId').value = '';
        deleteButton?.classList.add('d-none');
        importTypeSelect.value = '';
        importTypeDisplay.value = '';
        sourceTableInput.value = '';
        sourceTableDisplay.value = '';
        fillColumnSelects([]);
        renderAdjustmentRows([]);
    }

    function openCreate() {
        resetForm();
        fillImportTypeSelect(typeSelect, false, true);
        typeModal?.show();
    }

    async function confirmNewPolicyType() {
        const importType = String(typeSelect?.value || '').trim();
        if (!importType) {
            throw new Error('자료유형을 선택해 주세요.');
        }
        importTypeSelect.value = importType;
        importTypeDisplay.value = importTypeDisplayText(importType);
        await applyRecommendation(importType);
        typeModal?.hide();
        modal.show();
    }

    async function openEdit(id) {
        resetForm();
        const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);
        const row = json.data || {};
        document.getElementById('evidenceMetadataId').value = row.id || '';
        importTypeSelect.value = row.import_type || '';
        importTypeDisplay.value = importTypeDisplayText(row.import_type || '');
        sourceTableInput.value = row.source_table || '';
        sourceTableDisplay.value = row.source_table || '';
        document.getElementById('evidenceMetadataEvidenceType').value = row.evidence_type || 'DATA';
        const mappings = Array.isArray(row.mappings) ? row.mappings : [];
        const mappingValues = Object.fromEntries(mappings
            .filter((mapping) => mapping.semantic_key !== 'ADJUST_AMOUNT')
            .map((mapping) => [mapping.semantic_key, mapping.physical_column]));
        const adjustments = mappings.filter((mapping) => mapping.semantic_key === 'ADJUST_AMOUNT');
        await loadSourceColumns(row.source_table || '', mappingValues, adjustments);
        deleteButton?.classList.remove('d-none');
        modal.show();
    }

    async function loadSourceColumns(tableName, values = {}, adjustments = []) {
        if (!tableName) {
            fillColumnSelects([], values);
            renderAdjustmentRows(adjustments);
            return;
        }
        const json = await fetchJson(`${API.sourceColumns}?table=${encodeURIComponent(tableName)}`);
        fillColumnSelects(Array.isArray(json.data) ? json.data : [], values);
        renderAdjustmentRows(adjustments);
    }

    async function applyRecommendation(importType) {
        if (!importType) {
            sourceTableInput.value = '';
            sourceTableDisplay.value = '';
            fillColumnSelects([]);
            return;
        }
        const json = await fetchJson(`${API.recommend}?import_type=${encodeURIComponent(importType)}`);
        const recommendation = json.data || {};
        sourceTableInput.value = recommendation.source_table || '';
        sourceTableDisplay.value = recommendation.source_table || '';
        document.getElementById('evidenceMetadataEvidenceType').value = recommendation.evidence_type || 'DATA';
        const mappings = Array.isArray(recommendation.mappings) ? recommendation.mappings : [];
        const mappingValues = Object.fromEntries(mappings
            .filter((mapping) => mapping.semantic_key !== 'ADJUST_AMOUNT')
            .map((mapping) => [mapping.semantic_key, mapping.physical_column]));
        fillColumnSelects(Array.isArray(recommendation.columns) ? recommendation.columns : [], mappingValues);
        renderAdjustmentRows(mappings.filter((mapping) => mapping.semantic_key === 'ADJUST_AMOUNT'));
    }

    function formatImportTypeDisplay(importType) {
        return importTypeDisplayText(importType) || '-';
    }

    function importTypeDisplayText(importType) {
        const code = String(importType || '').trim().toUpperCase();
        if (!code) return '';
        const row = importTypes.find((item) => String(item.code || '').trim().toUpperCase() === code);
        const codeName = String(row?.code_name || '').trim();
        const sharedLabel = String(sharedImportTypeLabel(code) || '').trim();
        const label = codeName && codeName !== code ? codeName : (sharedLabel && sharedLabel !== code ? sharedLabel : code);
        return label === code ? code : `${label} (${code})`;
    }

    function fillColumnSelects(columnsList, values = {}) {
        sourceColumns = Array.isArray(columnsList) ? columnsList : [];
        mappingFields.forEach((field) => {
            const select = form.elements.namedItem(`mappings[${field}]`);
            if (!(select instanceof HTMLSelectElement)) return;
            select.replaceChildren(new Option('추천 없음', ''));
            sourceColumns.forEach((column) => {
                const label = column.label !== column.name ? `${column.name} - ${column.label}` : column.name;
                select.add(new Option(label, column.name));
            });
            select.value = values[field] || '';
        });
    }

    function renderAdjustmentRows(adjustments) {
        if (!adjustmentContainer) return;
        adjustmentContainer.replaceChildren();
        (Array.isArray(adjustments) ? adjustments : []).forEach((adjustment) => addAdjustmentRow(adjustment));
    }

    function addAdjustmentRow(adjustment = {}) {
        if (!adjustmentContainer) return;
        const row = document.createElement('div');
        row.className = 'metadata-adjustment-row';

        const directionSelect = document.createElement('select');
        directionSelect.className = 'form-select form-select-sm metadata-adjustment-direction';
        directionSelect.setAttribute('aria-label', '가감구분');
        directionSelect.append(new Option('추가', 'ADD'), new Option('차감', 'DEDUCT'));
        directionSelect.value = String(adjustment.adjustment_direction || 'ADD').toUpperCase();

        const columnSelect = document.createElement('select');
        columnSelect.className = 'form-select form-select-sm metadata-adjustment-column';
        columnSelect.setAttribute('aria-label', '가감 원본컬럼');
        columnSelect.append(new Option('원본컬럼 선택', ''));
        sourceColumns.forEach((column) => {
            const label = column.label !== column.name ? `${column.name} - ${column.label}` : column.name;
            columnSelect.add(new Option(label, column.name));
        });
        columnSelect.value = String(adjustment.physical_column || '');

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-outline-danger btn-sm metadata-adjustment-remove';
        removeButton.textContent = '삭제';

        row.append(directionSelect, columnSelect, removeButton);
        adjustmentContainer.append(row);
        reindexAdjustmentRows();
    }

    function reindexAdjustmentRows() {
        adjustmentContainer?.querySelectorAll('.metadata-adjustment-row').forEach((row, index) => {
            const direction = row.querySelector('.metadata-adjustment-direction');
            const column = row.querySelector('.metadata-adjustment-column');
            if (direction) direction.name = `adjustments[${index}][adjustment_direction]`;
            if (column) column.name = `adjustments[${index}][physical_column]`;
        });
    }

    async function save(event) {
        event.preventDefault();
        const payload = new FormData(form);
        const json = await fetchJson(API.save, { method: 'POST', body: payload });
        const savedImportType = String(importTypeSelect.value || '');
        const savedOption = importTypes.find((row) => String(row.code || '') === savedImportType);
        if (savedOption) savedOption.is_registered = true;
        window.notify?.('success', json.message || '저장되었습니다.');
        modal.hide();
        table.ajax.reload(null, false);
    }

    async function deleteCurrent() {
        const id = document.getElementById('evidenceMetadataId').value;
        if (!id) return;
        const detail = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);
        const row = detail.data || {};
        const impact = row.impact || {};
        const confirmed = await confirmDialog({
            title: '증빙정책 삭제',
            message: [
                `자료유형: ${formatImportTypeDisplay(row.import_type)}`,
                `원본테이블: ${row.source_table || '-'}`,
                `사용영역: ${EVIDENCE_TYPE_LABELS[row.evidence_type] || row.evidence_type || '-'}`,
                `의미 매핑: ${Array.isArray(row.mappings) ? row.mappings.length : 0}건`,
                `원본 증빙: ${Number(impact.body_count || 0).toLocaleString('ko-KR')}건`,
                `거래·전표 연결: ${Number(impact.link_count || 0).toLocaleString('ko-KR')}건`,
                impact.runtime_required ? 'Runtime 영향: 시스템 필수 정책' : 'Runtime 영향: 필수 Consumer 없음',
                '',
                '이 정책을 휴지통으로 이동하시겠습니까?',
            ].join('\n'),
            confirmText: '삭제',
            confirmClass: 'btn-danger',
        });
        if (!confirmed) return;
        await runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '증빙정책을 휴지통으로 이동 중', trashChanged: true }, async () => {
            const json = await fetchJson(API.delete, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
            });
            window.notify?.('success', json.message || '삭제되었습니다.');
            modal.hide();
            await new Promise(resolve => table.ajax.reload(() => resolve(), false));
        });
    }
})();

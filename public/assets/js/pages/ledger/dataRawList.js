(() => {
    'use strict';

    const API = {
        batches: '/api/import/batches',
        batchRows: '/api/import/batch/rows',
    };

    const refs = {
        reloadBtn: document.getElementById('btnReloadRawData'),
        batchBody: document.getElementById('seedBatchBody'),
        rowBody: document.getElementById('rawSeedRowsBody'),
        batchSummary: document.getElementById('seedBatchSummary'),
        rowSummary: document.getElementById('seedRowSummary'),
        selectedBatchLabel: document.getElementById('selectedBatchLabel'),
        rawPreview: document.getElementById('rawPayloadPreview'),
        parsedPreview: document.getElementById('parsedPayloadPreview'),
        errorPreview: document.getElementById('errorMessagePreview'),
    };

    let selectedBatchId = '';
    let selectedRowId = '';
    let batches = [];
    let rows = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatNumber(value) {
        const number = Number(value ?? 0);
        return Number.isFinite(number) ? number.toLocaleString('ko-KR') : '0';
    }

    function compact(value, length = 80) {
        const text = String(value ?? '').trim();
        if (text.length <= length) {
            return text;
        }
        return `${text.slice(0, length - 1)}...`;
    }

    function pretty(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }
        if (typeof value === 'string') {
            try {
                return JSON.stringify(JSON.parse(value), null, 2);
            } catch (error) {
                return value;
            }
        }
        return JSON.stringify(value, null, 2);
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        if (type === 'error') {
            console.error(message);
        }
    }

    async function fetchJson(url) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '자료를 불러오지 못했습니다.');
        }
        return json;
    }

    function statusBadge(status) {
        const normalized = String(status || '').toUpperCase();
        const map = {
            READY: 'text-bg-primary',
            PROCESSED: 'text-bg-success',
            ERROR: 'text-bg-danger',
            DUPLICATED: 'text-bg-warning',
            DELETED: 'text-bg-secondary',
        };
        return `<span class="badge ${map[normalized] || 'text-bg-light'}">${escapeHtml(normalized || '-')}</span>`;
    }

    function setRowEmpty(message) {
        refs.rowBody.innerHTML = `<tr><td colspan="11" class="text-center text-muted py-4">${escapeHtml(message)}</td></tr>`;
        refs.rowSummary.textContent = message;
        refs.rawPreview.textContent = 'Seed row를 선택하세요.';
        refs.parsedPreview.textContent = 'Seed row를 선택하세요.';
        refs.errorPreview.textContent = '-';
    }

    function renderBatches() {
        refs.batchSummary.textContent = `${formatNumber(batches.length)}개 배치`;
        if (batches.length === 0) {
            refs.batchBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">업로드 배치가 없습니다.</td></tr>';
            setRowEmpty('배치를 선택하세요.');
            return;
        }

        refs.batchBody.innerHTML = batches.map((batch) => {
            const id = String(batch.id || '');
            const selected = id === selectedBatchId ? 'table-primary' : '';
            const totalRows = batch.total_rows ?? batch.row_count ?? batch.total_count ?? 0;
            return `
                <tr class="${selected}" data-batch-id="${escapeHtml(id)}" role="button">
                    <td>${escapeHtml(batch.file_name || '-')}</td>
                    <td>${escapeHtml(batch.format_name || '-')}</td>
                    <td>${escapeHtml(batch.created_at || '-')}</td>
                    <td class="text-end">${formatNumber(totalRows)}</td>
                    <td class="text-end">${formatNumber(batch.valid_count ?? batch.ready_count)}</td>
                    <td class="text-end">${formatNumber(batch.warning_count)}</td>
                    <td class="text-end">${formatNumber(batch.error_count)}</td>
                    <td class="text-end">${formatNumber(batch.duplicate_count)}</td>
                    <td class="text-end">${formatNumber(batch.created_count ?? batch.processed_count)}</td>
                    <td>${statusBadge(batch.status || batch.process_status || '')}</td>
                    <td>${escapeHtml(batch.created_by || '-')}</td>
                </tr>
            `;
        }).join('');
    }

    function payloadValue(payload, keys) {
        if (!payload || typeof payload !== 'object') {
            return '';
        }
        for (const key of keys) {
            const value = payload[key];
            if (value !== undefined && value !== null && value !== '') {
                return value;
            }
        }
        return '';
    }

    function rawSummary(rawPayload) {
        if (!rawPayload || typeof rawPayload !== 'object') {
            return compact(rawPayload || '-');
        }
        const values = Object.values(rawPayload)
            .filter((value) => value !== null && value !== undefined && String(value).trim() !== '')
            .slice(0, 4);
        return compact(values.join(' | ') || JSON.stringify(rawPayload));
    }

    function renderRows() {
        refs.rowSummary.textContent = `${formatNumber(rows.length)}개 row`;
        if (rows.length === 0) {
            setRowEmpty('선택한 배치에 Seed row가 없습니다.');
            return;
        }

        refs.rowBody.innerHTML = rows.map((row) => {
            const mapped = row.mapped_payload || {};
            const raw = row.raw_payload || {};
            const id = String(row.id || '');
            const selected = id === selectedRowId ? 'table-primary' : '';
            const direction = payloadValue(mapped, ['transaction_direction', 'direction']);
            const client = payloadValue(mapped, [
                'client_company_name',
                'supplier_company_name',
                'customer_company_name',
                'client_business_number',
                'client_name',
            ]);
            return `
                <tr class="${selected}" data-row-id="${escapeHtml(id)}" role="button">
                    <td>${escapeHtml(row.row_no || '-')}</td>
                    <td>${statusBadge(row.status || row.process_status)}</td>
                    <td>${escapeHtml(row.source_type || row.import_type || '-')}</td>
                    <td>${escapeHtml(direction || '-')}</td>
                    <td>${escapeHtml(client || '-')}</td>
                    <td class="text-end">${formatNumber(payloadValue(mapped, ['supply_amount', 'amount_supply', 'supply']))}</td>
                    <td class="text-end">${formatNumber(payloadValue(mapped, ['vat_amount', 'tax_amount', 'vat']))}</td>
                    <td class="text-end">${formatNumber(payloadValue(mapped, ['total_amount', 'amount_total', 'total']))}</td>
                    <td title="${escapeHtml(pretty(raw))}">${escapeHtml(rawSummary(raw))}</td>
                    <td class="text-danger">${escapeHtml(compact(row.error_message || '-', 60))}</td>
                    <td>${statusBadge(row.status || row.process_status)}</td>
                </tr>
            `;
        }).join('');
    }

    function showRowDetail(rowId) {
        selectedRowId = rowId;
        const row = rows.find((item) => String(item.id || '') === rowId);
        renderRows();
        refs.rawPreview.textContent = row ? pretty(row.raw_payload) : 'Seed row를 선택하세요.';
        refs.parsedPreview.textContent = row ? pretty(row.mapped_payload) : 'Seed row를 선택하세요.';
        refs.errorPreview.textContent = row?.error_message || '-';
    }

    async function loadRows(batchId) {
        if (!batchId) {
            setRowEmpty('배치를 선택하세요.');
            return;
        }

        selectedRowId = '';
        refs.selectedBatchLabel.textContent = `batch: ${batchId}`;
        setRowEmpty('Seed row를 불러오는 중입니다.');

        const json = await fetchJson(`${API.batchRows}?batch_id=${encodeURIComponent(batchId)}`);
        rows = Array.isArray(json.data?.rows) ? json.data.rows : [];
        renderRows();
    }

    async function selectBatch(batchId) {
        selectedBatchId = batchId;
        renderBatches();
        await loadRows(batchId);
    }

    async function loadBatches() {
        refs.batchBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">업로드 배치를 불러오는 중입니다.</td></tr>';
        setRowEmpty('배치를 선택하세요.');

        const json = await fetchJson(API.batches);
        batches = Array.isArray(json.data) ? json.data : [];
        if (!batches.some((batch) => String(batch.id || '') === selectedBatchId)) {
            selectedBatchId = '';
        }
        renderBatches();
    }

    function bindEvents() {
        refs.reloadBtn?.addEventListener('click', () => {
            loadBatches().catch((error) => notify('error', error.message));
        });

        refs.batchBody?.addEventListener('click', (event) => {
            const row = event.target.closest('tr[data-batch-id]');
            if (!row) {
                return;
            }
            selectBatch(row.dataset.batchId || '').catch((error) => notify('error', error.message));
        });

        refs.rowBody?.addEventListener('click', (event) => {
            const row = event.target.closest('tr[data-row-id]');
            if (!row) {
                return;
            }
            showRowDetail(row.dataset.rowId || '');
        });
    }

    bindEvents();
    loadBatches().catch((error) => {
        refs.batchBody.innerHTML = `<tr><td colspan="11" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
        notify('error', error.message);
    });
})();

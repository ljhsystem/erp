(() => {
    const batchTableBody = document.querySelector('#uploadBatchTable tbody');
    const rowTableBody = document.querySelector('#uploadRowTable tbody');
    const reloadBatchesBtn = document.getElementById('reloadBatchesBtn');
    const createTransactionsBtn = document.getElementById('createTransactionsBtn');

    let selectedBatchId = '';
    let currentRows = [];

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
        if (type === 'error' || type === 'warning') alert(message);
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, options);
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.success === false) {
            throw new Error(json.message || '요청 처리에 실패했습니다.');
        }
        return json;
    }

    function formatNumber(value) {
        return Number(String(value || 0).replaceAll(',', '') || 0).toLocaleString('ko-KR');
    }

    function dataTypeLabel(type) {
        return {
            TAX_INVOICE: '세금계산서',
            CASH_RECEIPT: '현금영수증',
            CARD_PURCHASE: '카드(매입)',
            CARD_SALE: '카드(매출)',
            BANK: '입출',
            ETC: '기타',
        }[type] || type || '-';
    }

    function statusBadge(status) {
        const labels = {
            VALID: '정상',
            MAPPING_REQUIRED: '매핑필요',
            ERROR: '오류',
            CREATED: '생성완료',
        };
        const classes = {
            VALID: 'bg-success',
            MAPPING_REQUIRED: 'bg-warning text-dark',
            ERROR: 'bg-danger',
            CREATED: 'bg-primary',
        };
        return `<span class="badge ${classes[status] || 'bg-secondary'}">${labels[status] || escapeHtml(status)}</span>`;
    }

    function batchStatusSummary(batch) {
        const valid = Number(batch.valid_count || 0);
        const mapping = Number(batch.mapping_required_count || 0);
        const error = Number(batch.error_count || 0);
        const created = Number(batch.created_count || 0);
        return [
            valid ? `정상 ${valid}` : '',
            mapping ? `매핑 ${mapping}` : '',
            error ? `오류 ${error}` : '',
            created ? `생성 ${created}` : '',
        ].filter(Boolean).join(' / ') || '-';
    }

    function updateCreateButton() {
        const creatable = currentRows.some((row) => ['VALID', 'MAPPING_REQUIRED'].includes(row.status));
        if (createTransactionsBtn) {
            createTransactionsBtn.disabled = !selectedBatchId || !creatable;
        }
    }

    function renderBatches(batches) {
        if (!batchTableBody) return;
        if (!batches.length) {
            batchTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">업로드 배치가 없습니다.</td></tr>';
            return;
        }
        batchTableBody.innerHTML = batches.map((batch) => `
            <tr data-batch-id="${escapeHtml(batch.id)}" class="${batch.id === selectedBatchId ? 'table-primary' : ''}" role="button">
                <td>
                    <div class="fw-semibold text-truncate" title="${escapeHtml(batch.file_name)}">${escapeHtml(batch.file_name)}</div>
                    <div class="small text-muted">${escapeHtml(batch.created_at || '')}</div>
                </td>
                <td>${escapeHtml(dataTypeLabel(batch.data_type))}</td>
                <td class="text-end">${formatNumber(batch.total_rows)}</td>
                <td class="small">${escapeHtml(batchStatusSummary(batch))}</td>
            </tr>
        `).join('');
    }

    function renderRows(rows) {
        currentRows = rows;
        if (!rowTableBody) return;
        if (!rows.length) {
            rowTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">업로드 행이 없습니다.</td></tr>';
            updateCreateButton();
            return;
        }
        rowTableBody.innerHTML = rows.map((row) => {
            const mapped = row.mapped_payload || {};
            const rowClass = row.status === 'ERROR' ? 'table-danger' : (row.status === 'MAPPING_REQUIRED' ? 'table-warning' : '');
            return `
                <tr class="${rowClass}">
                    <td class="text-end">${escapeHtml(row.row_no)}</td>
                    <td>${statusBadge(row.status)}</td>
                    <td>${escapeHtml(mapped.transaction_date || '')}</td>
                    <td>${escapeHtml(mapped.company_name || mapped.business_number || '')}</td>
                    <td>${escapeHtml(mapped.description || '')}</td>
                    <td class="text-end">${formatNumber(mapped.total_amount || mapped.supply_amount || 0)}</td>
                    <td>${escapeHtml(row.error_message || '-')}</td>
                </tr>
            `;
        }).join('');
        updateCreateButton();
    }

    async function loadBatches() {
        const json = await fetchJson('/api/import/batches');
        renderBatches(json.data || []);
    }

    async function loadRows(batchId) {
        selectedBatchId = batchId;
        const json = await fetchJson(`/api/import/batch/rows?batch_id=${encodeURIComponent(batchId)}`);
        renderRows(json.data?.rows || []);
        await loadBatches();
    }

    batchTableBody?.addEventListener('click', (event) => {
        const row = event.target.closest('tr[data-batch-id]');
        if (!row) return;
        void loadRows(row.dataset.batchId).catch((error) => notify('error', error.message));
    });

    reloadBatchesBtn?.addEventListener('click', () => {
        void loadBatches().catch((error) => notify('error', error.message));
    });

    createTransactionsBtn?.addEventListener('click', async () => {
        if (!selectedBatchId) {
            notify('warning', '배치를 선택하세요.');
            return;
        }
        const json = await fetchJson('/api/import/create-transactions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ batch_id: selectedBatchId }),
        });
        notify('success', json.message || '거래가 생성되었습니다.');
        await loadRows(selectedBatchId);
    });

    loadBatches().catch((error) => notify('error', error.message));
})();

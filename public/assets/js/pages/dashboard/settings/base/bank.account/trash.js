export function bindTrashEvents({
    getTable,
    columnMap,
    escapeHtml,
    getCodeName,
    formatAccountNumber,
}) {
    document.addEventListener('trash:detail-render', (event) => {
        const { data, modal } = event.detail;
        if (modal.dataset.type !== 'account') return;

        const detailBox = modal.querySelector('.trash-detail');
        if (!detailBox) return;

        let html = `
            <div class="p-3">
                <h6 class="mb-3">계좌 상세</h6>
        `;

        Object.entries(columnMap).forEach(([key, config]) => {
            const value = data[key];
            if (value === null || value === undefined || value === '') return;

            let displayValue = value;
            if (key === 'bank_file') displayValue = value ? '등록됨' : '';
            if (key === 'bank_name' || key === 'account_type') {
                displayValue = getCodeName(key, value) || value;
            }
            if (key === 'account_number') {
                displayValue = formatAccountNumber(value, data.bank_name || '');
            }

            html += `<div><b>${config.label}:</b> ${displayValue}</div>`;
        });

        html += '</div>';
        detailBox.innerHTML = html;
    });

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.account = function (row) {
        return `
            <td>${row.sort_no ?? ''}</td>
            <td>${row.account_name ?? ''}</td>
            <td>${escapeHtml(getCodeName('bank_name', row.bank_name) || row.bank_name || '')}</td>
            <td>${escapeHtml(formatAccountNumber(row.account_number ?? '', row.bank_name ?? ''))}</td>
            <td>${row.account_holder ?? ''}</td>
            <td>${escapeHtml(getCodeName('account_type', row.account_type) || row.account_type || '')}</td>
            <td>${row.currency ?? ''}</td>
            <td>${row.deleted_at ?? ''}</td>
            <td>${row.deleted_by_name ?? row.deleted_by ?? ''}</td>
            <td>
                <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">삭제</button>
            </td>
        `;
    };

    document.addEventListener('trash:changed', (event) => {
        const { type } = event.detail || {};
        if (type === 'account') {
            getTable()?.ajax.reload(null, false);
        }
    });
}

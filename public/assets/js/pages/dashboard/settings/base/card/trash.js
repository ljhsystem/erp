export function bindTrashEvents({ getTable, columnMap, formatAmount }) {
    document.addEventListener('trash:detail-render', (event) => {
        const { data, modal } = event.detail;
        if (modal.dataset.type !== 'card') return;

        const detailBox = modal.querySelector('.trash-detail');
        if (!detailBox) return;

        let html = '<div class="p-3"><h6 class="mb-3">카드 상세</h6>';

        Object.entries(columnMap).forEach(([key, config]) => {
            const value = data[key];
            if (value === null || value === undefined || value === '') return;

            let displayValue = value;
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
            <td>${row.account_name ?? ''}</td>
            <td>${String(row.is_active) === '1' ? '사용' : '미사용'}</td>
            <td>${row.deleted_at ?? ''}</td>
            <td>${row.deleted_by_name ?? row.deleted_by ?? ''}</td>
            <td>
                <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">영구삭제</button>
            </td>
        `;
    };

    document.addEventListener('trash:changed', (event) => {
        if (event.detail?.type === 'card') {
            getTable()?.ajax.reload(null, false);
        }
    });
}

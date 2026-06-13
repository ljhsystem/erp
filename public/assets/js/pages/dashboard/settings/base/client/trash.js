export function bindTrashEvents({ getClientTable, clientColumnMap }) {
    document.addEventListener('trash:detail-render', function (event) {
        const { data, modal } = event.detail;
        if (modal.dataset.type !== 'client') return;

        const detailBox = modal.querySelector('.trash-detail');
        if (!detailBox) return;

        let html = `
            <div class="p-3">
                <h6 class="mb-3">거래처 상세</h6>
        `;

        Object.entries(clientColumnMap).forEach(([key, config]) => {
            const value = data[key];
            if (value === null || value === undefined || value === '') return;
            html += `
                <div><b>${config.label}:</b> ${value}</div>
            `;
        });

        html += `</div>`;
        detailBox.innerHTML = html;
    });

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.client = function (row) {
        return `
            <td>${row.sort_no ?? ''}</td>
            <td>${row.client_name ?? ''}</td>
            <td>${row.deleted_at ?? ''}</td>
            <td>${row.deleted_by_name ?? ''}</td>
            <td>
                <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">영구삭제</button>
            </td>
        `;
    };

    document.addEventListener('trash:changed', (event) => {
        const { type } = event.detail || {};
        if (type === 'client') {
            const table = typeof getClientTable === 'function' ? getClientTable() : null;
            if (table) {
                table.ajax.reload(null, false);
            }
        }
    });
}

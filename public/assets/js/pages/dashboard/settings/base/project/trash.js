export function bindTrashEvents({ getTable, columnMap, formatDateDisplay, formatAmount }) {
    document.addEventListener('trash:detail-render', (event) => {
        const { data, modal } = event.detail;
        if (modal.dataset.type !== 'project') return;
        const detailBox = modal.querySelector('.trash-detail');
        if (!detailBox) return;

        let html = `<div class="p-3"><h6 class="mb-3">프로젝트 상세</h6>`;
        Object.entries(columnMap).forEach(([key, config]) => {
            let value = data[key];
            if (value === null || value === undefined || value === '') return;
            if (['permit_date', 'contract_date', 'start_date', 'completion_date', 'bid_notice_date'].includes(key)) {
                value = formatDateDisplay(value);
            }
            if (key === 'initial_contract_amount') value = formatAmount(value);
            if (key === 'is_active') value = Number(value) === 1 ? '진행중' : '완료';
            html += `<div><b>${config.label}:</b> ${value}</div>`;
        });
        html += `</div>`;
        detailBox.innerHTML = html;
    });

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.project = function (row) {
        return `
            <td>${row.sort_no ?? ''}</td>
            <td>${row.project_name ?? ''}</td>
            <td>${row.deleted_at ?? ''}</td>
            <td>${row.deleted_by_name ?? ''}</td>
            <td>
                <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">삭제</button>
            </td>
        `;
    };

    document.addEventListener('trash:changed', (event) => {
        const { type } = event.detail || {};
        if (type === 'project') {
            const table = typeof getTable === 'function' ? getTable() : null;
            if (table) table.ajax.reload(null, false);
        }
    });
}

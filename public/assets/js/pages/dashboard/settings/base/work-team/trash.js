export function createWorkTeamTrashModule({ api, escapeHtml, reloadTable }) {
    function openTrashModal() {
        const modalEl = document.getElementById('workTeamTrashModal');
        if (!modalEl) return;

        modalEl.dataset.listUrl = api.TRASH;
        modalEl.dataset.restoreUrl = api.RESTORE;
        modalEl.dataset.restoreBulkUrl = api.RESTORE_BULK;
        modalEl.dataset.restoreAllUrl = api.RESTORE_ALL;
        modalEl.dataset.deleteUrl = api.PURGE;
        modalEl.dataset.deleteBulkUrl = api.PURGE_BULK;
        modalEl.dataset.deleteAllUrl = api.PURGE_ALL;

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function bindTrashEvents() {
        window.TrashColumns = window.TrashColumns || {};
        window.TrashColumns.workTeam = function (row) {
            return `
                <td>${row.sort_no ?? ''}</td>
                <td>${escapeHtml(row.team_name ?? '')}</td>
                <td>${escapeHtml(row.team_leader_client_name ?? '')}</td>
                <td>${row.deleted_at ?? ''}</td>
                <td>${escapeHtml(row.deleted_by_name ?? row.deleted_by ?? '')}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">영구삭제</button>
                </td>
            `;
        };

        document.addEventListener('trash:detail-render', function (event) {
            const { data, modal } = event.detail;
            if (modal.dataset.type !== 'workTeam') return;
            const detailBox = modal.querySelector('.trash-detail');
            if (!detailBox) return;

            detailBox.innerHTML = `
                <div class="p-3">
                    <h6 class="mb-3">팀관리 상세</h6>
                    <div><b>팀명</b> ${escapeHtml(data.team_name ?? '')}</div>
                    <div><b>팀장</b> ${escapeHtml(data.team_leader_client_name ?? '')}</div>
                    <div><b>상태</b> ${Number(data.is_active) === 1 ? '사용' : '미사용'}</div>
                    <div><b>비고</b> ${escapeHtml(data.note ?? '')}</div>
                    <div><b>메모</b> ${escapeHtml(data.memo ?? '')}</div>
                </div>
            `;
        });

        document.addEventListener('trash:changed', (event) => {
            if (event.detail?.type === 'workTeam') {
                reloadTable();
            }
        });
    }

    return {
        openTrashModal,
        bindTrashEvents,
    };
}

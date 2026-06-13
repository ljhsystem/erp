export function initTrashColumns(deps) {
    const { escapeHtml, escapeHtmlAttr } = deps;

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.cover = function (row) {
        const preview = row.url
            ? `<img src="${escapeHtmlAttr(row.url)}" style="width:60px;height:60px;object-fit:cover;">`
            : '<span class="text-muted">이미지 없음</span>';

        return `
            <td>${row.sort_no ?? ''}</td>
            <td>${preview}</td>
            <td>${escapeHtml(row.year ?? '')}</td>
            <td>${escapeHtml(row.title ?? '')}</td>
            <td>${escapeHtml(row.deleted_at ?? '')}</td>
            <td>${escapeHtml(row.deleted_by_name ?? '')}</td>
            <td>
                <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">삭제</button>
            </td>
        `;
    };
}

export function bindTrashEvents(deps) {
    const { DOM, qsa, escapeHtml, escapeHtmlAttr, getCoverTable } = deps;

    document.addEventListener('trash:detail-render', function (e) {
        const { data, modal, type } = e.detail || {};
        if (type !== 'cover') return;
        if (!modal || modal.dataset.type !== 'cover') return;

        const detailBox = modal.querySelector('.trash-detail');
        if (!detailBox) return;

        const preview = data?.url
            ? `<img src="${escapeHtmlAttr(data.url)}" class="cover-trash-detail-image" alt="${escapeHtmlAttr(data?.title ?? '')}">`
            : '<div class="cover-trash-empty-preview">이미지 없음</div>';

        detailBox.innerHTML = `
            <div class="cover-trash-detail-card">
                <div class="cover-trash-detail-head">
                    <h6 class="cover-trash-detail-title">삭제된 커버사진 상세</h6>
                    <button type="button" class="cover-trash-detail-close" id="btnCloseCoverTrashDetail">닫기</button>
                </div>
                <div class="cover-trash-preview">${preview}</div>
                <div class="cover-trash-detail-grid">
                    <div class="label">순번</div><div class="value">${escapeHtml(data?.sort_no ?? '')}</div>
                    <div class="label">해당년도</div><div class="value">${escapeHtml(data?.year ?? '')}</div>
                    <div class="label">타이틀</div><div class="value">${escapeHtml(data?.title ?? '')}</div>
                    <div class="label">Alt</div><div class="value">${escapeHtml(data?.alt ?? '')}</div>
                    <div class="label">설명</div><div class="value">${escapeHtml(data?.description ?? '')}</div>
                    <div class="label">삭제일</div><div class="value">${escapeHtml(data?.deleted_at ?? '')}</div>
                    <div class="label">삭제자</div><div class="value">${escapeHtml(data?.deleted_by_name || data?.deleted_by || '')}</div>
                </div>
            </div>
        `;
    });

    document.addEventListener('trash:changed', (e) => {
        const { type } = e.detail || {};
        if (type === 'cover') {
            const coverTable = getCoverTable();
            if (coverTable) {
                coverTable.ajax.reload(null, false);
            }
        }
    });

    window.jQuery(document).off('click', '#btnCloseCoverTrashDetail');
    window.jQuery(document).on('click', '#btnCloseCoverTrashDetail', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const modal = document.querySelector(DOM.trashModal);
        const layout = modal?.querySelector('.trash');
        const detail = modal?.querySelector('.trash-detail');

        if (layout) layout.classList.remove('open');
        if (detail) {
            detail.innerHTML = '';
            detail.style.display = 'none';
        }

        window.jQuery(`${DOM.trashTable} tbody tr`).removeClass('active');
        qsa('.tooltip-container').forEach(t => {
            t.style.display = 'none';
        });
    });
}

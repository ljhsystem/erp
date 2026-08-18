export function createPersonalExpenseListColumns({ actorColumn, escapeHtml, manageButtonRenderer, money, statusBadge }) {
    const virtual = (data, title, options = {}) => ({
        data, title, settingsKey: data, __dtColumnKind: 'virtual', ...options,
    });
    return [
        {
            data: null, settingsKey: '__reorder', __dtColumnKind: 'virtual',
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'reorder-handle no-colvis text-center no-export',
            headerClassName: 'text-center no-colvis', orderable: false, searchable: false,
            defaultContent: '<i class="bi bi-list"></i>', width: '36px',
        },
        {
            data: 'id', title: '신청서', visible: false, width: '90px',
            render: (_value, type, row) => type === 'display' ? `#${escapeHtml(row.sort_no || '-')}` : row.sort_no,
        },
        { data: 'sort_no', title: '신청순번', width: '80px' },
        { data: 'application_date', title: '신청일자', width: '110px' },
        { data: 'title', title: '신청제목', render: (value, type) => type === 'display' ? escapeHtml(value || '') : value },
        { data: 'description', title: '전체 비고', visible: false, render: (value, type) => type === 'display' ? escapeHtml(value || '-') : value },
        { data: 'memo', title: '전체 메모', visible: false, render: (value, type) => type === 'display' ? escapeHtml(value || '-') : value },
        {
            data: 'employee_id', title: '신청자', width: '100px',
            render: (_value, type, row) => type === 'display' ? escapeHtml(row.employee_name || '-') : (row.employee_name || ''),
        },
        { data: 'item_count', title: '활성 품목수', className: 'text-end', width: '85px' },
        { data: 'supply_amount', title: '공급가액 합계', className: 'text-end', render: value => money(value), width: '115px' },
        { data: 'vat_amount', title: '부가세 합계', className: 'text-end', render: value => money(value), width: '105px' },
        { data: 'total_amount', title: '신청금액 합계', className: 'text-end fw-bold', render: value => money(value), width: '120px' },
        {
            data: 'document_status', title: '결재상태', width: '95px',
            render: (_value, type, row) => type === 'display'
                ? statusBadge(row.approval_status || row.document_status)
                : (row.approval_status || row.document_status),
        },
        {
            data: 'current_approval_request_id', title: '현재 결재요청', defaultContent: '-', width: '120px',
            render: (_value, type, row) => {
                const label = Object.prototype.hasOwnProperty.call(row, 'approval_stage_name')
                    ? (row.approval_stage_name || '-')
                    : (row.current_step_name || (row.current_approval_request_id ? '결재 진행정보' : '-'));
                return type === 'display' ? escapeHtml(label) : label;
            },
        },
        virtual('approval_actor_name', '결재 처리자', {
            defaultContent: '-', width: '110px', orderable: false, searchable: false,
            render: (value, type) => type === 'display' ? escapeHtml(value || '-') : value,
        }),
        virtual('approval_action_result', '결재 처리결과', {
            defaultContent: '-', width: '180px', orderable: false, searchable: false,
            render: (value, type) => type === 'display' ? escapeHtml(value || '-') : value,
        }),
        { data: 'created_at', title: '생성일시', visible: false, width: '150px' },
        actorColumn('created_by', '생성자', { visible: false, width: '120px' }),
        { data: 'updated_at', title: '최종변경', width: '150px' },
        actorColumn('updated_by', '수정자', { visible: false, width: '120px' }),
        { data: 'deleted_at', title: '삭제일시', visible: false, width: '150px' },
        actorColumn('deleted_by', '삭제자', { visible: false, width: '120px' }),
        {
            data: null, settingsKey: '__actions', __dtColumnKind: 'virtual', title: '관리', width: '90px',
            className: 'text-center no-colvis', headerClassName: 'text-center no-colvis',
            orderable: false, searchable: false, defaultContent: '',
            render: (_value, type, row) => type === 'display' ? manageButtonRenderer(row, { escapeHtml }) : '',
        },
    ];
}

export function createPersonalExpenseTrashPresenter({ escapeHtml, money, status, statusKey }) {
    const columns = row => [
        `<td>#${escapeHtml(row.sort_no || '-')}</td>`,
        `<td>${escapeHtml(row.application_date || '-')}</td>`,
        `<td>${escapeHtml(row.title || '-')}</td>`,
        `<td>${escapeHtml(row.employee_name || '-')}</td>`,
        `<td class="text-end">${escapeHtml(row.item_count || 0)}건</td>`,
        `<td class="text-end">${escapeHtml(money(row.total_amount || 0))}원</td>`,
        `<td>${escapeHtml(row.deleted_at || '-')}</td>`,
        `<td class="text-nowrap">
            <button type="button" class="btn btn-outline-success btn-sm btn-restore" data-id="${escapeHtml(row.id)}">복원</button>
            <button type="button" class="btn btn-outline-danger btn-sm btn-purge" data-id="${escapeHtml(row.id)}">완전삭제</button>
        </td>`,
    ].join('');

    const renderDetail = (row = {}) => {
        const detailElement = document.getElementById('personal-expense-trash-detail');
        if (!detailElement) return;
        const items = Array.isArray(row.items) ? row.items : [];
        detailElement.innerHTML = `
            <h6 class="fw-bold mb-3">${escapeHtml(row.title || '개인경비 신청서')}</h6>
            <dl class="row small mb-3">
                <dt class="col-4">신청번호</dt><dd class="col-8">#${escapeHtml(row.sort_no || '-')}</dd>
                <dt class="col-4">신청자</dt><dd class="col-8">${escapeHtml(row.employee_name || '-')}</dd>
                <dt class="col-4">신청일자</dt><dd class="col-8">${escapeHtml(row.application_date || '-')}</dd>
                <dt class="col-4">결재상태</dt><dd class="col-8">${escapeHtml(status[statusKey(row.approval_status)]?.[0] || row.approval_status || '작성중')}</dd>
                <dt class="col-4">삭제자</dt><dd class="col-8">${escapeHtml(row.deleted_by_name || '-')}</dd>
                <dt class="col-4">삭제일시</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
            </dl>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead><tr><th>순번</th><th>지출일자</th><th>가맹점</th><th>품명</th><th class="text-end">합계금액</th></tr></thead>
                    <tbody>${items.length ? items.map(item => `<tr>
                        <td>${escapeHtml(item.sort_no || '-')}</td>
                        <td>${escapeHtml(item.expense_date || '-')}</td>
                        <td>${escapeHtml(item.merchant_name || '-')}</td>
                        <td>${escapeHtml(item.item_name || '-')}</td>
                        <td class="text-end">${escapeHtml(money(item.item_total_amount || 0))}원</td>
                    </tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted">개인경비 아이템이 없습니다.</td></tr>'}</tbody>
                </table>
            </div>`;
    };
    return { columns, renderDetail };
}

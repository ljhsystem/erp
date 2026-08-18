import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { actorColumn } from '/public/assets/js/common/actor.js';
import { getCodeNameByGroup } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { notify } from '/public/assets/js/common/notification.js';

function reorderPermission(table) {
    const info = table?.page?.info?.();
    const firstOrder = table?.order?.()?.[0] || [];
    const orderColumn = Number(firstOrder[0]);
    const orderDirection = String(firstOrder[1] || '').toLowerCase();
    const columnData = table?.settings?.()?.[0]?.aoColumns?.[orderColumn]?.mData;
    const fullList = !info || (Number(info.pages || 1) <= 1 && Number(info.recordsDisplay) === Number(info.recordsTotal));
    if (!fullList || columnData !== 'sort_no' || orderDirection !== 'asc') {
        return { allowed: false, message: '검색을 해제하고 순번 오름차순의 전체 목록에서만 순서를 변경할 수 있습니다.' };
    }
    return { allowed: true, message: '' };
}

function codeColumn(data, title, escape, visible = false) {
    const groups = {
        contract_type: 'EMPLOYMENT_CONTRACT_TYPE',
        contract_period_type: 'EMPLOYMENT_CONTRACT_PERIOD_TYPE',
        employment_category: 'EMPLOYMENT_CATEGORY',
        working_time_type: 'EMPLOYMENT_WORKING_TIME_TYPE',
        fixed_term_reason_code: 'EMPLOYMENT_CONTRACT_FIXED_TERM_REASON',
        work_location_type: 'WORK_LOCATION_TYPE',
        work_schedule_type: 'WORK_SCHEDULE_TYPE',
        salary_type: 'SALARY_TYPE',
        payment_timing: 'PAYMENT_TIMING',
        termination_reason: 'EMPLOYMENT_TERMINATION_REASON',
    };
    return {
        data,
        title,
        visible,
        defaultContent: '-',
        render: value => escape(getCodeNameByGroup(groups[data], value) || value || '-'),
    };
}

export async function createEmploymentContractTable({
    api,
    badge,
    escapeHtml,
    requestKey,
    onOpen,
    onNew,
    trashModal,
}) {
    const table = await createDataTable({
        tableSelector: '#employmentContractTable',
        api: api.list,
        selectable: true,
        deleteButton: true,
        deleteApi: api.delete,
        deletePayload: () => ({ request_key: requestKey() }),
        searching: true,
        serverSide: true,
        showSelectionMoveButtons: true,
        redrawAfterInitialVisibility: false,
        pageLength: 50,
        defaultOrder: [[1, 'asc']],
        tableSettings: {
            enabled: true,
            pageKey: 'institution.human_resources.employment_contracts',
            tableKey: 'employment-contract-main',
            storageKey: 'datatable.settings.institution.employment-contract.main.v1',
            tableLabel: '근로계약관리',
            metaDomain: 'employment-contract',
        },
        buttons: [
            {
                text: '휴지통',
                className: 'btn btn-danger btn-sm',
                action: () => trashModal?.show(),
            },
            {
                text: '신규등록',
                className: 'btn btn-warning btn-sm',
                action: onNew,
            },
        ],
        columns: [
            {
                data: null,
                title: '<i class="bi bi-arrows-move"></i>',
                settingsKey: '__reorder',
                __dtColumnKind: 'virtual',
                className: 'reorder-handle no-sort no-colvis text-center',
                headerClassName: 'no-colvis text-center',
                orderable: false,
                searchable: false,
                defaultContent: '<i class="bi bi-list"></i>',
            },
            {
                data: 'sort_no',
                title: '순번',
                orderable: true,
                searchable: false,
                className: 'text-center',
                defaultContent: '',
                render: value => Number(value || 0),
            },
            { data: 'contract_no', title: '계약번호', className: 'text-nowrap' },
            { data: 'employee_name', title: '직원명', defaultContent: '-' },
            { data: 'project_name', title: '프로젝트명', defaultContent: '-' },
            { data: 'previous_contract_no', title: '이전 계약번호', defaultContent: '-' },
            codeColumn('contract_type', '계약종류', escapeHtml),
            codeColumn('contract_period_type', '계약기간 구분', escapeHtml, true),
            codeColumn('employment_category', '고용구분', escapeHtml, true),
            codeColumn('working_time_type', '근로시간 구분', escapeHtml, true),
            codeColumn('fixed_term_reason_code', '기간제 계약 사유', escapeHtml),
            codeColumn('work_location_type', '근무장소 구분', escapeHtml),
            codeColumn('work_schedule_type', '근무형태', escapeHtml),
            codeColumn('salary_type', '급여형태', escapeHtml),
            codeColumn('payment_timing', '급여지급 기준', escapeHtml),
            { data: 'contract_start_date', title: '계약시작일', className: 'text-nowrap' },
            { data: 'contract_end_date', title: '계약종료일', className: 'text-nowrap', defaultContent: '기간의 정함 없음' },
            { data: 'contract_status', title: '상태', render: (value, _type, row) => badge(value, row.contract_status_name) },
            codeColumn('termination_reason', '종료사유', escapeHtml),
            { data: 'revision_no', title: '개정차수', className: 'text-center', render: value => `${Number(value || 1)}차` },
            actorColumn('created_by', '생성자'),
            actorColumn('updated_by', '수정자'),
            actorColumn('deleted_by', '삭제자', { visible: false }),
            { data: 'created_at', title: '생성일시', className: 'text-nowrap', defaultContent: '-' },
            { data: 'updated_at', title: '수정일시', className: 'text-nowrap', defaultContent: '-' },
            { data: 'deleted_at', title: '삭제일시', className: 'text-nowrap', defaultContent: '-', visible: false },
            { data: null, settingsKey: '__actions', title: '관리', orderable: false, searchable: false, className: 'text-center no-colvis', defaultOrder: 999999, settingsOrder: 999999, render: row => `<button type="button" class="btn btn-outline-primary btn-sm" data-open="${escapeHtml(row.id)}">상세</button>` },
        ],
    });

    const body = document.querySelector('#employmentContractTable tbody');
    body?.addEventListener('dblclick', event => {
        const row = table.row(event.target.closest('tr')).data();
        if (row?.id) onOpen(row.id);
    });
    body?.addEventListener('click', event => {
        const button = event.target.closest('[data-open]');
        if (button) onOpen(button.dataset.open);
    });
    SearchForm({
        table,
        apiList: api.list,
        tableId: 'employmentContract',
        defaultSearchField: 'keyword',
        dateOptions: ['contract_start_date', 'contract_end_date'],
    });
    bindRowReorder(table, {
        api: api.reorder,
        canReorder: () => reorderPermission(table),
        onSuccess: () => {
            notify('success', '근로계약 순서가 저장되었습니다.');
            table.ajax.reload(null, false);
        },
        onError: payload => {
            notify('error', payload?.message || '순서 저장 중 오류가 발생했습니다.');
            table.ajax.reload(null, false);
        },
    });
    return table;
}

export function createProjectTableModule({
    createDataTable,
    bindTableHighlight,
    bindRowReorder,
    SearchForm,
    formatDateDisplay,
    formatAmount,
    getCodeName,
    API,
    DATE_OPTIONS,
    formModule,
    modalModule,
    state,
}) {
    const PROJECT_COLUMN_MAP = {
        sort_no: { label: '순번', visible: true },
        project_name: { label: '프로젝트명', visible: true },
        construction_name: { label: '공사명', visible: false },
        employee_name: { label: '담당직원', visible: true },
        client_name: { label: '발주사명', visible: false },
        client_type: { label: '발주사분류', visible: false },
        bid_type: { label: '입찰방법', visible: false },
        site_agent: { label: '현장대리인', visible: false },
        linked_client_name: { label: '거래처', visible: true },
        contract_type: { label: '계약종류', visible: true },
        contract_method: { label: '계약방식', visible: true },
        director: { label: '감리관/부장', visible: false },
        manager: { label: '소장', visible: false },
        contract_work_type: { label: '계약종류(기존)', visible: false },
        housing_type: { label: '공사유형', visible: false },
        work_type: { label: '공종', visible: false },
        work_subtype: { label: '공종 세분류', visible: false },
        business_type: { label: '업종(대업종)', visible: false },
        work_detail_type: { label: '세부 공사종류', visible: true },
        site_region_city: { label: '시도', visible: false },
        site_region_district: { label: '시군구', visible: false },
        site_region_address: { label: '주소', visible: false },
        site_region_address_detail: { label: '상세주소', visible: false },
        permit_date: { label: '허가일자', visible: false },
        contract_date: { label: '계약일자', visible: true },
        start_date: { label: '착공일자', visible: true },
        completion_date: { label: '준공일자', visible: true },
        bid_notice_date: { label: '입찰공고일', visible: false },
        initial_contract_amount: { label: '최초계약금액', visible: false },
        permit_agency: { label: '허가기관', visible: false },
        authorized_company_seal: { label: '사용인감명', visible: false },
        note: { label: '비고', visible: true },
        memo: { label: '메모', visible: false },
        is_active: { label: '진행상황', visible: true },
        created_at: { label: '등록일시', visible: false },
        created_by_name: { label: '등록자', visible: false },
        updated_at: { label: '수정일시', visible: false },
        updated_by_name: { label: '수정자', visible: false },
        deleted_at: { label: '삭제일시', visible: false },
        deleted_by_name: { label: '삭제자', visible: false },
    };

    function getProjectColumnAlignClass(field) {
        if (['sort_no', 'employee_name', 'work_type', 'permit_date', 'contract_date', 'start_date', 'completion_date', 'bid_notice_date', 'is_active'].includes(field)) {
            return 'text-center';
        }
        if (field === 'initial_contract_amount') return 'text-end';
        return '';
    }

    function updateProjectCount(count) {
        const el = document.getElementById('projectCount');
        if (el) el.textContent = `총 ${count ?? 0}건`;
    }

    function normalizeActiveValue(value) {
        const raw = String(value ?? '').trim().toLowerCase();
        if (['1', '진행중', '사용중', '진행', '활성', 'active', 'y', 'yes', 'true'].includes(raw)) return '1';
        if (['0', '완료', '종료', '비활성', 'inactive', 'n', 'no', 'false'].includes(raw)) return '0';
        return '';
    }

    function normalizeProjectFilters(filters) {
        return (filters || []).map((filter) => {
            if (filter?.field !== 'is_active') return filter;
            const value = normalizeActiveValue(filter.value);
            return value === '' ? null : { field: 'is_active', value };
        }).filter(Boolean);
    }

    async function updateProjectActive(projectId, active, toggleEl) {
        if (!projectId) return;
        if (toggleEl) toggleEl.disabled = true;
        try {
            const data = await modalModule.fetchProjectDetail(projectId);
            const formData = new FormData();
            Object.entries(data || {}).forEach(([key, value]) => {
                formData.set(key, value ?? '');
            });
            formData.set('id', projectId);
            formData.set('is_active', active ? '1' : '0');
            const res = await window.jQuery.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            });
            if (!res.success) throw new Error(res.message || '상태 변경에 실패했습니다.');
            formModule.notify('success', active ? '진행중으로 변경되었습니다.' : '완료로 변경되었습니다.');
            state.projectTable?.ajax.reload(null, false);
        } catch (error) {
            if (toggleEl) toggleEl.checked = !active;
            console.error(error);
            formModule.notify('error', error.message || '상태 변경 중 오류가 발생했습니다.');
        } finally {
            if (toggleEl) toggleEl.disabled = false;
        }
    }

    function buildProjectColumns() {
        const columns = [{
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
            settingsKey: '__reorder',
            className: 'col-reorder reorder-handle no-sort no-colvis text-center',
            headerClassName: 'col-reorder no-colvis text-center',
            orderable: false,
            defaultContent: '<i class="bi bi-list"></i>',
        }];

        Object.entries(PROJECT_COLUMN_MAP).forEach(([field, config]) => {
            if (field === 'is_active') return;
            const alignClassName = getProjectColumnAlignClass(field);
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                className: alignClassName || '',
                headerClassName: alignClassName || '',
                defaultContent: '',
                render(data, type, row) {
                    if (data === null || data === undefined) return '';
                    if (field === 'site_agent') return row?.site_agent_name || data;
                    if (['permit_date', 'contract_date', 'start_date', 'completion_date', 'bid_notice_date'].includes(field)) {
                        return formatDateDisplay(data);
                    }
                    if (field === 'initial_contract_amount') return formatAmount(data);
                    if (['bid_type', 'contract_method', 'housing_type', 'contract_type', 'work_type'].includes(field)) {
                        return getCodeName(field, data);
                    }
                    return data;
                },
            });
        });

        columns.push({
            data: 'is_active',
            title: PROJECT_COLUMN_MAP.is_active.label,
            visible: true,
            className: 'text-center',
            headerClassName: 'text-center',
            defaultContent: '',
            render(data, type, row) {
                if (type !== 'display') return data;
                const active = Number(data) === 1;
                return `<div class="form-check form-switch d-inline-flex justify-content-center m-0"><input type="checkbox" class="form-check-input project-active-toggle" data-id="${formModule.escapeHtml(row.id || '')}" ${active ? 'checked' : ''} aria-label="진행상황 변경"></div>`;
            },
        });

        columns.push({
            data: null,
            settingsKey: '__actions',
            title: '관리',
            className: 'text-center no-colvis',
            headerClassName: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render(_data, type, row) {
                if (type !== 'display') return '';
                return `<button type="button" class="btn btn-outline-primary btn-sm project-edit-btn" data-id="${formModule.escapeHtml(row.id || '')}">수정</button>`;
            },
        });
        return columns;
    }

    function initDataTable() {
        state.projectTable = createDataTable({
            tableSelector: '#project-table',
            api: API.LIST,
            deleteApi: API.DELETE,
            columns: buildProjectColumns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            selectionColumn: { widthResizable: true },
            buttons: [
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: () => {
                        const trashModalEl = document.getElementById('projectTrashModal');
                        if (!trashModalEl) return;
                        trashModalEl.dataset.listUrl = API.TRASH;
                        trashModalEl.dataset.restoreUrl = API.RESTORE;
                        trashModalEl.dataset.deleteUrl = API.PURGE;
                        trashModalEl.dataset.deleteAllUrl = API.PURGE_ALL;
                        new bootstrap.Modal(trashModalEl).show();
                    },
                },
                { text: '엑셀관리', className: 'btn btn-success btn-sm', action: () => state.excelModal?.show() },
                { text: '새 프로젝트', className: 'btn btn-warning btn-sm', action: () => modalModule.openCreateModal() },
            ],
        });

        window.projectTable = state.projectTable;
        if (!state.projectTable) return;
        state.projectTable.on('init.dt', () => updateProjectCount(state.projectTable.page.info()?.recordsDisplay ?? 0));
        state.projectTable.on('draw.dt', () => updateProjectCount(state.projectTable.page.info()?.recordsDisplay ?? 0));
        SearchForm({
            table: state.projectTable,
            apiList: API.LIST,
            tableId: 'project',
            defaultSearchField: 'project_name',
            dateOptions: DATE_OPTIONS,
            normalizeFilters: normalizeProjectFilters,
        });
        bindTableHighlight('#project-table', state.projectTable);
        bindRowReorder(state.projectTable, {
            api: API.REORDER,
            onSuccess() {
                formModule.notify('success', '프로젝트 순서가 저장되었습니다.');
                state.projectTable?.ajax.reload(null, false);
            },
            onError(json) {
                formModule.notify('error', json?.message || '프로젝트 순서 저장에 실패했습니다.');
                state.projectTable?.ajax.reload(null, false);
            },
        });
    }

    function bindTableEvents($) {
        $('#project-table tbody').on('change', '.project-active-toggle', function (event) {
            event.preventDefault();
            event.stopPropagation();
            updateProjectActive(this.dataset.id, this.checked, this);
        });
        $('#project-table tbody').on('click', '.project-edit-btn', function (event) {
            event.preventDefault();
            event.stopPropagation();
            modalModule.openProjectEditModal(this.dataset.id);
        });
        $('#project-table tbody').on('dblclick', 'tr', async function () {
            const rowData = state.projectTable?.row(this).data();
            if (rowData?.id) await modalModule.openProjectEditModal(rowData.id);
        });
    }

    return {
        PROJECT_COLUMN_MAP,
        initDataTable,
        bindTableEvents,
    };
}

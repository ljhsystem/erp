export function createClientTableModule({
    createDataTable,
    bindTableHighlight,
    bindRowReorder,
    SearchForm,
    NumberFormat,
    getCodeName,
    API,
    DATE_OPTIONS,
    modalModule,
    formModule,
    state,
}) {
    const { formatBizNumber, formatCorpNumber, formatMobile, formatPhone, formatAccountNumber } = NumberFormat;
    const { notify, escapeHtml } = formModule;

    const CLIENT_COLUMN_MAP = {
        sort_no: { label: '순번', visible: true },
        client_name: { label: '거래처명', visible: true },
        company_name: { label: '상호', visible: true },
        registration_date: { label: '등록일자', visible: true },
        business_number: { label: '사업자등록번호', visible: true },
        rrn: { label: '법인/주민등록번호', visible: false },
        business_type: { label: '업태', visible: false },
        business_category: { label: '업종', visible: false },
        business_status: { label: '사업자상태', visible: true },
        business_certificate: { label: '사업자등록증', visible: false },
        address: { label: '주소', visible: false },
        address_detail: { label: '상세주소', visible: false },
        phone: { label: '전화번호', visible: true },
        fax: { label: '팩스', visible: false },
        email: { label: '이메일', visible: true },
        ceo_name: { label: '대표자', visible: true },
        ceo_phone: { label: '대표자전화', visible: false },
        manager_name: { label: '담당자', visible: false },
        manager_phone: { label: '담당자전화', visible: false },
        bank_name: { label: '은행명', visible: false },
        account_number: { label: '계좌번호', visible: false },
        account_holder: { label: '예금주', visible: false },
        bank_file: { label: '통장사본', visible: false },
        trade_category: { label: '거래구분', visible: false },
        default_account_text: { label: '기본계정과목', visible: false },
        item_category: { label: '취급품목', visible: false },
        client_category: { label: '거래처분류', visible: false },
        client_type: { label: '거래처구분', visible: false },
        tax_type: { label: '과세구분', visible: false },
        payment_term: { label: '결제조건', visible: false },
        client_grade: { label: '거래처등급', visible: false },
        homepage: { label: '홈페이지', visible: false },
        note: { label: '비고', visible: true },
        memo: { label: '메모', visible: false },
        is_active: { label: '상태', visible: true },
        created_at: { label: '생성일시', visible: false },
        created_by_name: { label: '생성자', visible: false },
        updated_at: { label: '수정일시', visible: false },
        updated_by_name: { label: '수정자', visible: false },
        deleted_at: { label: '삭제일시', visible: false },
        deleted_by_name: { label: '삭제자', visible: false },
        rrn_image: { label: '신분증이미지', visible: false },
    };

    const CLIENT_COLUMN_WIDTHS = {
        __reorder: '40px', sort_no: '80px', client_name: '200px', company_name: '160px', registration_date: '120px',
        business_number: '140px', rrn: '150px', rrn_image: '120px', business_type: '120px', business_category: '120px',
        business_status: '100px', business_certificate: '120px', address: '240px', address_detail: '220px', phone: '140px',
        fax: '140px', email: '200px', ceo_name: '120px', ceo_phone: '140px', manager_name: '120px', manager_phone: '140px',
        bank_name: '120px', account_number: '160px', account_holder: '120px', bank_file: '120px', trade_category: '120px',
        default_account_text: '180px', item_category: '140px', client_category: '140px', client_type: '120px', tax_type: '120px',
        payment_term: '140px', client_grade: '120px', homepage: '200px', note: '220px', memo: '220px', is_active: '90px',
        created_at: '160px', created_by_name: '120px', updated_at: '160px', updated_by_name: '120px', deleted_at: '160px', deleted_by_name: '120px',
    };

    function updateClientCount(count) {
        const el = document.getElementById('clientCount');
        if (el) el.textContent = `총 ${count ?? 0}건`;
    }

    function normalizeActiveValue(value) {
        const raw = String(value ?? '').trim().toLowerCase();
        if (['1', '사용', '사용중', '활성', 'active', 'y', 'yes', 'true'].includes(raw)) return '1';
        if (['0', '미사용', '비활성', 'inactive', 'n', 'no', 'false'].includes(raw)) return '0';
        return '';
    }

    function normalizeClientFilters(filters) {
        return (filters || []).map((filter) => {
            if (filter?.field !== 'is_active') return filter;
            const value = normalizeActiveValue(filter.value);
            return value === '' ? null : { field: 'is_active', value };
        }).filter(Boolean);
    }

    async function fetchClientDetail(id) {
        const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || '상세조회에 실패했습니다.');
        return json.data;
    }

    async function openClientEditModal(id) {
        if (!id) return;
        window.isNewClient = false;
        modalModule.resetClientFileInputs();
        document.getElementById('client-edit-form')?.reset();
        formModule.clearClientModalSelectDisplays();
        window.jQuery('#btnDeleteClient').show();
        window.jQuery('#modal_client_id').val(id);
        const titleEl = document.getElementById('clientModalLabel');
        if (titleEl) titleEl.textContent = '거래처 수정';
        state.clientModal?.show();
        try {
            const data = await fetchClientDetail(id);
            window.jQuery('#btnDeleteClient').show();
            window.jQuery('#modal_client_id').val(data.id);
            modalModule.resetClientFileInputs();
            await modalModule.prepareClientModalControls(data.default_account_id || '');
            modalModule.fillModal(data);
        } catch (error) {
            console.error(error);
            notify('error', error.message || '서버 오류가 발생했습니다.');
        }
    }

    async function updateClientActive(clientId, active, toggleEl) {
        if (!clientId) return;
        if (toggleEl) toggleEl.disabled = true;
        try {
            const data = await fetchClientDetail(clientId);
            const formData = new FormData();
            Object.entries(data || {}).forEach(([key, value]) => {
                if (['company_name_history', 'business_certificate', 'rrn_image', 'bank_file', 'rrn'].includes(key)) return;
                formData.set(key, value ?? '');
            });
            formData.set('id', clientId);
            formData.set('is_active', active ? '1' : '0');
            formData.set('delete_business_certificate', '0');
            formData.set('delete_rrn_image', '0');
            formData.set('delete_bank_file', '0');
            const res = await window.jQuery.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            });
            if (!res.success) throw new Error(res.message || '상태 변경에 실패했습니다.');
            notify('success', active ? '사용 상태로 변경되었습니다.' : '미사용 상태로 변경되었습니다.');
            state.clientTable?.ajax.reload(null, false);
        } catch (error) {
            if (toggleEl) toggleEl.checked = !active;
            console.error(error);
            notify('error', error.message || '상태 변경 중 오류가 발생했습니다.');
        } finally {
            if (toggleEl) toggleEl.disabled = false;
        }
    }

    function buildClientColumns() {
        const columns = [{
            title: '<i class="bi bi-arrows-move"></i>',
            settingsKey: '__reorder',
            width: CLIENT_COLUMN_WIDTHS.__reorder,
            widthResizable: true,
            className: 'reorder-handle no-sort no-colvis text-center',
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>',
        }];

        Object.entries(CLIENT_COLUMN_MAP).forEach(([field, config]) => {
            if (field === 'is_active') return;
            columns.push({
                data: field,
                title: config.label,
                width: CLIENT_COLUMN_WIDTHS[field] || '120px',
                visible: config.visible ?? true,
                defaultContent: '',
                render(data, type, row) {
                    if (data == null) return '';
                    if (type !== 'display') return data;
                    if (field === 'bank_file' || field === 'business_certificate' || field === 'rrn_image') return data ? '등록' : '';
                    if (field === 'business_number') return formatBizNumber(data);
                    if (field === 'rrn') return formatCorpNumber(data);
                    if (field === 'ceo_phone' || field === 'manager_phone') return formatMobile(data);
                    if (field === 'phone' || field === 'fax') return formatPhone(data);
                    if (['client_type', 'client_category', 'bank_name', 'trade_category', 'tax_type', 'payment_term'].includes(field)) return getCodeName(field, data);
                    if (field === 'account_number') return formatAccountNumber?.(data, row.bank_name || '') || data;
                    return data;
                },
            });
        });

        columns.push({
            data: 'is_active',
            title: '상태',
            width: CLIENT_COLUMN_WIDTHS.is_active || '90px',
            widthResizable: true,
            visible: true,
            className: 'text-center',
            defaultContent: '',
            render(data, type, row) {
                if (type !== 'display') return data;
                const active = Number(data) === 1;
                return `<div class="form-check form-switch d-inline-flex justify-content-center m-0"><input type="checkbox" class="form-check-input client-active-toggle" data-id="${escapeHtml(row.id || '')}" ${active ? 'checked' : ''} aria-label="상태 변경"></div>`;
            },
        });

        columns.push({
            data: null,
            settingsKey: '__actions',
            title: '관리',
            width: '90px',
            widthResizable: true,
            className: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render(_data, type, row) {
                if (type !== 'display') return '';
                return `<button type="button" class="btn btn-outline-primary btn-sm client-edit-btn" data-id="${escapeHtml(row.id || '')}">수정</button>`;
            },
        });
        return columns;
    }

    function bindTableEvents($) {
        $('#client-table tbody').on('change', '.client-active-toggle', function (event) {
            event.preventDefault();
            event.stopPropagation();
            updateClientActive(this.dataset.id, this.checked, this);
        });
        $('#client-table tbody').on('click', '.client-edit-btn', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openClientEditModal(this.dataset.id);
        });
        $('#client-table tbody').on('dblclick', 'tr', async function () {
            const row = state.clientTable?.row(this).data();
            if (row) await openClientEditModal(row.id);
        });
    }

    function initDataTable() {
        console.log('[CLIENT] table exists', document.querySelector('#client-table'));
        state.clientTable = createDataTable({
            tableSelector: '#client-table',
            api: API.LIST,
            deleteApi: API.DELETE,
            columns: buildClientColumns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            selectionColumn: { widthResizable: true },
            buttons: [
                { text: '엑셀관리', className: 'btn btn-success btn-sm', action: () => state.excelModal?.show() },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: () => {
                        const modalEl = document.getElementById('clientTrashModal');
                        if (!modalEl) return;
                        modalEl.dataset.listUrl = API.TRASH;
                        modalEl.dataset.restoreUrl = API.RESTORE;
                        modalEl.dataset.restoreBulkUrl = API.RESTORE_BULK;
                        modalEl.dataset.restoreAllUrl = API.RESTORE_ALL;
                        modalEl.dataset.deleteUrl = API.PURGE;
                        modalEl.dataset.deleteBulkUrl = API.PURGE_BULK;
                        modalEl.dataset.deleteAllUrl = API.PURGE_ALL;
                        new bootstrap.Modal(modalEl).show();
                    },
                },
                {
                    text: '새 거래처',
                    className: 'btn btn-warning btn-sm',
                    action: () => {
                        modalModule.openClientQuickCreate({
                            title: '거래처 빠른 등록',
                            onSuccess() { state.clientTable?.ajax?.reload(null, false); },
                            openDetail(values = {}) { modalModule.openClientCreateDetailModal(values); },
                        });
                    },
                },
            ],
        });

        window.clientTable = state.clientTable;
        if (!state.clientTable) return;
        SearchForm({
            table: state.clientTable,
            apiList: API.LIST,
            tableId: 'client',
            defaultSearchField: 'client_name',
            dateOptions: DATE_OPTIONS,
            normalizeFilters: normalizeClientFilters,
        });
        bindTableHighlight('#client-table', state.clientTable);
        state.clientTable.on('init.dt', () => updateClientCount(state.clientTable.page.info()?.recordsDisplay ?? 0));
        state.clientTable.on('draw.dt', () => {
            updateClientCount(state.clientTable.page.info()?.recordsDisplay ?? 0);
            console.log('[CLIENT] table rendered');
        });
        bindRowReorder(state.clientTable, {
            api: API.REORDER,
            onSuccess() {
                notify('success', '거래처 순서가 저장되었습니다.');
                state.clientTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '거래처 순서 저장에 실패했습니다.');
                state.clientTable?.ajax.reload(null, false);
            },
        });
    }

    return {
        CLIENT_COLUMN_MAP,
        initDataTable,
        bindTableEvents,
        normalizeClientFilters,
        updateClientCount,
    };
}

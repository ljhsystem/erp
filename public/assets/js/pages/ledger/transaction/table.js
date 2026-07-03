import { actorColumn } from '/public/assets/js/common/actor.js';

export function registerTable(ctx) {
    const {
        bindTableHighlight,
        createDataTable,
        SearchForm,
        bindRowReorder,
    } = ctx;

    function buildTransactionDataColumns() {
        const textColumn = (data, title, options = {}) => ({
            data,
            title,
            defaultContent: '',
            visible: options.visible ?? false,
            className: options.className || '',
            width: options.width,
            render(value) {
                if (typeof options.render === 'function') {
                    return options.render(value);
                }
                return ctx.escapeHtml(value || '');
            },
        });

        const amountColumn = (data, title, visible = false) => ({
            data,
            title,
            className: 'text-end',
            visible,
            defaultContent: 0,
            render(value) {
                return ctx.escapeHtml(ctx.formatAmount(value || 0));
            },
        });

        return [
            {
                title: '<i class="bi bi-arrows-move"></i>',
                className: 'reorder-handle no-colvis text-center',
                orderable: false,
                searchable: false,
                defaultContent: '<i class="bi bi-list"></i>',
            },
            textColumn('sort_no', '순번', {
                visible: true,
                className: 'text-center transaction-sort-no-cell',
            }),
            textColumn('transaction_date', '거래일자', { visible: true }),
            textColumn('business_unit', '사업구분'),
            textColumn('client_id', '거래처ID', { visible: false }),
            {
                data: 'client_name',
                title: '거래처',
                render(data, type, row) {
                    return ctx.escapeHtml(data || row.client_name || '-');
                },
            },
            textColumn('project_id', '프로젝트ID'),
            {
                data: 'project_name',
                title: '프로젝트',
                defaultContent: '',
                visible: false,
                render(data, type, row) {
                    return ctx.escapeHtml(data || row.project_name || '-');
                },
            },
            amountColumn('transaction_foreign_amount', '외화금액', true),
            amountColumn('transaction_supply_amount', '공급가액', true),
            amountColumn('transaction_settlement_amount', '정산금액', true),
            amountColumn('transaction_final_amount', '최종금액', true),
            textColumn('currency', '통화'),
            textColumn('exchange_rate', '환율', { className: 'text-end' }),
            {
                data: 'transaction_line_status',
                title: '거래라인상태',
                className: 'text-center text-nowrap',
                visible: true,
                render: ctx.renderLineStatus,
            },
            {
                data: 'description',
                title: '적요',
                className: 'transaction-description-cell',
                defaultContent: '',
                render(data) {
                    return ctx.escapeHtml(data || '');
                },
            },
            {
                data: 'match_status',
                title: '전표연결',
                className: 'text-center',
                visible: true,
                defaultContent: 'none',
                render(data) {
                    return ctx.renderMatchStatus(data);
                },
            },
            textColumn('status', '거래상태', {
                visible: true,
                className: 'text-center',
                render: ctx.renderTransactionStatus,
            }),
            textColumn('note', '비고'),
            textColumn('memo', '메모'),
            textColumn('created_at', '생성일시'),
            actorColumn('created_by', '생성자', { visible: false }),
            textColumn('updated_at', '수정일시'),
            actorColumn('updated_by', '수정자', { visible: false }),
            textColumn('deleted_at', '삭제일시'),
            actorColumn('deleted_by', '삭제자', { visible: false }),
            {
                data: 'id',
                title: 'ID',
                className: 'no-colvis',
                visible: false,
                searchable: false,
                defaultContent: '',
                render(data) {
                    return ctx.escapeHtml(data || '');
                },
            },
        ];
    }

    function updateCount() {
        if (!ctx.transactionTable || !ctx.countEl) return;

        const info = ctx.transactionTable.page.info();
        ctx.countEl.textContent = `${info.recordsDisplay || 0}건`; 
    }

    function initTransactionTable() {
        if (ctx.transactionTable || !window.jQuery?.fn?.DataTable) {
            return Boolean(ctx.transactionTable);
        }

        ctx.transactionTable = createDataTable({
            tableSelector: '#transaction-table',
            api: ctx.API.list,
            columns: buildTransactionDataColumns(),
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '엑셀 다운로드',
                    className: 'btn btn-outline-success btn-sm',
                    title: '거래내역',
                    filename: '거래내역',
                    exportOptions: {
                        columns: ':visible:not(.no-export):not(.no-colvis)',
                    },
                },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal,
                },
                {
                    text: '신규거래',
                    className: 'btn btn-warning btn-sm',
                    action: () => void ctx.openCreateModal(),
                },
            ],
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            searchTableId: 'transaction',
            deleteApi: ctx.API.remove,
        });

        bindRowReorder(ctx.transactionTable, {
            api: ctx.API.reorder,
            onSuccess() {
                ctx.notify('success', '거래 순번이 저장되었습니다.');
                ctx.transactionTable?.ajax.reload(null, false);
            },
            onError(json) {
                ctx.notify(
                    'error',
                    json?.message || '거래 순번 저장에 실패했습니다.'
                );
                ctx.transactionTable?.ajax.reload(null, false);
            },
        });

        bindTableHighlight('#transaction-table', ctx.transactionTable);
        ctx.transactionTable.on('init.dt draw.dt xhr.dt', updateCount);

        SearchForm({
            table: ctx.transactionTable,
            apiList: ctx.API.list,
            tableId: 'transaction',
            defaultSearchField: 'description',
            dateOptions: ctx.DATE_OPTIONS,
        });

        bindTableEvents();
        updateCount();
        return true;
    }

    function reloadTable() {
        ctx.transactionTable?.ajax?.reload(null, false);
    }

    function openTrashModal() {
        const trashModal = document.getElementById('transactionTrashModal');

        if (!trashModal) {
            ctx.notify('warning', '거래 휴지통 모달을 찾을 수 없습니다.');
            return;
        }

        trashModal.dataset.listUrl = ctx.API.trash;
        trashModal.dataset.restoreUrl = ctx.API.restore;
        trashModal.dataset.deleteUrl = ctx.API.purge;
        trashModal.dataset.deleteAllUrl = ctx.API.purgeAll;

        bootstrap.Modal
            .getOrCreateInstance(trashModal, { focus: false })
            .show();
    }

    function bindTableEvents() {
        const table = document.getElementById('transaction-table');
        table?.addEventListener('click', (event) => {
            const editBtn = event.target.closest('.btn-edit-transaction');
            if (editBtn) {
                void ctx.openDetail(editBtn.dataset.id || '');
                return;
            }

            const deleteRowBtn = event.target.closest('.btn-delete-transaction');
            if (deleteRowBtn && window.confirm('거래를 삭제하시겠습니까?')) {
                void ctx.deleteTransaction(deleteRowBtn.dataset.id || '');
            }
        });

        table?.querySelector('tbody')?.addEventListener('dblclick', (event) => {
            if (event.target.closest('a, button, input, select, textarea, .dropdown-menu, .reorder-handle')) {
                return;
            }

            const rowEl = event.target.closest('tr');
            if (!rowEl || !ctx.transactionTable) return;

            const rowData = ctx.transactionTable.row(rowEl).data();
            const id = rowData?.id || '';
            if (id) {
                void ctx.openDetail(id);
            }
        });
    }

    Object.assign(ctx, { buildTransactionDataColumns, updateCount, initTransactionTable, reloadTable, openTrashModal, bindTableEvents });
    return ctx;
}

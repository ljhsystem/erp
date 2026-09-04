import { actorColumn } from '/public/assets/js/common/actor.js';
import { formatAmount } from '/public/assets/js/common/format.js';
import { manageButtonRenderer } from '/public/assets/js/common/table/renderers/index.js';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
}[char]));
const notify = (type, message) => window.notify?.(type, message);
const renderTransactionStatus = (value) => {
    const requested = String(value || 'draft').toLowerCase();
    const status = ['draft', 'completed', 'closed', 'cancelled'].includes(requested) ? requested : 'draft';
    const labels = { draft: '임시저장', completed: '완료', closed: '마감', cancelled: '취소' };
    return `<span class="transaction-status transaction-status-${status}">${labels[status]}</span>`;
};
export function registerTable(ctx) {
    const {
        bindTableHighlight,
        createDataTable,
        SearchForm,
        bindRowReorder,
    } = ctx;
    const TRANSACTION_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.ledger.transaction.transaction-table.v2';

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
                return escapeHtml(value || '');
            },
        });

        const amountColumn = (data, title, visible = false) => ({
            data,
            title,
            className: 'text-end',
            visible,
            defaultContent: 0,
            render(value) {
                return escapeHtml(formatAmount(value || 0));
            },
        });

        const manageColumn = () => ({
            data: null,
            settingsKey: '__actions',
            __dtColumnKind: 'virtual',
            title: '\uAD00\uB9AC',
            width: '90px',
            widthResizable: true,
            className: 'text-center no-colvis',
            headerClassName: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render(_value, type, row) {
                if (type !== 'display') {
                    return '';
                }
                return manageButtonRenderer(row, { escapeHtml });
            },
        });

        return [
            {
                data: null,
                settingsKey: '__reorder',
                __dtColumnKind: 'virtual',
                title: '<i class="bi bi-arrows-move" aria-hidden="true"></i>',
                className: 'reorder-handle no-sort no-colvis text-center no-export',
                headerClassName: 'reorder-handle no-sort no-colvis text-center no-export',
                orderable: false,
                searchable: false,
                defaultContent: '',
                render: (_value, type) => type === 'display' ? '<i class="bi bi-list" aria-hidden="true"></i>' : '',
            },
            textColumn('sort_no', '순번', {
                visible: true,
                className: 'text-center',
            }),
            textColumn('transaction_date', '거래일자', { visible: true }),
            textColumn('business_unit', '사업구분'),
            {
                data: 'client_id',
                settingsKey: 'client_id',
                title: '거래처',
                render(data, type, row) {
                    return escapeHtml(data || row.client_name || '-');
                },
            },
            {
                data: 'project_id',
                settingsKey: 'project_id',
                title: '프로젝트',
                defaultContent: '',
                visible: false,
                render(data, type, row) {
                    return escapeHtml(data || row.project_name || '-');
                },
            },
            amountColumn('transaction_foreign_amount', '\uC678\uD654\uAE08\uC561', true),
            amountColumn('transaction_supply_amount', '\uACF5\uAE09\uAC00\uC561', true),
            amountColumn('transaction_settlement_amount', '\uC815\uC0B0\uAE08\uC561', true),
            amountColumn('transaction_final_amount', '\uCD5C\uC885\uAE08\uC561', true),
            textColumn('currency', '\uD1B5\uD654'),
            textColumn('exchange_rate', '\uD658\uC728', { className: 'text-end' }),
            {
                data: 'description',
                settingsKey: 'transaction_description',
                title: '\uC801\uC694',
                className: '',
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            textColumn('status', '\uAC70\uB798\uC0C1\uD0DC', {
                visible: true,
                className: 'text-center',
                render: renderTransactionStatus,
            }),
            textColumn('note', '\uBE44\uACE0'),
            textColumn('memo', '\uBA54\uBAA8'),
            textColumn('created_at', '\uC0DD\uC131\uC77C\uC2DC'),
            actorColumn('created_by', '\uC0DD\uC131\uC790', { visible: false }),
            textColumn('updated_at', '\uC218\uC815\uC77C\uC2DC'),
            actorColumn('updated_by', '\uC218\uC815\uC790', { visible: false }),
            textColumn('deleted_at', '\uC0AD\uC81C\uC77C\uC2DC'),
            actorColumn('deleted_by', '\uC0AD\uC81C\uC790', { visible: false }),
            {
                data: 'id',
                title: 'ID',
                className: 'no-colvis',
                visible: false,
                searchable: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            manageColumn(),
        ];
    }

    function updateCount() {
        if (!ctx.transactionTable || !ctx.countEl) {
            return;
        }

        const info = ctx.transactionTable.page.info();
        ctx.countEl.textContent = `총 ${info.recordsDisplay || 0}건`;
    }
    async function initTransactionTable() {
        if (ctx.transactionTable || !window.jQuery?.fn?.DataTable) {
            return Boolean(ctx.transactionTable);
        }

        ctx.transactionTable = await createDataTable({
            tableSelector: '#transaction-table',
            api: ctx.API.list,
            serverSide: true,
            columns: buildTransactionDataColumns(),
            ajaxData(request) {
                const order = Array.isArray(request?.order) ? request.order[0] : null;
                const columnIndex = Number(order?.column);
                const column = Number.isInteger(columnIndex) && columnIndex >= 0
                    ? request?.columns?.[columnIndex]
                    : null;

                return {
                    ...request,
                    sort_field: String(column?.data || '').trim(),
                    sort_direction: String(order?.dir || '').trim().toLowerCase() === 'asc'
                        ? 'asc'
                        : 'desc',
                };
            },
            tableSettings: {
                enabled: true,
                pageKey: 'ledger.transaction',
                tableKey: 'transaction-table',
                storageKey: TRANSACTION_TABLE_SETTINGS_STORAGE_KEY,
                metaDomain: 'transaction-header',
                description: ctx.TRANSACTION_PAGE_DESCRIPTION,
                tableLabel: '거래헤더',
                title: '거래헤더 테이블 설정',
                resetOnColumnSchemaChange: true,
            },
            buttons: [
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal,
                },
                {
                    text: '신규등록',
                    className: 'btn btn-warning btn-sm',
                    action: () => void ctx.openCreateModal(),
                },
            ],
            defaultOrder: [['sort_no', 'asc']],
            pageLength: 100,
            searchTableId: 'transaction',
            deleteApi: ctx.API.remove,
        });

        bindRowReorder(ctx.transactionTable, {
            api: ctx.API.reorder,
            onSuccess() {
                notify('success', '순서가 변경되었습니다.');
                const columns = ctx.transactionTable?.settings?.()?.[0]?.aoColumns || [];
                const sortNoIndex = columns.findIndex((column) => column?.data === 'sort_no');
                if (sortNoIndex >= 0) {
                    ctx.transactionTable.order([[sortNoIndex, 'asc']]).draw(false);
                    return;
                }
                ctx.transactionTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '정렬 저장 중 오류가 발생했습니다.');
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

    async function openTrashModal() {
        await ctx.ensureTrashManager?.();
        const trashModal = document.getElementById('transactionTrashModal');

        if (!trashModal) {
            notify('warning', '\uAC70\uB798 \uD734\uC9C0\uD1B5 \uBAA8\uB2EC\uC744 \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.');
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
            const editBtn = event.target.closest('.seed-row-edit-btn');
            if (editBtn) {
                void ctx.openDetail(editBtn.dataset.id || '');
                return;
            }

            const deleteRowBtn = event.target.closest('.btn-delete-transaction');
            if (
                deleteRowBtn &&
                window.confirm('정말 이 거래를 삭제하시겠습니까?')
            ) {
                void ctx.deleteTransaction(deleteRowBtn.dataset.id || '');
            }
        });

        table?.querySelector('tbody')?.addEventListener('dblclick', (event) => {
            if (
                event.target.closest(
                    'a, button, input, select, textarea, .dropdown-menu, .reorder-handle'
                )
            ) {
                return;
            }

            const rowEl = event.target.closest('tr');
            if (!rowEl || !ctx.transactionTable) {
                return;
            }

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

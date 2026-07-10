import { fetchDataTableMetaColumnsSync } from '/public/assets/js/common/datatable/dataTableSettings.js';
import { manageButtonRenderer } from '/public/assets/js/common/table/renderers/index.js';

const VOUCHER_META_DOMAIN = 'voucher-header';
const VOUCHER_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.ledger.voucher.voucher-table.v1';
const VOUCHER_TABLE_SETTINGS_PAGE_KEY = 'ledger.voucher';

export function registerTable(ctx) {
    const state = ctx.state;
    const {
        SearchForm,
        actorDisplay,
        bindRowReorder,
        bindTableHighlight,
        createDataTable,
        API,
        JOURNAL_DATE_OPTIONS,
        journalTableEl,
        tableBody,
        formatAmountValue,
        renderJournalStatusBadge,
        renderDragHandle,
        renderEllipsisText,
        escapeHtml,
        notify,
    } = ctx;

    function voucherHeaderMetaColumns() {
        return fetchDataTableMetaColumnsSync({
            metaDomain: VOUCHER_META_DOMAIN,
            storageKey: VOUCHER_TABLE_SETTINGS_STORAGE_KEY,
            userSettingPageKey: VOUCHER_TABLE_SETTINGS_PAGE_KEY,
        }).filter((column) => String(column?.column_type || 'physical') === 'physical');
    }

    function renderBooleanBadge(value) {
        const active = String(value || '').trim() === '1'
            || value === 1
            || value === true
            || String(value || '').trim().toLowerCase() === 'y';

        return active
            ? '<span class="journal-link-badge journal-link-linked">예</span>'
            : '<span class="journal-link-badge journal-link-unlinked">아니오</span>';
    }

    function renderVoucherField(field, data, type, row) {
        const value = data ?? row?.[field] ?? '';

        switch (field) {
        case 'sort_no':
            if (type === 'sort' || type === 'type') {
                return Number(row?.sort_no || 0);
            }
            return escapeHtml(row?.sort_no ?? '');

        case 'voucher_no':
            if (type === 'sort' || type === 'type') {
                return String(row?.voucher_no || '');
            }
            return renderEllipsisText(value || '');

        case 'voucher_date':
            return escapeHtml(value || '');

        case 'status':
            if (type === 'sort' || type === 'type') {
                return String(value || 'DRAFT').trim().toUpperCase();
            }
            return renderJournalStatusBadge(row);

        case 'summary':
            return renderEllipsisText(value || '');

        case 'debit_total':
        case 'credit_total':
            if (type === 'sort' || type === 'type') {
                return Number(value || 0);
            }
            return escapeHtml(formatAmountValue(value || 0) || '0');

        case 'line_count':
            if (type === 'sort' || type === 'type') {
                return Number(value || 0);
            }
            return escapeHtml(String(value ?? '0'));

        case 'summary_account_id':
            return renderEllipsisText(row?.summary_account_name || value || '');

        case 'summary_client_id':
            return renderEllipsisText(row?.summary_client_name || value || '');

        case 'summary_project_id':
            return renderEllipsisText(row?.summary_project_name || value || '');

        case 'summary_bank_account_id':
            return renderEllipsisText(row?.summary_bank_account_name || value || '');

        case 'summary_card_id':
            return renderEllipsisText(row?.summary_card_name || value || '');

        case 'summary_employee_id':
            return renderEllipsisText(row?.summary_employee_name || value || '');

        case 'summary_line_summary':
            return renderEllipsisText(value || '');

        case 'reject_reason':
            return renderEllipsisText(value || '');

        case 'is_reversal':
            if (type === 'sort' || type === 'type') {
                return value ? 1 : 0;
            }
            return renderBooleanBadge(value);

        case 'reversal_of':
            if (type === 'sort' || type === 'type') {
                return String(value || '');
            }
            return renderEllipsisText(row?.original_voucher_no || value || '');

        case 'created_by':
        case 'updated_by':
        case 'deleted_by':
            if (type === 'sort' || type === 'type') {
                return String(value || '');
            }
            return escapeHtml(actorDisplay(row, field));

        case 'id':
            return escapeHtml(value || '');

        default:
            if (type === 'sort' || type === 'type') {
                return value;
            }
            return escapeHtml(value || '');
        }
    }

    function columnClassName(field = '') {
        switch (field) {
        case 'sort_no':
        case 'voucher_date':
        case 'line_count':
        case 'is_reversal':
            return 'text-center';
        case 'status':
            return 'text-center dt-status-column';
        case 'debit_total':
        case 'credit_total':
            return 'text-end';
        default:
            return '';
        }
    }

    function buildPhysicalColumns() {
        return voucherHeaderMetaColumns().map((meta) => {
            const field = String(meta?.key || '').trim();
            if (field === '') {
                return null;
            }

            return {
                data: field,
                name: field,
                settingsKey: field,
                sourceField: field,
                title: String(meta?.label || field).trim() || field,
                className: columnClassName(field),
                visible: meta?.settings_visible !== false,
                defaultContent: '',
                render(data, type, row) {
                    return renderVoucherField(field, data, type, row);
                },
            };
        }).filter(Boolean);
    }

    function buildReorderColumn() {
        return {
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'reorder-handle no-sort no-colvis text-center no-export',
            headerClassName: 'no-colvis text-center no-export',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render() {
                return renderDragHandle();
            },
        };
    }

    function renderManageActions(row = {}) {
        return manageButtonRenderer(row, { escapeHtml });
    }

    function buildManageColumn() {
        return {
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
            render(_data, type, row) {
                if (type !== 'display') {
                    return '';
                }
                return renderManageActions(row);
            },
        };
    }

    function buildJournalColumns() {
        return [
            buildReorderColumn(),
            ...buildPhysicalColumns(),
            buildManageColumn(),
        ];
    }
    function updateJournalCount() {
        if (!state.journalTable?.page) {
            return;
        }

        const countEl = document.getElementById('journalCount');
        if (!countEl) {
            return;
        }

        const info = state.journalTable.page.info();
        countEl.textContent = "총 " + (info?.recordsDisplay ?? 0) + "건";
    }

    function bindJournalTableEvents() {
        if (!window.jQuery || !state.journalTable) {
            return;
        }

        const $ = window.jQuery;

        $('#journal-table tbody')
            .off('click.journalManageEdit')
            .on('click.journalManageEdit', '.seed-row-edit-btn', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const row = ctx.findJournalRowData?.(this) || null;
                if (!row) {
                    notify('error', '전표 정보를 확인할 수 없습니다.');
                    return;
                }

                void ctx.handleJournalAction?.('edit', row, this);
            });

        $('#journal-table tbody')
            .off('dblclick.journalEdit')
            .on('dblclick.journalEdit', 'tr', function (event) {
                if (event.target.closest('button, .dropdown-menu, .journal-manage-menu, .reorder-handle')) {
                    return;
                }

                const row = state.journalTable.row(this).data();
                const id = row?.id || '';
                if (!id) {
                    return;
                }

                void ctx.loadDetail?.(id);
            });
    }
    function initJournalTable() {
        if (state.journalTable || !journalTableEl || !tableBody || !window.jQuery?.fn?.DataTable) {
            return Boolean(state.journalTable);
        }

        state.journalTable = createDataTable({
            tableSelector: '#journal-table',
            api: API.list,
            columns: buildJournalColumns(),
            tableSettings: {
                enabled: true,
                pageKey: VOUCHER_TABLE_SETTINGS_PAGE_KEY,
                tableKey: 'voucher-table',
                storageKey: VOUCHER_TABLE_SETTINGS_STORAGE_KEY,
                userSettingPageKey: VOUCHER_TABLE_SETTINGS_PAGE_KEY,
                metaDomain: VOUCHER_META_DOMAIN,
                description: ctx.VOUCHER_PAGE_DESCRIPTION,
                tableLabel: '\uC804\uD45C\uC785\uB825 \uBAA9\uB85D',
                title: '\uC804\uD45C\uC785\uB825 \uBAA9\uB85D \uD14C\uC774\uBE14 \uC124\uC815',
            },
            buttons: [
                {
                    text: '\uD734\uC9C0\uD1B5',
                    className: 'btn btn-danger btn-sm voucher-trash-btn',
                    action: openTrashModal,
                },
                {
                    text: '\uC5D1\uC140\uAD00\uB9AC',
                    className: 'btn btn-success btn-sm voucher-excel-manager-btn',
                    action: () => ctx.openHeaderExcelManager?.(),
                },
                {
                    text: '\uC2E0\uADDC\uC804\uD45C',
                    className: 'btn btn-warning btn-sm voucher-create-btn',
                    action() {
                        void ctx.openCreateModal?.();
                    },
                },
            ],
            defaultOrder: [[2, 'asc']],
            pageLength: 10,
            searchTableId: 'journal',
            cellSearchFill: true,
            deleteApi: API.remove,
        });

        bindRowReorder(state.journalTable, {
            api: API.reorder,
            onSuccess() {
                notify('success', '\uC804\uD45C \uC21C\uC11C\uAC00 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
                state.journalTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '\uC804\uD45C \uC21C\uC11C \uC800\uC7A5 \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
                state.journalTable?.ajax.reload(null, false);
            },
        });
        bindTableHighlight('#journal-table', state.journalTable);

        state.journalTable.on('init.dt draw.dt', updateJournalCount);

        SearchForm({
            table: state.journalTable,
            apiList: API.list,
            tableId: 'journal',
            defaultSearchField: 'summary',
            dateOptions: JOURNAL_DATE_OPTIONS,
        });
        bindJournalTableEvents();
        updateJournalCount();

        return Boolean(state.journalTable);
    }

    function reloadJournalTable() {
        if (state.journalTable?.ajax) {
            state.journalTable.ajax.reload(null, false);
        }
    }

    function openTrashModal() {
        const modalEl = document.getElementById('journalTrashModal');
        if (!modalEl) {
            notify('warning', '\uC804\uD45C \uD734\uC9C0\uD1B5 \uBAA8\uB2EC\uC744 \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.');
            return;
        }

        modalEl.dataset.listUrl = API.trash;
        modalEl.dataset.restoreUrl = API.restore;
        modalEl.dataset.deleteUrl = API.purge;
        modalEl.dataset.deleteAllUrl = API.purgeAll;

        bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).show();
    }

    Object.assign(ctx, {
        buildJournalColumns,
        renderManageActions,
        updateJournalCount,
        bindJournalTableEvents,
        initJournalTable,
        reloadJournalTable,
        openTrashModal,
    });

    return ctx;
}

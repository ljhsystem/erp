export function createTransactionContext(deps = {}) {
    const ctx = { ...deps };

    const form = document.getElementById('transactionForm');
    const modalEl = document.getElementById('transactionModal');
    const gridEl = document.getElementById('transactionLineGrid');
    const settlementGridEl = document.getElementById('transactionSettlementGrid');
    const deleteBtn = document.getElementById('btnDeleteTransaction');
    const countEl = document.getElementById('transactionCount');
    const importToggle = document.getElementById('is_import');
    const fileToggle = document.getElementById('use_file_reference');
    const filePanel = document.getElementById('transactionFilePanel');
    const fileInput = document.getElementById('transaction_files');
    const fileListEl = document.getElementById('transaction_file_list');
    const fileDropzoneEl = document.getElementById('transaction_file_dropzone');
    const voucherStatusEl = document.getElementById('transactionVoucherStatus');
    const transactionStatusBadgeEl = document.getElementById('transactionStatusBadge');
    const voucherSummaryEl = document.getElementById('transaction_voucher_summary');
    const createVoucherBtn = document.getElementById('btnCreateTransactionVoucher');
    const selectVoucherBtn = document.getElementById('btnSelectTransactionVoucher');
    const linkVoucherBtn = document.getElementById('btnLinkTransactionVoucher');
    const unlinkVoucherBtn = document.getElementById('btnUnlinkTransactionVoucher');
    const transactionDateEl = document.getElementById('transaction_date');
    const pickerLayerEl = document.getElementById('transaction-today-picker');
    const clientSelectEl = document.getElementById('client_id');
    const projectSelectEl = document.getElementById('project_id');
    const bankAccountSelectEl = document.getElementById('bank_account_id');
    const cardSelectEl = document.getElementById('card_id');
    const teamSelectEl = document.getElementById('team_id');
    const employeeSelectEl = document.getElementById('employee_id');
    const currencySelectEl = document.getElementById('currency');
    const exchangeRateEl = document.getElementById('exchange_rate');
    const headerForeignAmountEl = document.getElementById('transaction_foreign_amount');
    const headerSupplyAmountEl = document.getElementById('transaction_supply_amount');
    const headerSettlementAmountEl = document.getElementById('transaction_settlement_amount');
    const headerFinalAmountEl = document.getElementById('transaction_final_amount');
    const settlementTitleEl = document.getElementById('transactionSettlementTitle');
    const settlementSubtitleEl = document.getElementById('transactionSettlementSubtitle');
    const modalBodyEl = modalEl?.querySelector('.transaction-modal-body');

    const AG_GRID_STYLE_URL = 'https://cdn.jsdelivr.net/npm/ag-grid-community@32.3.3/styles/ag-grid.css';
    const AG_GRID_THEME_URL = 'https://cdn.jsdelivr.net/npm/ag-grid-community@32.3.3/styles/ag-theme-quartz.css';
    const AG_GRID_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/ag-grid-community@32.3.3/dist/ag-grid-community.min.js';

    const LINE_GRID_SETTINGS_KEY = 'aggrid.settings.ledger.transaction.modal.line.v1';
    const SETTLEMENT_GRID_SETTINGS_KEY = 'aggrid.settings.ledger.transaction.modal.settlement.v1';
    const AG_GRID_MIN_SAVED_WIDTH = 40;
    const AG_GRID_WIDTH_SAVE_DEBOUNCE_MS = 300;
    const GRID_KIND_LINE = 'line';
    const GRID_KIND_SETTLEMENT = 'settlement';

    if (!form || !modalEl || !gridEl || !settlementGridEl) {
        ctx.isReady = false;
        return ctx;
    }

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    const modal = window.bootstrap ? new bootstrap.Modal(modalEl, { focus: false }) : null;
    const API = {
        list: '/api/ledger/transaction/list',
        detail: '/api/ledger/transaction/detail',
        save: '/api/ledger/transaction/save',
        remove: '/api/ledger/transaction/delete',
        trash: '/api/ledger/transaction/trash',
        restore: '/api/ledger/transaction/restore',
        purge: '/api/ledger/transaction/purge',
        purgeAll: '/api/ledger/transaction/purge-all',
        reorder: '/api/ledger/transaction/reorder',
        linkVoucher: '/api/ledger/transaction/link-voucher',
        unlinkVoucher: '/api/ledger/transaction/unlink-voucher',
        clientSearch: '/api/settings/base-info/client/search-picker',
        projectSearch: '/api/settings/base-info/project/search-picker',
        employeeSearch: '/api/settings/organization/employee/search-picker',
        bankAccountSearch: '/api/settings/base-info/bank-account/search-picker',
        cardSearch: '/api/settings/base-info/card/search-picker',
        workTeamList: '/api/settings/base-info/work-team/list',
        filePolicyList: '/api/system/file-policies',
    };

    const DATE_OPTIONS = [
        { value: 'transaction_date', label: '거래일' },
        { value: 'updated_at', label: '수정일시' },
    ];

    if (pickerLayerEl && pickerLayerEl.parentElement !== document.body) {
        document.body.appendChild(pickerLayerEl);
    }

    Object.assign(ctx, {
        form,
        modalEl,
        gridEl,
        settlementGridEl,
        deleteBtn,
        countEl,
        importToggle,
        fileToggle,
        filePanel,
        fileInput,
        fileListEl,
        fileDropzoneEl,
        voucherStatusEl,
        transactionStatusBadgeEl,
        voucherSummaryEl,
        createVoucherBtn,
        selectVoucherBtn,
        linkVoucherBtn,
        unlinkVoucherBtn,
        transactionDateEl,
        pickerLayerEl,
        clientSelectEl,
        projectSelectEl,
        bankAccountSelectEl,
        cardSelectEl,
        teamSelectEl,
        employeeSelectEl,
        currencySelectEl,
        exchangeRateEl,
        headerForeignAmountEl,
        headerSupplyAmountEl,
        headerSettlementAmountEl,
        headerFinalAmountEl,
        settlementTitleEl,
        settlementSubtitleEl,
        modalBodyEl,
        AG_GRID_STYLE_URL,
        AG_GRID_THEME_URL,
        AG_GRID_SCRIPT_URL,
        LINE_GRID_SETTINGS_KEY,
        SETTLEMENT_GRID_SETTINGS_KEY,
        AG_GRID_MIN_SAVED_WIDTH,
        AG_GRID_WIDTH_SAVE_DEBOUNCE_MS,
        GRID_KIND_LINE,
        GRID_KIND_SETTLEMENT,
        modal,
        transactionTable: null,
        lineGrid: null,
        settlementGrid: null,
        currentTransaction: null,
        currentFiles: [],
        pendingFiles: [],
        fileRowOrder: [],
        transactionDatePicker: null,
        lineDateEscHandler: null,
        lineGridEditing: false,
        manualLineDateEdit: false,
        activeLineDateInput: null,
        activeLineDateInputHandler: null,
        unitCodeSelectEl: null,
        unitOptions: [],
        pendingUnitCell: null,
        lastInvalidUnitNotice: '',
        taxTypeCodeSelectEl: null,
        taxTypeOptions: [],
        settlementTypeCodeSelectEl: null,
        settlementTypeOptions: [],
        amountSignCodeSelectEl: null,
        amountSignOptions: [],
        settlementLineKeySeed: 0,
        lineDeletedRows: [],
        settlementDeletedRows: [],
        lineRows: [],
        settlementRows: [],
        modalBaselineSnapshot: '',
        allowModalClose: false,
        floatingLineHeaderEl: null,
        lineHeaderFrame: null,
        selectedVoucherId: '',
        selectedVoucherLabel: '',
        fileDropzoneEmptyText: '파일을 드래그해서 첨부하세요',
        modalControlsInitialized: false,
        agGridLoadPromise: null,
        pendingLineGridInitAfterShow: false,
        modalShownResolvers: [],
        agGridWidthSaveTimers: new Map(),
        gridCellStates: {
            [GRID_KIND_LINE]: { activeCell: null, editingCell: null, selectedRow: -1, clipboardSelection: { mode: 'cell' } },
            [GRID_KIND_SETTLEMENT]: { activeCell: null, editingCell: null, selectedRow: -1, clipboardSelection: { mode: 'cell' } },
        },
        selection: {
            [GRID_KIND_LINE]: { activeCell: null, selectedRow: -1 },
            [GRID_KIND_SETTLEMENT]: { activeCell: null, selectedRow: -1 },
        },
        editingState: {
            [GRID_KIND_LINE]: { editingCell: null },
            [GRID_KIND_SETTLEMENT]: { editingCell: null },
        },
        API,
        DATE_OPTIONS,
        isReady: true,
    });

    return ctx;
}

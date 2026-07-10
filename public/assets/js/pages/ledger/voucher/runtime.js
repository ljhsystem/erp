import { createJournalBasicInfoBridge } from '/public/assets/js/pages/ledger/journal.basic-info.js';
import { createVoucherExcelManager } from './excel.js';
import { createVoucherDetailMetaManager } from './system-info.js';
import { createVoucherLineGridBridge } from './voucher-grid-bridge.js';

export function initializeVoucherRuntime(ctx) {
    window.AdminPicker = ctx.AdminPicker;

    ctx.basicInfoBridge = createJournalBasicInfoBridge({ notify: ctx.notify });

    ctx.headerExcelManager = createVoucherExcelManager({
        modalSelector: '#voucherExcelModal',
        formSelector: '#voucher-excel-upload-form',
        templateUrl: '/api/ledger/voucher/template',
        downloadUrl: '/api/ledger/voucher/excel',
        uploadUrl: '/api/ledger/voucher/excel-upload',
        description: ctx.VOUCHER_PAGE_DESCRIPTION,
    });
    ctx.openHeaderExcelManager = () => ctx.headerExcelManager?.open?.();

    ctx.setVoucherDetailMeta = createVoucherDetailMetaManager(ctx);
    void ctx.setVoucherDetailMeta({});

    ctx.state.lineGridBridge = createVoucherLineGridBridge(ctx);
    ctx.state.lineGridBridge.initialize();
    ctx.lineGridBridge = ctx.state.lineGridBridge;

    return ctx;
}

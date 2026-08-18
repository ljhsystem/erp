import { createJournalBasicInfoBridge } from '/public/assets/js/pages/ledger/journal.basic-info.js';
import { createVoucherDetailMetaManager } from './system-info.js';
import { createVoucherLineGridBridge } from './voucher-grid-bridge.js';

export async function initializeVoucherRuntime(ctx) {
    window.AdminPicker = ctx.AdminPicker;

    ctx.basicInfoBridge = createJournalBasicInfoBridge({ notify: ctx.notify });

    ctx.setVoucherDetailMeta = createVoucherDetailMetaManager(ctx);

    ctx.state.lineGridBridge = createVoucherLineGridBridge(ctx);
    ctx.state.lineGridBridge.initialize();
    ctx.lineGridBridge = ctx.state.lineGridBridge;

    return ctx;
}

import * as NumberFormat from '/public/assets/js/common/format.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { escapeHtml } from '/public/assets/js/common/html.js';
import { notify } from '/public/assets/js/common/notification.js';
import { getCodeName, initCodeSelectControls, onCodeOptionsLoaded } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { openClientQuickCreate } from '/public/assets/js/pages/dashboard/settings/base/client.js';
import { resolveDisplayText } from '/public/assets/js/pages/ledger/shared/utils.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import { createVoucherContext } from './context.js';
import { initializeVoucherRuntime } from './runtime.js';
import { registerHelpers } from './helpers.js?v=20260708-3';
import { registerForm } from './form.js';
import { registerTable } from './table.js';
import { registerActions } from './actions.js';
import '/public/assets/js/components/trash-manager.js';

const onlyNumber = NumberFormat.onlyNumber || ((value) => String(value ?? '').replace(/\D/g, ''));
const { formatNumber } = NumberFormat;

function reorderVoucherToolbar(ctx) {
    const wrapper = ctx.state?.journalTable?.table?.().container?.();
    const toolbar = wrapper?.querySelector?.('.dt-top');
    const buttonsWrap = toolbar?.querySelector?.('.dt-buttons');
    const lengthNode = toolbar?.querySelector?.('.dataTables_length');
    const settingsButton = toolbar?.querySelector?.('.dt-table-settings-trigger, .dt-table-settings-btn');

    if (!toolbar || !buttonsWrap) {
        return;
    }

    const orderedButtons = [
        buttonsWrap.querySelector('.dt-selected-move-up-btn'),
        buttonsWrap.querySelector('.dt-selected-move-down-btn'),
        buttonsWrap.querySelector('.buttons-copy'),
        buttonsWrap.querySelector('.dt-soft-delete-btn'),
        buttonsWrap.querySelector('.voucher-trash-btn'),
        buttonsWrap.querySelector('.voucher-excel-manager-btn'),
        buttonsWrap.querySelector('.voucher-create-btn'),
    ].filter(Boolean);

    orderedButtons.forEach((button) => {
        buttonsWrap.appendChild(button);
    });

    if (lengthNode && settingsButton && settingsButton.parentElement === toolbar) {
        toolbar.appendChild(settingsButton);
    }
}

const ctx = createVoucherContext({
    AdminPicker,
    SearchForm,
    actorDisplay,
    bindRowReorder,
    bindTableHighlight,
    createDataTable,
    escapeHtml,
    formatNumber,
    getCodeName,
    initCodeSelectControls,
    notify,
    openClientQuickCreate,
    onCodeOptionsLoaded,
    onlyNumber,
    resolveDisplayText,
});

if (ctx.isReady) {
    registerHelpers(ctx);
    registerForm(ctx);
    registerTable(ctx);
    initializeVoucherRuntime(ctx);
    registerActions(ctx);
    ctx.boot();
    reorderVoucherToolbar(ctx);
    ctx.state?.journalTable?.on?.('init.dt draw.dt', () => {
        reorderVoucherToolbar(ctx);
    });
}

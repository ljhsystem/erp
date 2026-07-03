import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { bindNumberInput, formatDateInputValue, formatNumber, parseNumber } from '/public/assets/js/common/format.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { createAgGridInputAdapter } from '/public/assets/js/common/grid/ag-grid-input.js';
import { selectEditor, dateStringEditor } from '/public/assets/js/common/grid/ag-grid-editors.js';
import { gridNumberFormatter, gridNumberParser } from '/public/assets/js/common/grid/ag-grid-formatters.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createCodeSelect,
    getCodeName,
    initCodeSelectControls,
    onCodeOptionsLoaded,
    openCodeQuickModal,
} from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { openClientQuickCreate } from '/public/assets/js/pages/dashboard/settings/base/client.js';
import { openVoucherModal } from '/public/assets/js/pages/ledger/voucherSelectModal.js';
import { openVoucherRecommendationModal } from '/public/assets/js/pages/ledger/voucherRecommendationModal.js';
import '/public/assets/js/components/trash-manager.js';

import { createTransactionContext } from './state.js';
import { registerEditors } from './editors.js';
import { registerTable } from './table.js';
import { registerCalculation } from './calculation.js';
import { registerStorage } from './storage.js';
import { registerKeyboard } from './keyboard.js';
import { registerGrid } from './grid.js';
import { registerModal } from './modal.js';
import { registerSelects } from './selects.js';
import { registerFiles } from './files.js';
import { registerValidation } from './validation.js';
import { registerEvents } from './events.js';

function init() {
    const ctx = createTransactionContext({
        bindTableHighlight,
        createDataTable,
        SearchForm,
        bindNumberInput,
        formatDateInputValue,
        formatNumber,
        parseNumber,
        bindRowReorder,
        createAgGridInputAdapter,
        selectEditor,
        dateStringEditor,
        gridNumberFormatter,
        gridNumberParser,
        AdminPicker,
        createCodeSelect,
        getCodeName,
        initCodeSelectControls,
        onCodeOptionsLoaded,
        openCodeQuickModal,
        openClientQuickCreate,
        openVoucherModal,
        openVoucherRecommendationModal,
    });

    if (!ctx.isReady) {
        return;
    }

    registerEditors(ctx);
    registerTable(ctx);
    registerCalculation(ctx);
    registerStorage(ctx);
    registerKeyboard(ctx);
    registerGrid(ctx);
    registerModal(ctx);
    registerSelects(ctx);
    registerFiles(ctx);
    registerValidation(ctx);
    registerEvents(ctx);

    if (typeof ctx.boot === 'function') {
        ctx.boot();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}

import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/common/table/data-table.js';
import { createExcelManagerSettingsCore } from '/public/assets/js/components/excel-manager/index.js';
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
import '/public/assets/js/components/excel-manager.js';
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

function reorderTransactionToolbar(ctx) {
    const wrapper = ctx.transactionTable?.table?.().container?.();
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
        buttonsWrap.querySelector('.btn.btn-danger.btn-sm:not(.dt-soft-delete-btn)'),
        buttonsWrap.querySelector('.btn.btn-success.btn-sm'),
        buttonsWrap.querySelector('.btn.btn-warning.btn-sm'),
    ].filter(Boolean);

    orderedButtons.forEach((button) => {
        buttonsWrap.appendChild(button);
    });

    if (lengthNode && settingsButton && settingsButton.parentElement === toolbar) {
        toolbar.appendChild(settingsButton);
    }
}

function parsePreparedColumns(form, type) {
    const datasetKey = type === 'template' ? 'excelTemplateColumns' : 'excelDownloadColumns';

    try {
        const parsed = JSON.parse(form?.dataset?.[datasetKey] || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function prepareExcelAction(form, type) {
    form.dispatchEvent(new CustomEvent('excel:before-prepare-action', {
        detail: { type },
    }));

    const columns = parsePreparedColumns(form, type);
    const policyState = form?.__excelPreparedPolicy?.[type] && typeof form.__excelPreparedPolicy[type] === 'object'
        ? form.__excelPreparedPolicy[type]
        : { displayName: {}, requirementPolicy: {} };

    return {
        columns,
        columnDisplayName: { ...(policyState.displayName || {}) },
        columnRequirementPolicy: type === 'template'
            ? { ...(policyState.requirementPolicy || {}) }
            : {},
    };
}

function parseDownloadFilename(disposition = '', fallback = 'download.xlsx') {
    const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (utf8Match?.[1]) {
        return decodeURIComponent(utf8Match[1]);
    }

    const basicMatch = disposition.match(/filename="?([^";]+)"?/i);
    if (basicMatch?.[1]) {
        return basicMatch[1];
    }

    return fallback;
}

function triggerBlobDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 0);
}

function initExcelManager(ctx) {
    const excelModalEl = ctx.excelModalEl;
    if (!excelModalEl || !window.bootstrap) {
        return;
    }

    ctx.excelModal = bootstrap.Modal.getOrCreateInstance(excelModalEl, { focus: false });
    excelModalEl.addEventListener('shown.bs.modal', () => {
        const backdrops = Array.from(document.querySelectorAll('.modal-backdrop.show'));
        const backdrop = backdrops[backdrops.length - 1];
        backdrop?.classList.add('transaction-excel-backdrop');
    });
    excelModalEl.addEventListener('hidden.bs.modal', () => {
        document.querySelectorAll('.modal-backdrop.transaction-excel-backdrop').forEach((backdrop) => {
            backdrop.classList.remove('transaction-excel-backdrop');
        });
    });

    const form = document.getElementById('transaction-excel-upload-form');
    if (!form) {
        return;
    }

    const modalTitleEl = excelModalEl.querySelector('.modal-title');
    const modalSubtitleEl = excelModalEl.querySelector('.excel-modal-subtitle');
    const EXCEL_CONTEXTS = {
        header: {
            domain: 'transaction',
            userSettingPageKey: 'ledger.transaction',
            metaDomain: 'transaction-header',
            templateUrl: ctx.API.excelTemplate,
            downloadUrl: ctx.API.excelDownload,
            uploadUrl: ctx.API.excelUpload,
            title: '거래헤더 엑셀관리',
            subtitle: '거래헤더 업로드 양식설정과 다운로드 설정을 관리합니다.',
            filename: 'transactions.xlsx',
        },
        item: {
            domain: 'transaction-item',
            userSettingPageKey: 'ledger.transaction-item',
            metaDomain: 'transaction-item',
            templateUrl: ctx.API.itemExcelTemplate,
            downloadUrl: ctx.API.itemExcelDownload,
            uploadUrl: ctx.API.itemExcelUpload,
            title: '거래품목 엑셀관리',
            subtitle: '거래품목 업로드 양식설정과 다운로드 설정을 관리합니다.',
            filename: 'transaction_items.xlsx',
        },
        settlement: {
            domain: 'transaction-settlement',
            userSettingPageKey: 'ledger.transaction-settlement',
            metaDomain: 'transaction-settlement',
            templateUrl: ctx.API.settlementExcelTemplate,
            downloadUrl: ctx.API.settlementExcelDownload,
            uploadUrl: ctx.API.settlementExcelUpload,
            title: '거래정산 엑셀관리',
            subtitle: '거래정산 업로드 양식설정과 다운로드 설정을 관리합니다.',
            filename: 'transaction_settlements.xlsx',
        },
    };

    function resetPreparedColumns() {
        form.dataset.excelTemplateColumns = JSON.stringify([]);
        form.dataset.excelDownloadColumns = JSON.stringify([]);
        form.__excelPreparedColumns = {
            template: [],
            download: [],
        };
    }

    function destroyExcelManagerCore() {
        ctx.excelManagerSettingsCore?.destroy?.();
        ctx.excelManagerSettingsCore = null;
        resetPreparedColumns();
    }

    function resolveExcelContext(contextKey = 'header') {
        return EXCEL_CONTEXTS[contextKey] || EXCEL_CONTEXTS.header;
    }

    function ensureExcelManagerCore(contextKey = 'header') {
        const config = resolveExcelContext(contextKey);
        if (!ctx.excelManagerSettingsCore || ctx.excelManagerSettingsCore.domain !== config.domain) {
            destroyExcelManagerCore();
            ctx.excelManagerSettingsCore = createExcelManagerSettingsCore({
                domain: config.domain,
                userSettingPageKey: config.userSettingPageKey,
                formSelector: '#transaction-excel-upload-form',
                metaDomain: config.metaDomain,
                description: ctx.TRANSACTION_PAGE_DESCRIPTION,
            });
            return ctx.excelManagerSettingsCore;
        }

        ctx.excelManagerSettingsCore.reload?.();
        return ctx.excelManagerSettingsCore;
    }

    function applyExcelManagerContext(contextKey = 'header') {
        const config = resolveExcelContext(contextKey);
        ctx.excelManagerContextKey = contextKey;

        form.dataset.templateUrl = config.templateUrl;
        form.dataset.downloadUrl = config.downloadUrl;
        form.dataset.uploadUrl = config.uploadUrl;
        form.dataset.excelContextKey = contextKey;

        if (modalTitleEl) {
            modalTitleEl.textContent = config.title;
        }
        if (modalSubtitleEl) {
            modalSubtitleEl.textContent = config.subtitle;
        }

        ensureExcelManagerCore(contextKey);
        return config;
    }

    function collectExcelRows(contextKey = 'header') {
        if (contextKey === 'item') {
            return ctx.collectLines?.() || [];
        }
        if (contextKey === 'settlement') {
            return ctx.collectSettlements?.() || [];
        }
        return [];
    }

    async function downloadGridExcel(contextKey = 'item') {
        const config = applyExcelManagerContext(contextKey);
        const prepared = prepareExcelAction(form, 'download');
        const payload = new FormData();
        payload.set('rows', JSON.stringify(collectExcelRows(contextKey)));
        if (prepared.columns.length > 0) {
            payload.set('columns', prepared.columns.join(','));
        }
        if (Object.keys(prepared.columnDisplayName || {}).length > 0) {
            payload.set('column_display_name', JSON.stringify(prepared.columnDisplayName));
        }

        const response = await fetch(config.downloadUrl, {
            method: 'POST',
            body: payload,
            headers: {
                Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream',
            },
        });

        if (!response.ok) {
            const text = await response.text();
            throw new Error((text || '엑셀 다운로드 중 오류가 발생했습니다.').slice(0, 240));
        }

        const blob = await response.blob();
        const filename = parseDownloadFilename(
            response.headers.get('Content-Disposition') || '',
            config.filename
        );
        triggerBlobDownload(blob, filename);
    }

    async function uploadGridExcel(contextKey = 'item') {
        const config = applyExcelManagerContext(contextKey);
        const fileInput = form.querySelector('input[type="file"]');
        if (!(fileInput instanceof HTMLInputElement) || !fileInput.files?.length) {
            window.AppCore?.notify?.('warning', '업로드할 엑셀 파일을 선택해 주세요.');
            return;
        }

        const prepared = prepareExcelAction(form, 'template');
        const formData = new FormData(form);
        if (prepared.columns.length > 0) {
            formData.set('excel_template_columns', prepared.columns.join(','));
        }
        if (Object.keys(prepared.columnDisplayName || {}).length > 0) {
            formData.set('column_display_name', JSON.stringify(prepared.columnDisplayName));
        }
        if (Object.keys(prepared.columnRequirementPolicy || {}).length > 0) {
            formData.set('column_requirement_policy', JSON.stringify(prepared.columnRequirementPolicy));
        }

        const modal = excelModalEl;
        try {
            const json = await window.ExcelManagerProgress.request(config.uploadUrl, formData, modal);
            if (!json?.success) {
                throw new Error(json?.message || '엑셀 업로드 중 오류가 발생했습니다.');
            }

            const rows = Array.isArray(json?.data?.rows) ? json.data.rows : [];
            if (contextKey === 'item') {
                ctx.setLines?.(rows);
                ctx.calculateTotals?.();
            } else if (contextKey === 'settlement') {
                ctx.setSettlements?.(rows);
            }

            window.ExcelManagerProgress.set(modal, {
                percent: 100,
                percentLabel: '100%',
                title: '업로드 완료',
                message: json.message || '엑셀 업로드가 완료되었습니다.',
            });
            window.AppCore?.notify?.('success', json.message || '엑셀 업로드가 완료되었습니다.');

            window.setTimeout(() => {
                ctx.excelModal?.hide();
                document.dispatchEvent(new CustomEvent('excel:uploaded', {
                    detail: { contextKey },
                }));
            }, 250);
        } finally {
            window.ExcelManagerProgress.lock(modal, false);
        }
    }

    function openExcelManager(contextKey = 'header') {
        applyExcelManagerContext(contextKey);
        ctx.excelModal?.show();
    }

    ctx.openHeaderExcelManager = () => openExcelManager('header');

    ctx.lineExcelBtn?.addEventListener('click', () => openExcelManager('item'));
    ctx.settlementExcelBtn?.addEventListener('click', () => openExcelManager('settlement'));

    form.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) {
            return;
        }

        const contextKey = String(ctx.excelManagerContextKey || 'header').trim();
        if (contextKey === 'header') {
            return;
        }

        if (button.classList.contains('btn-download-all')) {
            event.preventDefault();
            event.stopPropagation();
            void downloadGridExcel(contextKey).catch((error) => {
                window.AppCore?.notify?.('error', error.message || '엑셀 다운로드 중 오류가 발생했습니다.');
            });
            return;
        }

        if (button.classList.contains('btn-upload-excel')) {
            event.preventDefault();
            event.stopPropagation();
            void uploadGridExcel(contextKey).catch((error) => {
                window.ExcelManagerProgress.lock(excelModalEl, false);
                window.AppCore?.notify?.('error', error.message || '엑셀 업로드 중 오류가 발생했습니다.');
            });
        }
    });

    applyExcelManagerContext('header');

    document.addEventListener('excel:uploaded', (event) => {
        const contextKey = String(event?.detail?.contextKey || 'header').trim() || 'header';
        if (contextKey === 'header') {
            ctx.transactionTable?.ajax?.reload(null, false);
        }
    });
}

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
        createExcelManagerSettingsCore,
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

    initExcelManager(ctx);
    reorderTransactionToolbar(ctx);
    ctx.transactionTable?.on?.('init.dt draw.dt', () => {
        reorderTransactionToolbar(ctx);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}

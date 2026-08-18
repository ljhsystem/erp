import { bindTableHighlight, createDataTable } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { createTransactionContext } from './state.js?v=20260815-transaction-performance-1';
import { registerTable } from './table.js?v=20260815-transaction-performance-1';

const notify = (type, message, options = {}) => window.notify?.(type, message, options);

function embeddedEvidenceTypePolicies() {
    const node = document.getElementById('ledgerEvidenceTypePolicies');
    try {
        const policies = JSON.parse(node?.textContent || '[]');
        return Array.isArray(policies) ? policies : [];
    } catch {
        return [];
    }
}

let evidenceEditorPromise = null;
async function ensureEvidenceEditor() {
    if (!evidenceEditorPromise) {
        evidenceEditorPromise = (async () => {
            const [{ bootEvidencePage }, policies] = await Promise.all([
                import('/public/assets/js/pages/ledger/evidence-page-app.js'),
                (async () => {
                    const embedded = embeddedEvidenceTypePolicies();
                    if (embedded.length > 0) return embedded;
                    const response = await fetch('/api/ledger/evidence/type-policies', {
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) return [];
                    const json = await response.json();
                    return Array.isArray(json?.data) ? json.data : [];
                })(),
            ]);
            return bootEvidencePage({ editorOnly: true, evidenceTypePolicies: policies });
        })().catch((error) => {
            evidenceEditorPromise = null;
            console.error('[ledger-transaction] evidence editor load failed', error);
            notify('error', '증빙원본 편집 기능을 불러오지 못했습니다.');
            return null;
        });
    }
    return evidenceEditorPromise;
}

function ensureStylesheet(href) {
    const absoluteHref = new URL(href, window.location.origin).href;
    if (Array.from(document.styleSheets).some((sheet) => sheet.href === absoluteHref)) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.onload = resolve;
        link.onerror = () => reject(new Error(`stylesheet load failed: ${href}`));
        document.head.appendChild(link);
    });
}

let trashManagerPromise = null;
function ensureTrashManager() {
    if (!trashManagerPromise) {
        trashManagerPromise = Promise.all([
            ensureStylesheet('/public/assets/css/components/trash-manager.css'),
            import('/public/assets/js/components/trash-manager.js'),
        ]).catch((error) => {
            trashManagerPromise = null;
            console.error('[ledger-transaction] trash manager load failed', error);
            notify('error', '휴지통 기능을 불러오지 못했습니다.');
            throw error;
        });
    }
    return trashManagerPromise;
}

async function init() {
    const ctx = createTransactionContext({
        bindTableHighlight,
        createDataTable,
        SearchForm,
        bindRowReorder,
        ensureEvidenceEditor,
        ensureTrashManager,
        evidenceEditor: {
            async open(options) {
                return (await ensureEvidenceEditor())?.open?.(options);
            },
        },
    });
    if (!ctx.isReady) return;

    registerTable(ctx);
    await ctx.initTransactionTable();

    let runtimePromise = null;
    const ensureRuntime = () => {
        if (!runtimePromise) {
            runtimePromise = Promise.all([
                import('/public/assets/js/common/format.js'),
                import('/public/assets/js/common/grid/ag-grid-input.js'),
                import('/public/assets/js/common/grid/ag-grid-editors.js'),
                import('/public/assets/js/common/grid/ag-grid-formatters.js'),
                import('/public/assets/js/common/picker/admin_picker.js'),
                import('/public/assets/js/pages/dashboard/settings/system/code-select.js'),
                import('/public/assets/js/pages/dashboard/settings/base/client.js'),
                import('./editors.js'),
                import('./calculation.js'),
                import('./storage.js'),
                import('./keyboard.js'),
                import('./grid.js'),
                import('./modal.js'),
                import('./selects.js'),
                import('./files.js'),
                import('./validation.js'),
                import('./events.js'),
                import('./evidence-selection-table.js'),
                import('./recommendation.js'),
            ]).then((modules) => {
                const [numberFormat, gridInput, gridEditors, gridFormatters, picker, codeSelect, client,
                    editors, calculation, storage, keyboard, grid, modal, selects, files, validation,
                    events, evidenceSelection, recommendation] = modules;
                Object.assign(ctx, {
                    bindNumberInput: numberFormat.bindNumberInput,
                    formatDateInputValue: numberFormat.formatDateInputValue,
                    formatNumber: numberFormat.formatNumber,
                    parseNumber: numberFormat.parseNumber,
                    createAgGridInputAdapter: gridInput.createAgGridInputAdapter,
                    selectEditor: gridEditors.selectEditor,
                    dateStringEditor: gridEditors.dateStringEditor,
                    gridNumberFormatter: gridFormatters.gridNumberFormatter,
                    gridNumberParser: gridFormatters.gridNumberParser,
                    AdminPicker: picker.AdminPicker,
                    createCodeSelect: codeSelect.createCodeSelect,
                    getCodeName: codeSelect.getCodeName,
                    initCodeSelectControls: codeSelect.initCodeSelectControls,
                    onCodeOptionsLoaded: codeSelect.onCodeOptionsLoaded,
                    openCodeQuickModal: codeSelect.openCodeQuickModal,
                    openClientQuickCreate: client.openClientQuickCreate,
                });
                editors.registerEditors(ctx);
                calculation.registerCalculation(ctx);
                storage.registerStorage(ctx);
                keyboard.registerKeyboard(ctx);
                grid.registerGrid(ctx);
                evidenceSelection.registerTransactionEvidenceSelectionTable(ctx);
                modal.registerModal(ctx);
                selects.registerSelects(ctx);
                recommendation.registerTransactionRecommendation(ctx);
                files.registerFiles(ctx);
                validation.registerValidation(ctx);
                events.registerEvents(ctx);
                ctx.boot?.();
                return ctx;
            }).catch((error) => {
                runtimePromise = null;
                console.error('[ledger-transaction] modal runtime load failed', error);
                notify('error', '거래 입력 기능을 불러오지 못했습니다.');
                throw error;
            });
        }
        return runtimePromise;
    };

    ctx.ensureRuntime = ensureRuntime;
    const showModalShell = () => ctx.modal?.show();
    ctx.openCreateModal = (...args) => {
        showModalShell();
        return ensureRuntime().then(() => ctx.openCreateModal(...args));
    };
    ctx.openDetail = (...args) => {
        showModalShell();
        return ensureRuntime().then(() => ctx.openDetail(...args));
    };
    ctx.deleteTransaction = (...args) => ensureRuntime().then(() => ctx.deleteTransaction(...args));

    void ensureRuntime();
}

await init();

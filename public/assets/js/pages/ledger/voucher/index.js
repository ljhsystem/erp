import * as NumberFormat from '/public/assets/js/common/format.js';
import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { escapeHtml } from '/public/assets/js/common/html.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import { createVoucherContext } from './context.js';
import { registerHelpers } from './helpers.js?v=20260708-3';
import { registerTable } from './table.js?v=20260815-6';

const onlyNumber = NumberFormat.onlyNumber || ((value) => String(value ?? '').replace(/\D/g, ''));
const { formatNumber } = NumberFormat;
const notify = (type, message, options = {}) => window.notify?.(type, message, options);

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
        buttonsWrap.querySelector('.voucher-create-btn'),
    ].filter(Boolean);

    orderedButtons.forEach((button) => {
        buttonsWrap.appendChild(button);
    });

    if (lengthNode && settingsButton && settingsButton.parentElement === toolbar) {
        toolbar.appendChild(settingsButton);
    }
}

function embeddedEvidenceTypePolicies() {
    const node = document.getElementById('ledgerEvidenceTypePolicies');
    if (!node) return [];
    try {
        const policies = JSON.parse(node.textContent || '[]');
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
            console.error('[ledger-journal] evidence editor load failed', error);
            notify('error', '증빙원본 편집 기능을 불러오지 못했습니다.');
            return null;
        });
    }
    return evidenceEditorPromise;
}

let trashManagerPromise = null;
function ensureStylesheet(href) {
    const absoluteHref = new URL(href, window.location.origin).href;
    if (Array.from(document.styleSheets).some((sheet) => sheet.href === absoluteHref)) {
        return Promise.resolve();
    }
    return new Promise((resolve, reject) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.onload = resolve;
        link.onerror = () => reject(new Error(`stylesheet load failed: ${href}`));
        document.head.appendChild(link);
    });
}

function ensureTrashManager() {
    if (!trashManagerPromise) {
        trashManagerPromise = Promise.all([
            ensureStylesheet('/public/assets/css/components/trash-manager.css'),
            import('/public/assets/js/components/trash-manager.js'),
        ]).catch((error) => {
            trashManagerPromise = null;
            console.error('[ledger-journal] trash manager load failed', error);
            notify('error', '휴지통 기능을 불러오지 못했습니다.');
            throw error;
        });
    }
    return trashManagerPromise;
}

const ctx = createVoucherContext({
    SearchForm,
    actorDisplay,
    bindRowReorder,
    bindTableHighlight,
    createDataTable,
    escapeHtml,
    formatNumber,
    notify,
    onlyNumber,
    ensureEvidenceEditor,
    ensureTrashManager,
});

if (ctx.isReady) {
    registerHelpers(ctx);
    registerTable(ctx);
    await ctx.initJournalTable();
    reorderVoucherToolbar(ctx);
    ctx.state?.journalTable?.on?.('init.dt draw.dt', () => {
        reorderVoucherToolbar(ctx);
    });

    let modalRuntimePromise = null;
    const ensureModalRuntime = () => {
        if (!modalRuntimePromise) {
            modalRuntimePromise = Promise.all([
                import('/public/assets/js/common/picker/admin_picker.js'),
                import('/public/assets/js/pages/dashboard/settings/system/code-select.js'),
                import('/public/assets/js/pages/dashboard/settings/base/client.js'),
                import('/public/assets/js/pages/ledger/shared/utils.js'),
                import('./runtime.js'),
                import('./form.js'),
                import('./actions.js'),
                import('./evidence-links.js'),
                import('./evidence-selection-table.js'),
            ]).then(async ([
                { AdminPicker },
                codeSelect,
                { openClientQuickCreate },
                { resolveDisplayText },
                { initializeVoucherRuntime },
                { registerForm },
                { registerActions },
                { registerEvidenceLinks },
                { registerEvidenceSelectionTable },
            ]) => {
                Object.assign(ctx, {
                    AdminPicker,
                    getCodeName: codeSelect.getCodeName,
                    initCodeSelectControls: codeSelect.initCodeSelectControls,
                    onCodeOptionsLoaded: codeSelect.onCodeOptionsLoaded,
                    openClientQuickCreate,
                    resolveDisplayText,
                });
                registerForm(ctx);
                registerEvidenceLinks(ctx);
                registerEvidenceSelectionTable(ctx);
                await initializeVoucherRuntime(ctx);
                registerActions(ctx);
                await ctx.boot();
                return ctx;
            }).catch((error) => {
                modalRuntimePromise = null;
                console.error('[ledger-journal] modal runtime load failed', error);
                notify('error', '전표 입력 기능을 불러오지 못했습니다.');
                throw error;
            });
        }
        return modalRuntimePromise;
    };

    ctx.ensureModalRuntime = ensureModalRuntime;
    const showLazyModalShell = (mode) => {
        ctx.setModalTitle?.(mode);
        ctx.setJournalModalLoading?.(true);
        ctx.modal?.show();
    };
    ctx.openCreateModal = (...args) => {
        showLazyModalShell('create');
        return ensureModalRuntime().then(() => ctx.openCreateModal(...args));
    };
    ctx.loadDetail = (...args) => {
        showLazyModalShell('edit');
        return ensureModalRuntime().then(() => ctx.loadDetail(...args));
    };
    ctx.handleJournalAction = (action, ...args) => {
        if (action === 'edit' || action === 'detail') {
            showLazyModalShell('edit');
        }
        return ensureModalRuntime().then(() => ctx.handleJournalAction(action, ...args));
    };

    void ensureModalRuntime();
}

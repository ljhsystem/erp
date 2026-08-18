import { createHtmlGrid } from '/public/assets/js/common/html-grid/index.js';
import { createVoucherLineGridSchema } from './voucher-grid-schema.js';
import { createVoucherLineGridHooks } from './voucher-grid-hooks.js';
import { createRecommendationSnapshot, normalizeVoucherRefs, recommendationOrigin, reconcileRecommendationTracking, serializeVoucherRefs } from './recommendation-tracking.js';
import { createVoucherGridColumnState } from './voucher-grid-column-state.js';
function destroySelect2(element) {
    const jquery = window.jQuery || window.$;
    if (!element || !jquery?.fn?.select2) {
        return;
    }
    const $element = jquery(element);
    if (!$element.hasClass('select2-hidden-accessible')) {
        return;
    }
    const instance = $element.data('select2');
    if (isMountedElement(element) && typeof instance?.isOpen === 'function' && instance.isOpen()) {
        $element.select2('close');
    }
    $element.select2('destroy');
}
function closeSelect2(element) {
    const jquery = window.jQuery || window.$;
    if (!element || !jquery?.fn?.select2) {
        return;
    }
    const $element = jquery(element);
    if (!$element.hasClass('select2-hidden-accessible')) {
        return;
    }
    const instance = $element.data('select2');
    const isMounted = element.ownerDocument?.documentElement?.contains(element) === true;
    if (isMounted && typeof instance?.isOpen === 'function' && instance.isOpen()) {
        $element.select2('close');
    }
}
function isMountedElement(element) {
    return element?.ownerDocument?.documentElement?.contains(element) === true;
}
function focusSelect2(element) {
    const jquery = window.jQuery || window.$;
    if (!isMountedElement(element)) {
        return;
    }
    if (jquery?.fn?.select2 && jquery(element).hasClass('select2-hidden-accessible')) {
        jquery(element).select2('open');
        return;
    }
    element.focus?.();
}

function normalizeAmount(ctx, value) {
    return ctx.normalizeAmountValue(value ?? '') || '0';
}

function normalizeAccountValue(value) {
    const normalized = String(value || '').trim();
    if (normalized === '__add__') {
        return '';
    }
    return normalized;
}

const normalizeRefsValue = normalizeVoucherRefs, serializeRefsValue = serializeVoucherRefs;

function deriveDirtyRowState(currentState = '') {
    const normalized = String(currentState || '').trim();
    if (normalized === 'created') {
        return 'created';
    }
    if (['readonly', 'disabled', 'locked', 'saving', 'deleted'].includes(normalized)) {
        return normalized;
    }
    return 'updated';
}

function buildEmptyLine(rowIndex = 0, rowState = 'created') {
    return {
        rowId: `voucher-line-${Date.now()}-${rowIndex + 1}`,
        rowState,
        values: {
            line_no: rowIndex + 1,
            account_id: '',
            refs: [],
            debit: '0',
            credit: '0',
            line_summary: '',
            row_action: '',
        },
        meta: {},
    };
}

function buildLineRow(line = {}, rowIndex = 0, rowState = 'created') {
    const journalRuleId = String(line.journal_rule_id || '').trim();
    const isUserModified = Number(line.is_user_modified) === 1 ? 1 : 0;
    const recommendationSnapshot = String(line.recommendation_snapshot || '') || (
        journalRuleId !== '' && isUserModified === 0 ? createRecommendationSnapshot(line) : '');
    return {
        rowId: String(line.rowId || `voucher-line-${Date.now()}-${rowIndex + 1}`),
        rowState,
        values: {
            line_no: Number(line.line_no || rowIndex + 1),
            account_id: String(line.account_id || line.account_code || '').trim(),
            refs: Array.isArray(line.refs) ? line.refs : [],
            debit: String(line.debit ?? '0'),
            credit: String(line.credit ?? '0'),
            line_summary: String(line.line_summary || '').trim(),
            journal_rule_id: journalRuleId,
            is_user_modified: isUserModified,
            recommendation_snapshot: recommendationSnapshot,
            recommended_account_id: String(line.recommended_account_id || '').trim() || null,
            recommended_line_type: String(line.recommended_line_type || '').trim().toUpperCase() || null,
            recommended_amount: line.recommended_amount ?? null,
            row_action: '',
        },
        meta: {},
    };
}
function createBaseEditor(context, className) {
    const documentRef = context.document || context.host?.ownerDocument || document;
    const element = documentRef.createElement('div');
    element.className = className;

    return {
        element,
        create() {
            return element;
        },
        mount(host) {
            if (host && !element.parentNode) {
                host.textContent = '';
                host.appendChild(element);
            }
            return element;
        },
        focus() {},
        blur() {},
        getValue() {
            return null;
        },
        setValue() {},
        validate() {
            return { valid: true, message: '' };
        },
        isDirty() {
            return false;
        },
        destroy() {
            element.remove?.();
        },
    };
}

function createOrderEditorFactory(bridge) {
    return function createOrderEditor(context = {}) {
        const documentRef = context.document || context.host?.ownerDocument || document;
        const wrapper = documentRef.createElement('div');
        wrapper.className = 'journal-line-order-cell voucher-grid-order-cell';

        const button = documentRef.createElement('button');
        button.type = 'button';
        button.className = 'journal-line-drag-handle voucher-grid-row-handle';
        button.setAttribute('aria-label', '\uC21C\uC11C \uC774\uB3D9');
        button.setAttribute('title', '\uC21C\uC11C \uC774\uB3D9');
        button.dataset.rowId = String(context.row?.rowId || '');
        button.innerHTML = '<i class="bi bi-grip-vertical" aria-hidden="true"></i>';
        button.disabled = bridge.isReadOnly === true;

        const label = documentRef.createElement('span');
        label.className = 'journal-line-display-no';
        label.textContent = String(context.row?.values?.line_no || context.value || '');

        wrapper.appendChild(button);
        wrapper.appendChild(label);

        return {
            element: wrapper,
            create() {
                return wrapper;
            },
            mount(host) {
                if (host && !wrapper.parentNode) {
                    host.textContent = '';
                    host.appendChild(wrapper);
                }
                button.disabled = bridge.isReadOnly === true;
                return wrapper;
            },
            focus() {},
            blur() {},
            getValue() {
                return String(context.row?.values?.line_no || context.value || '');
            },
            setValue(value) {
                label.textContent = String(value || '');
                return label.textContent;
            },
            validate() {
                return { valid: true, message: '' };
            },
            isDirty() {
                return false;
            },
            destroy() {
                wrapper.remove?.();
            },
        };
    };
}

function createDeleteEditorFactory(bridge) {
    return function createDeleteEditor(context = {}) {
        const documentRef = context.document || context.host?.ownerDocument || document;
        const button = documentRef.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-link btn-sm btn-remove-line voucher-grid-delete-btn';
        button.textContent = '-\uC0AD\uC81C';
        button.disabled = bridge.isReadOnly === true;

        const handleClick = () => {
            if (bridge.isReadOnly === true) {
                return;
            }
            bridge.deleteRowByRowId(String(context.row?.rowId || ''));
        };

        button.addEventListener('click', handleClick);

        return {
            element: button,
            create() {
                return button;
            },
            mount(host) {
                if (host && !button.parentNode) {
                    host.textContent = '';
                    host.appendChild(button);
                }
                button.disabled = bridge.isReadOnly === true;
                return button;
            },
            focus() {
                button.focus?.();
            },
            blur() {
                button.blur?.();
            },
            getValue() {
                return '';
            },
            setValue() {
                return '';
            },
            validate() {
                return { valid: true, message: '' };
            },
            isDirty() {
                return false;
            },
            destroy() {
                button.removeEventListener('click', handleClick);
                button.remove?.();
            },
        };
    };
}

function createAccountEditorFactory(bridge, ctx) {
    return function createAccountEditor(context = {}) {
        const documentRef = context.document || context.host?.ownerDocument || document;
        const select = documentRef.createElement('select');
        select.className = 'form-select form-select-sm line-account-code-picker voucher-grid-account-editor html-grid-editor';
        select.dataset.rowId = String(context.row?.rowId || '');
        select.disabled = bridge.isReadOnly === true;
        const initialValue = String(context.value || '').trim();
        let lastCommittedValue = normalizeAccountValue(initialValue);
        let destroyed = false;
        let isInitializing = false;
        let cleanupCommitBinding = null;

        const bindCommit = (handler) => {
            cleanupCommitBinding?.();

            if (window.jQuery) {
                const $select = window.jQuery(select);
                $select.off('.voucherGridAccountCommit');
                $select.on('change.voucherGridAccountCommit', () => {
                    if (!destroyed && isMountedElement(select)) {
                        void handler();
                    }
                });
                cleanupCommitBinding = () => {
                    $select.off('.voucherGridAccountCommit');
                    cleanupCommitBinding = null;
                };
                return;
            }

            const guardedHandler = () => {
                if (!destroyed && isMountedElement(select)) {
                    handler();
                }
            };
            select.addEventListener('change', guardedHandler);
            cleanupCommitBinding = () => {
                select.removeEventListener('change', guardedHandler);
                cleanupCommitBinding = null;
            };
        };

        const populateOptions = async () => {
            isInitializing = true;
            const items = await ctx.ensureAccountPickerItems();
            if (destroyed || !isMountedElement(select)) {
                isInitializing = false;
                return;
            }

            try {
                if (ctx.AdminPicker?.reloadSelect2 && window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
                    ctx.AdminPicker.reloadSelect2(select, items, 'id', 'text', initialValue || '');
                } else {
                    select.textContent = '';
                    items.forEach((item) => {
                        const optionEl = documentRef.createElement('option');
                        optionEl.value = String(item.id || '');
                        optionEl.textContent = String(item.text || item.id || '');
                        select.appendChild(optionEl);
                    });
                    select.value = initialValue;
                }
                select.disabled = bridge.isReadOnly === true;
            } finally {
                isInitializing = false;
            }
        };

        const commit = async () => {
            const accountId = normalizeAccountValue(select.value);
            if (destroyed || isInitializing || accountId === lastCommittedValue) {
                return;
            }

            lastCommittedValue = accountId;
            bridge.updateAccount(String(context.row?.rowId || ''), accountId);
            if (accountId !== '') {
                await ctx.loadAccountPolicies(accountId);
            }
        };

        bindCommit(commit);
        void populateOptions();

        return {
            element: select,
            create() {
                return select;
            },
            mount(host) {
                if (host && !select.parentNode) {
                    host.textContent = '';
                    host.appendChild(select);
                }
                select.disabled = bridge.isReadOnly === true;
                return select;
            },
            focus() {
                if (bridge.isReadOnly === true) {
                    return;
                }
                focusSelect2(select);
            },
            blur() {
                select.blur?.();
            },
            getValue() {
                return String(select.value || '').trim();
            },
            setValue(value) {
                select.value = String(value || '').trim();
                return select.value;
            },
            validate() {
                return { valid: true, message: '' };
            },
            isDirty() {
                return String(select.value || '').trim() !== initialValue;
            },
            destroy() {
                destroyed = true;
                cleanupCommitBinding?.();
                destroySelect2(select);
                select.remove?.();
            },
        };
    };
}

function createRefsEditorFactory(bridge, ctx) {
    return function createRefsEditor(context = {}) {
        const documentRef = context.document || context.host?.ownerDocument || document;
        const wrapper = documentRef.createElement('div');
        wrapper.className = 'journal-line-subaccounts voucher-grid-refs-editor';
        const rowId = String(context.row?.rowId || '');
        let destroyed = false;
        let isInitializing = false;
        let currentSelects = [];
        let cleanupCommitBindings = [];
        let lastCommittedRefs = serializeRefsValue(context.row?.values?.refs ?? context.value);
        let renderSeq = 0;

        const destroyPickers = () => {
            currentSelects.forEach((selectEl) => {
                destroySelect2(selectEl);
            });
            currentSelects = [];
        };

        const collectRefs = () => normalizeRefsValue(currentSelects
            .map((selectEl) => ({
                ref_target: String(selectEl.dataset.refTarget || '').toUpperCase(),
                ref_id: String(selectEl.value || '').trim(),
                is_primary: selectEl.dataset.policyIndex === '0' ? 1 : 0,
            })));

        const bindCommit = (selectEl, handler) => {
            if (!selectEl) {
                return;
            }

            if (window.jQuery) {
                const $select = window.jQuery(selectEl);
                $select.off('.voucherGridRefsCommit');
                $select.on(
                    [
                        'change.voucherGridRefsCommit',
                        'select2:select.voucherGridRefsCommit',
                        'select2:clear.voucherGridRefsCommit',
                        'select2:unselect.voucherGridRefsCommit',
                    ].join(' '),
                    () => {
                        if (!destroyed && isMountedElement(selectEl)) {
                            handler();
                        }
                    }
                );
                cleanupCommitBindings.push(() => {
                    $select.off('.voucherGridRefsCommit');
                });
                return;
            }

            const guardedHandler = () => {
                if (!destroyed && isMountedElement(selectEl)) {
                    handler();
                }
            };
            selectEl.addEventListener('change', guardedHandler);
            cleanupCommitBindings.push(() => {
                selectEl.removeEventListener('change', guardedHandler);
            });
        };

        const commit = () => {
            if (destroyed || isInitializing) {
                return collectRefs();
            }

            const nextRefs = collectRefs();
            const serializedRefs = serializeRefsValue(nextRefs);
            if (serializedRefs === lastCommittedRefs) {
                return nextRefs;
            }

            lastCommittedRefs = serializedRefs;
            bridge.updateRefs(rowId, nextRefs, { row: context.row });
            return nextRefs;
        };

        const render = async () => {
            const seq = ++renderSeq;
            cleanupCommitBindings.forEach((cleanup) => cleanup());
            cleanupCommitBindings = [];
            destroyPickers();
            wrapper.textContent = '';

            const accountId = String(context.row?.values?.account_id || '').trim();
            if (accountId === '') {
                const empty = documentRef.createElement('span');
                empty.className = 'journal-subaccount-empty';
                empty.textContent = '-';
                wrapper.appendChild(empty);
                ctx.scheduleJournalModalLayoutUpdate?.();
                return;
            }

            const policies = await ctx.loadAccountPolicies(accountId);
            if (destroyed || seq !== renderSeq) {
                return;
            }

            if (!Array.isArray(policies) || policies.length === 0) {
                const empty = documentRef.createElement('span');
                empty.className = 'journal-subaccount-empty';
                empty.textContent = '\uBCF4\uC870\uACC4\uC815 \uC5C6\uC74C';
                wrapper.appendChild(empty);
                ctx.scheduleJournalModalLayoutUpdate?.();
                return;
            }

            wrapper.classList.add('journal-line-subaccount-grid');
            const refs = normalizeRefsValue(context.row?.values?.refs ?? context.value);
            lastCommittedRefs = serializeRefsValue(refs);
            const selectedMap = new Map();

            refs.forEach((ref) => {
                const refType = String(ref.ref_target || ref.line_ref_target || '').toUpperCase();
                const refId = String(ref.ref_id || '').trim();
                if (refType !== '' && refId !== '') {
                    ctx.refTypeAliases(refType).forEach((alias) => {
                        if (alias && !selectedMap.has(alias)) {
                            selectedMap.set(alias, refId);
                        }
                    });
                }
            });

            isInitializing = true;
            try {
                for (let index = 0; index < policies.length; index += 1) {
                    const policy = policies[index];
                    const refTarget = String(policy.ref_target || '').toUpperCase();
                    const field = documentRef.createElement('label');
                    field.className = 'journal-line-subaccount-field';

                    const caption = documentRef.createElement('span');
                    caption.textContent = ctx.translateType(refTarget);
                    if (policy.is_required) {
                        const required = documentRef.createElement('b');
                        required.className = 'journal-line-subaccount-required';
                        required.textContent = '*';
                        caption.appendChild(documentRef.createTextNode(' '));
                        caption.appendChild(required);
                    }

                    const selectEl = documentRef.createElement('select');
                    selectEl.className = 'form-select form-select-sm line-ref-picker voucher-grid-ref-picker';
                    selectEl.dataset.refTarget = refTarget;
                    selectEl.dataset.required = policy.is_required ? '1' : '0';
                    selectEl.dataset.policyIndex = String(index);
                    selectEl.dataset.rowId = rowId;
                    selectEl.disabled = bridge.isReadOnly === true;
                    bindCommit(selectEl, commit);

                    field.appendChild(caption);
                    field.appendChild(selectEl);
                    wrapper.appendChild(field);
                    currentSelects.push(selectEl);

                    const selectedValue = ctx.refTypeAliases(refTarget)
                        .map((alias) => selectedMap.get(alias))
                        .find((value) => String(value || '').trim() !== '')
                        || '';
                    await ctx.initRefPicker(selectEl, refTarget, selectedValue, {
                        isActive: () => !destroyed && seq === renderSeq && isMountedElement(selectEl),
                    });
                    if (destroyed || seq !== renderSeq) {
                        return;
                    }
                    selectEl.disabled = bridge.isReadOnly === true;
                }
            } finally {
                isInitializing = false;
            }
            ctx.scheduleJournalModalLayoutUpdate?.();
        };

        void render();
        const refsEditorHandle = {
            commit,
            getValue: collectRefs,
        };
        bridge.registerRefsEditor(rowId, refsEditorHandle);

        return {
            element: wrapper,
            create() {
                return wrapper;
            },
            mount(host) {
                if (host && !wrapper.parentNode) {
                    host.textContent = '';
                    host.appendChild(wrapper);
                }
                return wrapper;
            },
            focus() {
                if (bridge.isReadOnly === true) {
                    return;
                }
                focusSelect2(currentSelects[0]);
            },
            blur() {
                currentSelects[0]?.blur?.();
            },
            getValue() {
                return collectRefs();
            },
            setValue() {
                return collectRefs();
            },
            validate() {
                return { valid: true, message: '' };
            },
            isDirty() {
                return false;
            },
            destroy() {
                destroyed = true;
                renderSeq += 1;
                bridge.unregisterRefsEditor(rowId, refsEditorHandle);
                cleanupCommitBindings.forEach((cleanup) => cleanup());
                cleanupCommitBindings = [];
                destroyPickers();
                wrapper.remove?.();
            },
        };
    };
}

function createAmountEditorFactory(bridge, ctx, columnKey) {
    const oppositeKey = columnKey === 'debit' ? 'credit' : 'debit';
    const cssClass = columnKey === 'debit' ? 'line-debit' : 'line-credit';

    return function createAmountEditor(context = {}) {
        const documentRef = context.document || context.host?.ownerDocument || document;
        const input = documentRef.createElement('input');
        const initialValue = ctx.formatAmountValue(context.value || 0) || '';
        input.type = 'text';
        input.inputMode = 'numeric';
        input.className = `form-control form-control-sm input-amount ${cssClass} voucher-grid-amount-input html-grid-editor`;
        input.value = initialValue;
        input.dataset.rowId = String(context.row?.rowId || '');
        input.dataset.columnKey = columnKey;
        input.readOnly = bridge.isReadOnly === true;
        input.disabled = bridge.isReadOnly === true;
        let lastCommittedValue = normalizeAmount(ctx, context.value);

        const commit = () => {
            const rowId = String(context.row?.rowId || '');
            const normalized = normalizeAmount(ctx, input.value);
            const patch = { [columnKey]: normalized };
            if (Number(normalized) > 0) {
                patch[oppositeKey] = '0';
            }
            bridge.updateRowValues(rowId, patch);
            lastCommittedValue = normalized;
        };

        const handleBlur = () => {
            commit();
        };

        const handleKeyDown = (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                input.blur();
            }
        };

        input.addEventListener('blur', handleBlur);
        input.addEventListener('keydown', handleKeyDown);

        return {
            element: input,
            create() {
                return input;
            },
            mount(host) {
                if (host && !input.parentNode) {
                    host.textContent = '';
                    host.appendChild(input);
                }
                input.readOnly = bridge.isReadOnly === true;
                input.disabled = bridge.isReadOnly === true;
                return input;
            },
            focus() {
                if (bridge.isReadOnly === true) {
                    return;
                }
                input.focus?.();
            },
            blur() {
                input.blur?.();
            },
            getValue() {
                return normalizeAmount(ctx, input.value);
            },
            setValue(value) {
                input.value = ctx.formatAmountValue(value || 0) || '';
                lastCommittedValue = normalizeAmount(ctx, value);
                return input.value;
            },
            validate() {
                return { valid: true, message: '' };
            },
            isDirty() {
                return normalizeAmount(ctx, input.value) !== lastCommittedValue;
            },
            destroy() {
                input.removeEventListener('blur', handleBlur);
                input.removeEventListener('keydown', handleKeyDown);
                input.remove?.();
            },
        };
    };
}

function createSummaryEditorFactory(bridge) {
    return function createSummaryEditor(context = {}) {
        const documentRef = context.document || context.host?.ownerDocument || document;
        const input = documentRef.createElement('input');
        const initialValue = String(context.value || '');
        input.type = 'text';
        input.className = 'form-control form-control-sm line-summary voucher-grid-summary-input html-grid-editor';
        input.value = initialValue;
        input.dataset.rowId = String(context.row?.rowId || '');
        input.readOnly = bridge.isReadOnly === true;
        input.disabled = bridge.isReadOnly === true;

        const commit = () => {
            bridge.updateRowValues(String(context.row?.rowId || ''), {
                line_summary: String(input.value || '').trim(),
            });
        };

        const handleBlur = () => {
            commit();
        };

        const handleKeyDown = (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                input.blur();
            }
        };

        input.addEventListener('blur', handleBlur);
        input.addEventListener('keydown', handleKeyDown);

        return {
            element: input,
            create() {
                return input;
            },
            mount(host) {
                if (host && !input.parentNode) {
                    host.textContent = '';
                    host.appendChild(input);
                }
                input.readOnly = bridge.isReadOnly === true;
                input.disabled = bridge.isReadOnly === true;
                return input;
            },
            focus() {
                if (bridge.isReadOnly === true) {
                    return;
                }
                input.focus?.();
            },
            blur() {
                input.blur?.();
            },
            getValue() {
                return String(input.value || '').trim();
            },
            setValue(value) {
                input.value = String(value || '');
                return input.value;
            },
            validate() {
                return { valid: true, message: '' };
            },
            isDirty() {
                return String(input.value || '').trim() !== initialValue;
            },
            destroy() {
                input.removeEventListener('blur', handleBlur);
                input.removeEventListener('keydown', handleKeyDown);
                input.remove?.();
            },
        };
    };
}

export function createVoucherLineGridBridge(ctx) {
    const hooks = createVoucherLineGridHooks();
    const bridge = {
        grid: null,
        dragRowId: '',
        dragReady: false,
        headerDragKey: '',
        resizeSession: null,
        eventUnsubscribers: [],
        refsEditors: new Map(),
        isReadOnly: false,
        summaryRefreshFrame: 0,
    };

    const schema = createVoucherLineGridSchema();

    function isReadOnly() {
        return bridge.isReadOnly === true;
    }

    function getGrid() {
        return bridge.grid;
    }

    function getRows() {
        return getGrid()?.getState()?.rows || [];
    }

    function getRowIndexByRowId(rowId = '') {
        return getRows().findIndex((row) => String(row.rowId || '') === String(rowId || ''));
    }

    function getLineHost() {
        return ctx.lineGridHostEl || null;
    }
    const columnState = createVoucherGridColumnState(ctx, getGrid);
    const normalizeColumnState = columnState.normalize;
    const shouldApplyColumnState = columnState.shouldApply;
    const readSavedColumnState = columnState.read;
    const persistColumnState = columnState.persist;

    function buildAccountPickerAdapter() {
        return ({ editorElement }) => {
            if (!editorElement || !window.jQuery || !ctx.AdminPicker) {
                return null;
            }

            ctx.AdminPicker.select2(editorElement, {
                dropdownParent: window.jQuery(ctx.modalEl),
                width: '100%',
                templateResult: ctx.renderPickerOption,
                templateSelection: ctx.renderPickerSelection,
            });

            return {
                destroy() {
                    destroySelect2(editorElement);
                },
            };
        };
    }

    function applyReadOnlyState() {
        const host = getLineHost();
        if (!host) {
            return;
        }

        host.dataset.readonly = isReadOnly() ? '1' : '0';
        host.classList.toggle('is-readonly', isReadOnly());

        host.querySelectorAll('input, select, textarea, button').forEach((element) => {
            if (element.matches('.voucher-grid-amount-input, .voucher-grid-summary-input')) {
                element.readOnly = isReadOnly();
            }
            element.disabled = isReadOnly();
            if (window.jQuery?.fn?.select2 && element.matches('select')) {
                window.jQuery(element).prop('disabled', isReadOnly());
            }
            if (isReadOnly() && element.matches('select')) {
                closeSelect2(element);
            }
        });
    }

    function setReadOnly(nextReadOnly = false) {
        bridge.isReadOnly = nextReadOnly === true;
        applyReadOnlyState();
    }

    function queueSummaryRender() {
        const host = getLineHost();
        const view = host?.ownerDocument?.defaultView || window;
        if (bridge.summaryRefreshFrame) {
            return;
        }

        bridge.summaryRefreshFrame = view.requestAnimationFrame(() => {
            bridge.summaryRefreshFrame = 0;
            refreshSummaryFromState();
            ctx.scheduleJournalModalLayoutUpdate?.();
        });
    }

    function refreshValidationBadgeFromState() {
        const state = getGrid()?.getState();
        const rows = Array.isArray(state?.rows) ? state.rows.filter((row) => String(row.rowState || '') !== 'deleted') : [];
        const summary = buildSummarySnapshot(rows);
        ctx.updateTotals?.(summary);
    }

    function refreshSummaryFromState() {
        refreshValidationBadgeFromState();
    }

    function buildSummarySnapshot(rows = []) {
        const serializedRows = serializeCollectedRows(rows);
        const debit = serializedRows.reduce((sum, row) => sum + (Number(row?.debit || '0') || 0), 0);
        const credit = serializedRows.reduce((sum, row) => sum + (Number(row?.credit || '0') || 0), 0);
        const rowCount = serializedRows.length;
        const validation = rowCount === 0
            ? {
                type: 'error',
                message: '분개 라인을 먼저 입력해 주세요.',
            }
            : debit === credit
                ? {
                    type: 'ok',
                    message: '차변/대변 합계가 일치합니다.',
                }
                : {
                    type: 'error',
                    message: '차변/대변 합계가 일치하지 않습니다.',
                };

        return {
            rowCount,
            debit,
            credit,
            validation,
            lines: serializedRows,
        };
    }

    function refreshSummaryFromDom() {
        const summary = buildSummarySnapshot(syncFromDom());
        ctx.updateTotals?.(summary);
        return summary;
    }

    async function refreshSubAccountColumnVisibility() {
        const grid = getGrid();
        if (!grid) {
            return;
        }

        const currentState = grid.getColumnState();
        const normalizedState = normalizeColumnState(currentState);
        if (shouldApplyColumnState(currentState, normalizedState)) {
            grid.setColumnState(normalizedState);
        }
        syncHeaderActionButton();
        ctx.scheduleJournalModalLayoutUpdate?.();
    }

    function syncHeaderActionButton() {
        const host = getLineHost();
        if (!host || !ctx.addLineBtn) {
            return;
        }

        const headerCell = host.querySelector('.html-grid-header-cell[data-column-key="row_action"]');
        if (!headerCell) {
            return;
        }

        let actionWrap = headerCell.querySelector('.voucher-grid-header-action');
        if (!actionWrap) {
            actionWrap = host.ownerDocument.createElement('div');
            actionWrap.className = 'voucher-grid-header-action';
            headerCell.appendChild(actionWrap);
        }

        const headerLabel = headerCell.querySelector('.html-grid-header-label');
        if (headerLabel) {
            headerLabel.textContent = '';
        }

        if (ctx.addLineBtn.parentElement !== actionWrap) {
            actionWrap.appendChild(ctx.addLineBtn);
        }
    }

    function createGrid() {
        const host = getLineHost();
        if (!host) {
            return null;
        }

        host.classList.add('voucher-line-grid-host');
        host.textContent = '';
        const legacyTableWrap = document.getElementById('voucher-line-table')?.closest('.journal-lines-table-wrap');
        legacyTableWrap?.classList.add('is-legacy-hidden');

        const grid = createHtmlGrid({
            host,
            gridId: 'voucher-line-grid',
            columns: schema,
            rows: [],
            rowNumberField: 'line_no',
            hooks,
            footerDefinitions: [],
            capabilities: {
                addRow: true,
                deleteRow: true,
                reorder: true,
                selection: true,
                footer: false,
                keyboard: true,
                columnResize: false,
                columnHide: true,
                columnMove: false,
                clipboard: false,
            },
            editors: {
                'voucher-line-order': createOrderEditorFactory(bridge),
                'voucher-line-delete': createDeleteEditorFactory(bridge),
                'voucher-line-account': createAccountEditorFactory(bridge, ctx),
                'voucher-line-refs': createRefsEditorFactory(bridge, ctx),
                'voucher-line-debit': createAmountEditorFactory(bridge, ctx, 'debit'),
                'voucher-line-credit': createAmountEditorFactory(bridge, ctx, 'credit'),
                'voucher-line-summary': createSummaryEditorFactory(bridge),
            },
            adapters: {
                accountPicker: buildAccountPickerAdapter(),
            },
        });

        bridge.eventUnsubscribers.push(
            grid.on('row:added', () => {
                queueSummaryRender();
                void refreshSubAccountColumnVisibility();
            }),
            grid.on('row:deleted', () => {
                resequenceRows();
                queueSummaryRender();
                void refreshSubAccountColumnVisibility();
            }),
            grid.on('row:moved', () => {
                resequenceRows();
                queueSummaryRender();
            }),
            grid.on('row:updated', () => {
                queueSummaryRender();
                void refreshSubAccountColumnVisibility();
            }),
            grid.on('cell:changed', () => {
                queueSummaryRender();
            }),
            grid.on('footer:changed', () => {
                queueSummaryRender();
            }),
            grid.on('validation:changed', () => {
                queueSummaryRender();
            }),
        );

        grid.render({
            noDataMessage: '\uC870\uD68C\uB41C \uBD84\uAC1C\uB77C\uC778\uC774 \uC5C6\uC2B5\uB2C8\uB2E4.',
        });
        if (!ctx.state?.lineGridColumnState) {
            ctx.state.lineGridColumnState = readSavedColumnState();
        }
        if (ctx.state?.lineGridColumnState) {
            grid.setColumnState(normalizeColumnState(ctx.state.lineGridColumnState));
        }
        syncHeaderActionButton();
        applyReadOnlyState();
        ctx.scheduleJournalModalLayoutUpdate?.();

        bridge.grid = grid;
        return grid;
    }

    function ensureGrid() {
        return getGrid() || createGrid();
    }

    function flushRefsForRow(rowId) {
        const editor = bridge.refsEditors.get(String(rowId || ''));
        if (!editor || typeof editor.commit !== 'function') {
            return null;
        }
        return editor.commit();
    }

    function updateRowValues(rowId, patch = {}, options = {}) {
        const grid = ensureGrid();
        const rowIndex = getRowIndexByRowId(rowId);
        if (!grid || rowIndex < 0 || isReadOnly()) {
            return { executed: false };
        }
        if (!options.skipRefsFlush && !Object.prototype.hasOwnProperty.call(patch, 'refs')) {
            flushRefsForRow(rowId);
        }
        const currentValues = grid.getState().rows[rowIndex]?.values || {};
        const tracking = reconcileRecommendationTracking({ ...currentValues, ...patch });
        return grid.updateRow(rowIndex, { ...patch, ...tracking }, options);
    }

    function resequenceRows() {
        const grid = ensureGrid();
        if (!grid) {
            return;
        }

        const nextState = grid.getState();
        nextState.rows = nextState.rows.map((row, index) => ({
            ...row,
            values: {
                ...(row.values || {}),
                line_no: index + 1,
            },
        }));
        grid.setState(nextState);
    }

    function updateRefs(rowId, refs = [], options = {}) {
        const normalizedRefs = normalizeRefsValue(refs);
        const liveRow = options.row && typeof options.row === 'object' ? options.row : null;
        if (liveRow?.values && String(liveRow.rowId || '') === String(rowId || '')) {
            const currentSerialized = serializeRefsValue(liveRow.values.refs || []);
            const nextSerialized = serializeRefsValue(normalizedRefs);
            if (currentSerialized === nextSerialized) {
                return { executed: false, refs: normalizedRefs };
            }

            liveRow.values = {
                ...(liveRow.values || {}),
                refs: normalizedRefs,
                ...reconcileRecommendationTracking({
                    ...(liveRow.values || {}),
                    refs: normalizedRefs,
                }),
            };
            liveRow.dirtyFields = Array.from(new Set([...(liveRow.dirtyFields || []), 'refs']));
            liveRow.rowState = deriveDirtyRowState(liveRow.rowState);
            return { executed: true, refs: normalizedRefs };
        }

        return updateRowValues(rowId, { refs: normalizedRefs }, { skipRefsFlush: true });
    }

    function updateAccount(rowId, accountId = '') {
        if (isReadOnly()) {
            return { executed: false };
        }
        return updateRowValues(rowId, {
            account_id: String(accountId || '').trim(),
            refs: [],
        }, { deferRowRender: true });
    }

    function deleteRowByRowId(rowId = '') {
        const grid = ensureGrid();
        const rowIndex = getRowIndexByRowId(rowId);
        if (!grid || rowIndex < 0 || isReadOnly()) {
            return { executed: false };
        }
        return grid.deleteRow(rowIndex);
    }

    function registerRefsEditor(rowId, editor) {
        if (!rowId || !editor) {
            return;
        }
        bridge.refsEditors.set(String(rowId), editor);
    }

    function unregisterRefsEditor(rowId, editor = null) {
        if (!rowId) {
            return;
        }
        const key = String(rowId);
        if (editor && bridge.refsEditors.get(key) !== editor) {
            return;
        }
        bridge.refsEditors.delete(key);
    }

    function readRefsForRow(rowId, fallbackRefs = []) {
        const editor = bridge.refsEditors.get(String(rowId || ''));
        if (!editor || typeof editor.getValue !== 'function') {
            return normalizeRefsValue(fallbackRefs);
        }

        return normalizeRefsValue(editor.getValue());
    }

    function serializeCollectedRows(rows = []) {
        return rows
            .map((row) => {
                const recommendation = recommendationOrigin(row?.values?.recommendation_snapshot);
                const line = {
                    account_id: String(row?.values?.account_id || '').trim(),
                    refs: normalizeRefsValue(row?.values?.refs || []),
                    debit: normalizeAmount(ctx, row?.values?.debit ?? ''),
                    credit: normalizeAmount(ctx, row?.values?.credit ?? ''),
                    line_summary: String(row?.values?.line_summary || '').trim(),
                    journal_rule_id: String(row?.values?.journal_rule_id || '').trim() || null,
                    is_user_modified: Number(row?.values?.is_user_modified) === 1 ? 1 : 0,
                    recommended_account_id: recommendation.account_id || row?.values?.recommended_account_id || null,
                    recommended_line_type: recommendation.line_type || row?.values?.recommended_line_type || null,
                    recommended_amount: recommendation.amount ?? row?.values?.recommended_amount ?? null,
                };

                const hasContent = Boolean(
                    line.account_id
                    || line.refs.length > 0
                    || Number(line.debit || 0) > 0
                    || Number(line.credit || 0) > 0
                    || line.line_summary
                );

                if (String(row?.rowState || '') === 'deleted' || !hasContent) {
                    return null;
                }

                return line;
            })
            .filter((row) => row !== null);
    }

    function syncFromDom() {
        const grid = ensureGrid();
        const host = getLineHost();
        if (!grid || !host) {
            return [];
        }

        return grid.getState().rows.map((row) => {
            const rowId = String(row?.rowId || '');
            const rowEl = host.querySelector(`.html-grid-body-row[data-row-id="${rowId}"]`);
            const accountId = String(
                rowEl?.querySelector('.voucher-grid-account-editor')?.value
                || row?.values?.account_id
                || ''
            ).trim();
            const refs = rowEl
                ? readRefsForRow(rowId, row?.values?.refs || [])
                : normalizeRefsValue(row?.values?.refs || []);
            const debit = rowEl
                ? normalizeAmount(ctx, rowEl.querySelector('.line-debit')?.value ?? '')
                : normalizeAmount(ctx, row?.values?.debit ?? '');
            const credit = rowEl
                ? normalizeAmount(ctx, rowEl.querySelector('.line-credit')?.value ?? '')
                : normalizeAmount(ctx, row?.values?.credit ?? '');
            const lineSummary = String(
                rowEl?.querySelector('.line-summary')?.value
                || row?.values?.line_summary
                || ''
            ).trim();

            return {
                ...row,
                values: {
                    ...(row?.values || {}),
                    account_id: accountId,
                    refs,
                    debit,
                    credit,
                    line_summary: lineSummary,
                },
            };
        });
    }

    function collectLines() {
        return serializeCollectedRows(syncFromDom());
    }

    function calculateTotals() {
        return refreshSummaryFromDom();
    }

    function reset() {
        const grid = ensureGrid();
        if (!grid) {
            return;
        }
        grid.setState({
            ...grid.getState(),
            rows: [],
            cells: {},
        });
        refreshSummaryFromState();
        void refreshSubAccountColumnVisibility();
    }

    async function loadLines(lines = []) {
        const grid = ensureGrid();
        if (!grid) {
            return;
        }

        const normalizedRows = Array.isArray(lines) && lines.length > 0
            ? lines.map((line, index) => buildLineRow(line, index, 'created'))
            : [];

        grid.setState({
            ...grid.getState(),
            rows: normalizedRows,
            cells: {},
        });

        await Promise.all(
            normalizedRows
                .map((row) => String(row.values?.account_id || '').trim())
                .filter((accountId) => accountId !== '')
                .map((accountId) => ctx.loadAccountPolicies(accountId))
        );

        refreshSummaryFromState();
        await refreshSubAccountColumnVisibility();
    }

    function addRow(line = {}) {
        const grid = ensureGrid();
        if (!grid || isReadOnly()) {
            return { executed: false };
        }
        const rows = getRows();
        const maxLineNo = rows.reduce(
            (maxValue, row) => Math.max(maxValue, Number(row?.values?.line_no || 0)),
            0,
        );
        const result = grid.addRow(buildLineRow(
            { ...line, line_no: maxLineNo + 1 },
            maxLineNo,
            'created',
        ));
        return result;
    }

    function bindReorder() {
        if (bridge.dragReady) {
            return;
        }

        const host = getLineHost();
        const grid = ensureGrid();
        if (!host || !grid) {
            return;
        }

        bridge.dragReady = true;

        const getRowElement = (target) => target?.closest?.('.html-grid-body-row[data-row-id]') || null;

        host.addEventListener('pointerdown', (event) => {
            if (isReadOnly()) {
                event.preventDefault();
                return;
            }
            const handle = event.target.closest('.voucher-grid-row-handle');
            const rowEl = getRowElement(handle);
            if (!handle || !rowEl) {
                return;
            }

            bridge.dragRowId = String(rowEl.dataset.rowId || '');
            rowEl.draggable = true;
            rowEl.dataset.dragHandleActive = '1';
        });

        host.addEventListener('pointerup', () => {
            host.querySelectorAll('.html-grid-body-row[data-drag-handle-active="1"]').forEach((rowEl) => {
                rowEl.draggable = false;
                delete rowEl.dataset.dragHandleActive;
            });
        });

        host.addEventListener('dragstart', (event) => {
            if (isReadOnly()) {
                event.preventDefault();
                return;
            }
            const rowEl = getRowElement(event.target);
            if (!rowEl || rowEl.dataset.dragHandleActive !== '1') {
                event.preventDefault();
                return;
            }

            bridge.dragRowId = String(rowEl.dataset.rowId || '');
            rowEl.classList.add('journal-line-is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', bridge.dragRowId);
        });

        host.addEventListener('dragover', (event) => {
            if (isReadOnly() || !bridge.dragRowId) {
                return;
            }
            event.preventDefault();
        });

        host.addEventListener('drop', (event) => {
            if (isReadOnly() || !bridge.dragRowId) {
                return;
            }
            event.preventDefault();
            const fromIndex = getRowIndexByRowId(bridge.dragRowId);
            const rowEl = getRowElement(event.target);
            const toIndex = rowEl ? getRowIndexByRowId(String(rowEl.dataset.rowId || '')) : -1;
            if (fromIndex >= 0 && toIndex >= 0) {
                grid.reorderRow(fromIndex, toIndex);
            }
            bridge.dragRowId = '';
        });

        host.addEventListener('dragend', (event) => {
            const rowEl = getRowElement(event.target);
            if (rowEl) {
                rowEl.classList.remove('journal-line-is-dragging');
                rowEl.draggable = false;
                delete rowEl.dataset.dragHandleActive;
            }
            bridge.dragRowId = '';
        });

        host.addEventListener('mousedown', (event) => {
            if (!isReadOnly()) {
                return;
            }
            if (!event.target.closest('.select2-container, .select2-dropdown, input, select, textarea, button')) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
        }, true);

        host.addEventListener('click', (event) => {
            if (!isReadOnly()) {
                return;
            }
            if (!event.target.closest('.select2-container, .select2-dropdown, input, select, textarea, button')) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
        }, true);
    }

    function initialize() {
        ensureGrid();
        bindReorder();
        refreshSummaryFromState();
        void refreshSubAccountColumnVisibility();
    }

    function destroy() {
        bridge.eventUnsubscribers.forEach((unsubscribe) => {
            if (typeof unsubscribe === 'function') {
                unsubscribe();
            }
        });
        if (bridge.summaryRefreshFrame) {
            const host = getLineHost();
            const view = host?.ownerDocument?.defaultView || window;
            view.cancelAnimationFrame?.(bridge.summaryRefreshFrame);
            bridge.summaryRefreshFrame = 0;
        }
        bridge.eventUnsubscribers = [];
        bridge.grid?.destroy?.();
        bridge.grid = null;
        bridge.dragRowId = '';
        bridge.dragReady = false;
    }

    Object.assign(bridge, {
        initialize,
        destroy,
        ensureGrid,
        reset,
        loadLines,
        addRow,
        collectLines,
        calculateTotals,
        setReadOnly,
        syncFromDom,
        refreshSubAccountColumnVisibility,
        updateRowValues,
        updateRefs,
        updateAccount,
        registerRefsEditor,
        unregisterRefsEditor,
        flushRefsForRow,
        deleteRowByRowId,
        getRowIndexByRowId,
        getRows,
    });

    return bridge;
}

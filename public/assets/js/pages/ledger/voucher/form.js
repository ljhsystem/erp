export function registerForm(ctx) {
    const state = ctx.state;
    const {
        AdminPicker,
        resolveDisplayText,
        API,
        form,
        modalEl,
        debitTotalEl,
        creditTotalEl,
        balanceStatusEl,
        voucherStatusEl,
        voucherNoDisplayEl,
        voucherDateEl,
        modalDeleteBtn,
        modalSaveBtn,
        modalRequestReviewBtn,
        fetchJson,
        escapeHtml,
        formatDate,
        normalizeAmountValue,
        parseAmountValue,
        formatAmountValue,
        setAmountInputValue,
        traceVoucherStep,
        setStatusFlow,
        setRejectReason,
        translateType,
        refTypeAliases,
        translateSourceType,
        importTypeLabel,
        sourceTypeFromImportType,
        importTypeMatchesSource,
        setLinkedEvidence,
        setModalTitle,
        applyVoucherState,
        setModalEditability,
        renderVoucherValidation,
        renderPickerOption,
        renderPickerSelection
    } = ctx;

function lineGridBridge() {
    return state.lineGridBridge || ctx.lineGridBridge || null;
}

function buildAccountPickerItems(rows = []) {
    state.accountPickerById.clear();
    state.accountPickerByCode.clear();

    const mappedRows = rows
        .filter((row) => (
            Number(row.is_active ?? 1) === 1
            && String(row.is_postable ?? (Number(row.is_posting ?? 1) === 1 ? 'Y' : 'N')).toUpperCase() === 'Y'
        ))
        .map((row) => {
            const accountId = String(row.id ?? row.account_id ?? row.value ?? '').trim();
            const accountCode = String(row.account_code ?? '').trim();
            const accountName = String(row.account_name ?? row.name ?? '').trim();
            const accountPath = String(row.full_path ?? '').trim();

            return {
                id: accountId,
                text: accountPath ? `[${accountPath}]` : (accountCode && accountName ? `${accountCode} - ${accountName}` : accountCode),
                account_code: accountCode,
                account_name: accountName,
                full_path: accountPath,
                sort_no: row.sort_no,
            };
        })
        .filter((item) => item.id !== '');

    mappedRows.forEach((item) => {
        state.accountPickerById.set(item.id, item);
        if (item.account_code) {
            state.accountPickerByCode.set(item.account_code, item);
        }
    });

    return [
        { id: '', text: '계정과목(선택)' },
        ...mappedRows,
    ];
}

async function ensureAccountPickerItems(force = false) {
    if (!force && Array.isArray(state.accountPickerItems)) {
        return state.accountPickerItems;
    }
    if (!force && state.accountPickerPromise) {
        return state.accountPickerPromise;
    }

    state.accountPickerPromise = (async () => {
        try {
            const json = await fetchJson(API.accountList);
            const rows = Array.isArray(json?.data) ? json.data : [];
            state.accountPickerItems = buildAccountPickerItems(rows);
        } catch (error) {
            console.error('[ledger-journal] account list load failed', error);
            state.accountPickerItems = buildAccountPickerItems([]);
        } finally {
            state.accountPickerPromise = null;
        }

        return state.accountPickerItems;
    })();

    return state.accountPickerPromise;
}

async function resolveAccountPickerItem(value) {
    const rawValue = String(value || '').trim();
    if (rawValue === '') {
        return null;
    }

    await ensureAccountPickerItems();

    return state.accountPickerById.get(rawValue)
        || state.accountPickerByCode.get(rawValue)
        || null;
}

async function resolveAccountId(value) {
    const item = await resolveAccountPickerItem(value);
    return item?.id || String(value || '').trim();
}

function getAccountLabelFromLine(line = {}) {
    return (
        resolveDisplayText({
            display_name: line.account_text || line.account_label || '',
            account_name: line.account_name || '',
            bank_account_name: line.bank_account_name || '',
            card_name: line.card_name || '',
            project_name: line.project_name || '',
            client_name: line.client_name || '',
            name: line.name || '',
            text: line.account_code || '',
            code_name: line.code_name || '',
        })
        || [line.account_code, line.account_name].filter(Boolean).join(' - ')
        || '-'
    );
}

const REF_PICKER_CONFIG = {
    CLIENT: {
        url: API.clientList,
        label(row) {
            return resolveDisplayText(row)
                || row.client_name || row.business_name || row.name || row.company_name || '-';
        },
    },
    PROJECT: {
        url: API.projectList,
        label(row) {
            return row.text
                || row.project_name
                || row.construction_name
                || row.project_code
                || '-';
        },
    },
    EMPLOYEE: {
        url: API.employeeList,
        label(row) {
            return resolveDisplayText(row)
                || row.employee_name || row.name || row.user_name || '-';
        },
    },
    ACCOUNT: {
        url: API.bankAccountList,
        label(row) {
            return resolveDisplayText(row)
                || row.account_name || row.bank_name || row.account_no || row.account_number || '-';
        },
    },
    BANK_ACCOUNT: {
        url: API.bankAccountList,
        label(row) {
            return resolveDisplayText(row)
                || row.account_name || row.bank_name || row.account_no || row.account_number || '-';
        },
    },
    CARD: {
        url: API.cardList,
        label(row) {
            return resolveDisplayText(row)
                || row.card_name || row.card_no || row.card_number || row.client_name || '-';
        },
    },
    VOUCHER: {
        url: API.list,
        label(row) {
            return row.voucher_no || row.summary || '-';
        },
    },
    PAYMENT: {
        url: API.list,
        label(row) {
            return row.voucher_no || row.summary || '-';
        },
    },
};

const CLEAN_REF_PICKER_CONFIG = {
    CLIENT: {
        url: API.clientList,
        label(row) {
            return resolveDisplayText(row)
                || row.client_name || row.business_name || row.name || row.company_name || '-';
        },
    },
    PROJECT: {
        url: API.projectList,
        label(row) {
            return row.text
                || row.project_name
                || row.construction_name
                || row.project_code
                || '-';
        },
    },
    EMPLOYEE: {
        url: API.employeeList,
        label(row) {
            return resolveDisplayText(row)
                || row.employee_name || row.name || row.user_name || '-';
        },
    },
    ACCOUNT: {
        url: API.bankAccountList,
        label(row) {
            return resolveDisplayText(row)
                || row.account_name || row.bank_name || row.account_no || row.account_number || '-';
        },
    },
    BANK_ACCOUNT: {
        url: API.bankAccountList,
        label(row) {
            return resolveDisplayText(row)
                || row.account_name || row.bank_name || row.account_no || row.account_number || '-';
        },
    },
    CARD: {
        url: API.cardList,
        label(row) {
            return resolveDisplayText(row)
                || row.card_name || row.card_no || row.card_number || row.client_name || '-';
        },
    },
    VOUCHER: {
        url: API.list,
        label(row) {
            return row.voucher_no || row.summary || '-';
        },
    },
    PAYMENT: {
        url: API.list,
        label(row) {
            return row.voucher_no || row.summary || '-';
        },
    },
};

function normalizeRows(payload) {
    return Array.isArray(payload?.data) ? payload.data : [];
}

function normalizePickerStoredValue(value) {
    return String(value ?? '').trim();
}

function sortPickerOptionRows(items = []) {
    return [...(Array.isArray(items) ? items : [])].sort((left, right) => {
        const leftSort = Number(left?.sort_no ?? 0);
        const rightSort = Number(right?.sort_no ?? 0);
        if (leftSort !== rightSort) {
            return leftSort - rightSort;
        }

        return String(left?.text ?? '').localeCompare(String(right?.text ?? ''), 'ko');
    });
}

function populateRefPickerOptions(selectEl, items = [], selectedValue = '') {
    if (!selectEl) {
        return;
    }

    const normalizedSelectedValue = String(selectedValue ?? '').trim();
    let emptyOption = Array.from(selectEl.options || [])
        .find((option) => option.value === '');

    Array.from(selectEl.options || []).forEach((option) => {
        if (option.value !== '') {
            option.remove();
        }
    });

    if (!emptyOption) {
        emptyOption = new Option('', '', true, normalizedSelectedValue === '');
        selectEl.insertBefore(emptyOption, selectEl.firstChild);
    }
    emptyOption.textContent = '선택(없음)';
    if (emptyOption !== selectEl.firstElementChild) {
        selectEl.insertBefore(emptyOption, selectEl.firstChild);
    }

    sortPickerOptionRows(items).forEach((item) => {
        const id = String(item?.id ?? '').trim();
        if (id === '') {
            return;
        }

        const option = new Option(
            String(item?.text ?? id),
            id,
            false,
            normalizedSelectedValue === id
        );
        selectEl.appendChild(option);
    });

    selectEl.value = normalizedSelectedValue;
}

async function ensurePickerOptions(refType, force = false) {
    const requestedType = String(refType || '').toUpperCase();
    const type = requestedType === 'ACCOUNT'
        ? 'BANK_ACCOUNT'
        : (requestedType === 'PAYMENT' ? 'VOUCHER' : requestedType);
    const config = CLEAN_REF_PICKER_CONFIG[type];
    if (!config) {
        return [{ id: '', text: SAFE_PICKER_PLACEHOLDER }];
    }
    if (!force && state.pickerOptionCache[type]) {
        return state.pickerOptionCache[type];
    }
    if (!force && state.pickerOptionPromises[type]) {
        return state.pickerOptionPromises[type];
    }

    state.pickerOptionPromises[type] = (async () => {
        try {
            const json = await fetchJson(config.url);
            const rows = normalizeRows(json);
            state.pickerOptionCache[type] = [
                { id: '', text: '' },
                ...rows.map((row) => ({
                    id: String(row.id ?? row.value ?? '').trim(),
                    text: String(config.label(row)).trim(),
                    sort_no: row.sort_no,
                })).filter((item) => item.id !== ''),
            ];
        } catch (error) {
            console.error(`[ledger-journal] ${type} picker load failed`, error);
            state.pickerOptionCache[type] = [{ id: '', text: '' }];
        } finally {
            delete state.pickerOptionPromises[type];
        }

        return state.pickerOptionCache[type];
    })();

    return state.pickerOptionPromises[type];
}

async function initRefPicker(selectEl, refType, selectedValue = '', options = {}) {
    if (!selectEl || !window.jQuery) {
        return;
    }

    const isActive = typeof options.isActive === 'function' ? options.isActive : () => true;
    if (!isActive()) {
        return;
    }

    const type = String(refType || '').toUpperCase();
    const canQuickAddClient = type === 'CLIENT' && typeof ctx.openClientQuickCreate === 'function';
    const items = await ensurePickerOptions(type);
    if (!isActive()) {
        return;
    }

    populateRefPickerOptions(selectEl, items, selectedValue);
    if (!isActive()) {
        return;
    }

    AdminPicker.select2(selectEl, {
        dropdownParent: window.jQuery(modalEl),
        width: '100%',
        quickAddEnabled: canQuickAddClient,
    });
    if (!isActive()) {
        return;
    }

    if (selectEl.__voucherRefQuickAddHandler) {
        selectEl.removeEventListener('picker:add', selectEl.__voucherRefQuickAddHandler);
        delete selectEl.__voucherRefQuickAddHandler;
    }

    if (canQuickAddClient) {
        selectEl.__voucherRefQuickAddHandler = () => {
            ctx.openClientQuickCreate({
                select: selectEl,
                title: '거래처 빠른등록',
                getOptionText(values) {
                    return values.client_name || values.company_name || '';
                },
                onSuccess() {
                    delete state.pickerOptionCache.CLIENT;
                },
            });
        };
        selectEl.addEventListener('picker:add', selectEl.__voucherRefQuickAddHandler);
    }
}

async function loadAccountPolicies(accountId) {
    const id = await resolveAccountId(accountId);
    if (!id) {
        return [];
    }

    if (state.accountPolicyCache[id]) {
        return state.accountPolicyCache[id];
    }
    if (state.accountPolicyPromises[id]) {
        return state.accountPolicyPromises[id];
    }

    state.accountPolicyPromises[id] = (async () => {
        try {
            const json = await fetchJson(`${API.subAccountList}?account_id=${encodeURIComponent(id)}`);
            const rows = Array.isArray(json)
                ? json
                : normalizeRows(json);

            state.accountPolicyCache[id] = rows
                .map((row) => {
                    const rawRefTarget = String(row.ref_target || '').trim().toUpperCase();
                    const rawRefType = String(row.ref_target || '').trim().toUpperCase();
                    const subCode = String(row.sub_code || row.code || '').trim().toUpperCase();
                    const refTarget = rawRefTarget || (rawRefType === 'REF_TARGET' ? subCode : (rawRefType || subCode));

                    return {
                        ref_target: refTarget,
                        is_required: Number(row.is_required || 0),
                    };
                })
                .filter((row) => row.ref_target !== '');
        } catch (error) {
            console.error('[ledger-journal] account policy load failed', error);
            state.accountPolicyCache[id] = [];
        } finally {
            delete state.accountPolicyPromises[id];
        }

        return state.accountPolicyCache[id];
    })();

    return state.accountPolicyPromises[id];
}

function updateLineSubAccountColumnVisibility() {
    lineGridBridge()?.refreshSubAccountColumnVisibility?.();
}

async function legacyReloadAllAccountPickers({ selectedValue = '', selectedText = '', sourceEl = null } = {}) {
    const items = await ensureAccountPickerItems(true);

    Array.from(lineBody.querySelectorAll('.line-account-code-picker')).forEach((selectEl) => {
        const currentValue = selectEl === sourceEl
            ? selectedValue
            : String(selectEl.value || '').trim();

        AdminPicker.reloadSelect2(selectEl, items, 'id', 'text', currentValue || '');

    });
}

async function legacyInitLineAccountPicker(selectEl, selectedValue = '') {
    if (!selectEl || !window.jQuery) {
        return;
    }

    AdminPicker.select2(selectEl, {
        dropdownParent: window.jQuery(modalEl),
        width: '100%',
        templateResult: renderPickerOption,
        templateSelection: renderPickerSelection,
    });

    const items = await ensureAccountPickerItems();
    const resolvedValue = await resolveAccountId(selectedValue);
    AdminPicker.reloadSelect2(selectEl, items, 'id', 'text', resolvedValue || '');

    window.jQuery(selectEl)
        .off('change.journalLineAccount select2:select.journalLineAccount')
        .on('change.journalLineAccount select2:select.journalLineAccount', () => {
            const row = selectEl.closest('tr');
            if (row) {
                void renderLineSubAccountControls(row);
            }
        });
}

function legacyEmptyLineRow() {
    return '<tr class="voucher-line-empty"><td colspan="7" class="text-center text-muted py-4">분개 라인을 입력해 주세요.</td></tr>';
}

function legacySyncLineNumbers() {
    Array.from(lineBody.querySelectorAll('tr'))
        .filter((row) => !row.classList.contains('voucher-line-empty'))
        .forEach((row, index) => {
            const numberCell = row.querySelector('.line-no');
            if (numberCell) {
                let displayNo = numberCell.querySelector('.journal-line-display-no');
                if (!displayNo) {
                    numberCell.innerHTML = `
                        <span class="journal-line-order-cell">
                            <button type="button"
                                    class="journal-line-drag-handle"
                                    aria-label="??뽮퐣 ??猷?
                                    title="??뽮퐣 ??猷?>
                                <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                            </button>
                            <span class="journal-line-display-no"></span>
                        </span>
                    `;
                    displayNo = numberCell.querySelector('.journal-line-display-no');
                }
                displayNo.textContent = String(index + 1);
            }
        });
}

function legacyInitJournalLineReorder() {
    if (!lineBody || lineBody.dataset.reorderReady === '1') {
        return;
    }

    lineBody.dataset.reorderReady = '1';
    let draggingRow = null;

    const getLineRow = (target) => target?.closest?.('tr:not(.voucher-line-empty)') || null;
    const getAfterElement = (container, y) => {
        const rows = Array.from(container.querySelectorAll('tr:not(.voucher-line-empty)'))
            .filter((row) => row !== draggingRow);

        return rows.reduce((closest, row) => {
            const box = row.getBoundingClientRect();
            const offset = y - box.top - (box.height / 2);
            if (offset < 0 && offset > closest.offset) {
                return { offset, element: row };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    };

    lineBody.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('.journal-line-drag-handle');
        if (!handle) {
            return;
        }

        const row = getLineRow(handle);
        if (row) {
            row.draggable = true;
            row.dataset.dragHandleActive = '1';
        }
    });

    lineBody.addEventListener('pointerup', () => {
        if (draggingRow) {
            return;
        }
        lineBody.querySelectorAll('tr[data-drag-handle-active="1"]').forEach((row) => {
            row.draggable = false;
            delete row.dataset.dragHandleActive;
        });
    });

    lineBody.addEventListener('dragstart', (event) => {
        const row = getLineRow(event.target);
        if (!row || row.dataset.dragHandleActive !== '1') {
            event.preventDefault();
            return;
        }

        draggingRow = row;
        row.classList.add('journal-line-is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', '');
    });

    lineBody.addEventListener('dragover', (event) => {
        if (!draggingRow) {
            return;
        }

        event.preventDefault();
        const afterElement = getAfterElement(lineBody, event.clientY);
        if (afterElement) {
            lineBody.insertBefore(draggingRow, afterElement);
        } else {
            lineBody.appendChild(draggingRow);
        }
    });

    const finishDrag = () => {
        if (!draggingRow) {
            return;
        }

        draggingRow.classList.remove('journal-line-is-dragging');
        draggingRow.draggable = false;
        delete draggingRow.dataset.dragHandleActive;
        draggingRow = null;
        syncLineNumbers();
        calculateTotals();
    };

    lineBody.addEventListener('drop', (event) => {
        if (draggingRow) {
            event.preventDefault();
        }
        finishDrag();
    });
    lineBody.addEventListener('dragend', finishDrag);
}

function legacyCalculateTotals() {
    const rows = Array.from(lineBody.querySelectorAll('tr'))
        .filter((row) => !row.classList.contains('voucher-line-empty'));

    const debit = rows.reduce((sum, row) => {
        const value = parseAmountValue(row.querySelector('.line-debit')?.value || '0');
        return sum + (Number.isFinite(value) ? value : 0);
    }, 0);

    const credit = rows.reduce((sum, row) => {
        const value = parseAmountValue(row.querySelector('.line-credit')?.value || '0');
        return sum + (Number.isFinite(value) ? value : 0);
    }, 0);

    debitTotalEl.value = formatAmountValue(debit) || '0';
    creditTotalEl.value = formatAmountValue(credit) || '0';

    if (rows.length === 0) {
        setValidationBadge('error', '분개 라인을 먼저 입력해 주세요.');
        return;
    }

    if (debit === credit) {
        setValidationBadge('ok', '차변/대변 합계가 일치합니다.');
        return;
    }

    setValidationBadge('error', '차변/대변 합계가 일치하지 않습니다.');
}

function setValidationBadge(type = 'error', message = '') {
    if (!balanceStatusEl) {
        return;
    }

    const isOk = type === 'ok';
    balanceStatusEl.className = `voucher-validation-badge ${isOk ? 'voucher-validation-ok' : 'voucher-validation-error'}`;
    balanceStatusEl.textContent = message;
}

async function legacyRenderLineSubAccountControls(row, line = {}) {
    const container = row.querySelector('.journal-line-subaccounts');
    const selectedValue = row.querySelector('.line-account-code-picker')?.value?.trim()
        || line.account_id
        || line.account_code
        || '';
    const accountId = await resolveAccountId(selectedValue);
    if (!container) {
        return;
    }

    if (!accountId) {
        row.dataset.hasSubAccounts = '0';
        container.className = 'journal-line-subaccounts';
        container.textContent = '';
        updateLineSubAccountColumnVisibility();
        container.innerHTML = '<span class="journal-subaccount-empty">연결할 보조계정이 없습니다.</span>';
        return;
    }

    row.dataset.hasSubAccounts = '0';
    container.className = 'journal-line-subaccounts';
    container.innerHTML = '';

    const policies = await loadAccountPolicies(accountId);
    if (!policies.length) {
        container.className = 'journal-line-subaccounts';
        updateLineSubAccountColumnVisibility();
        container.innerHTML = '<span class="journal-subaccount-empty">연결할 보조계정이 없습니다.</span>';
        return;
    }

    row.dataset.hasSubAccounts = '1';
    container.className = 'journal-line-subaccounts journal-line-subaccount-grid';
    container.innerHTML = policies.map((policy, index) => `
        <label class="journal-line-subaccount-field">
            <span>${escapeHtml(translateType(policy.ref_target))}${policy.is_required ? ' <b class="journal-line-subaccount-required">*</b>' : ''}</span>
            <select class="form-select form-select-sm line-ref-picker"
                    data-ref-target="${escapeHtml(policy.ref_target)}"
                    data-required="${policy.is_required ? '1' : '0'}"
                    data-policy-index="${index}">
                <option value=""></option>
            </select>
        </label>
    `).join('');

    const selectedRefs = Array.isArray(line.refs) ? line.refs : [];
    const selectedMap = new Map();
    selectedRefs.forEach((ref) => {
        const refType = String(ref.ref_target || ref.line_ref_target || '').toUpperCase();
        const refId = String(ref.ref_id || ref.line_ref_id || '').trim();
        if (refType === '' || refId === '') {
            return;
        }
        refTypeAliases(refType).forEach((alias) => {
            if (alias && !selectedMap.has(alias)) {
                selectedMap.set(alias, refId);
            }
        });
    });
    for (const selectEl of container.querySelectorAll('.line-ref-picker')) {
        const refType = selectEl.dataset.refTarget || '';
        const selectedValue = refTypeAliases(refType)
            .map((alias) => selectedMap.get(alias))
            .find((value) => String(value || '').trim() !== '')
            || '';
        await initRefPicker(selectEl, refType, selectedValue);
    }
    updateLineSubAccountColumnVisibility();
}

async function legacyAddLineRow(line = {}) {
    lineBody.querySelector('.voucher-line-empty')?.remove();

    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="text-center line-no">
            <span class="journal-line-order-cell">
                <button type="button"
                        class="journal-line-drag-handle"
                        aria-label="??뽮퐣 ??猷?
                        title="??뽮퐣 ??猷?>
                    <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                </button>
                <span class="journal-line-display-no"></span>
            </span>
        </td>
        <td>
            <select class="form-select form-select-sm line-account-code-picker">
                <option value="">계정과목(선택)</option>
            </select>
        </td>
        <td class="line-ref-cell">
            <div class="journal-line-subaccounts"></div>
        </td>
        <td>
            <input type="text"
                   inputmode="numeric"
                   class="form-control form-control-sm line-debit input-amount"
                   value="${escapeHtml(line.debit || '')}"
                   placeholder="0">
        </td>
        <td>
            <input type="text"
                   inputmode="numeric"
                   class="form-control form-control-sm line-credit input-amount"
                   value="${escapeHtml(line.credit || '')}"
                   placeholder="0">
        </td>
        <td>
            <input type="text"
                   class="form-control form-control-sm line-summary"
                   value="${escapeHtml(line.line_summary || '')}"
                   placeholder="분개 적요">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-line">삭제</button>
        </td>
    `;

    lineBody.appendChild(row);
    setAmountInputValue(row.querySelector('.line-debit'));
    setAmountInputValue(row.querySelector('.line-credit'));
    syncLineNumbers();
    calculateTotals();

    const accountSelect = row.querySelector('.line-account-code-picker');
    const selectedAccountValue = line.account_id || line.account_code || '';
    await initLineAccountPicker(accountSelect, selectedAccountValue);

    await renderLineSubAccountControls(row, line);
}

function legacyResetModal() {
    form.reset();
    document.getElementById('journal_id').value = '';
    setJournalModalLoading(false);
    if (voucherNoDisplayEl) {
        voucherNoDisplayEl.value = '자동번호';
    }
    voucherDateEl.value = formatDate(new Date());
    voucherStatusEl.value = 'DRAFT';
    setStatusFlow('DRAFT');
    setRejectReason('DRAFT', '');
    state.releasedLinkedEvidenceKeys.clear();
    setLinkedEvidence({});
    lineBody.innerHTML = emptyLineRow();
    setModalTitle('create');
    updateLineSubAccountColumnVisibility();
    setModalEditability('DRAFT');
    calculateTotals();
}

function setJournalModalLoading(isLoading = false) {
    const loading = Boolean(isLoading);
    modalEl.classList.toggle('is-loading-detail', loading);
    modalEl.setAttribute('aria-busy', loading ? 'true' : 'false');
    if (!loading) {
        return;
    }
    [modalSaveBtn, modalRequestReviewBtn, modalDeleteBtn].forEach((button) => {
        if (button) {
            button.disabled = true;
        }
    });
}

function ensureSourceTypeOption() {}

async function loadImportTypeRows() {
    return [];
}

function rebuildImportTypeOptions() {}

async function refreshImportTypeOptions() {
    return [];
}

function setVoucherImportType() {}

function setVoucherSource() {}

function legacyCollectLines() {
    return Array.from(lineBody.querySelectorAll('tr'))
        .filter((row) => !row.classList.contains('voucher-line-empty'))
        .map((row) => {
            const accountValue = row.querySelector('.line-account-code-picker')?.value?.trim() ?? '';
            const accountItem = state.accountPickerById.get(accountValue) || state.accountPickerByCode.get(accountValue);
            return {
                account_id: accountItem?.id || accountValue,
                refs: getLineRefs(row),
                debit: normalizeAmountValue(row.querySelector('.line-debit')?.value ?? '') || '0',
                credit: normalizeAmountValue(row.querySelector('.line-credit')?.value ?? '') || '0',
                line_summary: row.querySelector('.line-summary')?.value?.trim() ?? '',
            };
        })
        .filter((line) => line.account_id || line.refs.length > 0 || Number(line.debit) > 0 || Number(line.credit) > 0 || line.line_summary);
}

function legacyGetLineRefs(row) {
    return Array.from(row.querySelectorAll('.line-ref-picker'))
        .map((selectEl) => ({
            ref_target: String(selectEl.dataset.refTarget || '').toUpperCase(),
            ref_id: normalizePickerStoredValue(selectEl.value),
            is_primary: selectEl.dataset.policyIndex === '0' ? 1 : 0,
        }))
        .filter((item) => item.ref_target !== '' && item.ref_id !== '');
}

function legacyValidateBeforeSave({ requireJournalReady = false } = {}) {
    const lines = collectLines();

    if (requireJournalReady && lines.length === 0) {
        notify('warning', '분개 라인을 1개 이상 입력해 주세요.');
        return false;
    }

    let debitTotal = 0;
    let creditTotal = 0;

    for (let index = 0; index < lines.length; index += 1) {
        const line = lines[index];
        const debit = Number(line.debit || '0');
        const credit = Number(line.credit || '0');

        if (!line.account_id) {
            notify('warning', `${index + 1}번째 분개라인의 계정과목을 선택해 주세요.`);
            return false;
        }

        const row = Array.from(lineBody.querySelectorAll('tr'))
            .filter((tr) => !tr.classList.contains('voucher-line-empty'))[index];
        const refPickers = Array.from(row?.querySelectorAll('.line-ref-picker') || []);
        const requiredPickers = refPickers.filter((selectEl) => selectEl.dataset.required === '1');
        const selectedPickers = refPickers.filter((selectEl) => normalizePickerStoredValue(selectEl.value) !== '');
        if (false && refPickers.length > 0 && selectedPickers.length === 0) {
            notify('warning', `${index + 1}번째 분개라인의 보조계정을 선택해 주세요.`);
            refPickers[0]?.focus();
            return false;
        }
        if (false && requiredPickers.length > 1) {
            notify('warning', `${index + 1}번째 분개라인에 필수 보조계정이 여러 개 지정되어 있습니다.`);
            requiredPickers[0]?.focus();
            return false;
        }
        for (const requiredPicker of requiredPickers) {
            if (normalizePickerStoredValue(requiredPicker.value) === '') {
                notify('warning', `${index + 1}번째 분개라인의 필수 보조계정을 선택해 주세요.`);
                requiredPicker.focus();
                return false;
            }
        }

        if (false && selectedPickers.length > 1) {
            notify('warning', `${index + 1}번째 분개라인에는 보조계정을 하나만 선택할 수 있습니다.`);
            selectedPickers[1]?.focus();
            return false;
        }

        if (debit <= 0 && credit <= 0) {
            notify('warning', `${index + 1}번째 분개라인의 차변 또는 대변 금액을 입력해 주세요.`);
            return false;
        }

        if (debit > 0 && credit > 0) {
            notify('warning', `${index + 1}번째 분개라인에는 차변과 대변을 동시에 입력할 수 없습니다.`);
            return false;
        }

        debitTotal += Number.isFinite(debit) ? debit : 0;
        creditTotal += Number.isFinite(credit) ? credit : 0;
    }

    if (requireJournalReady && debitTotal !== creditTotal) {
        notify('warning', '차변 합계와 대변 합계가 일치해야 합니다.');
        return false;
    }

    return true;
}

function emptyLineRow() {
    return '';
}

function syncLineNumbers() {
    lineGridBridge()?.calculateTotals?.();
}

function initJournalLineReorder() {
    lineGridBridge()?.initialize?.();
}

function buildTotalsSnapshot(lines = []) {
    const normalizedLines = Array.isArray(lines) ? lines : [];
    const debit = normalizedLines.reduce((sum, line) => sum + (Number(line?.debit || '0') || 0), 0);
    const credit = normalizedLines.reduce((sum, line) => sum + (Number(line?.credit || '0') || 0), 0);
    const rowCount = normalizedLines.length;
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
    };
}

function renderTotals(summary = {}) {
    if (debitTotalEl) {
        debitTotalEl.value = formatAmountValue(summary.debit || 0) || '0';
    }
    if (creditTotalEl) {
        creditTotalEl.value = formatAmountValue(summary.credit || 0) || '0';
    }

    traceVoucherStep?.('renderTotals', {
        input: summary,
        output: {
            debit: debitTotalEl?.value || '',
            credit: creditTotalEl?.value || '',
        },
    });
}

function renderValidation(summary = {}) {
    const validation = renderVoucherValidation(summary, voucherStatusEl?.value || 'DRAFT');

    traceVoucherStep?.('renderValidation', {
        input: summary,
        output: validation,
    });
}

function updateTotals(summary = {}) {
    traceVoucherStep?.('updateTotals', {
        input: summary,
    });
    renderTotals(summary);
    renderValidation(summary);
    ctx.syncRecommendationVisibility?.();
    return summary;
}

function calculateTotals() {
    const summary = lineGridBridge()?.calculateTotals?.() || buildTotalsSnapshot(collectLines());
    traceVoucherStep?.('calculateTotals', {
        input: {
            lines: collectLines(),
        },
        output: summary,
    });
    updateTotals(summary);
    return summary;
}

async function addLineRow(line = {}) {
    lineGridBridge()?.addRow?.(line);
    calculateTotals();
}

function resetModal() {
    form.reset();
    document.getElementById('journal_id').value = '';
    setJournalModalLoading(false);
    if (voucherNoDisplayEl) {
        voucherNoDisplayEl.value = '자동번호';
    }
    voucherDateEl.value = formatDate(new Date());
    voucherStatusEl.value = 'DRAFT';
    setStatusFlow('DRAFT');
    setRejectReason('DRAFT', '');
    setLinkedEvidence({});
    lineGridBridge()?.reset?.();
    setModalTitle('create');
    updateLineSubAccountColumnVisibility();
    const summary = calculateTotals();
    applyVoucherState('DRAFT', { status: 'DRAFT' }, { summary });
}

function collectLines() {
    return lineGridBridge()?.collectLines?.() || [];
}

function getLineRefs() {
    return [];
}

function validateVoucher(lines = [], { requireJournalReady = false } = {}) {
    if (requireJournalReady && lines.length === 0) {
        return {
            valid: false,
            message: '\uBD84\uAC1C \uB77C\uC778\uC744 1\uAC1C \uC774\uC0C1 \uC785\uB825\uD574 \uC8FC\uC138\uC694.',
        };
    }

    let debitTotal = 0;
    let creditTotal = 0;

    for (let index = 0; index < lines.length; index += 1) {
        const line = lines[index];
        const debit = Number(line.debit || '0');
        const credit = Number(line.credit || '0');

        if (!line.account_id) {
            return {
                valid: false,
                message: `${index + 1}\uBC88\uC9F8 \uB77C\uC778\uC758 \uACC4\uC815\uACFC\uBAA9\uC744 \uC120\uD0DD\uD574 \uC8FC\uC138\uC694.`,
            };
        }

        const policies = state.accountPolicyCache[String(line.account_id || '').trim()] || [];
        const requiredPolicies = policies.filter((policy) => Number(policy.is_required ?? 0) === 1);
        for (const policy of requiredPolicies) {
            const hasSelectedRef = (Array.isArray(line.refs) ? line.refs : [])
                .some((ref) => String(ref.ref_target || '').toUpperCase() === String(policy.ref_target || '').toUpperCase() && String(ref.ref_id || '').trim() !== '');
            if (!hasSelectedRef) {
                return {
                    valid: false,
                    message: `${index + 1}\uBC88\uC9F8 \uB77C\uC778\uC758 \uD544\uC218 \uBCF4\uC870\uACC4\uC815\uC744 \uC120\uD0DD\uD574 \uC8FC\uC138\uC694.`,
                };
            }
        }

        if (debit <= 0 && credit <= 0) {
            return {
                valid: false,
                message: `${index + 1}\uBC88\uC9F8 \uB77C\uC778\uC758 \uCC28\uBCC0 \uB610\uB294 \uB300\uBCC0 \uAE08\uC561\uC744 \uC785\uB825\uD574 \uC8FC\uC138\uC694.`,
            };
        }

        if (debit > 0 && credit > 0) {
            return {
                valid: false,
                message: `${index + 1}\uBC88\uC9F8 \uB77C\uC778\uC740 \uCC28\uBCC0\uACFC \uB300\uBCC0 \uC911 \uD558\uB098\uB9CC \uC785\uB825\uD560 \uC218 \uC788\uC2B5\uB2C8\uB2E4.`,
            };
        }

        debitTotal += Number.isFinite(debit) ? debit : 0;
        creditTotal += Number.isFinite(credit) ? credit : 0;
    }

    if (requireJournalReady && debitTotal !== creditTotal) {
        return {
            valid: false,
            message: '\uCC28\uBCC0 \uD569\uACC4\uC640 \uB300\uBCC0 \uD569\uACC4\uAC00 \uC77C\uCE58\uD574\uC57C \uD569\uB2C8\uB2E4.',
        };
    }

    return {
        valid: true,
        message: '',
        totals: {
            debit: debitTotal,
            credit: creditTotal,
        },
    };
}

function validateBeforeSave({ requireJournalReady = false } = {}) {
    const lines = collectLines();
    const result = validateVoucher(lines, { requireJournalReady });

    traceVoucherStep?.('validateVoucher', {
        input: {
            requireJournalReady,
            lines,
        },
        output: result,
    });

    if (!result.valid) {
        notify('warning', result.message || '\uC800\uC7A5 \uC804 \uAC80\uC99D\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
        return false;
    }

    return true;
}

function getVoucherSortNo(row = {}) {
    const numericSortNo = Number(String(row.sort_no ?? '').replace(/,/g, ''));
    return Number.isFinite(numericSortNo) ? numericSortNo : 0;
}

    Object.assign(ctx, {
        buildAccountPickerItems,
        ensureAccountPickerItems,
        resolveAccountPickerItem,
        resolveAccountId,
        getAccountLabelFromLine,
        normalizeRows,
        ensurePickerOptions,
        initRefPicker,
        loadAccountPolicies,
        updateLineSubAccountColumnVisibility,
        emptyLineRow,
        syncLineNumbers,
        initJournalLineReorder,
        calculateTotals,
        buildTotalsSnapshot,
        renderTotals,
        renderValidation,
        updateTotals,
        setValidationBadge,
        addLineRow,
        resetModal,
        setJournalModalLoading,
        ensureSourceTypeOption,
        loadImportTypeRows,
        rebuildImportTypeOptions,
        refreshImportTypeOptions,
        setVoucherImportType,
        setVoucherSource,
        collectLines,
        getLineRefs,
        validateVoucher,
        validateBeforeSave,
        getVoucherSortNo
    });

    return ctx;
}

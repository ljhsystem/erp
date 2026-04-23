import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { bindNumberInput, formatNumber, parseNumber } from '/public/assets/js/common/format.js';
import { initQuickCreateButtons } from '/public/assets/js/common/quick-create.js';

document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-transaction-page]');
    if (!page) {
        return;
    }

    if (page.dataset.transactionPage === 'create') {
        initTransactionCreate(page);
    }

    if (page.dataset.transactionPage === 'list') {
        initTransactionList(page);
    }
});

function initTransactionCreate(page) {
    const form = page.querySelector('[data-role="transaction-form"]');
    const itemsWrap = page.querySelector('[data-role="items"]');
    const addButton = page.querySelector('[data-role="add-item"]');
    const message = page.querySelector('[data-role="message"]');
    const dateInput = page.querySelector('[data-role="transaction-date"]');
    const clientPicker = page.querySelector('[data-role="client-picker"]');
    const projectPicker = page.querySelector('[data-role="project-picker"]');
    const quickCreateClientButton = page.querySelector('[data-role="quick-create-client"]');
    const quickCreateProjectButton = page.querySelector('[data-role="quick-create-project"]');
    const summarySupply = page.querySelector('[data-role="summary-supply"]');
    const summaryTotal = page.querySelector('[data-role="summary-total"]');
    const headerTaxType = page.querySelector('[data-role="header-tax-type"]');
    const useItemTaxCheckbox = page.querySelector('[data-role="use-item-tax"]');
    const itemTaxHeader = page.querySelector('[data-role="item-tax-header"]');
    const importCheckbox = page.querySelector('[data-role="is-import"]');
    const importFields = page.querySelector('[data-role="import-fields"]');
    const headerVatInput = page.querySelector('[data-role="header-vat"]');
    const autoVatButton = page.querySelector('[data-role="auto-vat"]');
    const saveUrl = page.dataset.saveUrl || '/api/transaction/save';
    const listUrl = page.dataset.listUrl || '/site/transaction';
    const params = new URLSearchParams(window.location.search);
    const editId = params.get('id');

    let datePicker = null;

    function initDatePicker() {
        if (datePicker) {
            return datePicker;
        }

        const container = document.getElementById('transaction-date-picker');
        if (!container) {
            return null;
        }

        if (container.parentElement !== document.body) {
            document.body.appendChild(container);
        }

        datePicker = AdminPicker.create({
            type: 'date',
            container,
        });

        datePicker.subscribe((_, date) => {
            if (!date || !dateInput) {
                return;
            }

            dateInput.value = formatDate(date);
            datePicker.close();
        });

        return datePicker;
    }

    function bindDateInput() {
        if (!dateInput) {
            return;
        }

        dateInput.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const picker = initDatePicker();
            if (!picker) {
                return;
            }

            picker.__target = dateInput;

            if (typeof picker.clearDate === 'function') {
                picker.clearDate();
            }

            if (dateInput.value) {
                const current = new Date(dateInput.value);
                if (!Number.isNaN(current.getTime()) && typeof picker.setDate === 'function') {
                    picker.setDate(current);
                }
            }

            picker.open({ anchor: dateInput });
        });
    }

    function initRemotePicker(selectEl, options) {
        if (!selectEl || !window.jQuery) {
            return;
        }

        const $select = window.jQuery(selectEl);
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        AdminPicker.select2Ajax(selectEl, {
            url: options.url,
            placeholder: options.placeholder,
            minimumInputLength: 0,
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                };
            },
            processResults(json) {
                const rows = json?.results ?? json?.data ?? [];

                return {
                    results: rows.map((row) => ({
                        id: String(row.id ?? ''),
                        text: options.text(row),
                        raw: row,
                    })).filter((item) => item.id !== ''),
                };
            },
        });
    }

    function setPickerValue(selectEl, value, text) {
        if (!selectEl || !window.jQuery) {
            return;
        }

        const normalizedValue = String(value ?? '').trim();
        const $select = window.jQuery(selectEl);

        if (normalizedValue === '') {
            $select.val(null).trigger('change');
            return;
        }

        $select.find(`option[value="${normalizedValue}"]`).remove();
        $select.append(new Option(text || normalizedValue, normalizedValue, true, true));
        $select.val(normalizedValue).trigger('change');
    }

    function createTaxOptions(index, selectedValue = 'TAXABLE') {
        return `
            <div class="site-transaction-tax-group">
                <label class="site-transaction-tax-option">
                    <input type="radio" data-field="tax_type" name="items[${index}][tax_type]" value="TAXABLE"${selectedValue === 'TAXABLE' ? ' checked' : ''}>
                    <span>과세</span>
                </label>
                <label class="site-transaction-tax-option">
                    <input type="radio" data-field="tax_type" name="items[${index}][tax_type]" value="EXEMPT"${selectedValue === 'EXEMPT' ? ' checked' : ''}>
                    <span>면세</span>
                </label>
            </div>
        `;
    }

    function appendRow(item = {}) {
        const index = itemsWrap.querySelectorAll('.site-transaction-item-row').length;
        const row = document.createElement('tr');
        row.className = 'site-transaction-item-row';
        row.innerHTML = `
            <td class="site-transaction-col-name">
                <input type="text" data-field="item_name" name="items[${index}][item_name]" value="${escapeHtml(item.item_name ?? '')}" placeholder="품명을 입력하세요">
            </td>
            <td class="site-transaction-col-spec">
                <input type="text" data-field="specification" name="items[${index}][specification]" value="${escapeHtml(item.specification ?? '')}" placeholder="규격">
            </td>
            <td class="site-transaction-col-unit">
                <input type="text" data-field="unit_name" name="items[${index}][unit_name]" value="${escapeHtml(item.unit_name ?? '')}" placeholder="단위">
            </td>
            <td class="site-transaction-col-tax" data-role="item-tax-cell" hidden>
                ${createTaxOptions(index, item.tax_type ?? headerTaxType.value ?? 'TAXABLE')}
            </td>
            <td class="site-transaction-col-qty">
                <input type="text" class="site-transaction-number number-input" data-field="quantity" name="items[${index}][quantity]" value="${escapeHtml(item.quantity ?? '')}" placeholder="0">
            </td>
            <td class="site-transaction-col-price">
                <input type="text" class="site-transaction-number number-input" data-field="unit_price" name="items[${index}][unit_price]" value="${escapeHtml(item.unit_price ?? '')}" placeholder="0">
            </td>
            <td class="site-transaction-col-total">
                <input type="text" class="site-transaction-number number-input" data-field="total_amount" name="items[${index}][total_amount]" value="${escapeHtml(item.total_amount ?? '0')}" readonly>
            </td>
            <td class="site-transaction-col-delete">
                <button type="button" class="site-transaction-btn danger" data-role="remove-item">삭제</button>
                <input type="hidden" data-field="note" name="items[${index}][note]" value="${escapeHtml(item.note ?? '')}">
                <input type="hidden" data-field="memo" name="items[${index}][memo]" value="${escapeHtml(item.memo ?? '')}">
                <input type="hidden" data-field="description" name="items[${index}][description]" value="${escapeHtml(item.description ?? '')}">
            </td>
        `;

        row.addEventListener('input', (event) => {
            if (event.target.matches('[data-field="quantity"], [data-field="unit_price"], [data-field="item_name"]')) {
                calculateRow(row);
                calculateSummary();
            }
        });

        row.addEventListener('change', (event) => {
            if (event.target.matches('[data-field="tax_type"]')) {
                calculateSummary();
            }
        });

        row.querySelector('[data-role="remove-item"]').addEventListener('click', () => {
            row.remove();
            if (!itemsWrap.querySelector('.site-transaction-item-row')) {
                appendRow();
            }
            syncItemNames();
            toggleItemTaxUI();
            calculateSummary();
        });

        itemsWrap.appendChild(row);
        bindRowNumberInputs(row);
        calculateRow(row);
        syncItemNames();
        toggleItemTaxUI();
        calculateSummary();
    }

    function bindRowNumberInputs(row) {
        row.querySelectorAll('[data-field="quantity"], [data-field="unit_price"]').forEach((input) => {
            bindNumberInput(input);
            input.addEventListener('input', () => {
                calculateRow(row);
                calculateSummary();
            });
            input.addEventListener('blur', () => {
                calculateRow(row);
                calculateSummary();
            });
        });

        const totalInput = row.querySelector('[data-field="total_amount"]');
        if (totalInput) {
            totalInput.value = formatNumber(totalInput.value || 0);
        }
    }

    function calculateRow(row) {
        const quantity = parseNumber(row.querySelector('[data-field="quantity"]').value);
        const unitPrice = parseNumber(row.querySelector('[data-field="unit_price"]').value);
        const total = roundNumber(quantity * unitPrice);

        setComputedValue(row.querySelector('[data-field="total_amount"]'), total);
    }

    function getSupplyAmount() {
        let supply = 0;
        itemsWrap.querySelectorAll('.site-transaction-item-row').forEach((row) => {
            supply += parseNumber(row.querySelector('[data-field="total_amount"]').value);
        });
        return roundNumber(supply);
    }

    function getTaxableBase() {
        if (!useItemTaxCheckbox.checked) {
            return headerTaxType.value === 'TAXABLE' ? getSupplyAmount() : 0;
        }

        let taxable = 0;
        itemsWrap.querySelectorAll('.site-transaction-item-row').forEach((row) => {
            const itemTaxType = row.querySelector('[data-field="tax_type"]:checked')?.value || 'EXEMPT';
            if (itemTaxType === 'TAXABLE') {
                taxable += parseNumber(row.querySelector('[data-field="total_amount"]').value);
            }
        });
        return roundNumber(taxable);
    }

    function calculateSummary() {
        const supply = getSupplyAmount();
        const vat = roundNumber(parseNumber(headerVatInput.value));
        const total = roundNumber(supply + vat);
        const firstNameField = itemsWrap.querySelector('.site-transaction-item-row [data-field="item_name"]');

        form.querySelector('[name="supply_amount"]').value = String(supply);
        form.querySelector('[name="vat_amount"]').value = String(vat);
        form.querySelector('[name="total_amount"]').value = String(total);
        form.querySelector('[name="item_summary"]').value = firstNameField ? firstNameField.value.trim() : '';

        summarySupply.textContent = formatNumber(supply);
        summaryTotal.textContent = formatNumber(total);
        headerVatInput.value = formatNumber(vat);
    }

    function applyAutoVat() {
        const vat = roundNumber(getTaxableBase() * 0.1);
        headerVatInput.value = formatNumber(vat);
        calculateSummary();
    }

    function syncItemNames() {
        itemsWrap.querySelectorAll('.site-transaction-item-row').forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach((field) => {
                field.name = `items[${index}][${field.dataset.field}]`;
            });
        });
    }

    function toggleItemTaxUI() {
        const enabled = useItemTaxCheckbox.checked;
        itemTaxHeader.hidden = !enabled;
        itemsWrap.querySelectorAll('[data-role="item-tax-cell"]').forEach((cell) => {
            cell.hidden = !enabled;
        });
        calculateSummary();
    }

    function toggleImportFields() {
        importFields.hidden = !importCheckbox.checked;
        if (!importCheckbox.checked) {
            const currencyInput = form.querySelector('[name="currency"]');
            const exchangeRateInput = form.querySelector('[name="exchange_rate"]');
            if (currencyInput && !currencyInput.value.trim()) {
                currencyInput.value = 'KRW';
            }
            if (exchangeRateInput && !exchangeRateInput.value.trim()) {
                exchangeRateInput.value = '';
            }
        }
    }

    function setComputedValue(input, value) {
        if (!input) {
            return;
        }

        input.value = formatNumber(roundNumber(value));
    }

    async function loadDetail(id) {
        try {
            const response = await fetch(`/api/site/transaction/detail?id=${encodeURIComponent(id)}`, {
                credentials: 'same-origin',
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || '거래 정보를 불러오지 못했습니다.');
            }

            hydrateForm(result.data || {});
        } catch (error) {
            setMessage(error.message || '거래 정보를 불러오지 못했습니다.', 'error');
        }
    }

    function hydrateForm(transaction) {
        form.querySelector('[name="id"]').value = transaction.id ?? '';
        form.querySelector('[name="transaction_date"]').value = transaction.transaction_date ?? formatDate(new Date());
        form.querySelector('[name="transaction_type"]').value = transaction.transaction_type ?? 'PURCHASE';
        form.querySelector('[name="ref_type"]').value = transaction.ref_type ?? '';
        form.querySelector('[name="tax_type"]').value = transaction.tax_type ?? 'TAXABLE';
        form.querySelector('[name="currency"]').value = transaction.currency ?? 'KRW';
        form.querySelector('[name="exchange_rate"]').value = transaction.exchange_rate ? formatNumber(transaction.exchange_rate) : '';
        form.querySelector('[name="note"]').value = transaction.note ?? '';
        form.querySelector('[name="memo"]').value = transaction.memo ?? '';
        form.querySelector('[name="vat_amount"]').value = formatNumber(transaction.vat_amount ?? 0);

        const useItemTax = Array.isArray(transaction.items)
            && transaction.items.some((item) => String(item.tax_type || '').toUpperCase() === 'EXEMPT')
            && transaction.tax_type === 'TAXABLE';
        useItemTaxCheckbox.checked = useItemTax;

        const isImport = Boolean(transaction.exchange_rate) || (transaction.currency && transaction.currency !== 'KRW');
        importCheckbox.checked = isImport;

        toggleItemTaxUI();
        toggleImportFields();

        setPickerValue(clientPicker, transaction.client_id ?? '', transaction.client_name ?? '');
        setPickerValue(projectPicker, transaction.project_id ?? '', transaction.project_name ?? '');

        itemsWrap.innerHTML = '';
        (transaction.items || []).forEach((item) => appendRow(item));
        if (!itemsWrap.querySelector('.site-transaction-item-row')) {
            appendRow();
        }

        calculateSummary();
    }

    function serializeForm() {
        const formData = new FormData(form);

        itemsWrap.querySelectorAll('.site-transaction-item-row').forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach((field) => {
                formData.set(`items[${index}][${field.dataset.field}]`, field.value);
            });
        });

        return formData;
    }

    async function submitForm(event) {
        event.preventDefault();
        setMessage('', '');
        calculateSummary();

        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: serializeForm(),
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || '거래 저장에 실패했습니다.');
            }

            setMessage(result.message || '거래가 저장되었습니다.', 'success');
            window.location.href = `${listUrl}?saved=1`;
        } catch (error) {
            setMessage(error.message || '거래 저장에 실패했습니다.', 'error');
        }
    }

    function setMessage(text, state) {
        if (!message) {
            return;
        }

        message.textContent = text;
        if (state) {
            message.dataset.state = state;
        } else {
            delete message.dataset.state;
        }
    }

    bindDateInput();

    initRemotePicker(clientPicker, {
        url: '/api/settings/base-info/client/search-picker',
        placeholder: '거래처 검색',
        text: (row) => row.text || row.client_name || row.name || '',
    });

    initRemotePicker(projectPicker, {
        url: '/api/settings/base-info/project/search-picker',
        placeholder: '프로젝트 검색',
        text: (row) => row.text || row.project_name || row.name || '',
    });

    initQuickCreateButtons([
        {
            button: quickCreateClientButton,
            type: 'client',
            targetSelect: clientPicker,
        },
        {
            button: quickCreateProjectButton,
            type: 'project',
            targetSelect: projectPicker,
            getPayload: () => ({
                client_id: clientPicker?.value || '',
            }),
        },
    ]);

    bindNumberInput(headerVatInput);
    headerVatInput.addEventListener('blur', calculateSummary);
    headerVatInput.addEventListener('input', calculateSummary);
    addButton.addEventListener('click', () => appendRow());
    autoVatButton.addEventListener('click', applyAutoVat);
    useItemTaxCheckbox.addEventListener('change', toggleItemTaxUI);
    headerTaxType.addEventListener('change', calculateSummary);
    importCheckbox.addEventListener('change', toggleImportFields);
    form.addEventListener('submit', submitForm);

    appendRow();
    toggleImportFields();

    if (editId) {
        loadDetail(editId);
    }
}

async function initTransactionList(page) {
    const body = page.querySelector('[data-role="transaction-list"]');
    const empty = page.querySelector('[data-role="transaction-empty"]');
    const flash = page.querySelector('[data-role="flash"]');
    const listApiUrl = page.dataset.listApiUrl || '/api/site/transaction/list';
    const workUnit = page.dataset.workUnit || '';
    const params = new URLSearchParams(window.location.search);

    if (params.get('saved') === '1') {
        flash.hidden = false;
        flash.textContent = '거래가 저장되었습니다.';
    }

    try {
        const query = new URLSearchParams();
        if (workUnit) {
            query.set('work_unit', workUnit);
        }

        const response = await fetch(`${listApiUrl}?${query.toString()}`, {
            credentials: 'same-origin',
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || '거래 목록을 불러오지 못했습니다.');
        }

        const rows = Array.isArray(result.data) ? result.data : [];
        body.innerHTML = '';

        if (!rows.length) {
            empty.hidden = false;
            return;
        }

        empty.hidden = true;

        rows.forEach((row) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(row.transaction_date || '')}</td>
                <td>${escapeHtml(row.client_name || '-')}</td>
                <td class="is-number">${formatNumber(row.total_amount || 0)}</td>
                <td><span class="site-transaction-status">${formatStatus(row.acct_status)}</span></td>
            `;
            body.appendChild(tr);
        });
    } catch (error) {
        empty.hidden = false;
        empty.textContent = error.message || '거래 목록을 불러오지 못했습니다.';
    }
}

function formatStatus(status) {
    if (status === 'unposted') {
        return '미전표';
    }

    return status || '-';
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function roundNumber(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

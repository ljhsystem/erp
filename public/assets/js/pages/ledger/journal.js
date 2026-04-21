import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

(() => {
    const tableBody = document.getElementById('journal-table-body');
    const form = document.getElementById('journal-edit-form');
    const modalEl = document.getElementById('journalModal');
    const openBtn = document.getElementById('btnOpenJournalModal');
    const refreshBtn = document.getElementById('btnJournalRefresh');
    const addLineBtn = document.getElementById('btnAddVoucherLine');
    const lineBody = document.getElementById('voucher-line-body');
    const deleteBtn = document.getElementById('btnDeleteVoucher');
    const debitTotalEl = document.getElementById('voucher_debit_total');
    const creditTotalEl = document.getElementById('voucher_credit_total');
    const balanceStatusEl = document.getElementById('voucher_balance_status');
    const voucherStatusEl = document.getElementById('voucher_status');
    const voucherRefTypeEl = document.getElementById('voucher_ref_type');
    const voucherDateEl = document.getElementById('voucher_date');
    const voucherRefIdEl = document.getElementById('voucher_ref_id');

    if (!tableBody || !form || !modalEl || !lineBody || !voucherDateEl) {
        return;
    }

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    const pickerLayerEl = document.getElementById('journal-today-picker');
    if (pickerLayerEl && pickerLayerEl.parentElement !== document.body) {
        document.body.appendChild(pickerLayerEl);
    }

    const API = {
        list: '/api/ledger/voucher/list',
        detail: '/api/ledger/voucher/detail',
        save: '/api/ledger/voucher/save',
        remove: '/api/ledger/voucher/delete',
        accountList: '/api/ledger/account/list',
    };

    const STATUS_LABELS = {
        draft: '임시저장',
        posted: '확정',
        locked: '마감',
        deleted: '삭제',
    };

    const REF_TYPE_LABELS = {
        CLIENT: '거래처',
        PROJECT: '프로젝트',
        ACCOUNT: '계좌',
        CARD: '카드',
        EMPLOYEE: '직원',
        ORDER: '주문',
    };

    const modal = window.bootstrap ? new bootstrap.Modal(modalEl) : null;
    let todayPicker = null;
    let accountPickerItems = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            AppCore.notify(type, message);
            return;
        }

        window.alert(message);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, options);
        return response.json();
    }

    function formatDate(date) {
        if (!date) return '';
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function translateStatus(value) {
        return STATUS_LABELS[value] || value || '-';
    }

    function translateRefType(value) {
        return REF_TYPE_LABELS[value] || value || '-';
    }

    function initTodayPicker() {
        if (todayPicker) {
            return todayPicker;
        }

        const container = document.getElementById('journal-today-picker');
        if (!container) {
            return null;
        }

        todayPicker = AdminPicker.create({
            type: 'today',
            container,
        });

        todayPicker.subscribe((_, date) => {
            const input = todayPicker.__target;
            if (!input || !date) return;

            input.value = formatDate(date);
            todayPicker.close();
        });

        return todayPicker;
    }

    function bindDatePickerInput() {
        voucherDateEl.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const picker = initTodayPicker();
            if (!picker) return;

            picker.__target = voucherDateEl;

            if (typeof picker.clearDate === 'function') {
                picker.clearDate();
            }

            if (voucherDateEl.value) {
                const current = new Date(voucherDateEl.value);
                if (!Number.isNaN(current.getTime()) && typeof picker.setDate === 'function') {
                    picker.setDate(current);
                }
            }

            picker.open({ anchor: voucherDateEl });
        });
    }

    function initStaticSelectPickers() {
        if (!window.jQuery || !voucherStatusEl || !voucherRefTypeEl) {
            return;
        }

        AdminPicker.select2(voucherStatusEl, {
            placeholder: '상태 선택',
            minimumResultsForSearch: Infinity,
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
        });

        AdminPicker.select2(voucherRefTypeEl, {
            placeholder: '참조유형 선택',
            minimumResultsForSearch: Infinity,
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
        });
    }

    async function ensureAccountPickerItems() {
        if (Array.isArray(accountPickerItems)) {
            return accountPickerItems;
        }

        try {
            const json = await fetchJson(API.accountList);
            const rows = Array.isArray(json?.data) ? json.data : [];

            accountPickerItems = [
                { id: '', text: '계정과목 선택' },
                ...rows.map((row) => ({
                    id: String(row.account_code ?? row.code ?? ''),
                    text: `${row.account_code ?? row.code ?? ''} - ${row.account_name ?? row.name ?? ''}`,
                })).filter((item) => item.id !== ''),
            ];
        } catch (error) {
            console.error('[ledger-journal] account list load failed', error);
            accountPickerItems = [{ id: '', text: '계정과목 선택' }];
        }

        return accountPickerItems;
    }

    async function initLineAccountPicker(selectEl, selectedValue = '') {
        if (!selectEl || !window.jQuery) {
            return;
        }

        const items = await ensureAccountPickerItems();

        AdminPicker.select2(selectEl, {
            placeholder: '계정과목 선택',
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
        });

        AdminPicker.reloadSelect2(selectEl, items, 'id', 'text', selectedValue || '');
    }

    function emptyLineRow() {
        return '<tr class="voucher-line-empty"><td colspan="6" class="text-center text-muted py-4">분개 라인을 추가해주세요.</td></tr>';
    }

    function renderEmptyTable(message = '등록된 전표가 없습니다.') {
        tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">${escapeHtml(message)}</td></tr>`;
    }

    function syncLineNumbers() {
        Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'))
            .forEach((row, index) => {
                const numberCell = row.querySelector('.line-no');
                if (numberCell) {
                    numberCell.textContent = String(index + 1);
                }
            });
    }

    function calculateTotals() {
        const rows = Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'));

        const debit = rows.reduce((sum, row) => {
            const value = parseFloat(row.querySelector('.line-debit')?.value || '0');
            return sum + (Number.isFinite(value) ? value : 0);
        }, 0);

        const credit = rows.reduce((sum, row) => {
            const value = parseFloat(row.querySelector('.line-credit')?.value || '0');
            return sum + (Number.isFinite(value) ? value : 0);
        }, 0);

        debitTotalEl.value = debit.toFixed(2);
        creditTotalEl.value = credit.toFixed(2);

        if (rows.length === 0) {
            balanceStatusEl.value = '분개 라인을 입력해주세요.';
            return;
        }

        balanceStatusEl.value = debit.toFixed(2) === credit.toFixed(2)
            ? '차변/대변 합계가 일치합니다.'
            : '차변/대변 합계가 일치하지 않습니다.';
    }

    async function addLineRow(line = {}) {
        lineBody.querySelector('.voucher-line-empty')?.remove();

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-center line-no"></td>
            <td>
                <select class="form-select form-select-sm line-account-code-picker">
                    <option value="">계정과목 선택</option>
                </select>
            </td>
            <td>
                <input type="number"
                       step="0.01"
                       min="0"
                       class="form-control form-control-sm line-debit"
                       value="${escapeHtml(line.debit || '')}"
                       placeholder="0.00">
            </td>
            <td>
                <input type="number"
                       step="0.01"
                       min="0"
                       class="form-control form-control-sm line-credit"
                       value="${escapeHtml(line.credit || '')}"
                       placeholder="0.00">
            </td>
            <td>
                <input type="text"
                       class="form-control form-control-sm line-summary"
                       value="${escapeHtml(line.line_summary || '')}"
                       placeholder="라인 적요">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-line">삭제</button>
            </td>
        `;

        lineBody.appendChild(row);
        syncLineNumbers();
        calculateTotals();

        const accountSelect = row.querySelector('.line-account-code-picker');
        await initLineAccountPicker(accountSelect, line.account_code || '');
    }

    function resetModal() {
        form.reset();
        document.getElementById('journal_id').value = '';
        voucherStatusEl.value = 'draft';
        voucherRefTypeEl.value = '';
        if (voucherRefIdEl) {
            voucherRefIdEl.value = '';
        }
        if (window.jQuery) {
            window.jQuery(voucherStatusEl).trigger('change');
            window.jQuery(voucherRefTypeEl).trigger('change');
        }
        lineBody.innerHTML = emptyLineRow();
        deleteBtn.style.display = 'none';
        calculateTotals();
    }

    function collectLines() {
        return Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'))
            .map((row) => ({
                account_code: row.querySelector('.line-account-code-picker')?.value?.trim() ?? '',
                debit: row.querySelector('.line-debit')?.value ?? '0',
                credit: row.querySelector('.line-credit')?.value ?? '0',
                line_summary: row.querySelector('.line-summary')?.value?.trim() ?? '',
            }));
    }

    function collectPayments() {
        const paymentRows = Array.from(document.querySelectorAll('[data-payment-row]'));

        if (paymentRows.length === 0) {
            return [];
        }

        return paymentRows.map((row) => ({
            payment_type: row.querySelector('.payment-type')?.value?.trim() ?? '',
            payment_id: row.querySelector('.payment-id')?.value?.trim() ?? '',
            amount: row.querySelector('.payment-amount')?.value ?? '0',
        }));
    }

    function validateBeforeSave() {
        const lines = collectLines();

        if (lines.length === 0) {
            notify('warning', '분개 라인을 1건 이상 입력해주세요.');
            return false;
        }

        let debitTotal = 0;
        let creditTotal = 0;

        for (let index = 0; index < lines.length; index += 1) {
            const line = lines[index];
            const debit = parseFloat(line.debit || '0');
            const credit = parseFloat(line.credit || '0');

            if (!line.account_code) {
                notify('warning', `${index + 1}번 라인의 계정과목을 선택해주세요.`);
                return false;
            }

            if (debit <= 0 && credit <= 0) {
                notify('warning', `${index + 1}번 라인의 차변 또는 대변 금액을 입력해주세요.`);
                return false;
            }

            if (debit > 0 && credit > 0) {
                notify('warning', `${index + 1}번 라인은 차변 또는 대변 중 하나만 입력할 수 있습니다.`);
                return false;
            }

            debitTotal += Number.isFinite(debit) ? debit : 0;
            creditTotal += Number.isFinite(credit) ? credit : 0;
        }

        if (debitTotal.toFixed(2) !== creditTotal.toFixed(2)) {
            notify('warning', '차변 합계와 대변 합계가 일치해야 합니다.');
            return false;
        }

        return true;
    }

    function renderTable(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            renderEmptyTable();
            return;
        }

        tableBody.innerHTML = rows.map((row) => `
            <tr data-id="${escapeHtml(row.id)}">
                <td>${escapeHtml(row.code || '')}</td>
                <td>${escapeHtml(row.voucher_date || '')}</td>
                <td>${escapeHtml(translateStatus(row.status))}</td>
                <td>${escapeHtml(translateRefType(row.ref_type))}</td>
                <td>${escapeHtml(row.ref_id || '')}</td>
                <td>${escapeHtml(row.summary_text || '')}</td>
                <td>${escapeHtml(row.updated_at || row.created_at || '')}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-primary btn-sm btn-edit-voucher">수정</button>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-delete-voucher">삭제</button>
                </td>
            </tr>
        `).join('');
    }

    async function loadList() {
        try {
            const json = await fetchJson(API.list);

            if (!json.success) {
                notify('error', json.message || '전표 목록을 불러오지 못했습니다.');
                renderEmptyTable('전표 목록을 불러오지 못했습니다.');
                return;
            }

            renderTable(json.data || []);
        } catch (error) {
            console.error('[ledger-journal] loadList failed', error);
            renderEmptyTable('전표 목록을 불러오지 못했습니다.');
        }
    }

    async function loadDetail(id) {
        try {
            const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);

            if (!json.success || !json.data) {
                notify('error', json.message || '전표 상세를 불러오지 못했습니다.');
                return;
            }

            const data = json.data;
            document.getElementById('journal_id').value = data.id || '';
            voucherDateEl.value = data.voucher_date || '';
            voucherStatusEl.value = data.status || 'draft';
            voucherRefTypeEl.value = data.ref_type || '';
            if (window.jQuery) {
                window.jQuery(voucherStatusEl).trigger('change');
                window.jQuery(voucherRefTypeEl).trigger('change');
            }
            document.getElementById('voucher_ref_id').value = data.ref_id || '';
            document.getElementById('voucher_summary_text').value = data.summary_text || '';
            document.getElementById('voucher_note').value = data.note || '';
            document.getElementById('voucher_memo').value = data.memo || '';

            lineBody.innerHTML = emptyLineRow();
            if (Array.isArray(data.lines) && data.lines.length > 0) {
                for (const line of data.lines) {
                    await addLineRow(line);
                }
            } else {
                await addLineRow();
            }

            deleteBtn.style.display = 'inline-block';
            calculateTotals();
            modal?.show();
        } catch (error) {
            console.error('[ledger-journal] loadDetail failed', error);
            notify('error', '전표 상세를 불러오지 못했습니다.');
        }
    }

    async function saveVoucher() {
        const formData = new FormData(form);
        formData.set('lines', JSON.stringify(collectLines()));
        formData.set('payments', JSON.stringify(collectPayments()));

        const json = await fetchJson(API.save, {
            method: 'POST',
            body: formData,
        });

        if (!json.success) {
            notify('error', json.message || '전표를 저장하지 못했습니다.');
            return;
        }

        notify('success', '저장 완료');
        modal?.hide();
        await loadList();
    }

    async function deleteVoucher(id) {
        const formData = new FormData();
        formData.append('id', id);

        const json = await fetchJson(API.remove, {
            method: 'POST',
            body: formData,
        });

        if (!json.success) {
            notify('error', json.message || '전표를 삭제하지 못했습니다.');
            return;
        }

        notify('success', '삭제 완료');
        modal?.hide();
        await loadList();
    }

    openBtn?.addEventListener('click', async () => {
        resetModal();
        await addLineRow();
        modal?.show();
    });

    refreshBtn?.addEventListener('click', loadList);
    addLineBtn?.addEventListener('click', async () => {
        await addLineRow();
    });

    lineBody.addEventListener('click', (event) => {
        const button = event.target.closest('.btn-remove-line');
        if (!button) {
            return;
        }

        button.closest('tr')?.remove();

        if (!lineBody.querySelector('tr')) {
            lineBody.innerHTML = emptyLineRow();
        }

        syncLineNumbers();
        calculateTotals();
    });

    lineBody.addEventListener('input', (event) => {
        if (event.target.closest('.line-debit, .line-credit, .line-summary')) {
            calculateTotals();
        }
    });

    lineBody.addEventListener('change', (event) => {
        if (event.target.closest('.line-account-code-picker')) {
            calculateTotals();
        }
    });

    tableBody.addEventListener('click', (event) => {
        const row = event.target.closest('tr[data-id]');
        if (!row) {
            return;
        }

        const id = row.dataset.id;

        if (event.target.closest('.btn-edit-voucher')) {
            resetModal();
            loadDetail(id);
            return;
        }

        if (event.target.closest('.btn-delete-voucher')) {
            if (!window.confirm('선택한 전표를 삭제하시겠습니까?')) {
                return;
            }

            deleteVoucher(id);
        }
    });

    deleteBtn?.addEventListener('click', () => {
        const id = document.getElementById('journal_id').value;
        if (!id) {
            return;
        }

        if (!window.confirm('현재 전표를 삭제하시겠습니까?')) {
            return;
        }

        deleteVoucher(id);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!validateBeforeSave()) {
            return;
        }

        try {
            await saveVoucher();
        } catch (error) {
            console.error('[ledger-journal] save failed', error);
            notify('error', '전표 저장 중 오류가 발생했습니다.');
        }
    });

    modalEl.addEventListener('shown.bs.modal', () => {
        initStaticSelectPickers();
    });

    voucherRefTypeEl?.addEventListener('change', () => {
        if (voucherRefIdEl) {
            voucherRefIdEl.value = '';
        }
    });

    bindDatePickerInput();
    initStaticSelectPickers();
    calculateTotals();
    loadList();

    modalEl.addEventListener('hidden.bs.modal', () => {
        todayPicker?.close?.();
    });
})();

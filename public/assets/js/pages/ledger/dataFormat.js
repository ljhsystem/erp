(() => {
    const API = {
        fields: '/api/import/fields',
        formats: '/api/import/formats',
        detail: '/api/import/format',
        save: '/api/import/format/save',
        remove: '/api/import/format/delete',
        copy: '/api/import/format/copy',
    };

    const DATA_TYPES = {
        TAX_INVOICE: '세금계산서',
        CASH_RECEIPT: '현금영수증',
        CARD_PURCHASE: '카드(매입)',
        CARD_SALE: '카드(매출)',
        BANK: '입출',
        ETC: '기타',
    };

    const formatTypeFilterEl = document.getElementById('formatTypeFilter');
    const formatListEl = document.getElementById('formatList');
    const formatIdEl = document.getElementById('formatId');
    const formatNameEl = document.getElementById('formatName');
    const formatDataTypeEl = document.getElementById('formatDataType');
    const formatIsDefaultEl = document.getElementById('formatIsDefault');
    const columnBodyEl = document.getElementById('formatColumnBody');
    const newFormatBtn = document.getElementById('newFormatBtn');
    const addColumnBtn = document.getElementById('addColumnBtn');
    const saveFormatBtn = document.getElementById('saveFormatBtn');
    const deleteFormatBtn = document.getElementById('deleteFormatBtn');
    const copyFormatBtn = document.getElementById('copyFormatBtn');
    const newFormatModalEl = document.getElementById('newFormatModal');
    const newFormatNameEl = document.getElementById('newFormatName');
    const newFormatDataTypeEl = document.getElementById('newFormatDataType');
    const confirmNewFormatBtn = document.getElementById('confirmNewFormatBtn');
    const newFormatModal = window.bootstrap && newFormatModalEl
        ? bootstrap.Modal.getOrCreateInstance(newFormatModalEl)
        : null;

    let systemFields = [];
    let formats = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, options);
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.success === false) {
            throw new Error(json.message || '요청 처리에 실패했습니다.');
        }
        return json;
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        if (type === 'error' || type === 'warning') alert(message);
    }

    function currentType() {
        return formatTypeFilterEl?.value || 'TAX_INVOICE';
    }

    function typeLabel(type) {
        return DATA_TYPES[type] || type || '';
    }

    function fieldOptions(selected = '') {
        return systemFields.map((field) => `
            <option value="${escapeHtml(field.value)}" ${field.value === selected ? 'selected' : ''}>
                ${escapeHtml(field.label)} (${escapeHtml(field.value)})
            </option>
        `).join('');
    }

    function columnRow(column = {}, index = 0) {
        return `
            <tr>
                <td><input type="number" class="form-control form-control-sm column-order" min="1" value="${escapeHtml(column.column_order || index + 1)}"></td>
                <td><input type="text" class="form-control form-control-sm excel-column-name" value="${escapeHtml(column.excel_column_name || '')}" placeholder="엑셀 컬럼명"></td>
                <td><select class="form-select form-select-sm system-field-name">${fieldOptions(column.system_field_name || '')}</select></td>
                <td class="text-center"><input type="checkbox" class="form-check-input is-required" ${Number(column.is_required || 0) === 1 ? 'checked' : ''}></td>
                <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm remove-column-btn">삭제</button></td>
            </tr>
        `;
    }

    function defaultColumnsFor(type = 'TAX_INVOICE') {
        if (type === 'BANK') {
            return [
                { excel_column_name: '거래일자', system_field_name: 'transaction_date', column_order: 1, is_required: 1 },
                { excel_column_name: '사업자등록번호', system_field_name: 'business_number', column_order: 2 },
                { excel_column_name: '상호', system_field_name: 'company_name', column_order: 3 },
                { excel_column_name: '적요', system_field_name: 'description', column_order: 4, is_required: 1 },
                { excel_column_name: '금액', system_field_name: 'total_amount', column_order: 5, is_required: 1 },
                { excel_column_name: '비고', system_field_name: 'note', column_order: 6 },
                { excel_column_name: '메모', system_field_name: 'memo', column_order: 7 },
            ];
        }

        if (type === 'CARD_PURCHASE' || type === 'CARD_SALE' || type === 'CASH_RECEIPT') {
            return [
                { excel_column_name: '거래일자', system_field_name: 'transaction_date', column_order: 1, is_required: 1 },
                { excel_column_name: '사업자등록번호', system_field_name: 'business_number', column_order: 2 },
                { excel_column_name: '상호', system_field_name: 'company_name', column_order: 3 },
                { excel_column_name: '적요', system_field_name: 'description', column_order: 4, is_required: 1 },
                { excel_column_name: '공급가액', system_field_name: 'supply_amount', column_order: 5 },
                { excel_column_name: '부가세', system_field_name: 'vat_amount', column_order: 6 },
                { excel_column_name: '합계금액', system_field_name: 'total_amount', column_order: 7, is_required: 1 },
                { excel_column_name: '비고', system_field_name: 'note', column_order: 8 },
                { excel_column_name: '메모', system_field_name: 'memo', column_order: 9 },
            ];
        }

        return [
            { excel_column_name: '작성일자', system_field_name: 'transaction_date', column_order: 1, is_required: 1 },
            { excel_column_name: '사업자등록번호', system_field_name: 'business_number', column_order: 2, is_required: type === 'TAX_INVOICE' ? 1 : 0 },
            { excel_column_name: '상호', system_field_name: 'company_name', column_order: 3, is_required: type === 'TAX_INVOICE' ? 1 : 0 },
            { excel_column_name: '사업명', system_field_name: 'project_name', column_order: 4 },
            { excel_column_name: '적요', system_field_name: 'description', column_order: 5, is_required: 1 },
            { excel_column_name: '공급가액', system_field_name: 'supply_amount', column_order: 6 },
            { excel_column_name: '부가세', system_field_name: 'vat_amount', column_order: 7 },
            { excel_column_name: '합계금액', system_field_name: 'total_amount', column_order: 8 },
            { excel_column_name: '과세구분', system_field_name: 'tax_type', column_order: 9 },
            { excel_column_name: '비고', system_field_name: 'note', column_order: 10 },
            { excel_column_name: '메모', system_field_name: 'memo', column_order: 11 },
        ];
    }

    function renderColumns(columns = []) {
        columnBodyEl.innerHTML = columns.length
            ? columns.map(columnRow).join('')
            : columnRow({ system_field_name: 'transaction_date', is_required: 1 }, 0);
    }

    function renderFormats() {
        if (!formats.length) {
            formatListEl.innerHTML = '<div class="list-group-item text-muted">선택한 자료유형의 양식이 없습니다.</div>';
            return;
        }

        const selectedId = formatIdEl.value;
        formatListEl.innerHTML = formats.map((format) => `
            <button type="button" class="list-group-item list-group-item-action ${format.id === selectedId ? 'active' : ''}" data-id="${escapeHtml(format.id)}">
                <div class="d-flex justify-content-between">
                    <strong>${escapeHtml(format.format_name)}</strong>
                    <span class="badge ${format.id === selectedId ? 'bg-light text-dark' : 'bg-secondary'}">${escapeHtml(typeLabel(format.data_type))}</span>
                </div>
                <small class="${format.id === selectedId ? 'text-white-50' : 'text-muted'}">${Number(format.is_default || 0) === 1 ? '기본양식' : ''}</small>
            </button>
        `).join('');
    }

    function resetForm(name = '', dataType = currentType()) {
        formatIdEl.value = '';
        formatNameEl.value = name;
        formatDataTypeEl.value = dataType;
        formatIsDefaultEl.checked = false;
        renderColumns(defaultColumnsFor(dataType));
        renderFormats();
    }

    async function loadFormats(selectedId = '') {
        const dataType = currentType();
        const json = await fetchJson(`${API.formats}?data_type=${encodeURIComponent(dataType)}&include_columns=1`);
        formats = json.data || [];
        renderFormats();
        if (selectedId) {
            await selectFormat(selectedId);
        } else {
            resetForm('', dataType);
        }
    }

    async function loadFields() {
        const json = await fetchJson(API.fields);
        systemFields = json.data || [];
    }

    async function selectFormat(id) {
        const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);
        const data = json.data || {};
        formatIdEl.value = data.id || '';
        formatNameEl.value = data.format_name || '';
        formatDataTypeEl.value = data.data_type || currentType();
        formatIsDefaultEl.checked = Number(data.is_default || 0) === 1;
        renderColumns(data.columns || []);
        renderFormats();
    }

    function collectColumns() {
        return Array.from(columnBodyEl.querySelectorAll('tr')).map((row, index) => ({
            column_order: Number(row.querySelector('.column-order')?.value || index + 1),
            excel_column_name: row.querySelector('.excel-column-name')?.value?.trim() || '',
            system_field_name: row.querySelector('.system-field-name')?.value || '',
            is_required: row.querySelector('.is-required')?.checked ? 1 : 0,
        })).filter((row) => row.excel_column_name !== '' && row.system_field_name !== '');
    }

    async function saveFormat() {
        const payload = {
            id: formatIdEl.value,
            format_name: formatNameEl.value.trim(),
            data_type: formatDataTypeEl.value,
            is_default: formatIsDefaultEl.checked ? 1 : 0,
            columns: collectColumns(),
        };
        const json = await fetchJson(API.save, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        notify('success', '양식이 저장되었습니다.');
        if (formatTypeFilterEl && formatTypeFilterEl.value !== payload.data_type) {
            formatTypeFilterEl.value = payload.data_type;
        }
        await loadFormats(json.id || payload.id);
    }

    async function deleteFormat() {
        const id = formatIdEl.value;
        if (!id || !window.confirm('선택한 양식을 삭제하시겠습니까?')) return;
        await fetchJson(API.remove, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        notify('success', '양식이 삭제되었습니다.');
        await loadFormats();
    }

    async function copyFormat() {
        const id = formatIdEl.value;
        if (!id) {
            notify('warning', '복사할 양식을 선택하세요.');
            return;
        }
        const json = await fetchJson(API.copy, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        notify('success', '양식이 복사되었습니다.');
        await loadFormats(json.id || '');
    }

    function openNewFormatModal() {
        if (newFormatNameEl) newFormatNameEl.value = '';
        if (newFormatDataTypeEl) newFormatDataTypeEl.value = currentType();
        if (newFormatModal) {
            newFormatModal.show();
            setTimeout(() => newFormatNameEl?.focus(), 150);
            return;
        }
        resetForm('', currentType());
    }

    function confirmNewFormat() {
        const name = newFormatNameEl?.value?.trim() || '';
        const dataType = newFormatDataTypeEl?.value || currentType();
        if (name === '') {
            notify('warning', '양식명을 입력하세요.');
            return;
        }
        if (formatTypeFilterEl) formatTypeFilterEl.value = dataType;
        resetForm(name, dataType);
        newFormatModal?.hide();
    }

    formatTypeFilterEl?.addEventListener('change', () => {
        void loadFormats().catch((error) => notify('error', error.message));
    });

    formatListEl?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-id]');
        if (button) void selectFormat(button.dataset.id);
    });

    columnBodyEl?.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-column-btn');
        if (button) button.closest('tr')?.remove();
    });

    newFormatBtn?.addEventListener('click', openNewFormatModal);
    confirmNewFormatBtn?.addEventListener('click', confirmNewFormat);
    addColumnBtn?.addEventListener('click', () => {
        columnBodyEl.insertAdjacentHTML('beforeend', columnRow({}, columnBodyEl.querySelectorAll('tr').length));
    });
    saveFormatBtn?.addEventListener('click', () => void saveFormat().catch((error) => notify('error', error.message)));
    deleteFormatBtn?.addEventListener('click', () => void deleteFormat().catch((error) => notify('error', error.message)));
    copyFormatBtn?.addEventListener('click', () => void copyFormat().catch((error) => notify('error', error.message)));

    (async () => {
        await loadFields();
        await loadFormats();
    })().catch((error) => notify('error', error.message));
})();

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { formatBizNumber, formatPhone } from '/public/assets/js/common/format.js';

function clientAutofillPayload(row = {}) {
    return {
        business_number: formatBizNumber(row.business_number || ''),
        ceo_name: row.ceo_name || '',
        address: [row.address || '', row.address_detail || ''].filter(Boolean).join(' '),
        email: row.email || '',
        phone: formatPhone(row.phone || ''),
    };
}

export const EVIDENCE_REF_PICKERS = {
    SUPPLIER_COMPANY: {
        picker: 'supplierCompany',
        idKey: '',
        nameKey: 'supplier_company_name',
        placeholder: '공급자 상호 선택',
        keys: ['supplier_company_name', 'supplier_name', '공급자상호', '공급자명'],
        allowText: true,
        quickAddEnabled: true,
        listText(row = {}) {
            const clientName = String(row.client_name || '').trim();
            const companyName = String(row.company_name || '').trim();
            return clientName && companyName ? `${clientName} / ${companyName}` : (clientName || companyName || '');
        },
        saveText(row = {}) {
            return row.isNew ? String(row.text || '').trim() : String(row.company_name || '').trim();
        },
        result: clientAutofillPayload,
        autofill: {
            supplier_business_number: 'business_number',
            supplier_ceo_name: 'ceo_name',
            supplier_address: 'address',
            supplier_email: 'email',
            supplier_phone: 'phone',
            supplier_ceo_phone: 'phone',
        },
    },
    CUSTOMER_COMPANY: {
        picker: 'customerCompany',
        idKey: '',
        nameKey: 'customer_company_name',
        placeholder: '공급받는자 상호 선택',
        keys: ['customer_company_name', 'customer_name', '공급받는자상호', '공급받는자명'],
        allowText: true,
        listText(row = {}) {
            const clientName = String(row.client_name || '').trim();
            const companyName = String(row.company_name || '').trim();
            return clientName && companyName ? `${clientName} / ${companyName}` : (clientName || companyName || '');
        },
        saveText(row = {}) {
            return row.isNew ? String(row.text || '').trim() : String(row.company_name || '').trim();
        },
        result: clientAutofillPayload,
        autofill: {
            customer_business_number: 'business_number',
            customer_ceo_name: 'ceo_name',
            customer_address: 'address',
            customer_email_1: 'email',
            customer_phone: 'phone',
            customer_ceo_phone: 'phone',
        },
    },
    CLIENT: {
        picker: 'client',
        idKey: 'client_id',
        nameKey: 'client_name',
        placeholder: '거래처 선택',
        keys: ['client_id', 'client_name', 'client_company_name', '거래처명', '거래처'],
        allowText: true,
        quickAddEnabled: true,
        listText(row = {}) {
            const clientName = String(row.client_name || '').trim();
            const companyName = String(row.company_name || '').trim();
            return clientName && companyName ? `${clientName} / ${companyName}` : (clientName || companyName || '');
        },
        saveText(row = {}) {
            return row.isNew ? String(row.text || '').trim() : String(row.client_name || '').trim();
        },
    },
    PROJECT: {
        picker: 'project',
        idKey: 'project_id',
        nameKey: 'project_name',
        placeholder: '프로젝트 선택',
        keys: ['project_id', 'project_name', 'project_code', '프로젝트명', '프로젝트'],
        label: (row) => row.text || row.project_name || row.construction_name || row.project_code || '',
    },
    EMPLOYEE: {
        picker: 'employee',
        idKey: 'employee_id',
        nameKey: 'employee_name',
        placeholder: '직원 선택',
        keys: ['employee_id', 'employee_name', 'user_name', 'user_id', '직원명', '직원'],
        label: (row) => row.text || row.employee_name || row.name || row.username || '',
    },
    TEAM: {
        picker: 'team',
        idKey: 'team_id',
        nameKey: 'team_name',
        placeholder: '팀 선택',
        keys: ['team_id', 'team_name', '팀명', '팀'],
        quickAddEnabled: true,
        label: (row) => row.text || row.team_name || '',
        dataBuilder(params) {
            const term = String(params.term || '').trim();
            const filters = [{ field: 'is_active', value: 1 }];
            if (term !== '') {
                filters.unshift({ field: '', value: term });
            }
            return { filters: JSON.stringify(filters) };
        },
        processResults(data) {
            const rows = data?.data ?? data?.results ?? [];
            return rows
                .map((row) => ({
                    id: String(row.id ?? ''),
                    text: row.team_name ?? row.text ?? '',
                }))
                .filter((item) => item.id !== '' && item.text !== '');
        },
    },
    ACCOUNT: {
        picker: 'bankAccount',
        idKey: 'bank_account_id',
        nameKey: 'bank_account_name',
        placeholder: '계좌 선택',
        keys: [
            'bank_account_id',
            'bank_account_name',
            'bank_account',
            'account_name',
            'payment_account_name',
            'account_number',
            'payment_account_number',
            '계좌명',
            '계좌',
        ],
        listText(row = {}) {
            return String(row.account_name || row.bank_account_name || row.name || '').trim();
        },
        saveText(row = {}) {
            return String(row.account_name || row.bank_account_name || row.name || '').trim();
        },
    },
    CARD: {
        picker: 'card',
        idKey: 'card_id',
        nameKey: 'card_name',
        placeholder: '카드 선택',
        keys: ['card_id', 'card_name', 'card_number', 'card_company_name', '카드명', '카드'],
        label: (row) => row.text || row.card_name || row.card_number || row.card_company_name || '',
    },
};

function normalizeToken(value) {
    return String(value ?? '').replace(/\*/g, '').replace(/\s+/g, '').toLowerCase();
}

function looksLikeUuid(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || '').trim());
}

function mapPickerResults(config, data) {
    const rows = typeof config.processResults === 'function'
        ? config.processResults(data)
        : (data?.results ?? data?.data ?? []);

    return rows.map((row) => {
        const text = typeof config.listText === 'function'
            ? config.listText(row)
            : config.label(row);
        const refText = typeof config.saveText === 'function' ? config.saveText(row) : text;

        return {
            id: String(row.id ?? text ?? ''),
            text,
            refText,
            selectionText: refText,
            ...(typeof config.result === 'function' ? config.result(row) : {}),
        };
    }).filter((item) => item.id !== '' && item.text !== '');
}

function applyHydratedOption(select, value, label) {
    if (!select) return;
    const normalizedValue = String(value || '').trim();
    const normalizedLabel = String(label || '').trim();
    if (normalizedValue === '' || normalizedLabel === '') return;

    const existing = Array.from(select.options || []).find((entry) => String(entry.value || '').trim() === normalizedValue);
    if (existing) {
        existing.textContent = normalizedLabel;
        existing.selected = true;
    } else {
        select.append(new Option(normalizedLabel, normalizedValue, true, true));
    }

    select.value = normalizedValue;
    select.dataset.refCurrentText = normalizedLabel;
    select.dataset.refSelectedText = normalizedLabel;

    if (window.jQuery?.fn?.select2) {
        const $select = window.jQuery(select);
        $select.find(`option[value="${normalizedValue.replace(/"/g, '\\"')}"]`).remove();
        $select.append(new Option(normalizedLabel, normalizedValue, true, true));
        $select.val(normalizedValue).trigger('change');
    } else {
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

async function hydrateInitialRefOption(select, config, url) {
    const value = String(select?.value || '').trim();
    if (!select || value === '' || value === '__add__') {
        return;
    }

    const currentText = String(select.dataset.refCurrentText || '').trim();
    const selectedText = String(select.selectedOptions?.[0]?.textContent || '').trim();
    const displayText = currentText || selectedText;
    if (displayText !== '' && displayText !== value && !looksLikeUuid(displayText)) {
        select.dataset.refSelectedText = displayText;
        return;
    }

    const params = typeof config.dataBuilder === 'function'
        ? config.dataBuilder({ term: value, page: 1 })
        : { q: value, limit: 20, is_active: 1 };
    const query = new URLSearchParams();
    Object.entries(params || {}).forEach(([key, rawValue]) => {
        if (rawValue === undefined || rawValue === null) return;
        query.append(key, String(rawValue));
    });

    try {
        const response = await fetch(`${url}?${query.toString()}`, {
            method: 'GET',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;
        const data = await response.json();
        const items = mapPickerResults(config, data);
        const matched = items.find((item) => item.id === value);
        if (!matched) return;

        const label = String(matched.selectionText || matched.refText || matched.text || value).trim();
        if (label === '' || label === value) return;

        applyHydratedOption(select, value, label);
    } catch (_error) {
        // Ignore hydration failures and leave the current label as-is.
    }
}

async function hydrateInitialRefOptionByDetail(select, config, detailUrl) {
    const value = String(select?.value || '').trim();
    if (!select || !detailUrl || value === '' || !looksLikeUuid(value)) {
        return false;
    }

    try {
        const response = await fetch(`${detailUrl}?id=${encodeURIComponent(value)}`, {
            method: 'GET',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return false;
        const data = await response.json();
        const row = data?.data;
        if (!row || typeof row !== 'object') return false;

        const mapped = mapPickerResults(config, { data: [row] });
        const matched = mapped[0] || null;
        const label = String(
            matched?.selectionText
            || matched?.refText
            || matched?.text
            || ''
        ).trim();
        if (label === '' || label === value) {
            return false;
        }

        applyHydratedOption(select, value, label);
        return true;
    } catch (_error) {
        return false;
    }
}

export function evidenceRefPickerForColumnLike(column = {}) {
    const field = normalizeToken(column.system_field_name || column.systemField);
    const excel = normalizeToken(column.excel_column_name || column.column_name || column.label);
    const key = normalizeToken(column.key || column.system_field_name || column.systemField || column.excel_column_name);
    const tokens = [field, excel, key].filter(Boolean);
    const rawTextFields = new Set([
        'supplier_company_name',
        'supplier_name',
        'customer_company_name',
        'customer_name',
        '공급자상호',
        '공급자명',
        '공급받는자상호',
        '공급받는자명',
    ].map((value) => normalizeToken(value)));

    if (tokens.some((token) => rawTextFields.has(token))) {
        return null;
    }

    return Object.values(EVIDENCE_REF_PICKERS).find((config) => (
        config.keys.some((candidate) => tokens.includes(normalizeToken(candidate)))
    )) || null;
}

export function initEvidenceRefSelect(select, {
    modal = null,
    api = {},
    onSelect = null,
    onClear = null,
} = {}) {
    if (!select || select.dataset.refSelectBound === 'true') return;
    if (!window.jQuery?.fn?.select2) return;

    const config = Object.values(EVIDENCE_REF_PICKERS).find((item) => item.picker === select.dataset.refPicker);
    if (!config) return;

    const url = {
        supplierCompany: api.clientSearch,
        customerCompany: api.clientSearch,
        client: api.clientSearch,
        project: api.projectSearch,
        employee: api.employeeSearch,
        team: api.workTeamList,
        bankAccount: api.bankAccountSearch,
        card: api.cardSearch,
    }[config.picker];
    if (!url) return;
    const detailUrl = {
        supplierCompany: api.clientDetail,
        customerCompany: api.clientDetail,
        client: api.clientDetail,
        project: api.projectDetail,
        employee: api.employeeDetail,
        team: api.workTeamDetail,
        bankAccount: api.bankAccountDetail,
        card: api.cardDetail,
    }[config.picker] || '';

    AdminPicker.select2Ajax(select, {
        url,
        placeholder: config.placeholder,
        allowClear: true,
        tags: !!config.allowText,
        quickAddEnabled: config.quickAddEnabled === true,
        minimumInputLength: 0,
        dropdownParent: window.jQuery(modal || select.closest('.modal') || document.body),
        width: '100%',
        templateSelection(item) {
            return item.selectionText || item.refText || item.text || '';
        },
        createTag(params) {
            if (!config.allowText) return null;
            const term = String(params.term || '').trim();
            if (term === '') return null;
            return { id: term, text: term, isNew: true };
        },
        insertTag(data, tag) {
            data.unshift(tag);
        },
        dataBuilder(params) {
            if (typeof config.dataBuilder === 'function') {
                return config.dataBuilder(params);
            }
            return { q: params.term || '', limit: 20, is_active: 1 };
        },
        processResults(data) {
            const rows = typeof config.processResults === 'function'
                ? config.processResults(data)
                : (data?.results ?? data?.data ?? []);

            return {
                results: [
                    ...rows.map((row) => {
                        const text = typeof config.listText === 'function'
                            ? config.listText(row)
                            : config.label(row);
                        const refText = typeof config.saveText === 'function' ? config.saveText(row) : text;

                        return {
                            id: String(row.id ?? text ?? ''),
                            text,
                            refText,
                            selectionText: refText,
                            ...(typeof config.result === 'function' ? config.result(row) : {}),
                        };
                    }).filter((item) => item.id !== '' && item.text !== ''),
                    ...(config.quickAddEnabled === true ? [{ id: '__add__', text: '+추가' }] : []),
                ],
            };
        },
    });

    window.jQuery(select)
        .off('select2:select.evidenceRef')
        .on('select2:select.evidenceRef', function (event) {
            const data = event.params?.data || {};
            this.dataset.refSelectedText = data.refText || data.selectionText || data.text || '';
            this.dataset.refCurrentTextOnly = '0';
            onSelect?.(this, data, config);
        })
        .off('select2:clear.evidenceRef')
        .on('select2:clear.evidenceRef', function () {
            this.dataset.refSelectedText = '';
            this.dataset.refCurrentTextOnly = '0';
            onClear?.(this, config);
        });

    select.dataset.refSelectBound = 'true';
    void (async () => {
        const hydrated = await hydrateInitialRefOptionByDetail(select, config, detailUrl);
        if (!hydrated) {
            await hydrateInitialRefOption(select, config, url);
        }
    })();
}

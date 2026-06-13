import {
    bindNumberInput as bindCommonNumberInput,
    formatDateInputValue,
    formatBizNumber,
    formatPhone,
    parseNumber as parseCommonNumber,
} from '/public/assets/js/common/format.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { initCodeSelectControls } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import {
    BANK_DEPRECATED_FORMAT_FIELDS,
    BANK_DEPRECATED_FORMAT_TITLES,
} from '/public/assets/js/pages/ledger/evidence-source/core/constants.js';
import { createEvidenceSourceCodeOptions } from '/public/assets/js/pages/ledger/evidence-source/core/code-options.js';
import { createEvidenceSourceEditorPickers } from '/public/assets/js/pages/ledger/evidence-source/editor/pickers.js';
import { createEvidenceSourceEditorFields } from '/public/assets/js/pages/ledger/evidence-source/editor/fields.js';
import { createEvidenceSourceEditorModal } from '/public/assets/js/pages/ledger/evidence-source/editor/modal.js?v=2026051902';

const EVIDENCE_API = {
    seedRows: '/api/import/evidences',
    saveSeedRow: '/api/import/evidence/save',
    createEvidence: '/api/import/evidence/create',
    clientSearch: '/api/settings/base-info/client/search-picker',
    projectSearch: '/api/settings/base-info/project/search-picker',
    employeeSearch: '/api/settings/organization/employee/search-picker',
    bankAccountSearch: '/api/settings/base-info/bank-account/search-picker',
    cardSearch: '/api/settings/base-info/card/search-picker',
    codeList: '/api/settings/system/code/list',
};

const CURRENT_TYPE = 'BANK_TRANSACTION';

function amount(value) {
    const number = Number(String(value ?? '0').replaceAll(',', ''));
    return Number.isFinite(number) ? number : 0;
}

function formatNumber(value) {
    return amount(value).toLocaleString('ko-KR');
}

function evidenceCellValue(value) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value.value ?? '';
    }
    return value;
}

function mapped(row = {}) {
    if (row.mapped_payload && typeof row.mapped_payload === 'object') return row.mapped_payload;
    if (row.parsed_json && typeof row.parsed_json === 'object') return row.parsed_json;
    return {};
}

function firstPayloadValue(payload = {}, keys = []) {
    for (const key of keys) {
        if (!key) continue;
        const value = evidenceCellValue(payload[key]);
        if (value !== undefined && value !== null && String(value) !== '') {
            return value;
        }
    }
    return undefined;
}

function columnAliasKeys(column = {}) {
    const field = String(column.system_field_name || '').trim();
    const excelName = String(column.excel_column_name || '').trim();
    const keys = [field, excelName];
    const aliasMap = {
        supplier_company_name: ['supplier_name', '\uACF5\uAE09\uC790 \uC0C1\uD638', '\uACF5\uAE09\uC790\uBA85'],
        supplier_name: ['supplier_company_name', '\uACF5\uAE09\uC790 \uC0C1\uD638', '\uACF5\uAE09\uC790\uBA85'],
        customer_company_name: ['customer_name', '\uACF5\uAE09\uBC1B\uB294\uC790 \uC0C1\uD638', '\uACF5\uAE09\uBC1B\uB294\uC790\uBA85'],
        customer_name: ['customer_company_name', '\uACF5\uAE09\uBC1B\uB294\uC790 \uC0C1\uD638', '\uACF5\uAE09\uBC1B\uB294\uC790\uBA85'],
        item_name: ['\uD488\uBAA9\uBA85', '\uD488\uBAA9'],
        issue_date: ['\uC791\uC131\uC77C\uC790', '\uBC1C\uD589\uC77C\uC790'],
        transmit_date: ['\uC804\uC1A1\uC77C\uC790'],
    };
    if (aliasMap[field]) keys.push(...aliasMap[field]);
    if (aliasMap[excelName]) keys.push(...aliasMap[excelName]);
    return Array.from(new Set(keys.filter(Boolean)));
}

function isAmountColumn(column = {}) {
    const field = String(column.system_field_name || '').toLowerCase();
    const title = String(column.excel_column_name || '').trim();
    const text = `${field} ${title}`;
    return /amount|price|total|vat|tax|fee|duty|qty|\uACF5\uAE09\uAC00\uC561|\uACF5\uAE09\uAC00$|\uBD80\uAC00\uC138|\uC138\uC561|\uD569\uACC4|\uD569\uACC4\uAE08\uC561|\uAE08\uC561|\uB2E8\uAC00|\uC218\uB7C9|\uAD00\uC138|\uC218\uC218\uB8CC|\uB9E4\uCD9C|\uC815\uC0B0\uC561|\uC9C0\uAE09\uC561|\uC6D0\uCC9C\uC138|\uC785\uAE08\uC561|\uCD9C\uAE08\uC561|\uC794\uC561/.test(text);
}

function isPhoneColumn(column = {}) {
    const field = String(column.system_field_name || '').toLowerCase();
    const title = String(column.excel_column_name || '').trim();
    return /phone|tel|mobile|\uC804\uD654|\uC5F0\uB77D\uCC98|\uD578\uB4DC\uD3F0/.test(`${field} ${title}`);
}

function isBusinessNumberColumn(column = {}) {
    const field = String(column.system_field_name || '').toLowerCase();
    const title = String(column.excel_column_name || '').trim();
    return /business.*number|biz.*number|\uC0AC\uC5C5\uC790.*\uBC88\uD638|\uB4F1\uB85D\uBC88\uD638/.test(`${field} ${title}`);
}

function isDateColumn(column = {}) {
    const text = [
        column.system_field_name,
        column.system_field,
        column.field_key,
        column.key,
        column.excel_column_name,
        column.column_name,
        column.label,
    ].map((value) => String(value || '').toLowerCase()).join(' ');
    return /(^|_)date$|_date_|date|datetime|\uC77C\uC790|\uB0A0\uC9DC|\uC77C\uC2DC/.test(text);
}

function isDateTimeColumn(column = {}) {
    const field = String(column.system_field_name || column.system_field || column.field_key || column.key || '').toLowerCase();
    const text = [
        field,
        column.excel_column_name,
        column.column_name,
        column.label,
    ].map((value) => String(value || '').toLowerCase()).join(' ');
    if (field === 'transaction_time') return true;
    if (field === 'transaction_date') return true;
    return /datetime|_at$|time|\uC77C\uC2DC|\uC2DC\uAC04|\uAC70\uB798\uC77C\uC2DC|\uC2B9\uC778\uC77C\uC2DC|\uAD6C\uB9E4\uC77C\uC2DC/.test(text);
}

function pad2(value) {
    return String(value).padStart(2, '0');
}

function normalizeDateInputValue(value, keepTime = false) {
    const raw = String(value ?? '').trim();
    if (raw === '' || raw === '-') return '';
    if (!/\d{4}[-/.]?\d{1,2}[-/.]?\d{1,2}|\d{1,2}[-/.]\d{1,2}[-/.]\d{2,4}/.test(raw)) {
        return '';
    }

    const datePart = formatDateInputValue(raw);
    if (!keepTime || datePart.length < 10) {
        return datePart;
    }

    const timeMatch = raw.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
    if (!timeMatch) {
        return datePart;
    }

    return `${datePart} ${pad2(timeMatch[1])}:${timeMatch[2]}${timeMatch[3] ? `:${timeMatch[3]}` : ''}`;
}

function formatPickerDate(date) {
    if (!(date instanceof Date)) return '';
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

function formatPickerDateTime(date, includeSeconds = false) {
    if (!(date instanceof Date)) return '';
    const time = `${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
    return `${formatPickerDate(date)} ${time}${includeSeconds ? `:${pad2(date.getSeconds())}` : ''}`;
}

function applyDateToPicker(picker, value, keepTime = false) {
    if (!picker) return;
    const normalized = normalizeDateInputValue(value, keepTime);
    const parsed = normalized ? new Date(normalized.replace(' ', 'T')) : new Date();
    const date = Number.isNaN(parsed.getTime()) ? new Date() : parsed;
    picker.setDate?.(date);

    if (!keepTime) {
        picker.toggleTime?.(false);
        picker.setTime?.({ hour: null, minute: null, meridiem: null });
        return;
    }

    const hour24 = date.getHours();
    const hour12 = hour24 === 0 ? 12 : (hour24 > 12 ? hour24 - 12 : hour24);
    picker.toggleTime?.(true);
    picker.setTime?.({
        hour: hour12,
        minute: date.getMinutes(),
        meridiem: hour24 >= 12 ? 'PM' : 'AM',
    });
}

function compareFormatColumns(a, b) {
    const aOrder = Number(a?.column_order || 0);
    const bOrder = Number(b?.column_order || 0);
    const aExcel = Number(a?.excel_column_index || 0);
    const bExcel = Number(b?.excel_column_index || 0);
    const aPrimary = aOrder > 0 ? aOrder : aExcel;
    const bPrimary = bOrder > 0 ? bOrder : bExcel;
    return (aPrimary - bPrimary) || (aExcel - bExcel);
}

function normalizeCodeKey(value) {
    return String(value ?? '').trim().toUpperCase();
}

function normalizeEvidenceType(value) {
    const raw = String(value || '').trim().toUpperCase();
    if (raw === 'BANK') return CURRENT_TYPE;
    return raw;
}

async function fetchJson(url) {
    const response = await fetch(url, {
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok || json.success === false) {
        throw new Error(json.message || '\uC790\uB8CC\uB97C \uBD88\uB7EC\uC624\uC9C0 \uBABB\uD588\uC2B5\uB2C8\uB2E4.');
    }
    return json;
}

export function createFundsEvidenceEditor({ table, notify }) {
    const refs = {
        editModal: document.getElementById('evidenceSeedRowEditModal'),
        editTitle: document.getElementById('evidenceSeedRowEditModalLabel'),
        editSubtitle: document.getElementById('evidenceSeedRowEditSubtitle'),
        editFields: document.getElementById('evidenceSeedRowEditFields'),
        editId: document.getElementById('evidenceSeedRowEditId'),
        editSaveBtn: document.getElementById('evidenceSeedRowEditSaveBtn'),
    };

    let activeFormat = null;
    let editingRow = null;
    let editModal = null;
    let editPickerLayers = [];
    let codeOptions = {};

    const {
        DISPLAY_CODE_FIELDS,
        CODE_NAME_ALIASES,
        loadDisplayCodeOptions,
    } = createEvidenceSourceCodeOptions({
        API: EVIDENCE_API,
        getCodeOptions: () => codeOptions,
    });

    function codeDisplayName(field, value) {
        const code = normalizeCodeKey(value);
        if (code === '') return '';
        const group = DISPLAY_CODE_FIELDS[field] || '';
        if (group === '') return String(value ?? '');

        const found = (codeOptions[group] || []).find((row) => normalizeCodeKey(row.code) === code);
        return found?.code_name || String(value ?? '');
    }

    function codeValueForField(field, value) {
        const raw = String(value ?? '').trim();
        if (raw === '') return '';
        const group = DISPLAY_CODE_FIELDS[field] || '';
        if (group === '') return raw;

        const normalized = normalizeCodeKey(raw);
        const found = (codeOptions[group] || []).find((row) => (
            normalizeCodeKey(row.code) === normalized
            || String(row.code_name ?? '').trim() === raw
        ));
        if (found?.code) return found.code;

        return CODE_NAME_ALIASES[field]?.[raw] || raw;
    }

    const {
        BANK_REF_PICKERS,
        BANK_CODE_PICKERS,
        clearEditPickerLayers,
        registerEditPickerLayer,
    } = createEvidenceSourceEditorPickers({
        API: EVIDENCE_API,
        formatBizNumber,
        formatPhone,
        getEditPickerLayers: () => editPickerLayers,
        setEditPickerLayers: (value) => { editPickerLayers = value; },
    });

    const {
        renderEditFields,
        bindEditFieldBehaviors,
        validateRequiredEditFields,
    } = createEvidenceSourceEditorFields({
        AdminPicker,
        refs,
        BANK_REF_PICKERS,
        BANK_CODE_PICKERS,
        BANK_DEPRECATED_FORMAT_FIELDS,
        BANK_DEPRECATED_FORMAT_TITLES,
        bindCommonNumberInput,
        initCodeSelectControls,
        escapeHtml: (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;'),
        formatNumber,
        formatBizNumber,
        formatPhone,
        mapped,
        firstPayloadValue,
        columnAliasKeys,
        isDateTimeColumn,
        isDateColumn,
        isAmountColumn,
        isBusinessNumberColumn,
        isPhoneColumn,
        normalizeDateInputValue,
        parseCommonNumber,
        formatPickerDate,
        formatPickerDateTime,
        applyDateToPicker,
        compareFormatColumns,
        notify,
        codeDisplayName,
        codeValueForField,
        getCurrentType: () => CURRENT_TYPE,
        getActiveFormat: () => activeFormat,
        getEditingRow: () => editingRow,
        clearEditPickerLayers,
        registerEditPickerLayer,
    });

    const { openEditModal, saveEditingRow } = createEvidenceSourceEditorModal({
        API: EVIDENCE_API,
        refs,
        bootstrap: window.bootstrap,
        tableRef: () => table,
        mapped,
        parseCommonNumber,
        normalizeDateInputValue,
        formatBizNumber,
        formatPhone,
        evidenceTypeDisplayName: (row = {}) => row.import_type_name || row.source_type_name || '\uC785\uCD9C\uAE08(\uC740\uD589)',
        selectedTypeLabel: () => '\uC785\uCD9C\uAE08(\uC740\uD589)',
        normalizedStatus: (row = {}) => {
            if (row.deleted_at) return 'DELETED';
            return String(row.process_status || row.status || 'READY').toUpperCase();
        },
        notify,
        renderEditFields,
        bindEditFieldBehaviors,
        validateRequiredEditFields,
        updateSummary: () => {},
        refreshEvidenceTypeCounts: async () => {},
        getCurrentType: () => CURRENT_TYPE,
        getActiveFormat: () => activeFormat,
        getEditingRow: () => editingRow,
        setEditingRow: (value) => { editingRow = value; },
        getEditModal: () => editModal,
        setEditModal: (value) => { editModal = value; },
        getLastRows: () => [],
        getSelectedIds: () => new Set(),
    });

    refs.editSaveBtn?.addEventListener('click', () => {
        void saveEditingRow().catch((error) => notify('error', error.message));
    });

    async function loadActiveFormat(row = {}) {
        const rowColumns = Array.isArray(row.format_columns) ? row.format_columns : [];
        if (rowColumns.length > 0) {
            activeFormat = {
                id: row.format_id || '',
                data_type: CURRENT_TYPE,
                format_name: row.format_name || '',
                columns: rowColumns,
            };
            return activeFormat;
        }

        activeFormat = null;
        return activeFormat;
    }

    async function fetchEvidenceRow(evidenceId) {
        const filters = encodeURIComponent(JSON.stringify([{ field: 'id', value: evidenceId }]));
        const json = await fetchJson(`${EVIDENCE_API.seedRows}?import_type=${encodeURIComponent(CURRENT_TYPE)}&filters=${filters}`);
        const rows = Array.isArray(json.data) ? json.data : [];
        const row = rows.find((item) => String(item.id || '') === String(evidenceId)) || rows[0] || null;
        if (!row) {
            throw new Error('\uC99D\uBE59\uC6D0\uBCF8\uC744 \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.');
        }
        row.import_type = normalizeEvidenceType(row.import_type || row.source_type || CURRENT_TYPE);
        row.source_type = normalizeEvidenceType(row.source_type || row.import_type || CURRENT_TYPE);
        return row;
    }

    async function open(evidenceId) {
        if (!refs.editModal) {
            notify('error', '\uC99D\uBE59\uC6D0\uBCF8 \uC218\uC815 \uBAA8\uB2EC\uC744 \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.');
            return;
        }
        if (!evidenceId) {
            notify('warning', '\uC5F0\uACB0\uB41C \uC99D\uBE59\uC6D0\uBCF8\uC774 \uC5C6\uC2B5\uB2C8\uB2E4.');
            return;
        }

        ['fundsBankSourceModal', 'fundsBankManageModal'].forEach((id) => {
            const modal = document.getElementById(id);
            if (modal?.classList.contains('show')) {
                window.bootstrap?.Modal.getInstance(modal)?.hide();
            }
        });
        if (refs.editTitle) {
            refs.editTitle.textContent = '\uC785\uCD9C\uAE08(\uC740\uD589) \uC6D0\uBCF8\uC790\uB8CC \uC218\uC815';
        }
        if (refs.editSubtitle) {
            refs.editSubtitle.textContent = '\uC99D\uBE59\uC6D0\uBCF8 \uC815\uBCF4\uB97C \uBD88\uB7EC\uC624\uB294 \uC911\uC785\uB2C8\uB2E4.';
        }
        if (refs.editFields) {
            refs.editFields.innerHTML = '<div class="text-muted py-4 text-center">\uC99D\uBE59\uC6D0\uBCF8 \uC815\uBCF4\uB97C \uBD88\uB7EC\uC624\uB294 \uC911\uC785\uB2C8\uB2E4.</div>';
        }
        if (refs.editSaveBtn) refs.editSaveBtn.disabled = true;
        editModal = window.bootstrap?.Modal.getOrCreateInstance(refs.editModal) || editModal;
        editModal?.show();

        let row = null;
        try {
            await loadDisplayCodeOptions().catch(() => {});
            row = await fetchEvidenceRow(evidenceId);
            await loadActiveFormat(row);
            if (!activeFormat || !Array.isArray(activeFormat.columns) || activeFormat.columns.length === 0) {
                throw new Error('\uC99D\uBE59\uC6D0\uBCF8 \uC591\uC2DD \uC815\uBCF4\uB97C \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.');
            }
        } catch (error) {
            editModal?.hide();
            throw error;
        }

        openEditModal(row);
        if (refs.editTitle) {
            refs.editTitle.textContent = '\uC785\uCD9C\uAE08(\uC740\uD589) \uC6D0\uBCF8\uC790\uB8CC \uC218\uC815';
        }
        if (refs.editSubtitle) {
            refs.editSubtitle.textContent = [
                `\uC21C\uBC88 ${row.row_no || '-'}`,
                row.import_type_name || '\uC785\uCD9C\uAE08(\uC740\uD589)',
                row.process_status || row.status || '-',
            ].join(' / ');
        }
    }

    function warmup() {
        void loadDisplayCodeOptions().catch(() => {});
    }

    return { open, warmup };
}

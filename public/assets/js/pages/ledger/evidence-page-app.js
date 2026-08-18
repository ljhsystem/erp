import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { fetchDataTableMetaColumns, readDataTableSettingsState, resolveDataTableColumnDisplayName, resolveDataTableColumnRequirementPolicy } from '/public/assets/js/common/datatable/dataTableSettings.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import {
    bindNumberInput as bindCommonNumberInput,
    formatDateInputValue,
    formatBizNumber,
    formatPhone,
    parseNumber as parseCommonNumber,
} from '/public/assets/js/common/format.js';
import { createEvidenceStatusModule } from '/public/assets/js/pages/ledger/evidence-list/status.js';
import { createEvidenceSearchModule } from '/public/assets/js/pages/ledger/evidence-list/search.js';
import { createEvidenceTableModule } from '/public/assets/js/pages/ledger/evidence-list/table.js';
import { createEvidenceExcelModule } from '/public/assets/js/pages/ledger/evidence-list/excel.js';

export function bootEvidencePage(options = {}) {
    const createExcelManagerSettingsCoreLazy = async (...args) => {
        const module = await import('/public/assets/js/components/excel-manager/index.js');
        return module.createExcelManagerSettingsCore(...args);
    };
    const API = {
        seedRows: '/api/import/evidences',
        fields: '/api/import/fields',
        dataTableColumns: '/api/settings/system/data-table-columns',
        preview: '/api/import/preview',
        upload: '/api/import/evidence-upload',
        uploadCancel: '/api/import/evidence-upload/cancel',
        download: '/api/import/evidences/download',
        trash: '/api/import/evidences/trash',
        deleteRows: '/api/import/evidences/delete',
        reorder: '/api/import/evidences/reorder',
        saveSeedRow: '/api/import/evidence/save',
        createEvidence: '/api/import/evidence/create',
        bulkSaveSeedRows: '/api/import/evidences/bulk-save',
        clientSearch: '/api/settings/base-info/client/search-picker',
        clientDetail: '/api/settings/base-info/client/detail',
        projectSearch: '/api/settings/base-info/project/search-picker',
        projectDetail: '/api/settings/base-info/project/detail',
        employeeSearch: '/api/settings/organization/employee/search-picker',
        employeeDetail: '/api/settings/organization/employee/detail',
        workTeamList: '/api/settings/base-info/work-team/list',
        workTeamDetail: '/api/settings/base-info/work-team/detail',
        bankAccountSearch: '/api/settings/base-info/bank-account/search-picker',
        bankAccountDetail: '/api/settings/base-info/bank-account/detail',
        cardSearch: '/api/settings/base-info/card/search-picker',
        cardDetail: '/api/settings/base-info/card/detail',
        codeList: '/api/settings/system/code/list',
    };
    const MAX_EXCEL_UPLOAD_BYTES = 25 * 1024 * 1024;
    const evidenceMetaColumnsCache = new Map();

    const DISPLAY_CODE_FIELDS = {
        business_unit: 'BUSINESS_UNIT',
        operation_type: 'OPERATION_TYPE',
        transaction_direction: 'TRANSACTION_DIRECTION',
        source_type: 'SOURCE_TYPE',
        import_type: 'IMPORT_TYPE',
    };
    const CODE_NAME_ALIASES = {
        transaction_direction: {
            FUND: '자금',
            IN: '자금',
            OUT: '자금',
            INCOME: '수익',
            EXPENSE: '비용',
            PURCHASE: '비용',
            SALES: '수익',
        },
    };
    const EVIDENCE_TYPE_COLUMN_CONFIG = {};
    const pageRoot = document.getElementById('ledgerDataStatusPage');
    const initialPageReady = pageRoot?.dataset?.pageReady !== '0';

    function clearGlobalLoadingOverlay() {
        try {
            window.AppLoading?.markReady?.();
            window.PageLoadingSpinner?.markReady?.();
            window.AppCore?.hideLoading?.('all');
            window.AppCore?.hideGlobalLoading?.('all');
        } catch (_error) {
            // Ignore overlay cleanup failures and fall back to direct DOM reset below.
        }

        const overlay = document.getElementById('global-loading-overlay');
        if (!overlay) {
            return;
        }

        overlay.classList.remove('is-active');
        overlay.style.display = 'none';
        overlay.removeAttribute('aria-busy');
    }

    function setPageLoadingState(isLoading) {
        if (!pageRoot) {
            return;
        }

        pageRoot.classList.toggle('is-page-loading', isLoading);
        pageRoot.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function showPageTransitionLoading(message = '\uC790\uB8CC\uC720\uD615 \uD398\uC774\uC9C0 \uC774\uB3D9 \uC911\uC785\uB2C8\uB2E4.') {
        try {
            if (typeof window.AppLoading?.show === 'function') {
                window.AppLoading.show(message);
                return;
            }
            if (typeof window.AppCore?.showLoading === 'function') {
                window.AppCore.showLoading(message);
            }
        } catch (_error) {
            // Ignore loading overlay failures and continue navigation.
        }
    }

    function readEvidenceTypePolicies() {
        const script = document.getElementById('ledgerEvidenceTypePolicies');
        if (!script) return [];

        try {
            const parsed = JSON.parse(script.textContent || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.warn('[data-status] failed to parse evidence type policies:', error);
            return [];
        }
    }

    function readEvidenceTypePageMap() {
        const script = document.getElementById('ledgerEvidenceTypePageMap');
        if (!script) return {};

        try {
            const parsed = JSON.parse(script.textContent || '{}');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            console.warn('[data-status] failed to parse evidence type page map:', error);
            return {};
        }
    }

    function normalizeEvidenceTypePolicies(rows = []) {
        return rows
            .map((row) => {
                const rawCode = String(row?.code || row?.import_type || row?.type || row?.value || '').trim().toUpperCase();
                if (!rawCode || rawCode.startsWith('__')) return null;

                const aliases = Array.isArray(row?.aliases)
                    ? row.aliases
                        .map((value) => String(value || '').trim().toUpperCase())
                        .filter((value) => value !== '' && value !== rawCode)
                    : [];
                const dateOptions = Array.isArray(row?.date_options)
                    ? row.date_options
                        .map((option) => ({
                            value: String(option?.value || '').trim(),
                            label: String(option?.label || '').trim(),
                        }))
                        .filter((option) => option.value !== '')
                    : [];

                return {
                    code: rawCode,
                    aliases,
                    label: String(row?.label || row?.code_name || rawCode).trim() || rawCode,
                    pageReady: row?.page_ready !== false,
                    pageNotice: String(row?.page_notice || '').trim(),
                    pageStatusLabel: String(row?.page_status_label || '').trim(),
                    excelTemplate: String(row?.excel_template || row?.excelTemplate || rawCode.toLowerCase()).trim(),
                    dateOptions: dateOptions.length > 0 ? dateOptions : [DEFAULT_DATE_OPTION],
                    metaDomain: String(row?.meta_domain || row?.metaDomain || '').trim(),
                    summaryBucket: String(row?.summary_bucket || row?.summaryBucket || 'evidence').trim() || 'evidence',
                    dateCandidateKeys: Array.isArray(row?.date_candidate_keys)
                        ? row.date_candidate_keys.map((value) => String(value || '').trim()).filter(Boolean)
                        : [],
                    sortTargetKeys: Array.isArray(row?.sort_target_keys)
                        ? row.sort_target_keys.map((value) => String(value || '').trim()).filter(Boolean)
                        : [],
                    transactionWorkflowRequired: row?.transaction_workflow_required !== false,
                    readOnly: row?.read_only === true,
                    excelManagerMode: String(row?.excel_manager_mode || row?.excelManagerMode || 'custom').trim() || 'custom',
                    excelManagerDomain: String(row?.excel_manager_domain || row?.excelManagerDomain || '').trim(),
                    sourceKeyAliases: row?.source_key_aliases && typeof row.source_key_aliases === 'object'
                        ? { ...row.source_key_aliases }
                        : {},
                    modalPreset: String(row?.modal_preset || row?.modalPreset || 'default').trim() || 'default',
                    deprecatedFormatFields: Array.isArray(row?.deprecated_format_fields)
                        ? row.deprecated_format_fields.map((value) => String(value || '').trim()).filter(Boolean)
                        : [],
                    deprecatedFormatTitles: Array.isArray(row?.deprecated_format_titles)
                        ? row.deprecated_format_titles.map((value) => String(value || '').replace(/\s+/g, '').trim()).filter(Boolean)
                        : [],
                };
            })
            .filter(Boolean);
    }

    const DEFAULT_DATE_OPTION = { value: 'created_at', label: '\uB4F1\uB85D\uC77C\uC2DC' };
    function evidenceTypePolicy(value) {
        const raw = String(value || '').trim().toUpperCase();
        if (raw === '') return null;
        return SERVER_TYPE_POLICY_MAP.get(raw) || SERVER_TYPE_ALIAS_MAP.get(raw) || null;
    }

    function currentTypePolicy(type = state.currentType) {
        return evidenceTypePolicy(type || '') || null;
    }

    function currentPageReady(type = state.currentType) {
        return currentTypePolicy(type)?.pageReady !== false;
    }

    function normalizeEvidenceType(value) {
        const raw = String(value || '').trim().toUpperCase();
        return evidenceTypePolicy(raw)?.code || raw;
    }

    const suppliedPolicies = Array.isArray(options.evidenceTypePolicies) ? options.evidenceTypePolicies : [];
    const normalizedPolicies = normalizeEvidenceTypePolicies(suppliedPolicies.length > 0 ? suppliedPolicies : readEvidenceTypePolicies());
    const fallbackPolicies = options.editorOnly === true
        ? []
        : normalizeEvidenceTypePolicies([{
            code: options.initialType || 'BANK_TRANSACTION',
            label: options.initialTypeLabel || options.initialType || '증빙원본',
            page_ready: true,
        }]);
    const SERVER_TYPE_POLICIES = normalizedPolicies.length > 0 ? normalizedPolicies : fallbackPolicies;
    const SERVER_TYPE_POLICY_MAP = new Map(SERVER_TYPE_POLICIES.map((policy) => [policy.code, policy]));
    const SERVER_TYPE_ALIAS_MAP = new Map(
        SERVER_TYPE_POLICIES.flatMap((policy) => (policy.aliases || []).map((alias) => [alias, policy]))
    );

    function defaultEvidenceTypeCode() {
        return SERVER_TYPE_POLICIES[0]?.code || '';
    }

    const state = {
        refs: {
            typeSelect: document.getElementById('evidenceTypeSelect'),
            typeTabs: document.getElementById('evidenceTypeTabs'),
            excelLabel: document.getElementById('dataExcelTemplateLabel'),
            excelForm: document.getElementById('dataExcelForm'),
            excelModal: document.getElementById('dataExcelModal'),
            trashBtn: document.getElementById('btnOpenEvidenceTrash'),
            trashModal: document.getElementById('evidenceStatusTrashModal'),
            templateBtn: document.getElementById('btnDownloadEvidenceTemplate'),
            dataDownloadBtn: document.getElementById('btnDownloadEvidenceData'),
            editModal: document.getElementById('evidenceSeedRowEditModal'),
            editTitle: document.getElementById('evidenceSeedRowEditModalLabel'),
            editSubtitle: document.getElementById('evidenceSeedRowEditSubtitle'),
            editFields: document.getElementById('evidenceSeedRowEditFields'),
            editId: document.getElementById('evidenceSeedRowEditId'),
            editSaveBtn: document.getElementById('evidenceSeedRowEditSaveBtn'),
            bulkModal: document.getElementById('evidenceBulkEditModal'),
            bulkSubtitle: document.getElementById('evidenceBulkEditSubtitle'),
            bulkFields: document.getElementById('evidenceBulkEditFields'),
            bulkSaveBtn: document.getElementById('evidenceBulkEditSaveBtn'),
            typeSelectCount: document.getElementById('evidenceTypeSelectCount'),
        },
        initialType: normalizeEvidenceType(options.initialType || pageRoot?.dataset?.initialEvidenceType || ''),
        fixedType: normalizeEvidenceType(options.fixedType || pageRoot?.dataset?.fixedEvidenceType || ''),
        currentType: '',
        table: null,
        lastRows: [],
        codeOptions: {},
        activeFormat: null,
        editModal: null,
        editingRow: null,
        editPickerLayers: [],
        bulkModal: null,
        selectedIds: new Set(),
        evidenceTypeCounts: {},
        evidenceTotalSummary: {
            total: 0,
            bank: 0,
            evidence: 0,
        },
        uploadingExcel: false,
        skipExcelUploadCloseConfirm: false,
        excelModalCloseRequested: false,
        excelUploadCanceled: false,
        excelUploadAbortController: null,
        excelUploadCancelToken: '',
        excelUploadPreviewToken: '',
        excelManagerSettingsCore: null,
    };

    function evidenceMetaDomain(type = '') {
        return evidenceTypePolicy(type || state.currentType || '')?.metaDomain || '';
    }
    const SERVER_TYPE_PAGE_MAP = Object.fromEntries(
        Object.entries(readEvidenceTypePageMap()).map(([type, path]) => [normalizeEvidenceType(type), String(path || '').trim()])
    );

    const evidenceFieldOptionsCache = new Map();
    let evidenceRefPickerForColumnLikeRuntime = () => null;
    const evidenceModalFieldOptionsCache = new Map();

    const BANK_CODE_PICKERS = {
        business_unit: {
            codeGroup: 'BUSINESS_UNIT',
            emptyLabel: '\uC0AC\uC5C5\uBD80\uB97C \uC120\uD0DD\uD558\uC138\uC694.',
            titles: ['\uC0AC\uC5C5\uBD80'],
        },
        operation_type: {
            codeGroup: 'OPERATION_TYPE',
            emptyLabel: '\uC5C5\uBB34\uC720\uD615\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            titles: ['\uC785\uCD9C\uAE08\uC720\uD615'],
        },
        transaction_direction: {
            codeGroup: 'TRANSACTION_DIRECTION',
            emptyLabel: '\uAC70\uB798\uAD6C\uBD84\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            titles: ['\uAC70\uB798\uAD6C\uBD84', '\uAC70\uB798\uBC29\uD5A5'],
        },
    };
    const BANK_DEPRECATED_FORMAT_FIELDS = new Set([
        'voucher_date',
        'summary_text',
        'note',
        'voucher_memo',
        'header_row_no',
        'line_no',
        'account_id',
        'debit',
        'credit',
        'line_summary',
    ]);
    const BANK_DEPRECATED_FORMAT_TITLES = new Set([
        '\uC804\uD45C\uC77C\uC790',
        '\uC804\uD45C\uC801\uC694',
        '\uC804\uD45C\uBA54\uBAA8',
        '\uC804\uD45C \uBE44\uACE0',
        '\uD5E4\uB354 \uC21C\uBC88',
        '\uB77C\uC778\uBC88\uD638',
        '\uACC4\uC815\uACFC\uBAA9',
        '\uCC28\uBCC0',
        '\uCC28\uBCC0\uAE08\uC561',
        '\uCC28\uBCC0 \uAE08\uC561',
        '\uB300\uBCC0',
        '\uB300\uBCC0\uAE08\uC561',
        '\uB300\uBCC0 \uAE08\uC561',
        '\uC801\uC694',
    ]);

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function mapped(row = {}) {
        return row && typeof row === 'object' ? row : {};
    }

    function valueText(value) {
        if (value === undefined || value === null) return '';
        if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
            const text = String(value).trim();
            if (isNoneSelectionValue(text)) return '';
            return text === '[object Object]' ? '' : text;
        }
        if (Array.isArray(value)) {
            return value.map((item) => valueText(item)).filter(Boolean).join(', ');
        }
        if (typeof value === 'object') {
            for (const key of ['text', 'label', 'name', 'value', 'code_name', 'code', 'client_name', 'company_name', 'project_name', 'employee_name', 'account_name', 'bank_name']) {
                const text = valueText(value[key]);
                if (text !== '') return text;
            }
            return '';
        }
        return String(value).trim();
    }

    function isNoneSelectionValue(value) {
        const text = String(value ?? '').trim();
        if (text === '') return true;
        return [
            '_none_',
            '__none',
            'none__',
            '--none--',
            'none',
            '\uC120\uD0DD\uD558\uC138\uC694.',
        ].includes(text);
    }

    function isEmptySelectionLabel(value) {
        const text = String(value ?? '').trim();
        if (isNoneSelectionValue(text)) return true;
        return [
            '\uC120\uD0DD',
            '\uC790\uB8CC\uC720\uD615\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            '\uAC70\uB798\uCC98\uB97C \uC120\uD0DD\uD558\uC138\uC694.',
            '\uCF54\uB4DC\uB97C \uC120\uD0DD\uD558\uC138\uC694.',
            '\uD504\uB85C\uC81D\uD2B8\uB97C \uC120\uD0DD\uD558\uC138\uC694.',
            '\uC0C1\uB300\uBC29\uBA85\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            '\uACC4\uC815\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            '\uC0AC\uC5C5\uBD80\uB97C \uC120\uD0DD\uD558\uC138\uC694.',
            '\uAC70\uB798\uBC29\uD5A5\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            '\uAC70\uB798\uAD6C\uBD84\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            '\uCD9C\uAE08\uACC4\uC88C\uB97C \uC120\uD0DD\uD558\uC138\uC694.',
            '\uACC4\uC88C\uAD6C\uBD84\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            '\uB300\uC0C1\uAD6C\uBD84\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            '\uC99D\uBE59\uC0DD\uC131\uC720\uD615\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
        ].includes(text);
    }

    function selectValueForSave(input) {
        const value = String(input?.value ?? '').trim();
        return isNoneSelectionValue(value) ? '' : value;
    }

    function selectTextForSave(input, { includeCurrentText = false } = {}) {
        if (!input) return '';
        if (isNoneSelectionValue(input.value)) return '';
        const currentText = String(includeCurrentText ? (input.dataset.refCurrentText || '') : '').trim();
        const selectedText = String(input.selectedOptions?.[0]?.textContent || '').trim();
        const selectedValue = String(input.value || '').trim();
        const text = String(
            input.dataset.refSelectedText
            || (
                includeCurrentText
                && currentText !== ''
                && (selectedText === '' || selectedText === selectedValue)
                    ? currentText
                    : selectedText
            )
            || currentText
            || ''
        ).trim();
        return isEmptySelectionLabel(text) ? '' : text;
    }

    function firstPayloadValue(payload = {}, keys = []) {
        for (const key of keys) {
            if (!key) continue;
            const value = payload[key];
            if (valueText(value) !== '') {
                return value;
            }
        }
        return undefined;
    }

    const BUSINESS_ONLY_RAW_FIELD_ALIASES = Object.freeze({
        written_date: 'raw_written_date',
        write_date: 'raw_written_date',
        issue_date: 'raw_issue_date',
        transmit_date: 'raw_transmit_date',
        approval_number: 'raw_approval_no',
        supplier_business_number: 'raw_supplier_business_number',
        supplier_branch_number: 'raw_supplier_branch_no',
        supplier_company_name: 'raw_supplier_company_name',
        supplier_name: 'raw_supplier_company_name',
        supplier_ceo_name: 'raw_supplier_ceo_name',
        supplier_address: 'raw_supplier_address',
        supplier_email: 'raw_supplier_email',
        customer_business_number: 'raw_customer_business_number',
        customer_branch_number: 'raw_customer_branch_no',
        customer_company_name: 'raw_customer_company_name',
        customer_name: 'raw_customer_company_name',
        customer_ceo_name: 'raw_customer_ceo_name',
        customer_address: 'raw_customer_address',
        customer_email_1: 'raw_customer_email1',
        customer_email_2: 'raw_customer_email2',
        tax_invoice_category: 'raw_invoice_category',
        tax_invoice_type: 'raw_invoice_kind',
        issue_type: 'raw_issue_type',
        receipt_claim_type: 'raw_claim_type',
        supply_amount: 'raw_supply_amount',
        vat_amount: 'raw_vat_amount',
        total_amount: 'raw_total_amount',
        note: 'raw_note',
        description: 'raw_note',
        item_date: 'raw_item_date',
        item_name: 'raw_item_name',
        item_spec: 'raw_item_spec',
        item_qty: 'raw_item_quantity',
        quantity: 'raw_item_quantity',
        item_price: 'raw_item_unit_price',
        unit_price: 'raw_item_unit_price',
        item_supply_amount: 'raw_item_supply_amount',
        item_vat_amount: 'raw_item_tax_amount',
        item_note: 'raw_item_note',
    });

    const BUSINESS_ONLY_RAW_SOURCE_ALIASES = Object.freeze({
        raw_written_date: ['written_date', 'write_date', '작성일자', '작성일'],
        raw_issue_date: ['issue_date', '발급일자', '발행일자'],
        raw_transmit_date: ['transmit_date', '전송일자'],
        raw_approval_no: ['approval_number', '승인번호'],
        raw_supplier_business_number: ['supplier_business_number', '공급자사업자등록번호'],
        raw_supplier_branch_no: ['supplier_branch_number', '공급자종사업장번호'],
        raw_supplier_company_name: ['supplier_company_name', 'supplier_name', '공급자상호', '공급자명'],
        raw_supplier_ceo_name: ['supplier_ceo_name', '공급자대표자명'],
        raw_supplier_address: ['supplier_address', '공급자주소'],
        raw_supplier_email: ['supplier_email', '공급자이메일'],
        raw_customer_business_number: ['customer_business_number', '공급받는자사업자등록번호'],
        raw_customer_branch_no: ['customer_branch_number', '공급받는자종사업장번호'],
        raw_customer_company_name: ['customer_company_name', 'customer_name', '공급받는자상호', '공급받는자명'],
        raw_customer_ceo_name: ['customer_ceo_name', '공급받는자대표자명'],
        raw_customer_address: ['customer_address', '공급받는자주소'],
        raw_customer_email1: ['customer_email_1', '공급받는자이메일1'],
        raw_customer_email2: ['customer_email_2', '공급받는자이메일2'],
        raw_invoice_category: ['tax_invoice_category', '전자세금계산서분류'],
        raw_invoice_kind: ['tax_invoice_type', '전자세금계산서종류'],
        raw_issue_type: ['issue_type', '발급유형'],
        raw_claim_type: ['receipt_claim_type', '영수청구구분'],
        raw_supply_amount: ['supply_amount', '공급가액'],
        raw_vat_amount: ['vat_amount', '세액'],
        raw_total_amount: ['total_amount', '합계금액'],
        raw_note: ['note', 'description', '비고'],
        raw_item_date: ['item_date', '품목일자'],
        raw_item_name: ['item_name', '품목명', '품목'],
        raw_item_spec: ['item_spec', '품목규격', '규격'],
        raw_item_quantity: ['item_qty', 'quantity', '품목수량', '수량'],
        raw_item_unit_price: ['item_price', 'unit_price', '품목단가', '단가'],
        raw_item_supply_amount: ['item_supply_amount', '품목공급가액'],
        raw_item_tax_amount: ['item_vat_amount', '품목세액', '품목부가세액'],
        raw_item_note: ['item_note', '품목비고'],
    });

    function businessOnlyFieldKey(key = '') {
        const normalized = String(key || '').trim();
        return BUSINESS_ONLY_RAW_FIELD_ALIASES[normalized] || normalized;
    }

    function isBusinessOnlyEvidenceType(row = {}) {
        const type = normalizeEvidenceType(
            row?.import_type
            || row?.source_type
            || state.currentType
            || ''
        );
        return String(evidenceTypePolicy(type)?.modalPreset || '').trim().toLowerCase() === 'business_only';
    }

    function columnAliasKeys(column = {}) {
        const field = String(column.system_field_name || '').trim();
        const excelName = String(column.excel_column_name || '').trim();
        const keys = [field, excelName];
        const aliasMap = {
            external_key: ['source_key', 'approval_number', 'approval_no'],
            source_key: ['external_key', 'approval_number', 'approval_no'],
            supplier_company_name: ['supplier_name', '\uACF5\uAE09\uC790\uBA85', '\uACF5\uAE09\uC790'],
            supplier_name: ['supplier_company_name', '\uACF5\uAE09\uC790\uBA85', '\uACF5\uAE09\uC790'],
            customer_company_name: ['customer_name', '\uACE0\uAC1D\uC0AC\uBA85', '\uACE0\uAC1D\uC0AC'],
            customer_name: ['customer_company_name', '\uACE0\uAC1D\uC0AC\uBA85', '\uACE0\uAC1D\uC0AC'],
            item_name: ['\uD488\uBAA9\uBA85', '\uD488\uBAA9'],
            issue_date: ['\uC791\uC131\uC77C\uC790', '\uC791\uC131\uC77C'],
            transmit_date: ['\uC804\uC1A1\uC77C\uC790'],
            raw_item_date: ['item_date', '\uD488\uBAA9\uC77C\uC790'],
            raw_item_name: ['item_name', '\uD488\uBAA9\uBA85', '\uD488\uBAA9'],
            raw_item_spec: ['item_spec', '\uD488\uBAA9\uADDC\uACA9', '\uADDC\uACA9'],
            raw_item_quantity: ['item_qty', 'quantity', '\uD488\uBAA9\uC218\uB7C9', '\uC218\uB7C9'],
            raw_item_unit_price: ['item_price', 'unit_price', '\uD488\uBAA9\uB2E8\uAC00', '\uB2E8\uAC00'],
            raw_item_supply_amount: ['item_supply_amount', '\uD488\uBAA9\uACF5\uAE09\uAC00\uC561'],
            raw_item_tax_amount: ['item_vat_amount', '\uD488\uBAA9\uC138\uC561', '\uD488\uBAA9\uBD80\uAC00\uC138\uC561'],
            raw_item_note: ['item_note', '\uD488\uBAA9\uBE44\uACE0'],
        };
        if (aliasMap[field]) keys.push(...aliasMap[field]);
        if (aliasMap[excelName]) keys.push(...aliasMap[excelName]);
        return Array.from(new Set(keys.filter(Boolean)));
    }

    function amount(value) {
        const number = Number(valueText(value || '0').replaceAll(',', ''));
        return Number.isFinite(number) ? number : 0;
    }

    function formatNumber(value) {
        return amount(value).toLocaleString('ko-KR');
    }

    function isAmountColumn(column = {}) {
        const field = String(column.system_field_name || '').toLowerCase();
        const title = String(column.excel_column_name || '').trim();
        const text = `${field} ${title}`;
        return /amount|price|total|vat|tax|fee|duty|qty|quantity|\uACF5\uAE09\uAC00|\uD569\uACC4|\uBD80\uAC00\uC138|\uC218\uC218\uB8CC|\uAE08\uC561|\uC785\uAE08|\uCD9C\uAE08|\uC794\uC561/.test(text);
    }

    function isPhoneColumn(column = {}) {
        const field = String(column.system_field_name || '').toLowerCase();
        const title = String(column.excel_column_name || '').trim();
        return /phone|tel|mobile|fax|\uC804\uD654\uBC88\uD638|\uD734\uB300\uD3F0|\uC5F0\uB77D\uCC98/.test(`${field} ${title}`);
    }

    function isBusinessNumberColumn(column = {}) {
        const field = String(column.system_field_name || '').toLowerCase();
        const title = String(column.excel_column_name || '').replace(/\s+/g, '').toLowerCase();
        const text = `${field} ${title}`;
        return /business_number|biz_number|businessnumber/.test(text)
            || title.includes('\uc0ac\uc5c5\uc790\ub4f1\ub85d\ubc88\ud638')
            || title.includes('\uc0ac\uc5c5\uc790\ubc88\ud638');
    }

    function clientName(row = {}) {
        const payload = mapped(row);
        return valueText(row.client_name)
            || valueText(payload.client_company_name)
            || valueText(payload.supplier_name)
            || valueText(payload.customer_name)
            || valueText(payload.supplier_company_name)
            || valueText(payload.customer_company_name)
            || valueText(payload['\uACF5\uAE09\uC790\uBA85'])
            || valueText(payload['\uACE0\uAC1D\uC0AC\uBA85'])
            || valueText(payload.employee_name)
            || valueText(payload.client_business_number)
            || '';
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        if (type === 'error') {
            console.error(message);
        } else {
            alert(message);
        }
    }

    function badge(text, cls = 'text-bg-light') {
        return `<span class="badge ${cls}">${escapeHtml(text)}</span>`;
    }

    function textColumn(data, title, renderer, options = {}) {
        return {
            settingsKey: typeof data === 'string' ? data : '',
            settingsTitle: title,
            data,
            title,
            defaultContent: '',
            className: 'evidence-data-column text-start text-nowrap',
            headerClassName: 'evidence-data-column text-start text-nowrap',
            render: (_value, _type, row) => escapeHtml(renderer(row)),
            ...options,
        };
    }

    function moneyColumn(data, title, renderer) {
        return {
            settingsKey: typeof data === 'string' ? data : '',
            settingsTitle: title,
            data,
            title,
            className: 'evidence-data-column text-end text-nowrap',
            headerClassName: 'evidence-data-column text-start text-nowrap',
            render: (_value, _type, row) => formatNumber(renderer(row)),
        };
    }

    function evidenceFieldValue(row = {}, field = '') {
        const key = String(field || '').trim();
        if (!key) return '';

        if (valueText(row?.[key]) !== '') {
            return row[key];
        }

        const payload = mapped(row);
        if (valueText(payload?.[key]) !== '') {
            return payload[key];
        }

        const fallbacks = {
            client_name: [clientName(row)],
            total_amount: [payload?.amount, row?.amount],
            supply_amount: [payload?.supply_price, row?.supply_price],
            vat_amount: [payload?.tax_amount, row?.tax_amount],
        }[key] || [];

        for (const candidate of fallbacks) {
            if (valueText(candidate) !== '') {
                return candidate;
            }
        }

        return '';
    }

        async function loadEvidenceFieldOptions(type) {
        const normalizedType = normalizeEvidenceType(type || '');
        if (!normalizedType) return [];
        if (evidenceFieldOptionsCache.has(normalizedType)) {
            return evidenceFieldOptionsCache.get(normalizedType) || [];
        }

        let data = [];
        try {
            const response = await fetch(`${API.fields}?data_type=${encodeURIComponent(normalizedType)}`, {
                credentials: 'include',
            });
            const json = await response.json().catch(() => ({}));
            data = response.ok && json?.success && Array.isArray(json.data) ? json.data : [];
        } catch (_error) {
            data = [];
        }
        evidenceFieldOptionsCache.set(normalizedType, data);
        return data;
    }

    async function loadEvidenceModalFieldOptions(type) {
        const normalizedType = normalizeEvidenceType(type || '');
        const domain = evidenceMetaDomain(normalizedType);
        if (!normalizedType || !domain) return [];
        if (evidenceModalFieldOptionsCache.has(domain)) {
            return evidenceModalFieldOptionsCache.get(domain) || [];
        }

        let data = [];
        try {
            const response = await fetch(`${API.dataTableColumns}?domain=${encodeURIComponent(domain)}`, {
                credentials: 'include',
            });
            const json = await response.json().catch(() => ({}));
            data = response.ok && json?.success && Array.isArray(json.data) ? json.data : [];
        } catch (_error) {
            data = [];
        }

        const columns = data
            .map((column, index) => {
                const key = String(column?.key || column?.value || '').trim();
                if (!key) return null;
                return {
                    value: key,
                    label: String(column?.label || column?.title || key).trim() || key,
                    group: '',
                    table: String(column?.table || '').trim(),
                    column: key,
                    data_type: String(column?.data_type || 'varchar').trim().toLowerCase(),
                    is_nullable: String(column?.is_nullable || 'YES').trim().toUpperCase(),
                    ordinal_position: Number(column?.ordinal_position || column?.settings_order || index + 1) || (index + 1),
                };
            })
            .filter(Boolean)
            .sort((left, right) => left.ordinal_position - right.ordinal_position);

        evidenceModalFieldOptionsCache.set(domain, columns);
        return columns;
    }

    function mergeEvidenceModalFieldOptions(sourceOptions = [], databaseOptions = []) {
        if (!Array.isArray(databaseOptions) || databaseOptions.length === 0) {
            return Array.isArray(sourceOptions) ? sourceOptions : [];
        }

        const sourceByKey = new Map(
            (Array.isArray(sourceOptions) ? sourceOptions : [])
                .map((field) => [String(field?.value || field?.column || '').trim(), field])
                .filter(([key]) => key !== '')
        );

        return databaseOptions.map((databaseField) => {
            const key = String(databaseField.value || databaseField.column || '').trim();
            const sourceField = sourceByKey.get(key) || {};
            return {
                ...databaseField,
                ...sourceField,
                value: key,
                column: key,
                table: databaseField.table || sourceField.table || '',
                ordinal_position: databaseField.ordinal_position,
                data_type: databaseField.data_type || sourceField.data_type || 'varchar',
                is_nullable: databaseField.is_nullable || sourceField.is_nullable || 'YES',
            };
        });
    }
    function fieldOptionToModalColumn(field = {}, index = 0) {
        const key = String(field.value || field.column || '').trim();
        if (!key) return null;

        return {
            original_column_key: key,
            system_field_name: key,
            excel_column_name: String(field.label || key).trim() || key,
            system_field_group: String(field.group || '').trim(),
            source_table: String(field.table || '').trim(),
            source_column: String(field.column || key).trim(),
            code_group: String(field.code_group || '').trim(),
            data_type: String(field.data_type || 'varchar').trim().toLowerCase(),
            is_nullable: String(field.is_nullable || 'YES').trim().toUpperCase(),
            is_required: Number(field.is_required || 0) === 1 ? 1 : 0,
            is_reference_column: /_id$/i.test(key) ? 1 : 0,
            display_order: index + 1,
            column_order: Number(field.ordinal_position || 0),
            excel_column_index: index + 1,
            is_visible: 1,
        };
    }

    function formatEvidenceColumnDisplayLabel(label, key) {
        const normalizedKey = String(key || '').trim();
        const normalizedLabel = String(label || '').trim();
        if (normalizedLabel !== '' && normalizedLabel !== normalizedKey) {
            return `${normalizedLabel} | ${normalizedKey}`;
        }

        return normalizedKey || normalizedLabel;
    }

    function buildActiveFormatFromFieldOptions(type, fieldOptions = []) {
        const fallbackType = defaultEvidenceTypeCode();
        const normalizedType = normalizeEvidenceType(type || state.currentType || fallbackType) || fallbackType;
        const columns = Array.isArray(fieldOptions)
            ? fieldOptions
                .map((field, index) => fieldOptionToModalColumn(field, index))
                .filter(Boolean)
            : [];

        return {
            format_name: normalizedType,
            import_type: normalizedType,
            columns,
        };
    }

    function sortEvidenceExcelColumns(columns = []) {
        return [...(Array.isArray(columns) ? columns : [])]
            .map((column, index) => ({ column, index }))
            .sort((left, right) => {
                const leftPrimary = Number(left.column?.column_order || left.column?.ordinal_position || 0);
                const rightPrimary = Number(right.column?.column_order || right.column?.ordinal_position || 0);
                const leftSecondary = Number(left.column?.display_order || left.column?.excel_column_index || 0);
                const rightSecondary = Number(right.column?.display_order || right.column?.excel_column_index || 0);

                if (leftPrimary !== rightPrimary) {
                    return leftPrimary - rightPrimary;
                }
                if (leftSecondary !== rightSecondary) {
                    return leftSecondary - rightSecondary;
                }
                return left.index - right.index;
            })
            .map(({ column }) => column);
    }

    async function ensureActiveFormat(type = state.currentType) {
        const fallbackType = defaultEvidenceTypeCode();
        const normalizedType = normalizeEvidenceType(type || state.currentType || fallbackType) || fallbackType;
        const currentActiveType = normalizeEvidenceType(state.activeFormat?.import_type || state.activeFormat?.format_name || '');
        if (currentActiveType === normalizedType && Array.isArray(state.activeFormat?.columns) && state.activeFormat.columns.length > 0) {
            return state.activeFormat;
        }

        const [fieldOptions, databaseOptions] = await Promise.all([
            loadEvidenceFieldOptions(normalizedType),
            loadEvidenceModalFieldOptions(normalizedType),
        ]);
        state.activeFormat = buildActiveFormatFromFieldOptions(
            normalizedType,
            mergeEvidenceModalFieldOptions(fieldOptions, databaseOptions)
        );
        return state.activeFormat;
    }

    function normalizeCodeKey(value) {
        return valueText(value).toUpperCase();
    }

    function codeDisplayName(field, value) {
        const code = normalizeCodeKey(value);
        if (code === '') return '';
        const alias = CODE_NAME_ALIASES[field]?.[code] || '';
        const group = DISPLAY_CODE_FIELDS[field] || '';
        if (group === '') return alias || valueText(value);

        const found = (state.codeOptions[group] || []).find((row) => normalizeCodeKey(row.code) === code);
        const foundName = String(found?.code_name || '').trim();
        if (alias !== '' && (foundName === '' || normalizeCodeKey(foundName) === code)) {
            return alias;
        }

        return foundName || alias || valueText(value);
    }

    function codeValueForField(field, value) {
        const raw = valueText(value);
        if (raw === '') return '';
        const group = DISPLAY_CODE_FIELDS[field] || '';
        if (group === '') return raw;

        const normalized = normalizeCodeKey(raw);
        const found = (state.codeOptions[group] || []).find((row) => (
            normalizeCodeKey(row.code) === normalized
            || String(row.code_name ?? '').trim() === raw
        ));
        if (found?.code) return found.code;

        return CODE_NAME_ALIASES[field]?.[raw] || raw;
    }

    function businessUnitRuleType(payload = {}) {
        const raw = valueText(payload.business_unit || payload.business_unit_code);
        const label = codeDisplayName('business_unit', raw);
        const normalized = `${raw} ${label}`.replace(/\s+/g, '').toUpperCase();
        if (normalized.includes('CONSTRUCTION') || normalized.includes('\uD604\uC7A5\uACF5\uC0AC')) return 'CONSTRUCTION';
        return normalized === '' ? '' : 'OTHER';
    }

    function hasProjectSelection(payload = {}) {
        return valueText(payload.project_id) !== '' || valueText(payload.project_name) !== '';
    }

    function focusBusinessProjectRuleTarget(key) {
        const input = editInputByKey(key);
        input?.classList.add('is-invalid');
        input?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        input?.focus?.();
    }

    function businessProjectRuleMessage(payload = {}) {
        const type = businessUnitRuleType(payload);
        const hasProject = hasProjectSelection(payload);

        if (type === 'OTHER' && hasProject) {
            return '\uC0AC\uC5C5\uAD6C\uBD84\uC774 \uD604\uC7A5\uACF5\uC0AC\uAC00 \uC544\uB2CC \uACBD\uC6B0 \uD504\uB85C\uC81D\uD2B8 \uC815\uBCF4\uB97C \uC785\uB825\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
        }
        if (type === 'CONSTRUCTION' && !hasProject) {
            return '\uC0AC\uC5C5\uAD6C\uBD84\uC774 \uD604\uC7A5\uACF5\uC0AC\uC778 \uACBD\uC6B0 \uD504\uB85C\uC81D\uD2B8\uB97C \uBC18\uB4DC\uC2DC \uC120\uD0DD\uD574\uC57C \uD569\uB2C8\uB2E4.';
        }
        return '';
    }

    function validateBusinessProjectRule(payload = {}) {
        const message = businessProjectRuleMessage(payload);
        editInputByKey('business_unit')?.classList.remove('is-invalid');
        editInputByKey('project_name')?.classList.remove('is-invalid');
        editInputByKey('project_id')?.classList.remove('is-invalid');

        if (message !== '') {
            focusBusinessProjectRuleTarget('project_name');
            notify('warning', message);
            return false;
        }
        return true;
    }

    function formatValue(row = {}, column = {}) {
        const payload = mapped(row);
        const systemField = String(column.system_field_name || '').trim();
        const excelName = String(column.excel_column_name || '').trim();
        const raw = row && typeof row === 'object' ? row : {};
        if (isDateTimeColumn(column)) {
            const explicit = firstPayloadValue(payload, [
                'transaction_datetime',
                'transaction_at',
                'approval_datetime',
                'purchase_datetime',
                excelName,
                systemField,
                '\uAC70\uB798\uC77C\uC2DC',
                '\uC2B9\uC778\uC77C\uC2DC',
                '\uAD6C\uB9E4\uC77C\uC2DC',
            ]);
            if (valueText(explicit) !== '') {
                const explicitText = valueText(explicit);
                if (/\d{1,2}:\d{2}/.test(explicitText)) {
                    return explicitText;
                }
                const rawDateTime = raw[excelName] ?? raw[systemField] ?? '';
                if (valueText(rawDateTime) !== '' && /\d{1,2}:\d{2}/.test(valueText(rawDateTime))) {
                    return valueText(rawDateTime);
                }
            }

            const dateValue = firstPayloadValue(payload, [
                systemField,
                'transaction_date',
                'approval_date',
                'purchase_date',
                excelName,
                '\uAC70\uB798\uC77C\uC790',
                '\uC2B9\uC778\uC77C\uC790',
                '\uAD6C\uB9E4\uC77C\uC790',
            ]) ?? raw[excelName] ?? raw[systemField] ?? '';
            const timeValue = firstPayloadValue(payload, [
                'transaction_time',
                'approval_time',
                'purchase_time',
                '\uAC70\uB798\uC2DC\uAC04',
                '\uC2B9\uC778\uC2DC\uAC04',
                '\uAD6C\uB9E4\uC2DC\uAC04',
            ]) ?? raw['\uAC70\uB798\uC2DC\uAC04'] ?? raw['\uC2B9\uC778\uC2DC\uAC04'] ?? raw['\uAD6C\uB9E4\uC2DC\uAC04'] ?? '';

            if (valueText(dateValue) !== '' && valueText(timeValue) !== '') {
                return `${valueText(dateValue)} ${valueText(timeValue)}`;
            }
            if (valueText(explicit) !== '') {
                return valueText(explicit);
            }
        }
        const value = firstPayloadValue(payload, columnAliasKeys(column));
        if (valueText(value) !== '') {
            return codeDisplayName(systemField, value);
        }

        const fallback = payload[excelName] ?? raw[excelName] ?? raw[systemField] ?? '-';
        return fallback === '-' ? fallback : codeDisplayName(systemField, fallback);
    }

    function isDateColumn(column = {}) {
        const field = String(column.system_field_name || '').toLowerCase();
        const title = String(column.excel_column_name || '').toLowerCase();
        return /(^|_)date$|_date_|date|datetime|\uC77C\uC790|\uB0A0\uC9DC|\uC77C\uC2DC/.test(`${field} ${title}`);
    }

    function isTimeColumn(column = {}) {
        const field = String(column.system_field_name || '').toLowerCase();
        const title = String(column.excel_column_name || '').toLowerCase();
        return !isDateColumn(column) && /(^|_)time$|time|\uC2DC\uAC04/.test(`${field} ${title}`);
    }

    function isDateTimeColumn(column = {}) {
        const field = String(column.system_field_name || '').toLowerCase();
        const title = String(column.excel_column_name || '').toLowerCase();
        if (field === 'transaction_time') return true;
        return /datetime|\uC77C\uC2DC/.test(`${field} ${title}`);
    }

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function formatDateValue(value) {
        const raw = valueText(value);
        if (raw === '' || raw === '-') return '-';

        const normalized = formatDateInputValue(raw);
        if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
            return normalized;
        }

        const iso = raw.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})/);
        if (iso) {
            return `${iso[1]}-${pad2(iso[2])}-${pad2(iso[3])}`;
        }

        const slash = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (slash) {
            return `${slash[3]}-${pad2(slash[1])}-${pad2(slash[2])}`;
        }

        const compact = raw.match(/^(\d{4})(\d{2})(\d{2})$/);
        if (compact) {
            return `${compact[1]}-${compact[2]}-${compact[3]}`;
        }

        return raw;
    }

    function formatDateTimeValue(value) {
        const raw = valueText(value);
        if (raw === '' || raw === '-') return '-';

        const date = formatDateValue(raw);
        const timeMatch = raw.match(/(?:\s|T)(\d{1,2}):(\d{2})(?::(\d{2}))?/);
        if (!timeMatch || date === '-') {
            return date;
        }

        return `${date} ${pad2(timeMatch[1])}:${timeMatch[2]}${timeMatch[3] ? `:${timeMatch[3]}` : ''}`;
    }

    function normalizeDateInputValue(value, keepTime = false) {
        const raw = valueText(value);
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

    function normalizeTimeInputValue(value) {
        const raw = valueText(value);
        const clockMatch = raw.match(/(?:^|\D)(\d{1,2}):(\d{2})(?::\d{2})?(?:\D|$)/);
        let hourText = clockMatch?.[1] || '';
        let minuteText = clockMatch?.[2] || '';
        if (!clockMatch) {
            const digits = raw.replace(/\D/g, '');
            const timeDigits = digits.length >= 4 ? digits.slice(-4) : digits;
            if (!/^\d{3,4}$/.test(timeDigits)) return '';
            hourText = timeDigits.length === 3 ? timeDigits.slice(0, 1) : timeDigits.slice(0, 2);
            minuteText = timeDigits.slice(-2);
        }
        const hour = Math.min(23, Math.max(0, Number(hourText || 0)));
        const minute = Math.min(59, Math.max(0, Number(minuteText || 0)));
        return `${pad2(hour)}:${pad2(minute)}`;
    }

    function explicitDateTimeValueForColumn(column = {}, row = {}, payload = {}) {
        const key = String(column.key || '').toLowerCase();
        const candidates = [];
        if (key === 'raw_transaction_datetime' || key === 'transaction_date' || key === 'transaction_datetime' || key === 'transaction_at') {
            candidates.push('raw_transaction_datetime', 'transaction_datetime', 'transaction_at');
        }
        if (key === 'approval_date' || key === 'approval_datetime') {
            candidates.push('approval_datetime');
        }
        if (key === 'purchase_date' || key === 'purchase_datetime') {
            candidates.push('purchase_datetime');
        }
        if (isDateTimeColumn(column.column || {})) {
            candidates.push(column.key, 'raw_transaction_datetime', 'transaction_datetime', 'transaction_at');
        }

        for (const candidate of Array.from(new Set(candidates.filter(Boolean)))) {
            const value = row[candidate] ?? payload[candidate];
            const text = valueText(value);
            if (text !== '' && /\d{1,2}:\d{2}/.test(text)) {
                return text;
            }
        }
        return '';
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

    function parseEditDateValue(value, keepTime = false) {
        const raw = valueText(value);
        const now = new Date();
        if (raw === '' || raw === '-') {
            return {
                date: new Date(now.getFullYear(), now.getMonth(), now.getDate(), keepTime ? now.getHours() : 0, keepTime ? now.getMinutes() : 0, 0, 0),
                hasTime: keepTime,
            };
        }

        const dateText = formatDateInputValue(raw);
        const match = dateText.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) {
            return {
                date: new Date(now.getFullYear(), now.getMonth(), now.getDate(), keepTime ? now.getHours() : 0, keepTime ? now.getMinutes() : 0, 0, 0),
                hasTime: keepTime,
            };
        }

        const timeMatch = raw.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
        const hour = timeMatch ? Number(timeMatch[1]) : (keepTime ? now.getHours() : 0);
        const minute = timeMatch ? Number(timeMatch[2]) : (keepTime ? now.getMinutes() : 0);
        const second = timeMatch && timeMatch[3] ? Number(timeMatch[3]) : 0;

        return {
            date: new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), hour, minute, second, 0),
            hasTime: Boolean(timeMatch) || keepTime,
        };
    }

    function applyDateToPicker(picker, value, keepTime = false) {
        if (!picker) return;

        const parsed = parseEditDateValue(value, keepTime);
        const date = parsed.date;
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

    function normalizedStatus(row = {}) {
        const currentProcessStatus = String(row?.status || '').trim().toUpperCase();
        if (currentProcessStatus === 'DELETED' || currentProcessStatus === '\uC0AD\uC81C') {
            return 'DELETED';
        }
        if (isDeletedRowByEvidenceStatus(row)) return 'DELETED';
        return processStatus(row);
    }

    function isDeletedRowByEvidenceStatus(row = {}) {
        const processStatus = String(row?.status || '').trim().toUpperCase();
        if (processStatus === 'DELETED' || processStatus === '\uC0AD\uC81C') {
            return true;
        }
        if (processStatus === 'USED' || processStatus === 'PROCESSED') {
            return false;
        }
        const deletedAt = valueText(row?.deleted_at);
        return deletedAt !== '';
    }

    function businessEvidenceStatusText(value = '') {
        const status = String(value || '').trim().toUpperCase();
        if (status === 'COMPLETED' || status === 'READY' || status === 'VERIFY_ONLY') {
            return '\uC644\uB8CC';
        }
        if (status === 'CORRECTION_REQUIRED' || status === 'NOT_READY' || status === 'REVIEW_REQUIRED') {
            return '\uBCF4\uC815\uD544\uC694';
        }
        if (status === 'DELETED') return '\uC0AD\uC81C';
        if (status === 'ERROR') return '\uC624\uB958';
        if (status === 'ACTIVE') return '\uD65C\uC131';
        if (status === 'PROCESSED' || status === 'USED') return '\uCC98\uB9AC\uC644\uB8CC';
        return valueText(value);
    }

    function editFieldKey(column = {}) {
        return String(column.system_field_name || column.excel_column_name || '').trim();
    }

    function normalizeActorFieldValue(value = '') {
        return valueText(value);
    }

    function resolveEditFieldKey(column = {}, row = {}) {
        const key = editFieldKey(column);
        if (key === '') {
            return key;
        }

        if (isBusinessOnlyEvidenceType(row)) {
            return businessOnlyFieldKey(key);
        }

        const lowerKey = String(key).toLowerCase();
        if (lowerKey === 'created_by' || lowerKey === 'created_by_name') {
            return 'created_by';
        }
        if (lowerKey === 'updated_by' || lowerKey === 'updated_by_name') {
            return 'updated_by';
        }
        if (lowerKey === 'deleted_by' || lowerKey === 'deleted_by_name') {
            return 'deleted_by';
        }
        if (isDateColumn(column) || isTimeColumn(column)) {
            return key;
        }

        const title = String(column.excel_column_name || column.label || '').trim();
        const lowerTitle = title.toLowerCase();
        const hasCreatedBy = valueText(row?.created_by) !== '' || valueText(row?.created_by_name) !== '';
        const hasUpdatedBy = valueText(row?.updated_by) !== '' || valueText(row?.updated_by_name) !== '';
        const hasDeletedBy = valueText(row?.deleted_by) !== '' || valueText(row?.deleted_by_name) !== '';
        const hasCreatedHint = lowerTitle.includes('\uc0dd\uc131\uc790') || lowerTitle.includes('created by') || lowerTitle.includes('creator');
        const hasUpdatedHint = lowerTitle.includes('\uc218\uc815\uc790') || lowerTitle.includes('updated by') || lowerTitle.includes('modifier');
        const hasDeletedHint = lowerTitle.includes('\uc0ad\uc81c\uc790') || lowerTitle.includes('deleted by') || lowerTitle.includes('deleter');

        if (hasCreatedBy && hasCreatedHint) {
            return 'created_by';
        }
        if (hasUpdatedBy && (hasUpdatedHint || lowerTitle.includes('\uc218\uc815'))) {
            return 'updated_by';
        }
        if (hasDeletedBy && (hasDeletedHint || lowerTitle.includes('\uc0ad\uc81c'))) {
            return 'deleted_by';
        }

        return key;
    }

    function isDeprecatedFormatColumn(column = {}) {
        const policy = evidenceTypePolicy(state.currentType);
        const field = String(column.system_field_name || '').trim();
        const title = String(column.excel_column_name || '').replace(/\s+/g, '').trim();
        const fields = new Set(Array.isArray(policy?.deprecatedFormatFields) ? policy.deprecatedFormatFields : []);
        const titles = new Set(Array.isArray(policy?.deprecatedFormatTitles) ? policy.deprecatedFormatTitles : []);
        return fields.has(field) || titles.has(title);
    }

    function editFieldValue(row = {}, column = {}) {
        const payload = mapped(row);
        const key = resolveEditFieldKey(column, row);
        const raw = row && typeof row === 'object' ? row : {};
        if (key === 'deleted_at' && normalizedStatus(row) !== 'DELETED') {
            return '';
        }
        if (isDateTimeColumn(column)) {
            const dateTimeValue = firstPayloadValue(payload, [
                'raw_transaction_datetime',
                'transaction_datetime',
                'transaction_at',
                'approval_datetime',
                'purchase_datetime',
                column.excel_column_name,
                column.system_field_name,
                key,
            ]);
            if (valueText(dateTimeValue) !== '') {
                return valueText(dateTimeValue);
            }

            const dateValue = firstPayloadValue(payload, [
                'approval_date',
                'purchase_date',
            ]);
            const timeValue = firstPayloadValue(payload, [
                'transaction_time',
                'approval_time',
                'purchase_time',
            ]);
            if (valueText(dateValue) !== '') {
                const timeText = valueText(timeValue);
                return `${valueText(dateValue)}${timeText !== '' ? ` ${timeText}` : ''}`;
            }
        }
        const actorField = (key === 'created_by' || key === 'updated_by' || key === 'deleted_by');
        if (actorField) {
            return normalizeActorFieldValue(raw[`${key}_name`] ?? raw[key] ?? '');
        }
        if (key === 'evidence_status') {
            return businessEvidenceStatusText(raw[key] ?? '');
        }
        const value = firstPayloadValue(payload, columnAliasKeys(column));
        if (valueText(value) !== '') {
            return codeDisplayName(key, value) || valueText(value);
        }

        const fallbackValue = valueText(raw[column.excel_column_name] ?? raw[column.system_field_name] ?? '');
        if (key === 'evidence_status') {
            return businessEvidenceStatusText(fallbackValue);
        }
        return codeDisplayName(key, fallbackValue) || fallbackValue;
    }

    function editInputType(column = {}, value = '', row = {}) {
        const key = resolveEditFieldKey(column, row).toLowerCase();
        if (key === 'created_by' || key === 'updated_by' || key === 'deleted_by') {
            return 'text';
        }
        if (businessRefPickerForColumn(column)) return 'ref';
        if (bankCodePickerForColumn(column)) return 'code';
        if (isTimeColumn(column)) return 'time';
        if (isDateColumn(column)) return 'date';
        if (isAmountColumn(column)) return 'number';
        if (isBusinessNumberColumn(column)) return 'business_number';
        if (isPhoneColumn(column)) return 'phone';
        if (valueText(value).length > 80 || /memo|note|description|address|\uBA54\uBAA8|\uBE44\uACE0|\uC0C1\uC138\uC124\uBA85|\uC8FC\uC18C/.test(key)) return 'textarea';
        return 'text';
    }

    function businessRefPickerForColumn(column = {}) {
        return evidenceRefPickerForColumnLikeRuntime({
            ...column,
            key: editFieldKey(column),
        });
    }

    function bankCodePickerForColumn(column = {}) {
        const field = String(column.system_field_name || '').trim();
        const excel = String(column.excel_column_name || '').trim().replace(/\s+/g, '');
        const key = editFieldKey(column);

        return Object.entries(BANK_CODE_PICKERS).find(([codeKey, config]) => (
            field === codeKey
            || key === codeKey
            || config.titles.some((title) => title === excel)
        ))?.[1] || null;
    }
    function requirementMode(column = {}) {
        const key = String(column.system_field_name || column.original_column_key || editFieldKey(column) || '').trim();
        const userSettingPageKey = evidenceMetaDomain(state.currentType);
        const policyState = readDataTableSettingsState(evidenceStatusTableSettingsStorageKey(state.currentType), {
            metaDomain: userSettingPageKey,
            userSettingPageKey,
        }) || {};
        const requirementPolicyMap = policyState.columnRequirementPolicy && typeof policyState.columnRequirementPolicy === 'object'
            ? policyState.columnRequirementPolicy
            : {};
        const hasExplicitPolicy = Object.prototype.hasOwnProperty.call(requirementPolicyMap, key);
        const policy = resolveDataTableColumnRequirementPolicy(
            { key, system_field_name: key, original_column_key: key },
            policyState
        );
        if (!hasExplicitPolicy && ['source_type', 'import_type', 'evidence_type'].includes(key)) {
            return 0;
        }
        if (hasExplicitPolicy && policy === 'none') return 0;
        if (policy === 'required') return 1;
        if (policy === 'optional') return 2;
        return Number(column.requirement_mode || column.is_required || 0);
    }

    function requirementStar(column = {}) {
        const mode = requirementMode(column);
        if (mode === 1) return '<span class="evidence-required-star">*</span>';
        if (mode === 2) return '<span class="evidence-optional-star">*</span>';
        return '';
    }

    function firstPayloadText(payload = {}, keys = []) {
        for (const key of keys) {
            const value = payload[key];
            const text = valueText(value);
            if (text !== '') {
                return text;
            }
        }
        return '';
    }

    function compareFormatColumns(a, b) {
        const aDisplay = Number(a?.display_order || 0);
        const bDisplay = Number(b?.display_order || 0);
        const aOrder = Number(a?.column_order || 0);
        const bOrder = Number(b?.column_order || 0);
        const aExcel = Number(a?.excel_column_index || 0);
        const bExcel = Number(b?.excel_column_index || 0);
        const aPrimary = aDisplay > 0 ? aDisplay : (aOrder > 0 ? aOrder : aExcel);
        const bPrimary = bDisplay > 0 ? bDisplay : (bOrder > 0 ? bOrder : bExcel);
        return (aPrimary - bPrimary) || (aOrder - bOrder) || (aExcel - bExcel);
    }

    async function postJson(url, payload = {}, options = {}) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            signal: options.signal,
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '\uc694\uccad \ucc98\ub9ac \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.');
        }
        return json;
    }

    async function showModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        if (id === 'dataExcelModal') {
            loadOptionalStyle('/public/assets/css/components/spreadsheet.css');
            loadOptionalStyle('/public/assets/css/components/excel-manager.css');
            await import('/public/assets/js/components/excel-manager.js');
            await ensureUploadEvents();
            await prepareExcelManager();
        }
        const frame = modal.querySelector('iframe[data-src]');
        if (frame && !frame.getAttribute('src')) {
            frame.setAttribute('src', frame.dataset.src || '');
        }
        bootstrap.Modal.getOrCreateInstance(modal, { focus: false }).show();
    }

    function loadOptionalStyle(href) {
        if (document.querySelector(`link[data-optional-style="${href}"]`)) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.optionalStyle = href;
        document.head.appendChild(link);
    }

    async function openEvidenceTrash() {
        if (!state.refs.trashModal) return;
        loadOptionalStyle('/public/assets/css/components/trash-manager.css');
        await import('/public/assets/js/components/trash-manager.js');
        const selectedLabel = state.refs.typeSelect?.selectedOptions?.[0]?.textContent?.trim() || currentConfig()?.label || '\uc99d\ube59';
        state.refs.trashModal.dataset.listUrl = `${API.trash}?import_type=${encodeURIComponent(state.currentType)}`;
        state.refs.trashModal.dataset.importType = state.currentType;
        state.refs.trashModal.dataset.trashTitle = `${selectedLabel} \ud734\uc9c0\ud1b5`;
        bootstrap.Modal.getOrCreateInstance(state.refs.trashModal, { focus: false }).show();
    }

    async function updateTrashButtonState() {
        const button = document.querySelector('.evidence-status-trash-btn');
        if (!button || !state.currentType) return;
        const listUrl = `${API.trash}?import_type=${encodeURIComponent(state.currentType)}`;
        try {
            if (state.refs.trashModal) {
                state.refs.trashModal.dataset.listUrl = listUrl;
                state.refs.trashModal.dataset.importType = state.currentType;
                state.refs.trashModal.dataset.trashTitle = `${selectedTypeLabel() || state.currentType} \ud734\uc9c0\ud1b5`;
            }
            const rows = state.refs.trashModal && window.TrashManager?.preloadTrash
                ? await window.TrashManager.preloadTrash(state.refs.trashModal)
                : [];
            const hasTrash = rows.length > 0;
            button.classList.toggle('btn-trash-has-data', hasTrash);
            button.classList.toggle('btn-outline-danger', !hasTrash);
            button.setAttribute('aria-label', hasTrash ? `\ud734\uc9c0\ud1b5 ${rows.length}\uac74` : '\ud734\uc9c0\ud1b5');
            button.title = hasTrash ? `\ud734\uc9c0\ud1b5 ${rows.length}\uac74` : '\ud734\uc9c0\ud1b5';
        } catch (error) {
            console.warn('[data-status] trash state failed:', error);
        }
    }

    function markTrashButtonHasData(count = 1) {
        const button = document.querySelector('.evidence-status-trash-btn');
        if (!button) return;
        const safeCount = Math.max(1, Number(count) || 1);
        button.classList.add('btn-trash-has-data');
        button.classList.remove('btn-outline-danger');
        button.setAttribute('aria-label', `\ud734\uc9c0\ud1b5 ${safeCount}\uac74 \uc788\uc74c`);
        button.title = `\ud734\uc9c0\ud1b5 ${safeCount}\uac74 \uc788\uc74c`;
    }

    function evidenceStatusTableSettingsStorageKey(type = state.currentType) {
        const fallbackType = defaultEvidenceTypeCode();
        const resolvedType = normalizeEvidenceType(type || fallbackType) || fallbackType;
        const typeKey = String(resolvedType).trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '-');
        return `ledger.data.status.${typeKey}.evidence-status.db-physical.v3`;
    }

    function handleEvidenceStatusTableOrderChange() {
        void rebuildTable();
        return true;
    }

    function runTypeChange(nextType) {
        const resolvedNextType = normalizeEvidenceType(nextType || state.currentType || '');
        const resolvedCurrentType = normalizeEvidenceType(state.currentType || '');
        if (!changeType(resolvedNextType) && resolvedNextType !== '' && resolvedNextType !== resolvedCurrentType) {
            return;
        }
        void rebuildTable().catch((error) => notify('error', error.message));
    }

    const { selectedTypeLabel, loadDisplayCodeOptions, currentConfig, evidenceTypeDisplayName, renderEvidenceTypeTabs, refreshEvidenceTypeCounts, syncTypeControls, changeType, initTypeSelect, filterEvidenceTypeSelect, evidenceTypeRoute } = createEvidenceSearchModule({
        state,
        API,
        DISPLAY_CODE_FIELDS,
        SERVER_TYPE_POLICIES,
        SERVER_TYPE_PAGE_MAP,
        normalizeEvidenceType,
        evidenceTypePolicy,
        defaultEvidenceTypeCode,
        escapeHtml,
    });

    const { processStatus, updateSummary, renderFieldBadge, compareFieldDisplayOrder, infoColumnTone } = createEvidenceStatusModule({
        state,
        escapeHtml,
        badge,
        mapped,
        editFieldKey,
        businessRefPickerForColumn,
        bankCodePickerForColumn,
        compareFormatColumns,
        normalizeEvidenceType,
        evidenceTypePolicy,
    });

    const { bindExcelEvents, syncExcelManager, prepareExcelManager } = createEvidenceExcelModule({
        state,
        API,
        createExcelManagerSettingsCore: createExcelManagerSettingsCoreLazy,
        evidenceFieldOptionsCache,
        fieldOptionToModalColumn,
        sortEvidenceExcelColumns,
        normalizeEvidenceType,
        evidenceTypePolicy,
        defaultEvidenceTypeCode,
        formatEvidenceColumnDisplayLabel,
        escapeHtml,
        notify,
        currentConfig,
        evidenceStatusTableSettingsStorageKey,
        evidenceMetaDomain,
    });

    let evidenceModalApi = null;
    let evidenceModalApiPromise = null;

    async function ensureEvidenceModalApi() {
        if (evidenceModalApi) return evidenceModalApi;
        if (!evidenceModalApiPromise) {
            evidenceModalApiPromise = Promise.all([
                import('/public/assets/js/pages/ledger/evidence-list/modal.js'),
                import('/public/assets/js/common/picker/admin_picker.js'),
                import('/public/assets/js/pages/ledger/shared/evidence-ref-picker.js'),
                import('/public/assets/js/pages/dashboard/settings/system/code-select.js'),
            ]).then(([modalModule, pickerModule, refPickerModule, codeSelectModule]) => {
                evidenceRefPickerForColumnLikeRuntime = refPickerModule.evidenceRefPickerForColumnLike;
                codeSelectModule.onCodeOptionsLoaded((options) => {
                    state.codeOptions = { ...(options || {}) };
                    state.codeOptions.IMPORT_TYPE = (state.codeOptions.IMPORT_TYPE || [])
                        .map((row) => ({ ...row, code: normalizeEvidenceType(row.code || row.value) }))
                        .filter((row) => row.code && !row.code.startsWith('__'))
                        .filter((row, index, list) => list.findIndex((item) => item.code === row.code) === index);
                });
                evidenceModalApi = modalModule.createEvidenceModalModule({
                    state,
                    API,
                    notify,
                    updateSummary,
                    refreshEvidenceTypeCounts,
                    ensureActiveFormat,
                    selectedTypeLabel,
                    evidenceTypeDisplayName,
                    normalizedStatus,
                    businessEvidenceStatusText,
                    escapeHtml,
                    valueText,
                    mapped,
                    firstPayloadText,
                    renderFieldBadge,
                    requirementStar,
                    compareFieldDisplayOrder,
                    infoColumnTone,
                    editFieldKey,
                    resolveEditFieldKey,
                    editInputType,
                    editFieldValue,
                    businessRefPickerForColumn,
                    bankCodePickerForColumn,
                    codeValueForField,
                    codeDisplayName,
                    isDateTimeColumn,
                    normalizeDateInputValue,
                    formatNumber,
                    formatBizNumber,
                    formatPhone,
                    parseCommonNumber,
                    bindCommonNumberInput,
                    formatPickerDateTime,
                    formatPickerDate,
                    applyDateToPicker,
                    AdminPicker: pickerModule.AdminPicker,
                    EVIDENCE_REF_PICKERS: refPickerModule.EVIDENCE_REF_PICKERS,
                    initEvidenceRefSelect: refPickerModule.initEvidenceRefSelect,
                    initCodeSelectControls: codeSelectModule.initCodeSelectControls,
                    selectValueForSave,
                    selectTextForSave,
                    validateBusinessProjectRule,
                    evidenceStatusTableSettingsStorageKey,
                    readDataTableSettingsState,
                    resolveDataTableColumnDisplayName,
                    resolveDataTableColumnRequirementPolicy,
                    normalizeEvidenceType,
                    evidenceTypePolicy,
                    defaultEvidenceTypeCode,
                    evidenceMetaDomain,
                });
                return evidenceModalApi;
            });
        }
        return evidenceModalApiPromise;
    }

    const editInputByKey = (...args) => evidenceModalApi?.editInputByKey(...args) || null;
    const toggleBulkField = (...args) => evidenceModalApi?.toggleBulkField(...args);
    const clearEditPickerLayers = (...args) => evidenceModalApi?.clearEditPickerLayers(...args);
    const saveBulkEdit = async (...args) => (await ensureEvidenceModalApi()).saveBulkEdit(...args);
    const saveEditingRow = async (...args) => (await ensureEvidenceModalApi()).saveEditingRow(...args);
    const openEvidenceEditModalLatest = async (...args) => (await ensureEvidenceModalApi()).openEvidenceEditModalLatest(...args);
    const openEvidenceNewModalLatest = async (...args) => (await ensureEvidenceModalApi()).openEvidenceNewModalLatest(...args);

    let uploadEventsPromise = null;
    async function ensureUploadEvents() {
        if (!uploadEventsPromise) {
            uploadEventsPromise = import('/public/assets/js/pages/ledger/evidence-list/upload.js')
                .then(({ createEvidenceUploadModule }) => {
                    const uploadApi = createEvidenceUploadModule({
                        state,
                        API,
                        MAX_EXCEL_UPLOAD_BYTES,
                        notify,
                        updateSummary,
                        refreshEvidenceTypeCounts,
                        currentStatusColumnPolicy: () => {
                            const normalizedType = normalizeEvidenceType(state.currentType || defaultEvidenceTypeCode());
                            const userSettingPageKey = evidenceMetaDomain(normalizedType);
                            return readDataTableSettingsState(evidenceStatusTableSettingsStorageKey(normalizedType), {
                                metaDomain: userSettingPageKey,
                                userSettingPageKey,
                            }) || {};
                        },
                    });
                    uploadApi.bindUploadEvents();
                    return uploadApi;
                });
        }
        return uploadEventsPromise;
    }

    const { rebuildTable } = createEvidenceTableModule({
        state,
        API,
        SearchForm,
        createDataTable,
        bindRowReorder,
        normalizeEvidenceType,
        evidenceTypePolicy,
        defaultEvidenceTypeCode,
        evidenceMetaDomain,
        evidenceMetaColumnsCache,
        fetchDataTableMetaColumns,
        valueText,
        formatNumber,
        escapeHtml,
        evidenceFieldValue,
        codeDisplayName,
        mapped,
        columnAliasKeys,
        firstPayloadValue,
        formatDateInputValue,
        parseCommonNumber,
        notify,
        textColumn,
        moneyColumn,
        badge,
        clientName,
        amount,
        processStatus,
        updateSummary,
        selectedTypeLabel,
        currentConfig,
        DEFAULT_DATE_OPTION,
        compareFieldDisplayOrder,
        compareFormatColumns,
        formatEvidenceColumnDisplayLabel,
        formatValue,
        isAmountColumn,
        isDateColumn,
        isDateTimeColumn,
        formatDateValue,
        formatDateTimeValue,
        normalizeDateInputValue,
        evidenceStatusTableSettingsStorageKey,
        handleEvidenceStatusTableOrderChange,
        refreshEvidenceTypeCounts,
        openEvidenceNewModalLatest,
        openEvidenceTrash: () => {
            void openEvidenceTrash().catch((error) => notify('error', error.message));
        },
        renderEvidenceTypeTabs,
        showModal: (id) => {
            void showModal(id).catch((error) => notify('error', error.message));
        },
        syncExcelManager,
        readDataTableSettingsState,
    });

    function bindEvents() {
        const evidenceMenuLink = document.querySelector('.sidebar a.nav-link[href="/ledger/data"]');
        let isTypeNavigationPending = false;
        if (evidenceMenuLink && window.location.pathname.startsWith('/ledger/data/')) {
            evidenceMenuLink.classList.add('active');
        }

        const navigateToEvidenceType = (value) => {
            if (isTypeNavigationPending) {
                return true;
            }

            const route = evidenceTypeRoute(value);
            if (!route || route === window.location.pathname + window.location.search) {
                return false;
            }

            isTypeNavigationPending = true;
            setPageLoadingState(true);
            showPageTransitionLoading();
            window.requestAnimationFrame(() => {
                window.location.href = route;
            });
            return true;
        };

        const handleTypeControlChange = () => {
            const value = String(state.refs.typeSelect?.value || '').trim();
            if (!state.fixedType && navigateToEvidenceType(value)) {
                return;
            }
            runTypeChange(value);
        };

        state.refs.typeSelect?.addEventListener('change', handleTypeControlChange);
        if (window.jQuery?.fn?.select2 && state.refs.typeSelect) {
            window.jQuery(state.refs.typeSelect)
                .off('change.dataStatusType select2:select.dataStatusType')
                .on('change.dataStatusType select2:select.dataStatusType', handleTypeControlChange);
        }
        state.refs.typeTabs?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-evidence-type]');
            if (!button) {
                return;
            }

            const nextType = button.dataset.evidenceType || '';
            if (!state.fixedType && navigateToEvidenceType(nextType)) {
                return;
            }

            runTypeChange(nextType);
        });

        state.refs.trashBtn?.addEventListener('click', () => {
            void openEvidenceTrash().catch((error) => notify('error', error.message));
        });
        state.refs.editSaveBtn?.addEventListener('click', () => {
            void saveEditingRow().catch((error) => notify('error', error.message));
        });
        state.refs.bulkSaveBtn?.addEventListener('click', () => {
            void saveBulkEdit().catch((error) => notify('error', error.message));
        });
        state.refs.editModal?.addEventListener('hidden.bs.modal', () => clearEditPickerLayers());
        state.refs.bulkFields?.addEventListener('change', (event) => {
            const toggle = event.target.closest('.evidence-bulk-toggle');
            if (toggle) toggleBulkField(toggle);
        });

        const evidenceTableEl = document.getElementById('evidenceStatusTable');
        const evidenceRowFromNode = (rowNode) => {
            if (!rowNode || !state.table?.row) return null;
            return state.table.row(rowNode).data() || null;
        };
        evidenceTableEl?.addEventListener('dblclick', (event) => {
            if (event.target.closest('a, button, input, select, textarea, .dt-select-column')) return;
            const row = evidenceRowFromNode(event.target.closest('tr'));
            if (row) {
                void openEvidenceEditModalLatest(row).catch((error) => notify('error', error.message));
            }
        });
        evidenceTableEl?.addEventListener('click', (event) => {
            const editButton = event.target.closest('.evidence-edit-row-btn');
            if (!editButton) return;
            const row = evidenceRowFromNode(editButton.closest('tr'));
            if (row) {
                void openEvidenceEditModalLatest(row).catch((error) => notify('error', error.message));
            }
        });
        evidenceTableEl?.addEventListener('datatable:selection-changed', (event) => {
            state.selectedIds = new Set((event.detail?.ids || []).map((id) => String(id)));
        });
        evidenceTableEl?.addEventListener('datatable:soft-delete-completed', (event) => {
            markTrashButtonHasData(event.detail?.ids?.length || 1);
            void updateTrashButtonState();
        });
        document.addEventListener('trash:changed', (event) => {
            if (event.detail?.type === 'evidenceStatus') {
                state.table?.ajax.reload(() => updateSummary(state.lastRows), false);
                void refreshEvidenceTypeCounts().catch(() => {});
                void updateTrashButtonState();
            }
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || event.data?.type !== 'data-format:saved') return;
            const dataType = String(event.data?.dataType || '').trim();
            if (dataType && dataType !== state.currentType) return;
            void rebuildTable().catch((error) => notify('error', error.message));
        });

        document.addEventListener('trash:detail-render', (event) => {
            if (event.detail?.type !== 'evidenceStatus') return;
            const detailEl = event.detail.modal?.querySelector('.trash-detail');
            const row = event.detail.data || {};
            if (!detailEl) return;
            detailEl.innerHTML = `
                <div class="small">
                    <dl class="row mb-0">
                        <dt class="col-4">\uc790\ub8cc\uc720\ud615</dt><dd class="col-8">${escapeHtml(row.import_type_name || row.import_type || row.source_type || '-')}</dd>
                        <dt class="col-4">\uac70\ub798\ucc98</dt><dd class="col-8">${escapeHtml(clientName(row) || '-')}</dd>
                        <dt class="col-4">\ucd1d\uae08\uc561</dt><dd class="col-8">${escapeHtml(formatNumber(mapped(row).total_amount))}</dd>
                        <dt class="col-4">\uc624\ub958\ub0b4\uc6a9</dt><dd class="col-8">${escapeHtml(row.error_message || '-')}</dd>
                        <dt class="col-4">\uc0ad\uc81c\uc77c\uc2dc</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
                    </dl>
                </div>
            `;
        });
    }

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.evidenceStatus = function (row = {}) {
        return `
            <td class="text-center">${escapeHtml(row.sort_no || '-')}</td>
            <td>${escapeHtml(row.import_type_name || row.import_type || row.source_type || '-')}</td>
            <td>${escapeHtml(clientName(row) || '-')}</td>
            <td class="text-end">${escapeHtml(formatNumber(mapped(row).total_amount))}</td>
            <td>${escapeHtml(row.deleted_at || '-')}</td>
            <td>
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id)}">\ubcf5\uad6c</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id)}">\uc0ad\uc81c</button>
            </td>
        `;
    };

    if (options.editorOnly === true) {
        if (state.refs.editModal?.parentElement !== document.body) {
            document.body.appendChild(state.refs.editModal);
        }
        state.currentType = normalizeEvidenceType(options.initialType || '') || defaultEvidenceTypeCode();
        state.refs.editSaveBtn?.addEventListener('click', () => {
            void saveEditingRow().catch((error) => notify('error', error.message));
        });
        state.refs.editModal?.addEventListener('hidden.bs.modal', () => clearEditPickerLayers());

        const ready = loadDisplayCodeOptions().catch(() => {});
        return {
            async open({ import_type: importType = '', evidence_id: evidenceId = '' } = {}) {
                if (SERVER_TYPE_POLICIES.length === 0) {
                    notify('warning', '증빙원본 자료유형 정책을 불러오지 못했습니다.');
                    return;
                }
                const normalizedType = normalizeEvidenceType(importType) || state.currentType || defaultEvidenceTypeCode();
                const normalizedId = String(evidenceId || '').trim();
                if (!normalizedType || !normalizedId) {
                    notify('warning', '수정할 증빙원본을 확인할 수 없습니다.');
                    return;
                }

                if (state.refs.editModal) {
                    document.body.appendChild(state.refs.editModal);
                }
                await ready;
                state.currentType = normalizedType;
                state.activeFormat = null;
                const query = new URLSearchParams({ id: normalizedId, import_type: normalizedType });
                const response = await fetch(`${API.seedRows}?${query.toString()}`);
                const json = await response.json().catch(() => ({}));
                const row = Array.isArray(json.data)
                    ? (json.data.find((item) => String(item.id || item.evidence_id || '') === normalizedId) || json.data[0])
                    : null;
                if (!response.ok || json.success === false || !row) {
                    notify('warning', '수정할 증빙원본을 불러오지 못했습니다.');
                    return;
                }
                await openEvidenceEditModalLatest(row);
            },
        };
    }

    bindEvents();
    if (!initialPageReady) {
        state.currentType = normalizeEvidenceType(state.initialType || state.fixedType || state.currentType || '') || defaultEvidenceTypeCode();
        filterEvidenceTypeSelect();
        if (state.refs.typeSelect && state.currentType) {
            state.refs.typeSelect.value = state.currentType;
        }
        void refreshEvidenceTypeCounts()
            .catch((error) => {
                console.error(error);
            })
            .finally(() => {
                clearGlobalLoadingOverlay();
                setPageLoadingState(false);
                syncTypeControls();
                renderEvidenceTypeTabs();
            });
        return;
    }
    initTypeSelect()
        .then(async () => {
            syncTypeControls();
            if (!currentPageReady()) {
                renderEvidenceTypeTabs();
                void refreshEvidenceTypeCounts().catch(() => {});
                return;
            }
            bindExcelEvents();
            const tableReady = rebuildTable();
            void refreshEvidenceTypeCounts().catch(() => {});
            void tableReady.then(() => loadDisplayCodeOptions()).catch(() => {});
            return tableReady;
        })
        .catch((error) => {
            console.error(error);
            state.currentType = state.currentType || defaultEvidenceTypeCode();
            syncTypeControls();
            if (!currentPageReady()) {
                renderEvidenceTypeTabs();
                return;
            }
            bindExcelEvents();
            void rebuildTable().catch((rebuildError) => notify('error', rebuildError.message));
        })
        .finally(() => {
            clearGlobalLoadingOverlay();
            setPageLoadingState(false);
        });
}

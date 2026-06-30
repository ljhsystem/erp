import { createDataTable, refreshDataTableLayout } from '/public/assets/js/common/table/data-table.js';
import { readDataTableSettingsState, resolveDataTableColumnDisplayName, resolveDataTableColumnRequirementPolicy } from '/public/assets/js/common/datatable/dataTableSettings.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import {
    bindNumberInput as bindCommonNumberInput,
    formatDateInputValue,
    formatBizNumber,
    formatPhone,
    parseNumber as parseCommonNumber,
} from '/public/assets/js/common/format.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createExcelManagerSettingsCore } from '/public/assets/js/components/excel-manager/index.js';
import { initCodeSelectControls, onCodeOptionsLoaded } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { EVIDENCE_REF_PICKERS, evidenceRefPickerForColumnLike, initEvidenceRefSelect } from '/public/assets/js/pages/ledger/shared/evidence-ref-picker.js';
import { createEvidenceStatusModule } from '/public/assets/js/pages/ledger/evidence-list/status.js';
import { createEvidenceSearchModule } from '/public/assets/js/pages/ledger/evidence-list/search.js';
import { createEvidenceTableModule } from '/public/assets/js/pages/ledger/evidence-list/table.js';
import { createEvidenceUploadModule } from '/public/assets/js/pages/ledger/evidence-list/upload.js';
import { createEvidenceExcelModule } from '/public/assets/js/pages/ledger/evidence-list/excel.js';
import { createEvidenceModalModule } from '/public/assets/js/pages/ledger/evidence-list/modal.js';
import { createEvidenceSplitModule } from '/public/assets/js/pages/ledger/evidence-list/split.js';
import { createEvidenceChildModule } from '/public/assets/js/pages/ledger/evidence-list/child.js';
import '/public/assets/js/components/trash-manager.js';
import '/public/assets/js/components/excel-manager.js';

export function bootEvidencePage(options = {}) {
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
        splitChild: '/api/import/evidence/split-child',
        updateProcessingChild: '/api/import/evidence/processing-child/update',
        deleteProcessingChild: '/api/import/evidence/processing-child/delete',
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
        transaction_type: 'TRANSACTION_TYPE',
        transaction_direction: 'TRANSACTION_DIRECTION',
    };
    const CODE_NAME_ALIASES = {
        transaction_direction: {
            IN: '입금',
            OUT: '출금',
            PURCHASE: '매입',
            SALES: '매출',
        },
    };
    CODE_NAME_ALIASES.transaction_direction.IN = '입금';
    CODE_NAME_ALIASES.transaction_direction.OUT = '출금';
    CODE_NAME_ALIASES.transaction_direction.PURCHASE = '매입';
    CODE_NAME_ALIASES.transaction_direction.SALES = '매출';
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
                    excelManagerMode: String(row?.excel_manager_mode || row?.excelManagerMode || 'custom').trim() || 'custom',
                    excelManagerDomain: String(row?.excel_manager_domain || row?.excelManagerDomain || '').trim(),
                    sourceKeyAliases: row?.source_key_aliases && typeof row.source_key_aliases === 'object'
                        ? { ...row.source_key_aliases }
                        : {},
                    modalPreset: String(row?.modal_preset || row?.modalPreset || 'default').trim() || 'default',
                    splitKeyAliases: row?.split_key_aliases && typeof row.split_key_aliases === 'object'
                        ? { ...row.split_key_aliases }
                        : {},
                    splitValueFallbacks: row?.split_value_fallbacks && typeof row.split_value_fallbacks === 'object'
                        ? Object.fromEntries(
                            Object.entries(row.split_value_fallbacks).map(([key, values]) => [
                                String(key || '').trim(),
                                Array.isArray(values) ? values.map((value) => String(value || '').trim()).filter(Boolean) : [],
                            ])
                        )
                        : {},
                    splitAllowedAmountKeys: Array.isArray(row?.split_allowed_amount_keys)
                        ? row.split_allowed_amount_keys.map((value) => String(value || '').trim()).filter(Boolean)
                        : [],
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

    const SERVER_TYPE_POLICIES = normalizeEvidenceTypePolicies(readEvidenceTypePolicies());
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
        excelManagerSettingsType: '',
        excelManagerSettingsState: {
            template: null,
            download: null,
        },
    };

    function evidenceMetaDomain(type = '') {
        return evidenceTypePolicy(type || state.currentType || '')?.metaDomain || '';
    }
    const SERVER_TYPE_PAGE_MAP = Object.fromEntries(
        Object.entries(readEvidenceTypePageMap()).map(([type, path]) => [normalizeEvidenceType(type), String(path || '').trim()])
    );

    const evidenceFieldOptionsCache = new Map();

    const BANK_CODE_PICKERS = {
        business_unit: {
            codeGroup: 'BUSINESS_UNIT',
            emptyLabel: '\uC0AC\uC5C5\uBD80\uB97C \uC120\uD0DD\uD558\uC138\uC694.',
            titles: ['\uC0AC\uC5C5\uBD80'],
        },
        transaction_type: {
            codeGroup: 'TRANSACTION_TYPE',
            emptyLabel: '\uAC70\uB798\uC720\uD615\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
            titles: ['\uAC70\uB798\uC720\uD615'],
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
            '__none__',
            '__CODE_NONE__',
            '_none_',
            '__none',
            'none__',
            '--none--',
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

    function columnAliasKeys(column = {}) {
        const field = String(column.system_field_name || '').trim();
        const excelName = String(column.excel_column_name || '').trim();
        const keys = [field, excelName];
        const aliasMap = {
            supplier_company_name: ['supplier_name', '\uACF5\uAE09\uC790\uBA85', '\uACF5\uAE09\uC790'],
            supplier_name: ['supplier_company_name', '\uACF5\uAE09\uC790\uBA85', '\uACF5\uAE09\uC790'],
            customer_company_name: ['customer_name', '\uACE0\uAC1D\uC0AC\uBA85', '\uACE0\uAC1D\uC0AC'],
            customer_name: ['customer_company_name', '\uACE0\uAC1D\uC0AC\uBA85', '\uACE0\uAC1D\uC0AC'],
            item_name: ['\uD488\uBAA9\uBA85', '\uD488\uBAA9'],
            issue_date: ['\uC791\uC131\uC77C\uC790', '\uC791\uC131\uC77C'],
            transmit_date: ['\uC804\uC1A1\uC77C\uC790'],
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

        const fieldOptions = await loadEvidenceFieldOptions(normalizedType);
        state.activeFormat = buildActiveFormatFromFieldOptions(normalizedType, fieldOptions);
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
        if (row.deleted_at) return 'DELETED';
        return processStatus(row);
    }

    function editFieldKey(column = {}) {
        return String(column.system_field_name || column.excel_column_name || '').trim();
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
        const key = editFieldKey(column);
        const raw = row && typeof row === 'object' ? row : {};
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
        const value = firstPayloadValue(payload, columnAliasKeys(column));
        if (valueText(value) !== '') {
            return valueText(value);
        }

        return valueText(raw[column.excel_column_name] ?? raw[column.system_field_name] ?? '');
    }

    function editInputType(column = {}, value = '') {
        const key = editFieldKey(column).toLowerCase();
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
        return evidenceRefPickerForColumnLike({
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
        const policyState = readDataTableSettingsState(evidenceStatusTableSettingsStorageKey(state.currentType)) || {};
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
        if (editFieldKey(column) === 'raw_balance_amount') return '';
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

    function showModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        const frame = modal.querySelector('iframe[data-src]');
        if (frame && !frame.getAttribute('src')) {
            frame.setAttribute('src', frame.dataset.src || '');
        }
        bootstrap.Modal.getOrCreateInstance(modal, { focus: false }).show();
    }

    function openEvidenceTrash() {
        if (!state.refs.trashModal) return;
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
        initCodeSelectControls,
    });

    const { processStatus, renderSeedStatus, renderTransactionStatus, renderVoucherStatus, renderReviewStatus, renderRecommendStatus, renderUserModified, renderWorkflowStatus, renderTransactionWorkflowStatus, renderVoucherWorkflowStatus, updateSummary, renderFieldBadge, compareFieldDisplayOrder, infoColumnTone } = createEvidenceStatusModule({
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

    const { bindExcelEvents, syncExcelManager } = createEvidenceExcelModule({
        state,
        API,
        createExcelManagerSettingsCore,
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

        const { renderEditInput, editableColumnsForRow, toggleBulkField, clearEditPickerLayers, bindDateEditInput, editInputByKey, saveBulkEdit, saveEditingRow, openEvidenceEditModalLatest, openEvidenceNewModalLatest } = createEvidenceModalModule({
        state,
        API,
        notify,
        updateSummary,
        refreshEvidenceTypeCounts,
        ensureActiveFormat,
        selectedTypeLabel,
        evidenceTypeDisplayName,
        normalizedStatus,
        escapeHtml,
        valueText,
        mapped,
        firstPayloadText,
        renderFieldBadge,
        requirementStar,
        compareFieldDisplayOrder,
        isDeprecatedFormatColumn,
        infoColumnTone,
        editFieldKey,
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
        AdminPicker,
        EVIDENCE_REF_PICKERS,
        initEvidenceRefSelect,
        initCodeSelectControls,
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
    });

    const { splitModalColumns, splitCellHtml, bindSplitModalHorizontalWheel, bindSplitModalInputs, splitChildFromRow, splitMissingRequiredForRow, openProcessingSplitModal } = createEvidenceSplitModule({
        state,
        API,
        notify,
        updateSummary,
        refreshDataTableLayout,
        escapeHtml,
        mapped,
        valueText,
        compareFormatColumns,
        editFieldKey,
        editFieldValue,
        editableColumnsForRow,
        renderEditInput,
        renderFieldBadge,
        isAmountColumn,
        infoColumnTone,
        editInputType,
        requirementMode,
        requirementStar,
        businessRefPickerForColumn,
        bankCodePickerForColumn,
        DISPLAY_CODE_FIELDS,
        codeValueForField,
        normalizeCodeKey,
        CODE_NAME_ALIASES,
        codeDisplayName,
        normalizeTimeInputValue,
        explicitDateTimeValueForColumn,
        normalizeDateInputValue,
        isDateTimeColumn,
        formatNumber,
        amount,
        firstPayloadText,
        bindCommonNumberInput,
        bindDateEditInput,
        initEvidenceRefSelect,
        initCodeSelectControls,
        selectValueForSave,
        selectTextForSave,
        businessProjectRuleMessage,
        pad2,
        AdminPicker,
        evidenceTypePolicy,
    });

    const { bindUploadEvents } = createEvidenceUploadModule({
        state,
        API,
        MAX_EXCEL_UPLOAD_BYTES,
        notify,
        updateSummary,
        refreshEvidenceTypeCounts,
        evidenceStatusTableSettingsStorageKey,
        readDataTableSettingsState,
        normalizeEvidenceType,
        defaultEvidenceTypeCode,
    });

    const { rowDataFromTableNode, rebuildTable } = createEvidenceTableModule({
        state,
        API,
        SearchForm,
        createDataTable,
        refreshDataTableLayout,
        bindRowReorder,
        normalizeEvidenceType,
        evidenceTypePolicy,
        defaultEvidenceTypeCode,
        evidenceMetaDomain,
        evidenceMetaColumnsCache,
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
        ensureActiveFormat,
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
        updateTrashButtonState,
        openEvidenceNewModalLatest,
        openEvidenceTrash,
        renderEvidenceTypeTabs,
        showModal,
        syncExcelManager,
        renderSeedStatus,
        renderTransactionStatus,
        renderVoucherStatus,
        renderReviewStatus,
        renderRecommendStatus,
        renderUserModified,
        renderWorkflowStatus,
        renderTransactionWorkflowStatus,
        renderVoucherWorkflowStatus,
        readDataTableSettingsState,
    });

    const { bindProcessingChildEvents } = createEvidenceChildModule({
        state,
        API,
        notify,
        updateSummary,
        refreshDataTableLayout,
        escapeHtml,
        mapped,
        splitModalColumns,
        requirementStar,
        splitCellHtml,
        bindSplitModalInputs,
        bindSplitModalHorizontalWheel,
        splitMissingRequiredForRow,
        splitChildFromRow,
        businessProjectRuleMessage,
        rowDataFromTableNode,
        openProcessingSplitModal,
        openEvidenceEditModalLatest,
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

        state.refs.trashBtn?.addEventListener('click', openEvidenceTrash);
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
        evidenceTableEl?.addEventListener('datatable:selection-changed', (event) => {
            state.selectedIds = new Set((event.detail?.ids || []).map((id) => String(id)));
        });
        evidenceTableEl?.addEventListener('datatable:soft-delete-completed', (event) => {
            markTrashButtonHasData(event.detail?.ids?.length || 1);
            void updateTrashButtonState();
        });
        bindProcessingChildEvents(evidenceTableEl);

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

    onCodeOptionsLoaded((options) => {
        state.codeOptions = { ...(options || {}) };
        state.codeOptions.IMPORT_TYPE = (state.codeOptions.IMPORT_TYPE || [])
            .map((row) => ({ ...row, code: normalizeEvidenceType(row.code || row.value) }))
            .filter((row) => row.code && !row.code.startsWith('__'))
            .filter((row, index, list) => list.findIndex((item) => item.code === row.code) === index);
    });

    bindEvents();
    if (!initialPageReady) {
        setPageLoadingState(true);
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
    setPageLoadingState(true);
    initTypeSelect()
        .then(async () => {
            await refreshEvidenceTypeCounts();
            syncTypeControls();
            if (!currentPageReady()) {
                renderEvidenceTypeTabs();
                return;
            }
            bindExcelEvents();
            bindUploadEvents();
            await loadDisplayCodeOptions();
            syncTypeControls();
            return rebuildTable();
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
            bindUploadEvents();
            void rebuildTable().catch((rebuildError) => notify('error', rebuildError.message));
        })
        .finally(() => {
            clearGlobalLoadingOverlay();
            setPageLoadingState(false);
            window.requestAnimationFrame(() => {
                refreshDataTableLayout?.();
            });
        });
}

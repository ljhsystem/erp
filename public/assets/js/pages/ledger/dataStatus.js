import { createDataTable, refreshDataTableLayout } from '/public/assets/js/common/table/data-table.js';
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
import { initCodeSelectControls, onCodeOptionsLoaded } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { EVIDENCE_REF_PICKERS, evidenceRefPickerForColumnLike, initEvidenceRefSelect } from '/public/assets/js/pages/ledger/shared/evidence-ref-picker.js';
import '/public/assets/js/components/trash-manager.js';
import '/public/assets/js/components/excel-manager.js';

(() => {
    'use strict';

    const API = {
        seedRows: '/api/import/evidences',
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
        projectSearch: '/api/settings/base-info/project/search-picker',
        employeeSearch: '/api/settings/organization/employee/search-picker',
        bankAccountSearch: '/api/settings/base-info/bank-account/search-picker',
        cardSearch: '/api/settings/base-info/card/search-picker',
        codeList: '/api/settings/system/code/list',
    };
    const MAX_EXCEL_UPLOAD_BYTES = 25 * 1024 * 1024;

    const EVIDENCE_UPLOAD_TYPES = new Set([
        'TAX_INVOICE',
        'CASH_RECEIPT',
        'CARD_HOMETAX',
        'CARD_STATEMENT',
        'BANK_TRANSACTION',
    ]);

    const LEGACY_TYPE_MAP = {
        CARD: 'CARD_STATEMENT',
        BANK: 'BANK_TRANSACTION',
        TAX: 'TAX_INVOICE',
        DATA: 'TAX_INVOICE',
    };

    const DATA_TYPE_SORT_RULES = {
        BANK_TRANSACTION: ['거래일시', 'transaction_datetime', 'transaction_at', 'transaction_date', '거래일자'],
        TAX_INVOICE: ['작성일자', 'write_date', 'written_date', 'issue_write_date', 'transaction_date', 'evidence_date'],
        CASH_RECEIPT: ['매입일시', 'purchase_datetime', 'purchase_at', 'purchase_date', '매입일자'],
        CASH_RECEIPT_PURCHASE: ['매입일시', 'purchase_datetime', 'purchase_at', 'purchase_date', '매입일자'],
        CASH_RECEIPT_SALES: ['매입일시', 'purchase_datetime', 'purchase_at', 'purchase_date', '매입일자'],
        CARD_APPROVAL: ['승인일자', 'approval_date', 'approved_date', 'approval_datetime', 'approved_at', '승인일시'],
        CARD_STATEMENT: ['승인일자', 'approval_date', 'approved_date', 'approval_datetime', 'approved_at', '승인일시'],
        CARD_HOMETAX: ['승인일자', 'approval_date', 'approved_date', 'approval_datetime', 'approved_at', '승인일시'],
        CARD_COMPANY: ['승인일자', 'approval_date', 'approved_date', 'approval_datetime', 'approved_at', '승인일시'],
        CARD: ['승인일자', 'approval_date', 'approved_date', 'approval_datetime', 'approved_at', '승인일시'],
        CREDIT_CARD: ['승인일자', 'approval_date', 'approved_date', 'approval_datetime', 'approved_at', '승인일시'],
    };

    const DISPLAY_CODE_FIELDS = {
        business_unit: 'BUSINESS_UNIT',
        transaction_type: 'TRANSACTION_TYPE',
        transaction_direction: 'TRANSACTION_DIRECTION',
    };
    const CODE_NAME_ALIASES = {
        transaction_direction: {
            입금: 'IN',
            출금: 'OUT',
            매입: 'PURCHASE',
            매출: 'SALES',
        },
    };

    const DATA_TYPE_CONFIG = {
        TAX_INVOICE: {
            label: '세금계산서(홈택스)',
            api: `${API.seedRows}?import_type=TAX_INVOICE`,
            excelTemplate: 'tax_invoice',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '작성일자' }],
            evidenceColumns: [
                textColumn('mapped_payload.approval_number', '승인번호', (row) => mapped(row).approval_number || mapped(row).approval_no || '-'),
                textColumn('mapped_payload.supplier_company_name', '공급자', (row) => mapped(row).supplier_company_name || '-'),
                textColumn('mapped_payload.customer_company_name', '공급받는자', (row) => mapped(row).customer_company_name || '-'),
                moneyColumn('mapped_payload.supply_amount', '공급가액', (row) => amount(mapped(row).supply_amount)),
                moneyColumn('mapped_payload.vat_amount', '부가세', (row) => amount(mapped(row).vat_amount)),
                moneyColumn('mapped_payload.total_amount', '합계', (row) => amount(mapped(row).total_amount)),
            ],
        },
        CARD_HOMETAX: {
            label: '카드(홈택스)',
            api: `${API.seedRows}?import_type=CARD_HOMETAX`,
            excelTemplate: 'card_approval',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '사용일자' }],
            evidenceColumns: [
                textColumn('mapped_payload.card_name', '카드', (row) => mapped(row).card_name || mapped(row).card_no || '-'),
                textColumn('client_name', '가맹점', (row) => clientName(row) || '-'),
                textColumn('mapped_payload.approval_number', '승인번호', (row) => mapped(row).approval_number || mapped(row).approval_no || '-'),
                moneyColumn('mapped_payload.supply_amount', '공급가액', (row) => amount(mapped(row).supply_amount)),
                moneyColumn('mapped_payload.vat_amount', '부가세', (row) => amount(mapped(row).vat_amount)),
                moneyColumn('mapped_payload.total_amount', '합계', (row) => amount(mapped(row).total_amount)),
            ],
        },
        CARD_APPROVAL: {
            label: '카드(카드사)',
            api: `${API.seedRows}?import_type=CARD_APPROVAL`,
            excelTemplate: 'card_approval',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '사용일자' }],
            evidenceColumns: [
                textColumn('mapped_payload.card_name', '카드', (row) => mapped(row).card_name || mapped(row).card_no || '-'),
                textColumn('client_name', '가맹점', (row) => clientName(row) || '-'),
                textColumn('mapped_payload.approval_number', '승인번호', (row) => mapped(row).approval_number || mapped(row).approval_no || '-'),
                moneyColumn('mapped_payload.supply_amount', '공급가액', (row) => amount(mapped(row).supply_amount)),
                moneyColumn('mapped_payload.vat_amount', '부가세', (row) => amount(mapped(row).vat_amount)),
                moneyColumn('mapped_payload.total_amount', '합계', (row) => amount(mapped(row).total_amount)),
            ],
        },
        CARD_STATEMENT: {
            label: '카드(카드사)',
            api: `${API.seedRows}?import_type=CARD_STATEMENT`,
            excelTemplate: 'card_statement',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '사용일자' }],
            evidenceColumns: [
                textColumn('mapped_payload.card_name', '카드', (row) => mapped(row).card_name || mapped(row).card_no || '-'),
                textColumn('client_name', '가맹점', (row) => clientName(row) || '-'),
                textColumn('mapped_payload.approval_number', '승인번호', (row) => mapped(row).approval_number || mapped(row).approval_no || '-'),
                moneyColumn('mapped_payload.supply_amount', '공급가액', (row) => amount(mapped(row).supply_amount)),
                moneyColumn('mapped_payload.vat_amount', '부가세', (row) => amount(mapped(row).vat_amount)),
                moneyColumn('mapped_payload.total_amount', '합계', (row) => amount(mapped(row).total_amount)),
            ],
        },
        BANK_TRANSACTION: {
            label: '입출금(은행)',
            api: `${API.seedRows}?import_type=BANK_TRANSACTION`,
            excelTemplate: 'bank_transaction',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '거래일자' }],
            evidenceColumns: [
                textColumn('mapped_payload.bank_account_name', '계좌', (row) => mapped(row).bank_account_name || mapped(row).account_no || '-'),
                textColumn('mapped_payload.bank_direction', '입출금', (row) => mapped(row).bank_direction || '-'),
                textColumn('client_name', '거래처', (row) => clientName(row) || '-'),
                moneyColumn('mapped_payload.deposit_amount', '입금', (row) => amount(mapped(row).deposit_amount)),
                moneyColumn('mapped_payload.withdrawal_amount', '출금', (row) => amount(mapped(row).withdrawal_amount)),
                moneyColumn('mapped_payload.total_amount', '금액', (row) => amount(mapped(row).total_amount || mapped(row).amount)),
            ],
        },
        CASH_RECEIPT: {
            label: '현금영수증(홈택스)',
            api: `${API.seedRows}?import_type=CASH_RECEIPT`,
            excelTemplate: 'cash_receipt',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '발행일자' }],
            evidenceColumns: [
                textColumn('mapped_payload.approval_number', '승인번호', (row) => mapped(row).approval_number || '-'),
                textColumn('client_name', '거래처', (row) => clientName(row) || '-'),
                textColumn('mapped_payload.transaction_direction', '거래구분', (row) => mapped(row).transaction_direction || '-'),
                moneyColumn('mapped_payload.supply_amount', '공급가액', (row) => amount(mapped(row).supply_amount)),
                moneyColumn('mapped_payload.vat_amount', '부가세', (row) => amount(mapped(row).vat_amount)),
                moneyColumn('mapped_payload.total_amount', '합계', (row) => amount(mapped(row).total_amount)),
            ],
        },
        IMPORT_INVOICE: {
            label: '수입신고',
            api: `${API.seedRows}?import_type=IMPORT_INVOICE`,
            excelTemplate: 'import_invoice',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '신고일자' }],
            evidenceColumns: [
                textColumn('mapped_payload.declaration_number', '신고번호', (row) => mapped(row).declaration_number || mapped(row).approval_number || '-'),
                textColumn('client_name', '거래처', (row) => clientName(row) || '-'),
                moneyColumn('mapped_payload.customs_duty', '관세', (row) => amount(mapped(row).customs_duty)),
                moneyColumn('mapped_payload.vat_amount', '부가세', (row) => amount(mapped(row).vat_amount)),
                moneyColumn('mapped_payload.total_amount', '합계', (row) => amount(mapped(row).total_amount)),
            ],
        },
        SHOPPING_ORDER: {
            label: '쇼핑몰정산',
            api: `${API.seedRows}?import_type=SHOPPING_ORDER`,
            excelTemplate: 'shopping_order',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '정산일자' }],
            evidenceColumns: [
                textColumn('mapped_payload.order_number', '주문번호', (row) => mapped(row).order_number || mapped(row).approval_number || '-'),
                textColumn('client_name', '쇼핑몰/구매자', (row) => clientName(row) || '-'),
                moneyColumn('mapped_payload.sales_amount', '매출', (row) => amount(mapped(row).sales_amount || mapped(row).total_amount)),
                moneyColumn('mapped_payload.fee_amount', '수수료', (row) => amount(mapped(row).fee_amount)),
                moneyColumn('mapped_payload.settlement_amount', '정산액', (row) => amount(mapped(row).settlement_amount || mapped(row).total_amount)),
            ],
        },
        PAYROLL_WITHHOLDING: {
            label: '원천/급여',
            api: `${API.seedRows}?import_type=PAYROLL_WITHHOLDING`,
            excelTemplate: 'payroll_withholding',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '귀속일자' }],
            evidenceColumns: [
                textColumn('mapped_payload.employee_name', '대상자', (row) => mapped(row).employee_name || clientName(row) || '-'),
                textColumn('mapped_payload.income_type', '소득유형', (row) => mapped(row).income_type || '-'),
                moneyColumn('mapped_payload.gross_amount', '지급액', (row) => amount(mapped(row).gross_amount || mapped(row).total_amount)),
                moneyColumn('mapped_payload.tax_amount', '원천세', (row) => amount(mapped(row).tax_amount || mapped(row).vat_amount)),
                moneyColumn('mapped_payload.total_amount', '합계', (row) => amount(mapped(row).total_amount)),
            ],
        },
    };
    const refs = {
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
    };

    let currentType = '';
    let table = null;
    let lastRows = [];
    let codeOptions = {};
    let activeFormat = null;
    let editModal = null;
    let editingRow = null;
    let editPickerLayers = [];
    let bulkModal = null;
    let selectedIds = new Set();
    let evidenceTypeCounts = {};
    let evidenceTotalSummary = {
        total: 0,
        bank: 0,
        evidence: 0,
    };
    let uploadingExcel = false;
    let excelUploadCanceled = false;
    let excelUploadAbortController = null;
    let excelUploadCancelToken = '';
    let excelUploadPreviewToken = '';

    const BANK_CODE_PICKERS = {
        business_unit: {
            codeGroup: 'BUSINESS_UNIT',
            emptyLabel: '사업구분선택',
            titles: ['사업구분'],
        },
        transaction_type: {
            codeGroup: 'TRANSACTION_TYPE',
            emptyLabel: '거래유형선택',
            titles: ['거래유형'],
        },
        transaction_direction: {
            codeGroup: 'TRANSACTION_DIRECTION',
            emptyLabel: '거래구분선택',
            titles: [],
        },
    };    const BANK_DEPRECATED_FORMAT_FIELDS = new Set([
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
        '전표일자',
        '전표적요',
        '전표비고',
        '전표메모',
        '헤더순번',
        '헤더행번호',
        '분개라인번호',
        '계정',
        '차변금액',
        '차변',
        '대변금액',
        '대변',
        '라인적요',
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
        return row.mapped_payload && typeof row.mapped_payload === 'object' ? row.mapped_payload : {};
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
            '선택(없음)',
        ].includes(text);
    }

    function isEmptySelectionLabel(value) {
        const text = String(value ?? '').trim();
        if (isNoneSelectionValue(text)) return true;
        return [
            '선택',
            '직접 선택',
            '거래처 선택',
            '프로젝트 선택',
            '직원 선택',
            '계좌 선택',
            '카드 선택',
            '사업구분 선택',
            '거래구분 선택',
            '거래유형 선택',
            '자료출처 선택',
            '자료유형 선택',
            '라인유형 선택',
            '차변구분 선택',
        ].includes(text);
    }

    function selectValueForSave(input) {
        const value = String(input?.value ?? '').trim();
        return isNoneSelectionValue(value) ? '' : value;
    }

    function selectTextForSave(input, { includeCurrentText = false } = {}) {
        if (!input) return '';
        if (isNoneSelectionValue(input.value)) return '';
        const text = String(
            input.dataset.refSelectedText
            || input.selectedOptions?.[0]?.textContent
            || (includeCurrentText ? input.dataset.refCurrentText : '')
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
            supplier_company_name: ['supplier_name', '공급자 상호', '공급자명'],
            supplier_name: ['supplier_company_name', '공급자 상호', '공급자명'],
            customer_company_name: ['customer_name', '공급받는자 상호', '공급받는자명'],
            customer_name: ['customer_company_name', '공급받는자 상호', '공급받는자명'],
            item_name: ['품목명', '품목'],
            issue_date: ['발급일자', '발행일자'],
            transmit_date: ['전송일자'],
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
        return /amount|price|total|vat|tax|fee|duty|qty|공급가액|공급가$|부가세|세액|합계|합계금액|금액|단가|수량|관세|수수료|매출|정산액|지급액|원천세|입금액|출금액|잔액/.test(text);
    }

    function isPhoneColumn(column = {}) {
        const field = String(column.system_field_name || '').toLowerCase();
        const title = String(column.excel_column_name || '').trim();
        return /phone|tel|mobile|fax|전화|연락처|휴대폰|핸드폰|팩스/.test(`${field} ${title}`);
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
            || valueText(payload['공급자 상호'])
            || valueText(payload['공급받는자 상호'])
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

    function processStatus(row = {}) {
        return String(row.process_status || row.status || '').toUpperCase();
    }

    function renderSeedStatus(row = {}) {
        const status = processStatus(row);
        const map = {
            READY: 'text-bg-primary',
            PROCESSED: 'text-bg-success',
            ERROR: 'text-bg-danger',
            DUPLICATED: 'text-bg-warning',
            DELETED: 'text-bg-secondary',
        };
        return badge(status || '-', map[status] || 'text-bg-light');
    }

    function renderTransactionStatus(row = {}) {
        if (String(row.transaction_id || '').trim() !== '') {
            return badge('생성됨', 'text-bg-success');
        }
        if (processStatus(row) === 'ERROR') {
            return badge('생성오류', 'text-bg-danger');
        }
        return badge('미생성', 'text-bg-primary');
    }

    function renderVoucherStatus(row = {}) {
        if (String(row.transaction_id || '').trim() === '') {
            return badge('거래 전', 'text-bg-light');
        }
        return badge('전표 확인필요', 'text-bg-warning');
    }

    function renderVoucherStatus(row = {}) {
        const status = String(row.voucher_status || '').trim().toUpperCase();
        if (['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'].includes(status)) {
            return badge('전표생성완료', 'text-bg-success');
        }
        if (status === 'READY') {
            return badge('분개라인확인', 'text-bg-primary');
        }
        if (['WAITING', 'NONE', ''].includes(status)) {
            return badge('전표생성대기', 'text-bg-warning');
        }
        if (['ERROR', 'FAILED'].includes(status)) {
            return badge('전표오류', 'text-bg-danger');
        }
        if (['DUPLICATED', 'DUPLICATE'].includes(status)) {
            return badge('중복', 'text-bg-secondary');
        }
        return badge(row.voucher_status || '전표생성대기', 'text-bg-info');
    }

    function renderReviewStatus(row = {}) {
        const status = processStatus(row);
        if (status === 'ERROR') return badge('검토필요', 'text-bg-danger');
        if (status === 'DUPLICATED') return badge('중복검토', 'text-bg-warning');
        return badge('정상', 'text-bg-success');
    }

    function renderRecommendStatus(row = {}) {
        if (String(row.transaction_id || '').trim() !== '') {
            return badge('추천 처리', 'text-bg-success');
        }
        if (processStatus(row) === 'READY') {
            return badge('추천대기', 'text-bg-primary');
        }
        return badge('확인필요', 'text-bg-secondary');
    }

    function renderUserModified(row = {}) {
        const payload = mapped(row);
        return payload.is_user_modified || payload.user_modified_at
            ? badge('있음', 'text-bg-warning')
            : badge('없음', 'text-bg-light');
    }

    function workflowStateBadge(label, state) {
        const cls = state === '생성'
            ? 'text-bg-success'
            : (state === 'READY' ? 'text-bg-primary' : 'text-bg-secondary');
        return `<span class="badge ${cls}">${escapeHtml(label)}(${escapeHtml(state)})</span>`;
    }

    function workflowStatusBadge(state) {
        const cls = state === '생성'
            ? 'text-bg-success'
            : (state === 'READY' ? 'text-bg-primary' : (state === 'NOT_REQUIRED' ? 'text-bg-light text-dark border' : 'text-bg-secondary'));
        return `<span class="badge ${cls}">${escapeHtml(state)}</span>`;
    }

    function transactionWorkflowState(row = {}) {
        if (String(row.import_type || row.source_type || '').trim().toUpperCase() === 'BANK_TRANSACTION') {
            return 'NOT_REQUIRED';
        }
        const transactionStatus = String(row.transaction_status || row.process_status || '').trim().toUpperCase();
        if (String(row.transaction_id || '').trim() !== ''
            || ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED'].includes(transactionStatus)) {
            return '생성';
        }
        if (['READY', 'NONE', ''].includes(transactionStatus) && !row.error_message && processStatus(row) !== 'ERROR') {
            return 'READY';
        }
        return 'NOT_READY';
    }

    function voucherWorkflowState(row = {}) {
        const status = String(row.voucher_status || '').trim().toUpperCase();
        if (['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'].includes(status)) {
            return '생성';
        }
        if (status === 'READY') {
            return 'READY';
        }
        return 'NOT_READY';
    }

    function renderWorkflowStatus(row = {}) {
        return `
            <div class="d-inline-flex align-items-center gap-1 flex-nowrap">
                ${workflowStateBadge('거래', transactionWorkflowState(row))}
                <span class="text-muted">+</span>
                ${workflowStateBadge('전표', voucherWorkflowState(row))}
            </div>
        `;
    }

    function renderTransactionWorkflowStatus(row = {}) {
        return workflowStatusBadge(transactionWorkflowState(row));
    }

    function renderVoucherWorkflowStatus(row = {}) {
        return workflowStatusBadge(voucherWorkflowState(row));
    }

    function textColumn(data, title, renderer, options = {}) {
        return {
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
            data,
            title,
            className: 'evidence-data-column text-end text-nowrap',
            headerClassName: 'evidence-data-column text-start text-nowrap',
            render: (_value, _type, row) => formatNumber(renderer(row)),
        };
    }

    function normalizeCodeKey(value) {
        return valueText(value).toUpperCase();
    }

    function codeDisplayName(field, value) {
        const code = normalizeCodeKey(value);
        if (code === '') return '';
        const group = DISPLAY_CODE_FIELDS[field] || '';
        if (group === '') return valueText(value);

        const found = (codeOptions[group] || []).find((row) => normalizeCodeKey(row.code) === code);
        return found?.code_name || valueText(value);
    }

    function codeValueForField(field, value) {
        const raw = valueText(value);
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

    function businessUnitRuleType(payload = {}) {
        const raw = valueText(payload.business_unit || payload.business_unit_code);
        const label = codeDisplayName('business_unit', raw);
        const normalized = `${raw} ${label}`.replace(/\s+/g, '').toUpperCase();
        if (normalized.includes('CONSTRUCTION') || normalized.includes('전문건설')) return 'CONSTRUCTION';
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
            return '사업구분이 전문건설업이 아닐 때는 프로젝트를 선택할 수 없습니다.';
        }
        if (type === 'CONSTRUCTION' && !hasProject) {
            return '사업구분이 전문건설업일 때는 프로젝트를 선택해야 합니다.';
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
        const raw = row.raw_payload && typeof row.raw_payload === 'object' ? row.raw_payload : {};
        if (isDateTimeColumn(column)) {
            const explicit = firstPayloadValue(payload, [
                'transaction_datetime',
                'transaction_at',
                'approval_datetime',
                'purchase_datetime',
                excelName,
                systemField,
                '거래일시',
                '승인일시',
                '매입일시',
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
                '거래일자',
                '승인일자',
                '매입일자',
            ]) ?? raw[excelName] ?? raw[systemField] ?? '';
            const timeValue = firstPayloadValue(payload, [
                'transaction_time',
                'approval_time',
                'purchase_time',
                '거래시간',
                '승인시간',
                '매입시간',
            ]) ?? raw['거래시간'] ?? raw['승인시간'] ?? raw['매입시간'] ?? '';

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
        return /(^|_)date$|_date_|date|datetime|일자|날짜|일시/.test(`${field} ${title}`);
    }

    function isTimeColumn(column = {}) {
        const field = String(column.system_field_name || '').toLowerCase();
        const title = String(column.excel_column_name || '').toLowerCase();
        return !isDateColumn(column) && /(^|_)time$|time|시간|시각/.test(`${field} ${title}`);
    }

    function isDateTimeColumn(column = {}) {
        const field = String(column.system_field_name || '').toLowerCase();
        const title = String(column.excel_column_name || '').toLowerCase();
        if (field === 'transaction_time') return true;
        return /datetime|일시/.test(`${field} ${title}`);
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
        if (key === 'transaction_date' || key === 'transaction_datetime' || key === 'transaction_at') {
            candidates.push('transaction_datetime', 'transaction_at');
        }
        if (key === 'approval_date' || key === 'approval_datetime') {
            candidates.push('approval_datetime');
        }
        if (key === 'purchase_date' || key === 'purchase_datetime') {
            candidates.push('purchase_datetime');
        }
        if (isDateTimeColumn(column.column || {})) {
            candidates.push(column.key, 'transaction_datetime', 'transaction_at');
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

    function isDeprecatedBankFormatColumn(column = {}) {
        if (currentType !== 'BANK_TRANSACTION') return false;
        const field = String(column.system_field_name || '').trim();
        const title = String(column.excel_column_name || '').replace(/\s+/g, '').trim();

        return BANK_DEPRECATED_FORMAT_FIELDS.has(field) || BANK_DEPRECATED_FORMAT_TITLES.has(title);
    }

    function editFieldValue(row = {}, column = {}) {
        const payload = mapped(row);
        const key = editFieldKey(column);
        const raw = row.raw_payload && typeof row.raw_payload === 'object' ? row.raw_payload : {};
        if (isDateTimeColumn(column)) {
            const dateTimeValue = firstPayloadValue(payload, [
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
                'transaction_date',
                'approval_date',
                'purchase_date',
                'evidence_date',
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
        if (valueText(value).length > 80 || /memo|note|description|address|비고|메모|주소|적요/.test(key)) return 'textarea';
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

    function infoColumnTone(column = {}) {
        const group = String(column.system_field_group || '').trim();
        if (group.includes('기준정보')) return 'standard';
        if (group.includes('기초정보')) return 'basic';
        if (group !== '') return '';
        if (Number(column.is_reference_column || 0) === 1 && businessRefPickerForColumn(column)) return 'basic';
        if (currentType !== 'BANK_TRANSACTION') return '';

        const field = String(column.system_field_name || '').trim();
        const excel = String(column.excel_column_name || '').trim().replace(/\s+/g, '');
        const standardFields = new Set([
            'business_unit',
            'transaction_type',
            'transaction_direction',
        ]);
        const standardTitles = new Set([
            '사업구분',
            '거래유형',
        ]);
        if (standardFields.has(field) || standardTitles.has(excel) || !!bankCodePickerForColumn(column)) {
            return 'standard';
        }

        const basicFields = new Set([
            'client_name',
            'project_name',
            'employee_name',
            'bank_account_name',
            'card_name',
        ]);
        const basicTitles = new Set([
            '사업구분',
            '거래유형',
            '거래처명',
            '거래처',
            '프로젝트명',
            '프로젝트',
            '직원명',
            '직원',
            '계좌명',
            '계좌',
            '카드명',
            '카드',
        ]);

        return basicFields.has(field) || basicTitles.has(excel) || !!businessRefPickerForColumn(column) ? 'basic' : '';
    }

    function requirementMode(column = {}) {
        return Number(column.is_required || 0);
    }

    function requirementStar(column = {}) {
        if (editFieldKey(column) === 'balance_amount') return '';
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

    function renderRefSelect(column = {}, value = '') {
        const config = businessRefPickerForColumn(column);
        const key = editFieldKey(column);
        const payload = mapped(editingRow);
        const selectedId = firstPayloadText(payload, [config.idKey]);
        const selectedText = (
            config.picker === 'client' && selectedId !== ''
                ? valueText(editingRow?.client_name)
                : ''
        ) || firstPayloadText(payload, [config.nameKey, ...config.keys, key]) || valueText(value);
        const optionValue = selectedId || selectedText;
        const textOnly = selectedId === '' && selectedText !== '';
        const option = optionValue !== ''
            ? `<option value="${escapeHtml(optionValue)}" selected>${escapeHtml(selectedText || optionValue)}</option>`
            : '';

        return `
            <select class="form-select form-select-sm evidence-edit-input evidence-edit-ref"
                data-key="${escapeHtml(key)}"
                data-value-kind="ref"
                data-ref-picker="${escapeHtml(config.picker)}"
                data-ref-id-key="${escapeHtml(config.idKey)}"
                data-ref-name-key="${escapeHtml(config.nameKey)}"
                data-ref-allow-text="${config.allowText ? '1' : '0'}"
                data-ref-current-text="${escapeHtml(selectedText)}"
                data-ref-current-text-only="${textOnly ? '1' : '0'}">
                <option value=""></option>
                ${option}
            </select>
        `;
    }

    function renderCodeSelect(column = {}, value = '') {
        const config = bankCodePickerForColumn(column);
        const key = editFieldKey(column);
        const selectedValue = codeValueForField(key, value);
        const selectedText = codeDisplayName(key, selectedValue);

        return `
            <select class="form-select form-select-sm evidence-edit-input evidence-edit-code"
                data-key="${escapeHtml(key)}"
                data-code-group="${escapeHtml(config.codeGroup)}"
                data-empty-label="${escapeHtml(config.emptyLabel)}"
                data-code-searchable="true">
                ${selectedValue !== '' ? `<option value="${escapeHtml(selectedValue)}" selected>${escapeHtml(selectedText || selectedValue)}</option>` : ''}
            </select>
        `;
    }

    function renderEditInput(column = {}, value = '') {
        const key = editFieldKey(column);
        const type = editInputType(column, value);
        const safeKey = escapeHtml(key);
        const keepTime = isDateTimeColumn(column);
        const displayValue = type === 'date'
            ? normalizeDateInputValue(value, keepTime)
            : type === 'number'
                ? formatNumber(value)
                : type === 'business_number'
                    ? formatBizNumber(value)
                    : type === 'phone'
                    ? formatPhone(value)
                    : valueText(value);
        const safeValue = escapeHtml(displayValue === '-' ? '' : displayValue);
        const required = Number(column.is_required || 0) === 1 ? 'required' : '';

        if (type === 'textarea') {
            return `<textarea class="form-control form-control-sm evidence-edit-input" rows="2" data-key="${safeKey}" ${required}>${safeValue}</textarea>`;
        }
        if (type === 'ref') {
            return renderRefSelect(column, value);
        }
        if (type === 'code') {
            return renderCodeSelect(column, value);
        }
        if (type === 'number') {
            return `<input type="text" inputmode="decimal" class="form-control form-control-sm evidence-edit-input evidence-edit-number" data-key="${safeKey}" data-value-kind="number" value="${safeValue}" ${required}>`;
        }
        if (type === 'phone') {
            return `<input type="text" inputmode="tel" class="form-control form-control-sm evidence-edit-input evidence-edit-phone" data-key="${safeKey}" data-value-kind="phone" value="${safeValue}" ${required}>`;
        }
        if (type === 'business_number') {
            return `<input type="text" inputmode="numeric" class="form-control form-control-sm evidence-edit-input evidence-edit-business-number" data-key="${safeKey}" data-value-kind="business_number" value="${safeValue}" ${required}>`;
        }
        if (type === 'date') {
            return `
                <div class="evidence-edit-date-wrap">
                    <input type="text" inputmode="numeric" class="form-control form-control-sm evidence-edit-input evidence-edit-date" data-key="${safeKey}" data-value-kind="date" data-keep-time="${keepTime ? '1' : '0'}" value="${safeValue}" placeholder="${keepTime ? 'YYYY-MM-DD HH:mm:ss' : 'YYYY-MM-DD'}" ${required}>
                    <button type="button" class="btn btn-outline-secondary btn-sm evidence-edit-date-btn" aria-label="날짜 선택">
                        <i class="bi bi-calendar3"></i>
                    </button>
                </div>
            `;
        }
        return `<input type="${type}" class="form-control form-control-sm evidence-edit-input" data-key="${safeKey}" value="${safeValue}" ${required}>`;
    }

    function bulkEditableColumns() {
        const columns = Array.isArray(activeFormat?.columns) ? activeFormat.columns : [];
        return columns
            .slice()
            .sort(compareFormatColumns)
            .filter((column) => !isDeprecatedBankFormatColumn(column))
            .filter((column) => editFieldKey(column) !== '')
            .filter((column) => ['basic', 'standard'].includes(infoColumnTone(column)));
    }

    function renderBulkInput(column = {}) {
        const type = editInputType(column, '');
        const key = editFieldKey(column);
        const safeKey = escapeHtml(key);
        const keepTime = isDateTimeColumn(column);

        if (type === 'ref') {
            const config = businessRefPickerForColumn(column);
            if (!config) return `<input type="text" class="form-control form-control-sm evidence-bulk-input" data-key="${safeKey}" disabled>`;
            return `
                <select class="form-select form-select-sm evidence-bulk-input evidence-bulk-ref"
                    data-key="${safeKey}"
                    data-value-kind="ref"
                    data-ref-picker="${escapeHtml(config.picker)}"
                    data-ref-id-key="${escapeHtml(config.idKey)}"
                    data-ref-name-key="${escapeHtml(config.nameKey)}"
                    data-ref-allow-text="${config.allowText ? '1' : '0'}"
                    disabled>
                    <option value=""></option>
                </select>
            `;
        }

        if (type === 'code') {
            const config = bankCodePickerForColumn(column);
            return `
                <select class="form-select form-select-sm evidence-bulk-input evidence-bulk-code"
                    data-key="${safeKey}"
                    data-code-group="${escapeHtml(config?.codeGroup || '')}"
                    data-empty-label="${escapeHtml(config?.emptyLabel || '선택(없음)')}"
                    data-code-searchable="true"
                    disabled></select>
            `;
        }

        if (type === 'number') {
            return `<input type="text" inputmode="decimal" class="form-control form-control-sm evidence-bulk-input evidence-bulk-number" data-key="${safeKey}" data-value-kind="number" disabled>`;
        }
        if (type === 'business_number') {
            return `<input type="text" inputmode="numeric" class="form-control form-control-sm evidence-bulk-input evidence-bulk-business-number" data-key="${safeKey}" data-value-kind="business_number" disabled>`;
        }
        if (type === 'phone') {
            return `<input type="text" inputmode="tel" class="form-control form-control-sm evidence-bulk-input evidence-bulk-phone" data-key="${safeKey}" data-value-kind="phone" disabled>`;
        }
        if (type === 'date') {
            return `
                <div class="evidence-edit-date-wrap">
                    <input type="text" inputmode="numeric" class="form-control form-control-sm evidence-bulk-input evidence-bulk-date evidence-edit-date" data-key="${safeKey}" data-value-kind="date" data-keep-time="${keepTime ? '1' : '0'}" placeholder="${keepTime ? 'YYYY-MM-DD HH:mm:ss' : 'YYYY-MM-DD'}" disabled>
                    <button type="button" class="btn btn-outline-secondary btn-sm evidence-edit-date-btn" aria-label="날짜 선택" disabled>
                        <i class="bi bi-calendar3"></i>
                    </button>
                </div>
            `;
        }
        if (type === 'textarea') {
            return `<textarea class="form-control form-control-sm evidence-bulk-input" rows="2" data-key="${safeKey}" disabled></textarea>`;
        }
        return `<input type="text" class="form-control form-control-sm evidence-bulk-input" data-key="${safeKey}" disabled>`;
    }

    function renderBulkFields() {
        if (!refs.bulkFields) return;
        const columns = bulkEditableColumns();
        if (columns.length === 0) {
            refs.bulkFields.innerHTML = '<div class="evidence-bulk-empty">현재 양식에는 일괄보정 가능한 기준정보/기초정보 컬럼이 없습니다.</div>';
            return;
        }

        refs.bulkFields.innerHTML = columns.map((column) => {
            const key = editFieldKey(column);
            const tone = infoColumnTone(column);
            const title = String(column.excel_column_name || column.system_field_name || key || '항목');
            return `
                <div class="evidence-bulk-field evidence-bulk-field-${escapeHtml(tone)}">
                    <label class="evidence-bulk-check" title="${escapeHtml(title)}">
                        <input type="checkbox" class="evidence-bulk-toggle" data-key="${escapeHtml(key)}">
                        <span>${escapeHtml(title)} ${requirementStar(column)}</span>
                    </label>
                    <div class="evidence-bulk-control">
                        ${renderBulkInput(column)}
                    </div>
                </div>
            `;
        }).join('');
    }

    function initBulkRefSelect(select) {
        if (!select || select.dataset.refSelectBound === 'true') return;
        if (!window.jQuery?.fn?.select2) return;

        const config = Object.values(EVIDENCE_REF_PICKERS).find((item) => item.picker === select.dataset.refPicker);
        if (!config) return;
        const url = {
            supplierCompany: API.clientSearch,
            customerCompany: API.clientSearch,
            client: API.clientSearch,
            project: API.projectSearch,
            employee: API.employeeSearch,
            bankAccount: API.bankAccountSearch,
            card: API.cardSearch,
        }[config.picker];
        if (!url) return;

        AdminPicker.select2Ajax(select, {
            url,
            placeholder: config.placeholder,
            allowClear: true,
            tags: !!config.allowText,
            minimumInputLength: 0,
            dropdownParent: window.jQuery(refs.bulkModal),
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
                return { q: params.term || '', limit: 20, is_active: 1 };
            },
            processResults(data) {
                const rows = data?.results ?? data?.data ?? [];
                return {
                    results: [
                        { id: '', text: '선택(없음)' },
                        ...rows.map((row) => {
                            const text = typeof config.listText === 'function' ? config.listText(row) : config.label(row);
                            const refText = typeof config.saveText === 'function' ? config.saveText(row) : text;
                            return {
                                id: String(row.id ?? text ?? ''),
                                text,
                                refText,
                                selectionText: refText,
                                ...(typeof config.result === 'function' ? config.result(row) : {}),
                            };
                        }).filter((item) => item.id !== '' && item.text !== ''),
                    ],
                };
            },
        });

        window.jQuery(select)
            .off('select2:select.evidenceBulkRef')
            .on('select2:select.evidenceBulkRef', function (event) {
                const data = event.params?.data || {};
                this.dataset.refSelectedText = data.refText || data.selectionText || data.text || '';
            })
            .off('select2:clear.evidenceBulkRef')
            .on('select2:clear.evidenceBulkRef', function () {
                this.dataset.refSelectedText = '';
            });

        select.dataset.refSelectBound = 'true';
    }

    function bindBulkFieldBehaviors() {
        refs.bulkFields?.querySelectorAll('.evidence-bulk-number').forEach((input) => {
            bindCommonNumberInput(input);
        });
        refs.bulkFields?.querySelectorAll('.evidence-bulk-business-number').forEach((input) => {
            if (input.dataset.businessNumberFormatBound === 'true') return;
            const apply = () => { input.value = formatBizNumber(input.value); };
            input.addEventListener('input', apply);
            input.addEventListener('blur', apply);
            input.dataset.businessNumberFormatBound = 'true';
        });
        refs.bulkFields?.querySelectorAll('.evidence-bulk-phone').forEach((input) => {
            if (input.dataset.phoneFormatBound === 'true') return;
            const apply = () => { input.value = formatPhone(input.value); };
            input.addEventListener('input', apply);
            input.addEventListener('blur', apply);
            input.dataset.phoneFormatBound = 'true';
        });
        refs.bulkFields?.querySelectorAll('.evidence-edit-date-wrap').forEach((wrap) => {
            bindDateEditInput(
                wrap.querySelector('.evidence-bulk-date'),
                wrap.querySelector('.evidence-edit-date-btn')
            );
        });
        refs.bulkFields?.querySelectorAll('.evidence-bulk-ref').forEach((select) => {
            initBulkRefSelect(select);
        });
        if (refs.bulkFields?.querySelector('select.evidence-bulk-code')) {
            void initCodeSelectControls(refs.bulkFields);
        }
    }

    function toggleBulkField(toggle) {
        const field = toggle.closest('.evidence-bulk-field');
        field?.querySelectorAll('.evidence-bulk-input, .evidence-edit-date-btn').forEach((control) => {
            control.disabled = !toggle.checked;
            if (window.jQuery?.fn?.select2 && control.matches?.('select')) {
                window.jQuery(control).prop('disabled', !toggle.checked).trigger('change.select2');
            }
        });
    }

    function openBulkEditModal() {
        const ids = table?.getSelectedIds?.() || Array.from(selectedIds);
        if (ids.length === 0) {
            notify('warning', '일괄보정할 행을 선택하세요.');
            return;
        }
        if (!activeFormat || !Array.isArray(activeFormat.columns) || activeFormat.columns.length === 0) {
            notify('warning', '먼저 양식관리에서 이 자료유형의 양식을 생성하세요.');
            return;
        }
        if (refs.bulkSubtitle) {
            refs.bulkSubtitle.textContent = `${selectedTypeLabel() || currentType} ${ids.length.toLocaleString('ko-KR')}건 선택`;
        }
        renderBulkFields();
        bindBulkFieldBehaviors();
        refs.bulkFields?.querySelectorAll('.evidence-bulk-toggle').forEach((toggle) => toggleBulkField(toggle));
        bulkModal = bootstrap.Modal.getOrCreateInstance(refs.bulkModal, { focus: false });
        bulkModal.show();
    }

    function collectBulkPayload() {
        const patch = {};
        refs.bulkFields?.querySelectorAll('.evidence-bulk-toggle:checked').forEach((toggle) => {
            const key = toggle.dataset.key || '';
            const input = refs.bulkFields.querySelector(`.evidence-bulk-input[data-key="${escapeSelectorValue(key)}"]`);
            if (!key || !input) return;

            if (input.dataset.valueKind === 'ref') {
                const idKey = input.dataset.refIdKey || '';
                const nameKey = input.dataset.refNameKey || key;
                const allowsText = input.dataset.refAllowText === '1';
                const selectedId = selectValueForSave(input);
                const selectedText = selectTextForSave(input);
                const isFreeTextSelection = allowsText && selectedId !== '' && selectedText !== '' && selectedId === selectedText;
                patch[key] = selectedId === '' ? '' : selectedText;
                if (nameKey) patch[nameKey] = selectedId === '' ? '' : selectedText;
                if (idKey) patch[idKey] = selectedId !== '' && !isFreeTextSelection ? selectedId : '';
                return;
            }

            if (input.dataset.valueKind === 'number') {
                patch[key] = input.value === '' ? '' : String(parseCommonNumber(input.value));
                return;
            }
            if (input.dataset.valueKind === 'date') {
                patch[key] = normalizeDateInputValue(input.value, input.dataset.keepTime === '1');
                return;
            }
            if (input.dataset.valueKind === 'business_number') {
                patch[key] = formatBizNumber(input.value);
                return;
            }
            if (input.dataset.valueKind === 'phone') {
                patch[key] = formatPhone(input.value);
                return;
            }
            patch[key] = input.matches('select') ? selectValueForSave(input) : input.value;
        });
        return patch;
    }

    async function saveBulkEdit() {
        const ids = table?.getSelectedIds?.() || Array.from(selectedIds);
        const patch = collectBulkPayload();
        if (ids.length === 0) {
            notify('warning', '일괄보정할 행을 선택하세요.');
            return;
        }
        if (Object.keys(patch).length === 0) {
            notify('warning', '저장할 보정 항목을 체크하세요.');
            return;
        }
        const mode = refs.bulkModal?.querySelector('input[name="evidenceBulkMode"]:checked')?.value || 'fill_blank';
        const button = refs.bulkSaveBtn;
        const originalText = button?.textContent || '선택항목 저장';
        if (button) {
            button.disabled = true;
            button.textContent = '저장 중';
        }
        try {
            const json = await fetch(API.bulkSaveSeedRows, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    evidence_ids: ids,
                    parsed_patch: patch,
                    mode,
                }),
            }).then(async (response) => {
                const body = await response.json().catch(() => ({}));
                if (!response.ok || body.success === false) {
                    throw new Error(body.message || '일괄보정 저장에 실패했습니다.');
                }
                return body;
            });
            notify('success', json.message || '선택한 증빙원본이 일괄보정되었습니다.');
            bulkModal?.hide();
            table?.clearSelectedIds?.();
            table?.ajax.reload(() => updateSummary(lastRows), false);
            void refreshEvidenceTypeCounts().catch(() => {});
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    }

    function renderEditFields(row = {}) {
        if (!refs.editFields) return;
        clearEditPickerLayers();
        const columns = Array.isArray(activeFormat?.columns) ? activeFormat.columns : [];
        const editableColumns = columns
            .slice()
            .sort(compareFormatColumns)
            .filter((column) => !isDeprecatedBankFormatColumn(column))
            .filter((column) => editFieldKey(column) !== '');

        if (editableColumns.length === 0) {
            refs.editFields.innerHTML = '<div class="text-muted text-center py-4">현재 양식에 수정할 컬럼이 없습니다.</div>';
            return;
        }

        refs.editFields.innerHTML = editableColumns.map((column) => {
            const key = editFieldKey(column);
            const title = String(column.excel_column_name || key);
            const systemField = String(column.system_field_name || '').trim();
            const value = editFieldValue(row, column);
            const cleanTitle = title.replace(/\s*\*$/u, '');
            const displayStar = requirementStar(column);
            const infoTone = infoColumnTone(column);
            const infoToneClass = infoTone ? ` evidence-edit-field-${infoTone}` : '';
            return `
                <label class="evidence-edit-field${infoToneClass}">
                    <span class="form-label small mb-1 d-flex align-items-center gap-2">
                        <span class="evidence-edit-title">
                            <span class="evidence-edit-title-text">${escapeHtml(cleanTitle)}</span>
                            ${displayStar}
                        </span>
                        <span class="text-muted">${escapeHtml(systemField || key)}</span>
                    </span>
                    ${renderEditInput(column, value)}
                </label>
            `;
        }).join('');
    }

    function clearEditPickerLayers() {
        editPickerLayers.forEach((layer) => layer.remove());
        editPickerLayers = [];
    }

    function bindDateEditInput(input, button) {
        if (!input || input.dataset.dateFormatBound === 'true') return;

        const keepTime = input.dataset.keepTime === '1';
        const normalize = () => {
            input.value = normalizeDateInputValue(input.value, keepTime);
        };

        input.addEventListener('change', normalize);
        input.addEventListener('blur', normalize);

        if (!button) {
            input.dataset.dateFormatBound = 'true';
            return;
        }

        const layer = document.createElement('div');
        layer.className = 'picker is-hidden evidence-edit-picker-layer';
        document.body.appendChild(layer);
        editPickerLayers.push(layer);

        const picker = AdminPicker.create({ type: keepTime ? 'datetime' : 'today', container: layer });
        let primingPicker = false;

        picker.subscribe((state, finalDate) => {
            if (primingPicker) return;
            if (!(finalDate instanceof Date)) return;

            input.value = keepTime && state?.timeEnabled
                ? formatPickerDateTime(finalDate, /:\d{2}:\d{2}/.test(String(input.value || '')))
                : formatPickerDate(finalDate);
            input.dispatchEvent(new Event('change', { bubbles: true }));
            if (!keepTime) {
                picker.close?.();
            }
        });

        input.addEventListener('focus', () => {
            picker.close?.();
        });

        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (input.disabled) return;
            primingPicker = true;
            applyDateToPicker(picker, input.value, keepTime);
            requestAnimationFrame(() => {
                primingPicker = false;
            });
            picker.open?.({ anchor: input });
        });

        input.dataset.dateFormatBound = 'true';
    }

    function escapeSelectorValue(value) {
        const text = String(value ?? '');
        if (window.CSS?.escape) return window.CSS.escape(text);
        return text.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function editInputByKey(key) {
        if (!refs.editFields || !key) return null;
        return refs.editFields.querySelector(`.evidence-edit-input[data-key="${escapeSelectorValue(key)}"]`);
    }

    function formatValueForEditInput(input, value) {
        if (input?.dataset?.valueKind === 'business_number') return formatBizNumber(value);
        if (input?.dataset?.valueKind === 'phone') return formatPhone(value);
        return valueText(value);
    }

    function applyEditValueIfBlank(key, value, options = {}) {
        const input = editInputByKey(key);
        const nextValue = formatValueForEditInput(input, value);
        if (!input || nextValue === '') return;
        if (!options.overwrite && String(input.value ?? '').trim() !== '') return;

        input.value = nextValue;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function applyRefAutofill(config = {}, data = {}) {
        if (!config.autofill) return;
        Object.entries(config.autofill).forEach(([targetKey, sourceKey]) => {
            applyEditValueIfBlank(targetKey, data[sourceKey], { overwrite: true });
        });
    }

    function initEditRefSelect(select) {
        const config = Object.values(EVIDENCE_REF_PICKERS).find((item) => item.picker === select.dataset.refPicker);
        if (!config) return;
        initEvidenceRefSelect(select, {
            modal: refs.editModal,
            api: API,
            onSelect(_select, data) {
                applyRefAutofill(config, data);
            },
        });
    }

    function bindEditFieldBehaviors() {
        refs.editFields?.querySelectorAll('.evidence-edit-number').forEach((input) => {
            bindCommonNumberInput(input);
        });

        refs.editFields?.querySelectorAll('.evidence-edit-business-number').forEach((input) => {
            if (input.dataset.businessNumberFormatBound === 'true') return;

            const apply = () => {
                input.value = formatBizNumber(input.value);
            };

            input.addEventListener('input', apply);
            input.addEventListener('blur', apply);
            apply();
            input.dataset.businessNumberFormatBound = 'true';
        });

        refs.editFields?.querySelectorAll('.evidence-edit-phone').forEach((input) => {
            if (input.dataset.phoneFormatBound === 'true') return;

            const apply = () => {
                input.value = formatPhone(input.value);
            };

            input.addEventListener('input', apply);
            input.addEventListener('blur', apply);
            apply();
            input.dataset.phoneFormatBound = 'true';
        });

        refs.editFields?.querySelectorAll('.evidence-edit-date-wrap').forEach((wrap) => {
            bindDateEditInput(
                wrap.querySelector('.evidence-edit-date'),
                wrap.querySelector('.evidence-edit-date-btn')
            );
        });

        refs.editFields?.querySelectorAll('.evidence-edit-ref').forEach((select) => {
            initEditRefSelect(select);
        });
        if (refs.editFields?.querySelector('select.evidence-edit-code')) {
            void initCodeSelectControls(refs.editFields);
        }
    }

    function openEditModal(row = {}) {
        if (!row?.id || !refs.editModal) return;
        if (!activeFormat || !Array.isArray(activeFormat.columns) || activeFormat.columns.length === 0) {
            notify('warning', '먼저 양식관리에서 이 자료유형의 양식을 생성하세요.');
            return;
        }
        editingRow = row;
        refs.editId.value = row.id;
        const typeLabel = evidenceTypeDisplayName(row);
        if (refs.editTitle) {
            refs.editTitle.textContent = `${typeLabel} 원본자료 수정`;
        }
        if (refs.editSubtitle) {
            refs.editSubtitle.textContent = [
                `순번 ${row.row_no || '-'}`,
                typeLabel,
                normalizedStatus(row) || '-',
            ].join(' / ');
        }
        renderEditFields(row);
        bindEditFieldBehaviors();
        const isEditable = normalizedStatus(row) !== 'PROCESSED' && normalizedStatus(row) !== 'DELETED';
        refs.editFields?.querySelectorAll('.evidence-edit-input').forEach((input) => {
            input.disabled = !isEditable;
            if (input.classList.contains('evidence-edit-ref') && window.jQuery?.fn?.select2) {
                window.jQuery(input).prop('disabled', !isEditable).trigger('change.select2');
            }
        });
        refs.editFields?.querySelectorAll('.evidence-edit-date-btn').forEach((button) => {
            button.disabled = !isEditable;
        });
        if (refs.editSaveBtn) refs.editSaveBtn.disabled = !isEditable;
        editModal = bootstrap.Modal.getOrCreateInstance(refs.editModal, { focus: false });
        editModal.show();
    }

    function openNewEvidenceModal() {
        if (!refs.editModal) return;
        if (!activeFormat || !Array.isArray(activeFormat.columns) || activeFormat.columns.length === 0) {
            notify('warning', '먼저 양식관리에서 이 자료유형의 양식을 생성하세요.');
            return;
        }

        editingRow = {
            __isNew: true,
            id: '',
            format_id: activeFormat.id || '',
            import_type: currentType,
            source_type: currentType,
            import_type_name: selectedTypeLabel() || currentType,
            source_type_name: selectedTypeLabel() || currentType,
            mapped_payload: {},
            raw_payload: {},
        };
        refs.editId.value = '';
        const typeLabel = evidenceTypeDisplayName(editingRow);
        if (refs.editTitle) {
            refs.editTitle.textContent = `${typeLabel} 새 증빙`;
        }
        if (refs.editSubtitle) {
            refs.editSubtitle.textContent = [
                typeLabel,
                '신규 입력',
            ].join(' / ');
        }
        renderEditFields(editingRow);
        bindEditFieldBehaviors();
        refs.editFields?.querySelectorAll('.evidence-edit-input').forEach((input) => {
            input.disabled = false;
            if (input.classList.contains('evidence-edit-ref') && window.jQuery?.fn?.select2) {
                window.jQuery(input).prop('disabled', false).trigger('change.select2');
            }
        });
        refs.editFields?.querySelectorAll('.evidence-edit-date-btn').forEach((button) => {
            button.disabled = false;
        });
        if (refs.editSaveBtn) refs.editSaveBtn.disabled = false;
        editModal = bootstrap.Modal.getOrCreateInstance(refs.editModal, { focus: false });
        editModal.show();
    }

    function splitRowsForEvidence(row = {}) {
        const evidenceId = String(row?.evidence_id || row?.id || '').trim();
        if (Array.isArray(row?.processing_children)) {
            const parent = { ...row };
            delete parent.processing_children;
            const children = row.processing_children
                .filter((item) => item && typeof item === 'object')
                .sort((a, b) => String(a.processing_display_path || a.row_no || '').localeCompare(String(b.processing_display_path || b.row_no || ''), 'ko-KR', { numeric: true }));
            return { evidenceId, parent, children };
        }
        const rows = lastRows.filter((item) => String(item.evidence_id || item.id || '').trim() === evidenceId);
        const parent = rows.find((item) => item.processing_has_children)
            || rows.find((item) => !item.processing_is_child)
            || row;
        const children = rows
            .filter((item) => item.processing_is_child)
            .sort((a, b) => String(a.processing_display_path || a.row_no || '').localeCompare(String(b.processing_display_path || b.row_no || ''), 'ko-KR', { numeric: true }));
        return { evidenceId, parent, children };
    }

    function ensureSplitModal() {
        let modal = document.getElementById('evidenceProcessingSplitModal');
        if (modal) return modal;
        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="evidenceProcessingSplitModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content evidence-split-modal">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title">분할 항목 수정</h5>
                                <div class="small text-muted evidence-split-subtitle"></div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info py-2 small">부모 금액과 자식행 금액 합계가 같아야 저장됩니다.</div>
                            <div class="table-responsive evidence-split-scroll">
                                <table class="table table-sm align-middle evidence-split-table mb-2">
                                    <thead>
                                        <tr>
                                            <th style="width:72px;">순번</th>
                                            <th>금액</th>
                                            <th>적요</th>
                                            <th style="width:70px;">관리</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm evidence-split-add">+ 추가</button>
                        </div>
                        <div class="modal-footer">
                            <span class="me-auto small evidence-split-total"></span>
                            <button type="button" class="btn btn-primary btn-sm evidence-split-save">저장</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        modal = document.getElementById('evidenceProcessingSplitModal');
        modal.querySelector('.evidence-split-add')?.addEventListener('click', () => {
            const tbody = modal.querySelector('tbody');
            const parentPayload = modal._splitParentPayload && typeof modal._splitParentPayload === 'object'
                ? { ...modal._splitParentPayload }
                : {};
            const amountKeys = splitAmountKeys();
            const childCount = tbody?.querySelectorAll('tr[data-split-role="child"]').length || 0;
            tbody?.insertAdjacentHTML('beforeend', splitRowHtml({
                sort_no: childCount + 1,
                mapped_payload: splitPayloadWithAmounts(parentPayload, amountKeys, 0),
            }));
            bindSplitModalInputs(modal);
            refreshSplitModalRows(modal);
        });
        modal.querySelector('tbody')?.addEventListener('click', (event) => {
            const button = event.target.closest('.evidence-split-remove');
            if (!button) return;
            button.closest('tr')?.remove();
            refreshSplitModalRows(modal);
        });
        modal.querySelector('tbody')?.addEventListener('input', () => refreshSplitModalRows(modal));
        modal.querySelector('.evidence-split-save')?.addEventListener('click', () => saveSplitModal(modal));
        bindSplitModalHorizontalWheel(modal);
        return modal;
    }

    function ensureChildEditModal() {
        let modal = document.getElementById('evidenceProcessingChildEditModal');
        if (modal) return modal;
        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="evidenceProcessingChildEditModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content evidence-split-modal">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title">자식 항목 수정</h5>
                                <div class="small text-muted evidence-child-edit-subtitle"></div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive evidence-split-scroll">
                                <table class="table table-sm align-middle evidence-split-table mb-0">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-sm evidence-child-edit-save">저장</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        modal = document.getElementById('evidenceProcessingChildEditModal');
        modal.querySelector('.evidence-child-edit-save')?.addEventListener('click', () => saveProcessingChildEdit(modal));
        bindSplitModalHorizontalWheel(modal);
        return modal;
    }

    function splitModalColumns() {
        const formatColumns = Array.isArray(activeFormat?.columns) ? activeFormat.columns.slice().sort(compareFormatColumns) : [];
        const columns = formatColumns.map((column) => ({
            column,
            key: splitColumnKey(column),
            title: String(column.excel_column_name || column.system_field_name || '').trim(),
            amount: isAmountColumn(column),
            tone: infoColumnTone(column),
            type: editInputType(column, ''),
        })).filter((column) => column.key && column.title && !isSplitExcludedColumn(column));
        return columns;
    }

    function splitColumnKey(column = {}) {
        const key = editFieldKey(column);
        if (currentType === 'BANK_TRANSACTION' && key === 'withdrawal_amount') {
            return 'withdraw_amount';
        }
        return key;
    }

    function isSplitExcludedColumn(column = {}) {
        const key = String(column.key || column.column?.system_field_name || '').toLowerCase();
        const title = String(column.title || column.column?.excel_column_name || '').trim();
        const text = `${key} ${title}`;
        if (/balance_amount|check_bill_amount|balance|거래후잔액|잔액|수표어음/.test(text)) {
            return true;
        }
        if (currentType === 'BANK_TRANSACTION' && column.amount) {
            return !/(^|_)(deposit|withdraw|withdrawal)(_|$)|입금|출금/.test(text);
        }
        return false;
    }

    function splitAmountColumns() {
        return splitModalColumns().filter((column) => {
            const field = String(column.key || column.column?.system_field_name || '').toLowerCase();
            const title = String(column.title || column.column?.excel_column_name || '').trim();
            const text = `${field} ${title}`;
            if (/unit_price|foreign_unit_price|exchange_rate|rate|quantity|qty|balance_amount|단가|수량|환율|잔액/.test(text)) {
                return false;
            }
            return /amount|total|vat|tax|fee|charge|duty|deposit|withdraw|withdrawal|supply|settlement|gross|withholding|공급가액|부가세|세액|합계|합계금액|금액|관세|수수료|봉사료|매출|정산액|지급액|원천세|입금|출금/.test(text);
        });
    }

    function splitAmountKeys() {
        return splitAmountColumns().map((column) => column.key).filter(Boolean);
    }

    function splitPayloadWithAmounts(payload = {}, amountKeys = [], value = 0) {
        const next = { ...payload };
        amountKeys.forEach((key) => {
            next[key] = value;
        });
        return next;
    }

    function splitAmountSummary(modal) {
        const parentPayload = modal._splitParentPayload && typeof modal._splitParentPayload === 'object'
            ? modal._splitParentPayload
            : {};
        return splitAmountColumns().map((column) => {
            const parentValue = amount(parentPayload[column.key]);
            let childValue = 0;
            modal.querySelectorAll('tbody tr[data-split-role="child"]').forEach((tr) => {
                const input = Array.from(tr.querySelectorAll('.evidence-split-field'))
                    .find((field) => field.dataset.key === column.key);
                childValue += amount(input?.value || 0);
            });
            return {
                title: column.title,
                parentValue,
                childValue,
                valid: Math.abs(parentValue - childValue) <= 0.01,
            };
        });
    }

    function splitRequiredColumns() {
        return splitModalColumns().filter((column) => requirementMode(column.column || {}) === 1);
    }

    function renderSplitModalHeader(modal) {
        const headerRow = modal.querySelector('.evidence-split-table thead tr');
        if (!headerRow) return;
        headerRow.innerHTML = `
            <th style="width:36px;"></th>
            <th style="width:72px;">순번</th>
            ${splitModalColumns().map((column) => `<th>${escapeHtml(column.title)}${requirementStar(column.column || {})}</th>`).join('')}
            <th style="width:70px;">관리</th>
        `;
    }

    function renderChildEditModalHeader(modal) {
        const headerRow = modal.querySelector('.evidence-split-table thead tr');
        if (!headerRow) return;
        headerRow.innerHTML = `
            <th style="width:72px;">순번</th>
            ${splitModalColumns().map((column) => `<th>${escapeHtml(column.title)}${requirementStar(column.column || {})}</th>`).join('')}
        `;
    }

    function splitSelectOption(value = '', text = '') {
        const optionValue = valueText(value);
        const optionText = valueText(text) || optionValue;
        return optionValue !== ''
            ? `<option value="${escapeHtml(optionValue)}" selected>${escapeHtml(optionText || optionValue)}</option>`
            : '';
    }

    function splitCodeValue(column, value) {
        const raw = valueText(value);
        if (raw === '') return '';
        const group = bankCodePickerForColumn(column.column || {})?.codeGroup || DISPLAY_CODE_FIELDS[column.key] || '';
        if (group === '') return codeValueForField(column.key, raw);
        const normalized = normalizeCodeKey(raw);
        const found = (codeOptions[group] || []).find((row) => (
            normalizeCodeKey(row.code) === normalized
            || String(row.code_name ?? '').trim() === raw
        ));
        return found?.code || CODE_NAME_ALIASES[column.key]?.[raw] || raw;
    }

    function splitCodeDisplayName(column, value) {
        const code = normalizeCodeKey(value);
        if (code === '') return '';
        const group = bankCodePickerForColumn(column.column || {})?.codeGroup || DISPLAY_CODE_FIELDS[column.key] || '';
        if (group === '') return codeDisplayName(column.key, value);
        const found = (codeOptions[group] || []).find((row) => normalizeCodeKey(row.code) === code);
        return found?.code_name || valueText(value);
    }

    function splitTimeForColumn(column, row = {}, payload = {}) {
        const key = String(column.key || '');
        const keys = Array.from(new Set([
            key.replace(/datetime/i, 'time'),
            key.replace(/date/i, 'time'),
            key.replace(/일시/g, '시간'),
            key.replace(/일자/g, '시간'),
            'transaction_time',
            'approval_time',
            'purchase_time',
            'time',
            '거래시간',
            '승인시간',
            '매입시간',
        ].filter(Boolean)));
        for (const candidate of keys) {
            const value = row[candidate] ?? payload[candidate];
            const normalized = normalizeTimeInputValue(value);
            if (normalized !== '') return normalized;
        }
        return '';
    }

    function splitValueForColumn(column, row = {}, payload = {}) {
        let rawValue = row[column.key] ?? payload[column.key] ?? '';
        if (currentType === 'BANK_TRANSACTION' && column.key === 'withdraw_amount' && valueText(rawValue) === '') {
            rawValue = row.withdrawal_amount ?? payload.withdrawal_amount ?? '';
        }
        if (column.type === 'date') {
            const explicitDateTime = explicitDateTimeValueForColumn(column, row, payload);
            if (explicitDateTime !== '') {
                return normalizeDateInputValue(explicitDateTime, true);
            }
            const raw = valueText(rawValue);
            const hasInlineTime = /\d{1,2}:\d{2}/.test(raw);
            const timeValue = splitTimeForColumn(column, row, payload);
            const keepTime = isDateTimeColumn(column.column || {}) || hasInlineTime || timeValue !== '';
            const source = keepTime && !hasInlineTime && timeValue !== '' ? `${raw} ${timeValue}` : raw;
            return normalizeDateInputValue(source, keepTime);
        }
        if (column.type === 'time') {
            return normalizeTimeInputValue(rawValue);
        }
        if (column.type === 'code') {
            return splitCodeValue(column, rawValue);
        }
        if (column.amount && rawValue !== '') {
            return formatNumber(rawValue);
        }
        return valueText(rawValue);
    }

    function splitCellHtml(column, child = {}, payload = {}) {
        const value = splitValueForColumn(column, child, payload);
        const toneClass = column.tone ? ` evidence-split-cell-${escapeHtml(column.tone)}` : '';
        const safeKey = escapeHtml(column.key);
        const editType = column.type || (column.amount ? 'number' : 'text');
        const keepTime = editType === 'date' && /\d{1,2}:\d{2}/.test(value);
        const required = requirementMode(column.column || {}) === 1 ? 'required' : '';
        let control = `<input type="text"
                       class="form-control form-control-sm evidence-split-field ${column.amount ? 'text-end evidence-split-amount' : ''}"
                       data-key="${safeKey}"
                       data-value-kind="${column.amount ? 'number' : 'text'}"
                       data-amount="${column.amount ? '1' : '0'}"
                       value="${escapeHtml(value)}" ${required}>`;
        if (editType === 'ref') {
            const config = businessRefPickerForColumn(column.column || {});
            const selectedId = firstPayloadText(payload, [config?.idKey]);
            const selectedText = firstPayloadText(payload, [config?.nameKey, ...(config?.keys || []), column.key]) || value;
            const optionValue = selectedId || selectedText;
            control = `
                <select class="form-select form-select-sm evidence-split-field evidence-split-ref"
                    data-key="${safeKey}"
                    data-value-kind="ref"
                    data-ref-picker="${escapeHtml(config?.picker || '')}"
                    data-ref-id-key="${escapeHtml(config?.idKey || '')}"
                    data-ref-name-key="${escapeHtml(config?.nameKey || column.key)}"
                    data-ref-allow-text="${config?.allowText ? '1' : '0'}"
                    data-amount="0" ${required}>
                    <option value=""></option>
                    ${splitSelectOption(optionValue, selectedText)}
                </select>
            `;
        } else if (editType === 'code') {
            const config = bankCodePickerForColumn(column.column || {});
            const selectedValue = splitCodeValue(column, value);
            const selectedText = splitCodeDisplayName(column, selectedValue);
            control = `
                <select class="form-select form-select-sm evidence-split-field evidence-split-code"
                    data-key="${safeKey}"
                    data-code-group="${escapeHtml(config?.codeGroup || '')}"
                    data-empty-label="${escapeHtml(config?.emptyLabel || '선택(없음)')}"
                    data-code-searchable="true"
                    data-value-kind="code"
                    data-amount="0" ${required}>
                    ${splitSelectOption(selectedValue, selectedText || selectedValue)}
                </select>
            `;
        } else if (editType === 'date') {
            control = `
                <div class="evidence-edit-date-wrap">
                    <input type="text"
                       inputmode="numeric"
                       class="form-control form-control-sm evidence-split-field evidence-split-date evidence-edit-date"
                       data-key="${safeKey}"
                       data-value-kind="date"
                       data-keep-time="${keepTime ? '1' : '0'}"
                       data-amount="0"
                       value="${escapeHtml(normalizeDateInputValue(value, keepTime))}"
                       placeholder="${keepTime ? 'YYYY-MM-DD HH:mm:ss' : 'YYYY-MM-DD'}" ${required}>
                    <button type="button" class="btn btn-outline-secondary btn-sm evidence-split-date-btn evidence-edit-date-btn" aria-label="${keepTime ? '일시 선택' : '날짜 선택'}">
                        <i class="bi bi-calendar3"></i>
                    </button>
                </div>
            `;
        } else if (editType === 'time') {
            control = `
                <div class="evidence-edit-date-wrap">
                    <input type="text"
                       inputmode="numeric"
                       class="form-control form-control-sm evidence-split-field evidence-split-time"
                       data-key="${safeKey}"
                       data-value-kind="time"
                       data-amount="0"
                       value="${escapeHtml(normalizeTimeInputValue(value))}"
                       placeholder="HH:mm" ${required}>
                    <button type="button" class="btn btn-outline-secondary btn-sm evidence-split-time-btn" aria-label="시간 선택">
                        <i class="bi bi-clock"></i>
                    </button>
                </div>
            `;
        }
        return `
            <td class="${toneClass}">
                ${control}
            </td>
        `;
    }

    function splitDisplayCellHtml(column, row = {}, payload = {}) {
        const value = column.type === 'code'
            ? splitCodeDisplayName(column, splitValueForColumn(column, row, payload))
            : splitValueForColumn(column, row, payload);
        const toneClass = column.tone ? ` evidence-split-cell-${escapeHtml(column.tone)}` : '';
        return `
            <td class="${toneClass}">
                <span class="evidence-split-parent-value ${column.amount ? 'text-end' : ''}">${escapeHtml(value)}</span>
            </td>
        `;
    }

    function splitParentRowHtml(parent = {}, parentPayload = {}) {
        const columns = splitModalColumns();
        const displayNo = parent.processing_display_path || parent.row_no || '부모';
        return `
            <tr class="evidence-split-parent-row" data-split-role="parent">
                <td class="text-center"><span class="evidence-split-parent-label">부모</span></td>
                <td class="text-center"><span>${escapeHtml(displayNo)}</span></td>
                ${columns.map((column) => splitDisplayCellHtml(column, parent, parentPayload)).join('')}
                <td class="text-center">-</td>
            </tr>
        `;
    }

    function splitRowHtml(child = {}) {
        const columns = splitModalColumns();
        const payload = mapped(child);
        const sortNo = child.sort_no || '';
        return `
            <tr data-id="${escapeHtml(child.processing_item_id || child.id || '')}" data-split-role="child">
                <td class="evidence-split-drag text-center"><span class="bi bi-list" title="순서변경"></span></td>
                <td class="text-center"><span class="evidence-split-sort">${escapeHtml(sortNo)}</span></td>
                ${columns.map((column) => splitCellHtml(column, child, payload)).join('')}
                <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm evidence-split-remove">삭제</button></td>
            </tr>
        `;
    }

    function childEditRowHtml(child = {}) {
        const columns = splitModalColumns();
        const payload = mapped(child);
        const sortNo = child.processing_display_path || child.row_no || child.sort_no || '';
        return `
            <tr data-id="${escapeHtml(child.processing_item_id || child.id || '')}" data-split-role="child">
                <td class="text-center"><span class="evidence-split-sort">${escapeHtml(sortNo)}</span></td>
                ${columns.map((column) => splitCellHtml(column, child, payload)).join('')}
            </tr>
        `;
    }
    function refreshSplitModalRows(modal) {
        const rows = Array.from(modal.querySelectorAll('tbody tr[data-split-role="child"]'));
        rows.forEach((tr, index) => {
            const sortNode = tr.querySelector('.evidence-split-sort');
            if (sortNode) sortNode.textContent = String(index + 1);
        });
        const total = modal.querySelector('.evidence-split-total');
        if (total) {
            const summaries = splitAmountSummary(modal);
            total.textContent = summaries.length > 0
                ? summaries.map((item) => `${item.title} 부모 ${formatNumber(item.parentValue)} / 자식합계 ${formatNumber(item.childValue)}`).join(' · ')
                : '분할 기준 금액 컬럼이 없습니다.';
            total.classList.toggle('text-danger', summaries.some((item) => !item.valid));
        }
    }

    function openProcessingSplitModal(row = {}) {
        const modal = ensureSplitModal();
        const { evidenceId, parent, children } = splitRowsForEvidence(row);
        const parentPayload = mapped(parent);
        modal._splitParentPayload = { ...parentPayload };
        modal.dataset.evidenceId = evidenceId;
        modal.dataset.parentProcessingItemId = parent.processing_item_id || row.processing_parent_item_id || row.processing_item_id || '';
        const subtitle = modal.querySelector('.evidence-split-subtitle');
        if (subtitle) {
            subtitle.textContent = `순번 ${parent.processing_display_path || parent.row_no || '-'}`;
        }
        const amountKeys = splitAmountKeys();
        const initialChildren = children.length > 0 ? children : [
            { sort_no: 1, mapped_payload: { ...parentPayload } },
            { sort_no: 2, mapped_payload: splitPayloadWithAmounts(parentPayload, amountKeys, 0) },
        ];
        renderSplitModalHeader(modal);
        bindSplitModalReorder(modal);
        const tbody = modal.querySelector('tbody');
        if (tbody) {
            const childRows = initialChildren.map((child, index) => splitRowHtml({
                ...child,
                sort_no: child.sort_no || index + 1,
            })).join('');
            tbody.innerHTML = splitParentRowHtml(parent, parentPayload) + childRows;
        }
        bindSplitModalInputs(modal);
        refreshSplitModalRows(modal);
        bootstrap.Modal.getOrCreateInstance(modal, { focus: false }).show();
    }

    function openProcessingChildEditModal(row = {}) {
        const modal = ensureChildEditModal();
        modal.dataset.processingItemId = row.processing_item_id || row.id || '';
        const subtitle = modal.querySelector('.evidence-child-edit-subtitle');
        if (subtitle) {
            subtitle.textContent = `순번 ${row.processing_display_path || row.row_no || '-'}`;
        }
        renderChildEditModalHeader(modal);
        const tbody = modal.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = childEditRowHtml(row);
        }
        bindSplitModalInputs(modal);
        bootstrap.Modal.getOrCreateInstance(modal, { focus: false }).show();
    }

    function bindSplitModalHorizontalWheel(modal) {
        const scroll = modal.querySelector('.evidence-split-scroll');
        if (!scroll || scroll.dataset.horizontalWheelBound === 'true') return;
        scroll.addEventListener('wheel', (event) => {
            if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;
            if (scroll.scrollWidth <= scroll.clientWidth) return;
            event.preventDefault();
            scroll.scrollLeft += event.deltaY;
        }, { passive: false });
        scroll.dataset.horizontalWheelBound = 'true';
    }

    function bindSplitTimeInput(input) {
        if (!input || input.dataset.timeInputBound === 'true') return;
        const normalize = () => {
            input.value = normalizeTimeInputValue(input.value);
        };
        input.addEventListener('change', normalize);
        input.addEventListener('blur', normalize);
        input.dataset.timeInputBound = 'true';
    }

    function bindSplitTimePicker(button) {
        if (!button || button.dataset.timePickerBound === 'true') return;

        button.addEventListener('click', (event) => {
            event.preventDefault();
            const input = button.closest('.evidence-edit-date-wrap')?.querySelector('.evidence-split-time');
            if (!input || input.disabled) return;

            const layer = document.createElement('div');
            layer.className = 'picker is-hidden evidence-edit-picker-layer';
            document.body.appendChild(layer);
            editPickerLayers.push(layer);

            const picker = AdminPicker.create({ type: 'time-list', container: layer, options: { step: 10, rows: 8 } });
            const currentTime = normalizeTimeInputValue(input.value);
            if (currentTime) {
                const [hour, minute] = currentTime.split(':').map((item) => Number(item));
                picker.setTime?.({ hour, minute, meridiem: hour >= 12 ? 'PM' : 'AM' });
            }
            const closePicker = picker.close?.bind(picker);
            picker.close = () => {
                closePicker?.();
                window.setTimeout(() => layer.remove(), 0);
            };
            picker.subscribe((state) => {
                if (typeof state?.hour !== 'number' || typeof state?.minute !== 'number') return;
                input.value = `${pad2(state.hour)}:${pad2(state.minute)}`;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                picker.close?.();
            });
            picker.open?.({ anchor: input });
        });

        button.dataset.timePickerBound = 'true';
    }

    function bindSplitModalInputs(modal) {
        modal.querySelectorAll('.evidence-split-amount').forEach((input) => bindCommonNumberInput(input));
        modal.querySelectorAll('.evidence-edit-date-wrap').forEach((wrap) => {
            bindDateEditInput(
                wrap.querySelector('.evidence-split-date'),
                wrap.querySelector('.evidence-split-date-btn')
            );
        });
        modal.querySelectorAll('.evidence-split-time').forEach((input) => bindSplitTimeInput(input));
        modal.querySelectorAll('.evidence-split-time-btn').forEach((button) => bindSplitTimePicker(button));
        modal.querySelectorAll('.evidence-split-ref').forEach((select) => initEvidenceRefSelect(select, {
            modal,
            api: API,
        }));
        if (modal.querySelector('select.evidence-split-code')) {
            void initCodeSelectControls(modal);
        }
    }
    function bindSplitModalReorder(modal) {
        const tbody = modal.querySelector('.evidence-split-table tbody');
        if (!tbody || !window.jQuery || typeof window.jQuery(tbody).sortable !== 'function') return;
        const $tbody = window.jQuery(tbody);
        if ($tbody.data('ui-sortable')) {
            $tbody.sortable('destroy');
        }
        $tbody.sortable({
            handle: '.evidence-split-drag',
            items: '> tr[data-split-role="child"]',
            axis: 'y',
            containment: 'parent',
            tolerance: 'pointer',
            stop: () => refreshSplitModalRows(modal),
        });
    }

    function collectSplitChildPayload(tr) {
        const payload = {};
        tr.querySelectorAll('.evidence-split-field').forEach((input) => {
            const key = input.dataset.key || '';
            if (!key) return;
            if (input.dataset.valueKind === 'ref') {
                const idKey = input.dataset.refIdKey || '';
                const nameKey = input.dataset.refNameKey || key;
                const selectedId = selectValueForSave(input);
                const selectedText = selectTextForSave(input);
                payload[key] = selectedText;
                if (nameKey) {
                    payload[nameKey] = selectedText;
                }
                if (idKey) {
                    payload[idKey] = selectedId && selectedId !== selectedText ? selectedId : '';
                }
                return;
            }
            if (input.dataset.valueKind === 'date') {
                const keepTime = input.dataset.keepTime === '1';
                const normalized = normalizeDateInputValue(input.value, keepTime);
                payload[key] = normalized;
                if (keepTime && /transaction_(date|time|datetime|at)$/i.test(key)) {
                    payload.transaction_datetime = normalized;
                    payload.transaction_date = normalized.slice(0, 10);
                }
                return;
            }
            payload[key] = input.dataset.amount === '1'
                ? amount(input.value || 0)
                : (input.matches('select') ? selectValueForSave(input) : input.value);
        });
        return payload;
    }

    function splitChildFromRow(tr, index = 0) {
        const payload = collectSplitChildPayload(tr);
        const child = {
            id: tr.dataset.id || '',
            sort_no: Number(tr.querySelector('.evidence-split-sort')?.textContent || index + 1),
            description: payload.description || payload.memo || '',
            mapped_payload: payload,
        };
        ['quantity', 'unit_price', 'supply_amount', 'vat_amount', 'total_amount', 'deposit_amount', 'withdraw_amount', 'withdrawal_amount'].forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(payload, key)) {
                child[key] = payload[key];
            }
        });
        return child;
    }

    function splitMissingRequiredForRow(tr, index = 0) {
        const payload = collectSplitChildPayload(tr);
        const missing = [];
        splitRequiredColumns().forEach((column) => {
            if (valueText(payload[column.key]) === '') {
                missing.push(`${index + 1}행 ${column.title}`);
            }
        });
        return missing;
    }

    async function saveSplitModal(modal) {
        const evidenceId = String(modal.dataset.evidenceId || '').trim();
        const processingItemId = String(modal.dataset.parentProcessingItemId || '').trim();
        const missing = [];
        const children = Array.from(modal.querySelectorAll('tbody tr[data-split-role="child"]')).map((tr, index) => {
            missing.push(...splitMissingRequiredForRow(tr, index));
            return splitChildFromRow(tr, index);
        });
        const ruleMessages = children
            .map((child, index) => {
                const message = businessProjectRuleMessage(child.mapped_payload || child);
                return message ? `${index + 1}행 ${message}` : '';
            })
            .filter(Boolean);
        if (ruleMessages.length > 0) {
            notify('warning', ruleMessages.slice(0, 5).join(' '));
            return;
        }
        if (missing.length > 0) {
            notify('warning', `필수 항목을 입력해야 저장할 수 있습니다: ${missing.slice(0, 5).join(', ')}${missing.length > 5 ? ` 외 ${missing.length - 5}건` : ''}`);
            return;
        }
        const response = await fetch(API.splitChild, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: evidenceId,
                evidence_id: evidenceId,
                processing_item_id: processingItemId,
                children,
            }),
        });
        const text = await response.text();
        let json = {};
        try {
            json = text ? JSON.parse(text) : {};
        } catch (error) {
            const cleaned = text
                .replace(/<style[\s\S]*?<\/style>/gi, ' ')
                .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                .replace(/<[^>]+>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            throw new Error(/Fatal error|Call to undefined method|Parse error|Exception/i.test(cleaned)
                ? '\uc11c\ubc84 \ucc98\ub9ac \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4. \uad00\ub9ac\uc790\uc5d0\uac8c \ubb38\uc758\ud558\uc138\uc694.'
                : (cleaned || '\uc5c5\ub85c\ub4dc \ucc98\ub9ac \uc911 \uc11c\ubc84 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.').slice(0, 240));
        }
        if (!response.ok || json.success === false) {
            notify('error', json.message || '분할 항목 저장에 실패했습니다.');
            return;
        }
        notify('success', json.message || '분할 항목을 저장했습니다.');
        bootstrap.Modal.getInstance(modal)?.hide();
        table?.ajax.reload(() => {
            updateSummary(lastRows);
            refreshDataTableLayout(table);
            window.setTimeout(() => refreshDataTableLayout(table), 150);
        }, false);
    }

    async function saveProcessingChildEdit(modal) {
        const row = modal.querySelector('tbody tr[data-split-role="child"]');
        if (!row) return;
        const missing = splitMissingRequiredForRow(row, 0);
        if (missing.length > 0) {
            notify('warning', `필수 항목을 입력해야 저장할 수 있습니다: ${missing.slice(0, 5).join(', ')}`);
            return;
        }
        const child = splitChildFromRow(row, 0);
        const ruleMessage = businessProjectRuleMessage(child.mapped_payload || child);
        if (ruleMessage !== '') {
            notify('warning', ruleMessage);
            return;
        }
        const response = await fetch(API.updateProcessingChild, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                processing_item_id: modal.dataset.processingItemId || child.id || '',
                child,
            }),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            notify('error', json.message || '자식 항목 수정에 실패했습니다.');
            return;
        }
        notify('success', json.message || '자식 항목을 수정했습니다.');
        bootstrap.Modal.getInstance(modal)?.hide();
        table?.ajax.reload(() => {
            updateSummary(lastRows);
            refreshDataTableLayout(table);
            window.setTimeout(() => refreshDataTableLayout(table), 150);
        }, false);
    }

    async function deleteProcessingChild(button) {
        const processingItemId = String(button?.dataset.processingItemId || '').trim();
        if (!processingItemId) return;
        if (!window.confirm('자식행을 삭제하시겠습니까? 이 삭제는 되돌릴 수 없습니다.')) {
            return;
        }

        const response = await fetch(API.deleteProcessingChild, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ processing_item_id: processingItemId }),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '자식행 삭제에 실패했습니다.');
        }
        notify('success', json.message || '자식행을 삭제했습니다.');
        table?.ajax.reload(() => updateSummary(lastRows), false);
    }

    function collectEditPayload() {
        const next = { ...mapped(editingRow) };
        refs.editFields?.querySelectorAll('.evidence-edit-input').forEach((input) => {
            const key = input.dataset.key || '';
            if (!key) return;
            if (input.dataset.valueKind === 'ref') {
                const idKey = input.dataset.refIdKey || '';
                const nameKey = input.dataset.refNameKey || key;
                const allowsText = input.dataset.refAllowText === '1';
                const selectedId = selectValueForSave(input);
                let selectedText = selectTextForSave(input, { includeCurrentText: true });
                const isTextOnlyInitialValue = input.dataset.refCurrentTextOnly === '1'
                    && selectedId !== ''
                    && selectedId === String(input.dataset.refCurrentText || '').trim();
                const isFreeTextSelection = allowsText && selectedId !== '' && selectedText !== '' && selectedId === selectedText;
                if (selectedId === '') {
                    selectedText = '';
                }

                next[key] = selectedText;
                if (nameKey) {
                    next[nameKey] = selectedText;
                }
                if (idKey) {
                    next[idKey] = selectedId !== '' && !isTextOnlyInitialValue && !isFreeTextSelection ? selectedId : '';
                }
                return;
            }
            if (input.dataset.valueKind === 'number') {
                next[key] = input.value === '' ? '' : String(parseCommonNumber(input.value));
                return;
            }
            if (input.dataset.valueKind === 'date') {
                const keepTime = input.dataset.keepTime === '1';
                const normalized = normalizeDateInputValue(input.value, keepTime);
                next[key] = normalized;
                if (keepTime && /transaction_(date|time|datetime|at)$/i.test(key)) {
                    next.transaction_datetime = normalized;
                    next.transaction_date = normalized.slice(0, 10);
                }
                return;
            }
            if (input.dataset.valueKind === 'business_number') {
                next[key] = formatBizNumber(input.value);
                return;
            }
            if (input.dataset.valueKind === 'phone') {
                next[key] = formatPhone(input.value);
                return;
            }
            next[key] = valueText(input.matches('select') ? selectValueForSave(input) : input.value);
        });
        Object.keys(next).forEach((key) => {
            if (key.startsWith('_')) return;
            const value = next[key];
            if (value && typeof value === 'object') {
                next[key] = valueText(value);
            }
            if (next[key] === '[object Object]') {
                next[key] = '';
            }
        });
        return next;
    }

    function requiredEditColumns() {
        const columns = Array.isArray(activeFormat?.columns) ? activeFormat.columns : [];
        return columns.filter((column) => requirementMode(column) === 1 && editFieldKey(column) !== 'balance_amount');
    }

    function validateRequiredEditFields() {
        const missing = [];
        let firstInput = null;
        requiredEditColumns().forEach((column) => {
            const key = editFieldKey(column);
            if (!key) return;
            const input = editInputByKey(key);
            const value = input
                ? (input.dataset.valueKind === 'ref'
                    ? (selectTextForSave(input, { includeCurrentText: true }) || selectValueForSave(input))
                    : (input.matches('select') ? selectValueForSave(input) : String(input.value ?? '').trim()))
                : '';
            input?.classList.remove('is-invalid');
            if (value !== '') return;
            input?.classList.add('is-invalid');
            firstInput = firstInput || input;
            missing.push(String(column.excel_column_name || column.system_field_name || key).replace(/\s*\*$/u, ''));
        });

        if (missing.length === 0) return true;
        firstInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInput?.focus?.();
        notify('warning', `필수 항목을 입력해야 저장할 수 있습니다: ${missing.slice(0, 5).join(', ')}${missing.length > 5 ? ` 외 ${missing.length - 5}건` : ''}`);
        return false;
    }

    async function saveEditingRow() {
        if (!editingRow) return;
        if (!validateRequiredEditFields()) return;
        const payload = collectEditPayload();
        if (!validateBusinessProjectRule(payload)) return;
        const isNew = editingRow.__isNew === true;
        const json = await fetch(isNew ? API.createEvidence : API.saveSeedRow, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: editingRow.id || '',
                format_id: activeFormat?.id || editingRow.format_id || '',
                import_type: currentType,
                parsed_json: payload,
            }),
        }).then(async (response) => {
            const body = await response.json().catch(() => ({}));
            if (!response.ok || body.success === false) {
                throw new Error(body.message || '자료 수정에 실패했습니다.');
            }
            return body;
        });
        notify('success', json.message || (isNew ? '새 증빙원본이 생성되었습니다.' : '자료가 수정되었습니다.'));
        editModal?.hide();
        editingRow = null;
        table?.ajax.reload(() => updateSummary(lastRows), false);
        void refreshEvidenceTypeCounts().catch(() => {});
    }

    function formatColumnsFromTemplate(format) {
        const columns = Array.isArray(format?.columns) ? format.columns : [];
        return columns
            .slice()
            .sort(compareFormatColumns)
            .map((column) => {
                const field = String(column.system_field_name || '');
                const title = String(column.excel_column_name || field || '컬럼');
                const isAmount = isAmountColumn(column);
                const isDate = isDateColumn(column);
                const dataField = field !== '' ? `mapped_payload.${field}` : `mapped_payload.${title}`;

                return {
                    data: dataField,
                    title,
                    name: field || title,
                    sourceField: field,
                    excelColumnName: title,
                    visible: Number(column.is_visible ?? 1) === 1,
                    className: `evidence-data-column ${isAmount ? 'text-end' : 'text-start'} text-nowrap`,
                    headerClassName: 'evidence-data-column text-start text-nowrap',
                    render: (_value, type, row) => {
                        const value = formatValue(row, column);
                        if (type === 'sort' || type === 'type') {
                            if (isDate) return normalizeDateInputValue(value, isDateTimeColumn(column)) || '';
                            if (isAmount) return parseCommonNumber(value);
                            return valueText(value);
                        }
                        if (isDate) {
                            return escapeHtml(isDateTimeColumn(column) ? formatDateTimeValue(value) : formatDateValue(value));
                        }
                        return isAmount && value !== '-' ? escapeHtml(formatNumber(value)) : escapeHtml(value || '-');
                    },
                };
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

    function normalizeSortText(value) {
        return valueText(value)
            .trim()
            .toLowerCase()
            .replace(/[\s_()/-]/g, '');
    }

    function sortTargetsForCurrentType() {
        const byType = DATA_TYPE_SORT_RULES[currentType] || [];
        const label = selectedTypeLabel();
        const formatName = String(activeFormat?.format_name || '');
        const typeText = `${currentType} ${label} ${formatName}`;
        if (/BANK_TRANSACTION|입출|은행/.test(typeText)) return DATA_TYPE_SORT_RULES.BANK_TRANSACTION;
        if (/TAX_INVOICE|세금계산서/.test(typeText)) return DATA_TYPE_SORT_RULES.TAX_INVOICE;
        if (/CASH_RECEIPT|현금영수증/.test(typeText)) return DATA_TYPE_SORT_RULES.CASH_RECEIPT;
        if (/CARD_STATEMENT|카드사/.test(typeText)) return DATA_TYPE_SORT_RULES.CARD_STATEMENT;
        if (/CARD|카드|홈택스/.test(typeText)) return DATA_TYPE_SORT_RULES.CARD_APPROVAL;
        if (/입출|은행/.test(label)) return DATA_TYPE_SORT_RULES.BANK_TRANSACTION;
        if (/세금계산서/.test(label)) return DATA_TYPE_SORT_RULES.TAX_INVOICE;
        if (/현금영수증/.test(label)) return DATA_TYPE_SORT_RULES.CASH_RECEIPT;
        if (/카드사/.test(label)) return DATA_TYPE_SORT_RULES.CARD_STATEMENT;
        if (/카드/.test(label)) return DATA_TYPE_SORT_RULES.CARD_APPROVAL;
        return byType;
    }

    function defaultOrderForConfig() {
        return [[1, 'asc']];
    }

    function commonColumns(config) {
        return [
            {
                data: null,
                title: '<i class="bi bi-arrows-move"></i>',
                orderable: false,
                searchable: false,
                className: 'reorder-handle no-sort no-colvis text-center',
                headerClassName: 'no-colvis text-center',
                width: '36px',
                render: (_value, type, row) => {
                    if (type !== 'display') return '';
                    if (row?.processing_is_child) {
                        return '<i class="bi bi-arrow-return-right processing-child-branch" title="자식행"></i>';
                    }
                    return '<i class="bi bi-list"></i>';
                },
            },
            {
                key: 'row_no',
                data: 'row_no',
                title: '순번',
                className: 'text-center text-nowrap',
                headerClassName: 'text-center text-nowrap',
                width: '56px',
                render(value, type, row, meta) {
                    const display = value || row?.processing_display_path || (meta.row + meta.settings._iDisplayStart + 1);
                    if (type === 'sort' || type === 'type') {
                        return String(display)
                            .split('-')
                            .reduce((total, part, index) => total + (Number(part) || 0) / Math.pow(1000, index), 0);
                    }
                    if (type !== 'display') return escapeHtml(display);
                    const depth = Math.max(1, Number(row?.processing_level || 1));
                    return `<span class="processing-row-no depth-${depth}">${escapeHtml(display)}</span>`;
                },
            },
            ...config.evidenceColumns,
            {
                data: null,
                title: '증빙상태',
                className: 'dt-status-column text-center text-nowrap no-colvis',
                headerClassName: 'dt-status-column text-center text-nowrap no-colvis',
                orderable: false,
                searchable: false,
                width: '86px',
                render: (_value, type, row) => {
                    if (type !== 'display') return evidenceStatusState(row);
                    return renderEvidenceStatusSimple(row);
                },
            },
            {
                data: null,
                title: '관리',
                className: 'dt-action-column text-center no-colvis',
                headerClassName: 'dt-action-column text-center no-colvis',
                orderable: false,
                searchable: false,
                width: '64px',
                render: (_value, type, row) => {
                    if (type !== 'display') return '';
                    return `
                        <button type="button"
                                class="btn btn-outline-primary btn-sm evidence-edit-row-btn"
                                data-id="${escapeHtml(row?.id || '')}">
                            수정
                        </button>
                    `;
                },
            },
            {
                data: null,
                title: '추가',
                className: 'dt-action-column text-center no-colvis',
                headerClassName: 'dt-action-column text-center no-colvis',
                orderable: false,
                searchable: false,
                width: '52px',
                render: (_value, type, row) => {
                    if (type !== 'display') return '';
                    if (row?.processing_is_child) {
                        return `
                            <button type="button"
                                    class="btn btn-outline-danger btn-sm evidence-delete-child-row-btn"
                                    data-processing-item-id="${escapeHtml(row?.processing_item_id || '')}"
                                    title="자식행 삭제"
                                    aria-label="자식행 삭제">
                                -
                            </button>
                        `;
                    }
                    return `
                        <button type="button"
                                class="btn btn-outline-primary btn-sm evidence-add-child-row-btn"
                                data-id="${escapeHtml(row?.id || '')}"
                                title="자식행 추가"
                                aria-label="자식행 추가">
                            +
                        </button>
                    `;
                },
            },
        ];
    }

    function genericEvidenceColumns() {
        return [
            textColumn('client_name', '거래처', (row) => clientName(row) || '-'),
            textColumn('mapped_payload.approval_number', '증빙번호', (row) => mapped(row).approval_number || mapped(row).approval_no || mapped(row).declaration_number || mapped(row).order_number || '-'),
            moneyColumn('mapped_payload.supply_amount', '공급가', (row) => amount(mapped(row).supply_amount)),
            moneyColumn('mapped_payload.vat_amount', '부가세', (row) => amount(mapped(row).vat_amount)),
            moneyColumn('mapped_payload.total_amount', '합계', (row) => amount(mapped(row).total_amount || mapped(row).amount)),
        ];
    }

    function processingChildrenForParent(row = {}) {
        const parentId = String(row.processing_item_id || '').trim();
        if (!parentId) return [];
        return lastRows.filter((item) => (
            item?.processing_is_child
            && String(item.processing_parent_item_id || '').trim() === parentId
        ));
    }

    function explicitMissingFieldResolved(row = {}, item = '') {
        const field = valueText(item);
        if (field === '') return false;
        const payload = mapped(row);
        if (valueText(payload[field]) !== '' || valueText(row[field]) !== '') {
            return true;
        }
        const aliases = {
            counterparty_name: ['counterparty_account_holder_name', 'counterparty_account_holder', 'client_id', 'client_name', 'client_company_name'],
            counterparty_account_holder_name: ['counterparty_name', 'counterparty_account_holder', 'client_id', 'client_name', 'client_company_name'],
            client_id: ['client_name', 'client_company_name', 'counterparty_name'],
            client_name: ['client_id', 'client_company_name', 'counterparty_name'],
        }[field] || [];
        if (aliases.some((key) => valueText(payload[key]) !== '' || valueText(row[key]) !== '')) {
            return true;
        }

        const columns = Array.isArray(activeFormat?.columns) ? activeFormat.columns : [];
        const column = columns.find((candidate) => {
            const systemField = String(candidate.system_field_name || '').trim();
            const excelName = String(candidate.excel_column_name || '').replace(/\s*\*$/u, '').trim();
            return systemField === field || excelName === field || editFieldKey(candidate) === field;
        });
        if (!column) return false;

        const value = formatValue(row, column);
        return valueText(value) !== '' && valueText(value) !== '-';
    }

    function ownEvidenceStatusMissingMessages(row = {}) {
        const explicit = [
            row.evidence_missing_required,
            row.required_missing_fields,
            row.missing_required,
            row.missing_fields,
        ];
        const messages = [];
        if (!row.processing_is_child && !row.processing_has_children) {
            explicit.forEach((value) => {
                if (Array.isArray(value)) {
                    value.forEach((item) => {
                        const text = valueText(item);
                        if (text !== '' && !explicitMissingFieldResolved(row, text)) messages.push(text);
                    });
                    return;
                }
                const text = valueText(value);
                if (text !== '') {
                    text.split(/[,\n]/)
                        .map((item) => item.trim())
                        .filter(Boolean)
                        .filter((item) => !explicitMissingFieldResolved(row, item))
                        .forEach((item) => messages.push(item));
                }
            });
        }

        const columns = Array.isArray(activeFormat?.columns) ? activeFormat.columns : [];
        columns
            .filter((column) => requirementMode(column) === 1 && editFieldKey(column) !== 'balance_amount')
            .forEach((column) => {
                const key = editFieldKey(column);
                if (!key) return;
                const value = formatValue(row, column);
                if (valueText(value) !== '' && valueText(value) !== '-') return;
                messages.push(String(column.excel_column_name || column.system_field_name || key).replace(/\s*\*$/u, ''));
            });

        return Array.from(new Set(messages.map((item) => String(item || '').trim()).filter(Boolean)));
    }

    function evidenceStatusMissingMessages(row = {}) {
        if (row.processing_has_children) {
            const children = processingChildrenForParent(row);
            if (children.length === 0) return ['분할 자식행 없음'];
            const messages = [];
            children.forEach((child) => {
                const childMessages = ownEvidenceStatusMissingMessages(child);
                if (childMessages.length === 0) return;
                const no = valueText(child.processing_display_path || child.row_no || child.sort_no || '');
                childMessages.forEach((message) => {
                    messages.push(`${no ? `${no} ` : ''}${message}`);
                });
            });
            return Array.from(new Set(messages));
        }
        return ownEvidenceStatusMissingMessages(row);
    }

    function ownEvidenceStatusState(row = {}) {
        const status = String(row.evidence_status || row.process_status || row.status || '').trim().toUpperCase();
        if (['ERROR', 'FAILED', 'NOT_READY', 'INCOMPLETE', 'MISSING'].includes(status)) return 'INCOMPLETE';
        if (ownEvidenceStatusMissingMessages(row).length > 0) return 'INCOMPLETE';
        if (['READY', 'COMPLETE', 'COMPLETED', 'DONE', 'NORMAL', 'PROCESSED'].includes(status)) return 'COMPLETE';
        return valueText(row.id) !== '' ? 'COMPLETE' : 'INCOMPLETE';
    }

    function evidenceStatusState(row = {}) {
        if (row.processing_has_children) {
            const children = processingChildrenForParent(row);
            if (children.length === 0) return 'INCOMPLETE';
            return children.every((child) => ownEvidenceStatusState(child) === 'COMPLETE')
                ? 'COMPLETE'
                : 'INCOMPLETE';
        }
        return ownEvidenceStatusState(row);
    }

    function renderEvidenceStatusSimple(row = {}) {
        const missing = evidenceStatusMissingMessages(row);
        const state = evidenceStatusState(row);
        if (state === 'COMPLETE') {
            return '<span class="badge evidence-origin-status-badge evidence-origin-status-complete">완료</span>';
        }
        const title = missing.length > 0 ? ` title="${escapeHtml(missing.join(', '))}"` : '';
        return `<span class="badge evidence-origin-status-badge evidence-origin-status-incomplete"${title}>미완료</span>`;
    }

    function splitGenerationRows(rows = []) {
        return rows.filter((row) => row?.processing_is_child || !row?.processing_has_children);
    }

    function updateSummary(rows = []) {
        const generationRows = splitGenerationRows(rows);
        const summary = {
            total: rows.length,
            transactionPending: generationRows.filter((row) => String(row.transaction_id || '').trim() === '').length,
            transactionCreated: generationRows.filter((row) => String(row.transaction_id || '').trim() !== '').length,
            voucherReview: generationRows.filter((row) => String(row.transaction_id || '').trim() !== '').length,
            errors: rows.filter((row) => processStatus(row) === 'ERROR' || row.error_message).length,
            duplicates: rows.filter((row) => processStatus(row) === 'DUPLICATED').length,
        };
        Object.entries(summary).forEach(([key, value]) => {
            const el = document.querySelector(`[data-summary="${key}"]`);
            if (el) el.textContent = value.toLocaleString('ko-KR');
        });
    }

    function currentConfig() {
        const label = selectedTypeLabel() || currentType || '자료유형';
        return {
            label,
            api: `${API.seedRows}?import_type=${encodeURIComponent(currentType)}`,
            excelTemplate: currentType ? currentType.toLowerCase() : '',
            dateOptions: [{ value: 'mapped_payload.transaction_date', label: '거래일자' }],
            evidenceColumns: genericEvidenceColumns(),
        };
    }

    async function loadActiveFormat(type) {
        return null;
    }

    function noFormatColumns() {
        return [{
            data: 'message',
            title: '상태',
            orderable: false,
            searchable: false,
            className: 'text-center text-muted py-4 no-colvis',
            headerClassName: 'no-colvis',
            render: (value) => escapeHtml(value && !String(value).includes('?')
                ? value
                : `${selectedTypeLabel() || currentType || '선택한'} 자료유형은 아직 양식이 생성되지 않아 원본 데이터를 표시할 수 없습니다. 양식관리에서 먼저 양식을 만들어주세요.`),
        }];
    }

    function selectedTypeLabel() {
        const selected = refs.typeSelect?.selectedOptions?.[0];
        const text = String(selected?.textContent || '').trim();
        if (text && !text.startsWith('+') && !text.includes('기준추가')) {
            return text;
        }

        const found = (codeOptions.IMPORT_TYPE || []).find((row) => normalizeEvidenceType(row.code) === currentType);
        return found?.code_name || '';
    }

    function evidenceTypeDisplayName(row = {}) {
        const type = normalizeEvidenceType(row.import_type || row.source_type || currentType);
        const found = (codeOptions.IMPORT_TYPE || []).find((option) => normalizeEvidenceType(option.code) === type);
        return found?.code_name || selectedTypeLabel() || type || '자료유형';
    }

    function normalizeCodeRows(rows = []) {
        return rows
            .map((row) => ({
                code: String(row.code ?? row.value ?? '').trim(),
                code_name: String(row.code_name ?? row.label ?? row.code ?? row.value ?? '').trim(),
                is_active: Number(row.is_active ?? 1),
            }))
            .filter((row) => row.code !== '' && row.is_active === 1);
    }

    async function loadDisplayCodeOptions() {
        await Promise.all(Object.values(DISPLAY_CODE_FIELDS).map(async (group) => {
            if ((codeOptions[group] || []).length > 0) return;
            const response = await fetch(`${API.codeList}?code_group=${encodeURIComponent(group)}&filters=[]`, { cache: 'no-store' });
            const json = await response.json().catch(() => ({}));
            const rows = Array.isArray(json) ? json : (json.data || []);
            codeOptions[group] = normalizeCodeRows(rows);
        }));
    }

    function normalizeEvidenceType(value) {
        const raw = String(value || '').trim().toUpperCase();
        return LEGACY_TYPE_MAP[raw] || raw;
    }

    function isEvidenceUploadType(value) {
        const type = normalizeEvidenceType(value);
        if (!type || type.startsWith('__')) return false;

        const importTypes = codeOptions.IMPORT_TYPE || [];
        if (importTypes.length > 0) {
            return importTypes.some((row) => normalizeEvidenceType(row.code || row.value) === type);
        }

        return EVIDENCE_UPLOAD_TYPES.has(type) || Boolean(DATA_TYPE_CONFIG[type]);
    }

    function evidenceTypeOptions() {
        return Array.from(refs.typeSelect?.options || [])
            .map((option) => ({
                value: normalizeEvidenceType(option.value || ''),
                label: String(option.textContent || '').trim(),
                disabled: option.disabled,
            }))
            .filter((option) => option.value && !option.disabled && !option.value.startsWith('__') && isEvidenceUploadType(option.value));
    }

    function evidenceTypeLabel(value) {
        const type = normalizeEvidenceType(value);
        const found = (codeOptions.IMPORT_TYPE || []).find((row) => normalizeEvidenceType(row.code) === type);
        return found?.code_name || type;
    }

    function renderEvidenceTypeTabs() {
        if (!refs.typeTabs) return;
        const options = evidenceTypeOptions();
        if (refs.typeSelectCount) {
            const total = Number(evidenceTotalSummary.total || 0);
            const bank = Number(evidenceTotalSummary.bank || 0);
            const evidence = Number(evidenceTotalSummary.evidence || 0);
            refs.typeSelectCount.textContent = `전체 ${total.toLocaleString('ko-KR')}건`;
            refs.typeSelectCount.title = [
                `입출거래 ${bank.toLocaleString('ko-KR')}건`,
                `통합증빙 ${evidence.toLocaleString('ko-KR')}건`,
                `합계 ${total.toLocaleString('ko-KR')}건`,
            ].join(' / ');
        }
        refs.typeTabs.innerHTML = options.map((option) => {
            const count = Number(evidenceTypeCounts[option.value] || 0);
            const active = option.value === currentType ? ' active' : '';
            const label = option.label || evidenceTypeLabel(option.value);
            return `
                <button type="button"
                    class="evidence-type-tab${active}"
                    data-evidence-type="${escapeHtml(option.value)}"
                    aria-pressed="${option.value === currentType ? 'true' : 'false'}">
                    <span>${escapeHtml(label)}</span>
                    <span class="evidence-type-tab-count">${count.toLocaleString('ko-KR')}</span>
                </button>
            `;
        }).join('');
    }

    async function refreshEvidenceTypeCounts() {
        const response = await fetch(`${API.seedRows}?type_counts=1`, { cache: 'no-store' });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '자료유형별 건수를 불러오지 못했습니다.');
        }
        const nextCounts = {};
        (Array.isArray(json.data) ? json.data : []).forEach((row) => {
            const type = normalizeEvidenceType(row.import_type || row.source_type || '');
            if (!type) return;
            nextCounts[type] = Number(row.row_count || row.count || 0);
        });
        const bank = Number(nextCounts.BANK_TRANSACTION || 0);
        const total = Object.values(nextCounts).reduce((sum, count) => sum + Number(count || 0), 0);
        evidenceTypeCounts = nextCounts;
        evidenceTotalSummary = {
            total,
            bank,
            evidence: total - bank,
        };
        renderEvidenceTypeTabs();
    }

    function filterEvidenceTypeSelect() {
        if (!refs.typeSelect) return;
        Array.from(refs.typeSelect.options || []).forEach((option) => {
            const value = String(option.value || '').trim();
            if (!value || value.startsWith('__')) return;
            if (!isEvidenceUploadType(value)) {
                option.remove();
            } else {
                option.value = normalizeEvidenceType(value);
            }
        });
        if (window.jQuery?.fn?.select2 && window.jQuery(refs.typeSelect).hasClass('select2-hidden-accessible')) {
            window.jQuery(refs.typeSelect).trigger('change.select2');
        }
        renderEvidenceTypeTabs();
    }

    function firstAvailableType() {
        const options = Array.from(refs.typeSelect?.options || []);
        const first = options.find((option) => {
            const value = String(option.value || '').trim();
            return value && !option.disabled && !value.startsWith('__') && isEvidenceUploadType(value);
        });
        return first?.value || 'TAX_INVOICE';
    }

    function syncTypeControls() {
        const config = currentConfig();
        if (refs.typeSelect && refs.typeSelect.value !== currentType) {
            refs.typeSelect.value = currentType;
        }
        if (window.jQuery?.fn?.select2 && window.jQuery(refs.typeSelect).data('select2')) {
            window.jQuery(refs.typeSelect).val(currentType).trigger('change.select2');
        }
        if (refs.excelLabel) {
            refs.excelLabel.textContent = `${config.label} / 템플릿 ${config.excelTemplate}`;
        }
        renderEvidenceTypeTabs();
        syncExcelManager(config, Boolean(activeFormat));
    }
    function syncExcelManager(config = currentConfig(), hasFormat = Boolean(activeFormat)) {
        if (!refs.excelForm) return;

        const formatId = String(activeFormat?.id || '').trim();
        refs.excelForm.dataset.templateUrl = formatId
            ? `/api/import/template?format_id=${encodeURIComponent(formatId)}`
            : `/api/import/template?type=${encodeURIComponent(currentType || 'TAX_INVOICE')}`;
        refs.excelForm.dataset.downloadUrl = hasFormat && formatId
            ? `${API.download}?import_type=${encodeURIComponent(currentType)}&format_id=${encodeURIComponent(formatId)}`
            : '';
        refs.excelForm.dataset.uploadUrl = hasFormat && formatId ? API.upload : '';
        refs.excelForm.dataset.formatId = formatId;
        refs.excelForm.dataset.importType = currentType || '';

        const subtitle = refs.excelModal?.querySelector('.excel-modal-subtitle');
        if (subtitle) {
            subtitle.textContent = hasFormat
                ? `${config.label} / 양식 ${config.excelTemplate}`
                : `${config.label} / 양식 없음`;
        }

        const templateBtn = refs.excelModal?.querySelector('.btn-template-download');
        if (templateBtn) {
            templateBtn.disabled = !formatId && !currentType;
        }

        const downloadBtn = refs.excelModal?.querySelector('.btn-download-all');
        if (downloadBtn) {
            downloadBtn.disabled = !hasFormat || !formatId;
            downloadBtn.title = hasFormat ? '' : '양식이 생성된 자료유형만 다운로드할 수 있습니다.';
        }

        const uploadBtn = refs.excelModal?.querySelector('.btn-upload-excel');
        if (uploadBtn) {
            uploadBtn.disabled = !hasFormat || !formatId;
            uploadBtn.title = hasFormat ? '' : '양식이 생성된 자료유형만 업로드할 수 있습니다.';
        }
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
            throw new Error(json.message || '요청 처리에 실패했습니다.');
        }
        return json;
    }

    function createUploadCancelToken() {
        if (window.crypto?.randomUUID) {
            return window.crypto.randomUUID().replace(/-/g, '');
        }
        return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 14)}`;
    }

    function requestExcelUploadCancel() {
        if (!excelUploadCancelToken) return;
        const payload = JSON.stringify({
            upload_cancel_token: excelUploadCancelToken,
            preview_token: excelUploadPreviewToken || '',
        });
        const blob = new Blob([payload], { type: 'application/json' });
        if (navigator.sendBeacon?.(API.uploadCancel, blob)) {
            return;
        }
        fetch(API.uploadCancel, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: payload,
            keepalive: true,
        }).catch(() => {});
    }

    function validateExcelUploadFile(file) {
        const extension = String(file?.name || '').split('.').pop()?.toLowerCase() || '';
        if (!['xlsx', 'xls'].includes(extension)) {
            return '\uc5d1\uc140 \ud30c\uc77c(.xlsx, .xls)\ub9cc \uc5c5\ub85c\ub4dc\ud560 \uc218 \uc788\uc2b5\ub2c8\ub2e4.';
        }
        const size = Number(file?.size || 0);
        if (size > MAX_EXCEL_UPLOAD_BYTES) {
            const mb = (size / 1024 / 1024).toFixed(1);
            return `\uc5c5\ub85c\ub4dc \ud30c\uc77c\uc774 \ub108\ubb34 \ud07d\ub2c8\ub2e4. \ud604\uc7ac ${mb}MB\uc774\uba70, \uc5d1\uc140 \uc591\uc2dd \uc5c5\ub85c\ub4dc\ub294 25MB \uc774\ud558\ub9cc \ud5c8\uc6a9\ud569\ub2c8\ub2e4. \uc120\ud0dd\ud55c \uc790\ub8cc\uc720\ud615\uc758 \uc591\uc2dd \ud30c\uc77c\uc778\uc9c0 \ud655\uc778\ud558\uc138\uc694.`;
        }
        return '';
    }

    function refreshAfterUploadCancel() {
        window.setTimeout(() => {
            table?.ajax.reload(() => updateSummary(lastRows), false);
            void refreshEvidenceTypeCounts().catch(() => {});
        }, 400);
        window.setTimeout(() => {
            table?.ajax.reload(() => updateSummary(lastRows), false);
            void refreshEvidenceTypeCounts().catch(() => {});
        }, 1800);
    }

    function uploadResultProgressMessage(result = {}, fallback = '') {
        const totalRows = Number(result.total_rows || 0);
        const processed = Number(result.processed_count || 0)
            || Number(result.new_count || 0)
            + Number(result.updated_count || 0)
            + Number(result.unchanged_count || 0)
            + Number(result.error_count || 0);
        const skipped = Number(result.skipped_count || Math.max(0, totalRows - processed));
        const parts = [
            `\uc2e4\uc81c \ucc98\ub9ac ${processed.toLocaleString('ko-KR')}\uac74`,
            `\uc2e0\uaddc ${Number(result.new_count || 0).toLocaleString('ko-KR')}\uac74`,
            `\ubcc0\uacbd ${Number(result.updated_count || 0).toLocaleString('ko-KR')}\uac74`,
            `\ub3d9\uc77c ${Number(result.unchanged_count || 0).toLocaleString('ko-KR')}\uac74`,
        ];
        if (Number(result.error_count || 0) > 0) {
            parts.push(`\uc624\ub958 ${Number(result.error_count || 0).toLocaleString('ko-KR')}\uac74`);
        }
        if (Number(result.protected_update_count || 0) > 0) {
            const protectedParts = [];
            if (Number(result.protected_transaction_count || 0) > 0) {
                protectedParts.push(`거래생성 ${Number(result.protected_transaction_count || 0).toLocaleString('ko-KR')}건`);
            }
            if (Number(result.protected_voucher_count || 0) > 0) {
                protectedParts.push(`전표생성 ${Number(result.protected_voucher_count || 0).toLocaleString('ko-KR')}건`);
            }
            parts.push(`생성완료 수정제외 ${Number(result.protected_update_count || 0).toLocaleString('ko-KR')}건${protectedParts.length > 0 ? `(${protectedParts.join(', ')})` : ''}`);
        }
        const otherSkipped = Math.max(0, skipped - Number(result.protected_update_count || 0));
        if (otherSkipped > 0) {
            parts.push(`\uc81c\uc678 ${otherSkipped.toLocaleString('ko-KR')}\uac74`);
        }
        if (totalRows > 0 && totalRows !== processed) {
            return `\uc5d1\uc140 \uac10\uc9c0 ${totalRows.toLocaleString('ko-KR')}\ud589 / ${parts.join(', ')}`;
        }
        if (processed > 0) {
            return `\uc5c5\ub85c\ub4dc \uc644\ub8cc: ${parts.join(', ')}`;
        }
        return fallback || '\uc5c5\ub85c\ub4dc\uac00 \uc644\ub8cc\ub418\uc5b4 \ubaa9\ub85d\uc744 \uc0c8\ub85c\uace0\uce68\ud569\ub2c8\ub2e4.';
    }

    function dualWriteUploadMessage(result = {}) {
        const status = String(result?.dual_write_status || '').trim();
        if (!status) return '';
        const successCount = Number(result?.dual_write_success_count || 0);
        const failedCount = Number(result?.dual_write_failed_count || 0);
        return `Dual write: ${status} (success ${successCount}, failed ${failedCount})`;
    }

    async function uploadExcelFromModal(button) {
        if (uploadingExcel) return;
        const form = refs.excelForm;
        const fileInput = refs.excelModal?.querySelector('input[type="file"]');
        const file = fileInput?.files?.[0] || null;
        const formatId = String(form?.dataset.formatId || activeFormat?.id || '').trim();
        const progress = window.ExcelManagerProgress;
        let saveStartedAt = 0;
        let saveTimer = null;
        const formatElapsed = (ms) => {
            const totalSeconds = Math.max(0, Math.floor((Number(ms) || 0) / 1000));
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        };
        const stopSaveTimer = () => {
            if (saveTimer) {
                clearInterval(saveTimer);
                saveTimer = null;
            }
        };
        const startSaveTimer = (totalRows = 0) => {
            saveStartedAt = Date.now();
            stopSaveTimer();
            const render = () => {
                progress?.set(refs.excelModal, {
                    percent: 100,
                    percentLabel: '\uc800\uc7a5 \uc911',
                    title: '\uc11c\ubc84 \uc800\uc7a5 \uc911',
                    message: totalRows > 0
                        ? `\uba38\ub9bf\uae00 \uc81c\uc678 \ub370\uc774\ud130 ${totalRows.toLocaleString('ko-KR')}\ud589\uc744 \uc800\uc7a5\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4. \uacbd\uacfc ${formatElapsed(Date.now() - saveStartedAt)}`
                        : `\uc774\ubbf8 \uac80\uc99d\ud55c \uc5d1\uc140 \ub370\uc774\ud130\ub97c \uc800\uc7a5\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4. \uacbd\uacfc ${formatElapsed(Date.now() - saveStartedAt)}`,
                    indeterminate: true,
                });
            };
            render();
            saveTimer = setInterval(render, 1000);
        };

        if (!activeFormat || !formatId) {
            notify('warning', '\uba3c\uc800 \uc790\ub8cc\uc720\ud615\uc758 \uc591\uc2dd\uc744 \uc0dd\uc131\ud558\uc138\uc694.');
            return;
        }
        if (!file) {
            notify('warning', '\uc5c5\ub85c\ub4dc\ud560 \uc5d1\uc140 \ud30c\uc77c\uc744 \uc120\ud0dd\ud558\uc138\uc694.');
            return;
        }
        const fileMessage = validateExcelUploadFile(file);
        if (fileMessage) {
            progress?.set(refs.excelModal, {
                percent: 100,
                title: '\uc5c5\ub85c\ub4dc \ubd88\uac00',
                message: fileMessage,
            });
            notify('warning', fileMessage);
            return;
        }

        uploadingExcel = true;
        excelUploadCanceled = false;
        excelUploadAbortController = null;
        excelUploadCancelToken = createUploadCancelToken();
        excelUploadPreviewToken = '';
        progress?.lock?.(refs.excelModal, true);
        const formData = new FormData();
        formData.append('upload_cancel_token', excelUploadCancelToken);
        formData.append('format_id', formatId);
        formData.append('file', file);
        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = '\uc5c5\ub85c\ub4dc \uc911';
        progress?.set(refs.excelModal, {
            percent: 8,
            title: '\ud30c\uc77c \uc900\ube44 \uc911',
            message: `${file.name} \ud30c\uc77c\uc744 \uc5c5\ub85c\ub4dc \uc694\uccad\uc73c\ub85c \uc900\ube44\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.`,
        });

        try {
            await new Promise((resolve) => requestAnimationFrame(resolve));
            progress?.set(refs.excelModal, {
                percent: 25,
                title: '\uc11c\ubc84\ub85c \uc804\uc1a1 \uc911',
                message: '\uc5c5\ub85c\ub4dc \ud30c\uc77c\uc744 \uc11c\ubc84\ub85c \ubcf4\ub0b4\uace0 \uc788\uc2b5\ub2c8\ub2e4.',
                indeterminate: true,
            });
            let uploadJson = progress?.request
                ? await progress.request(form?.dataset.uploadUrl || API.upload, formData, refs.excelModal)
                : await (async () => {
                    const uploadResponse = await fetch(form?.dataset.uploadUrl || API.upload, {
                        method: 'POST',
                        body: formData,
                    });
                    const json = await uploadResponse.json().catch(() => ({}));
                    if (!uploadResponse.ok) {
                        throw new Error(json.message || '\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.');
                    }
                    return json;
                })();
            if (uploadJson.requires_confirmation && uploadJson.confirmation_code === 'REQUIRED_FIELD_MISSING') {
                const confirmed = window.confirm(uploadJson.message || '필수요소가 입력되어 있지 않습니다. 생성센터에서 보정필요로 처리됩니다. 그래도 업로드를 진행할까요?');
                if (!confirmed) {
                    progress?.set(refs.excelModal, {
                        percent: 100,
                        title: '업로드 취소',
                        message: '필수 누락 항목이 있어 업로드를 취소했습니다.',
                    });
                    return;
                }
                const previewToken = String(uploadJson.data?.preview_token || '').trim();
                if (!previewToken) {
                    throw new Error('검증 결과 토큰이 없습니다. 다시 업로드를 실행하세요.');
                }
                excelUploadPreviewToken = previewToken;
                const totalRows = Number(uploadJson.data?.total_rows || 0);
                stopSaveTimer();
                uploadJson = await progress.saveChunks(form?.dataset.uploadUrl || API.upload, {
                    modal: refs.excelModal,
                    totalRows,
                    chunkSize: 5,
                    initialPayload: {
                        preview_token: previewToken,
                        allow_required_missing: true,
                        upload_cancel_token: excelUploadCancelToken,
                    },
                    isCanceled: () => excelUploadCanceled,
                });
                stopSaveTimer();
                excelUploadAbortController = null;
            }
            if (uploadJson.success === false) {
                throw new Error(uploadJson.message || '\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.');
            }
            const result = uploadJson.data || {};
            const dualWriteMessage = dualWriteUploadMessage(result);
            const completedMessage = [
                uploadResultProgressMessage(result, uploadJson.message),
                dualWriteMessage,
            ].filter(Boolean).join('\n');
            progress?.set(refs.excelModal, {
                percent: 100,
                title: '\uc5c5\ub85c\ub4dc \uc644\ub8cc',
                message: completedMessage,
            });
            notify(
                'success',
                completedMessage
            );

            setTimeout(() => bootstrap.Modal.getInstance(refs.excelModal)?.hide(), 250);
            table?.ajax.reload(() => updateSummary(lastRows), false);
            void refreshEvidenceTypeCounts().catch(() => {});
            document.dispatchEvent(new CustomEvent('excel:uploaded', {
                detail: { type: 'evidenceStatus', importType: currentType, result },
            }));
        } catch (error) {
            stopSaveTimer();
            if (excelUploadCanceled) {
                progress?.set(refs.excelModal, {
                    percent: 100,
                    percentLabel: '\ucde8\uc18c',
                    title: '\uc5c5\ub85c\ub4dc \ucde8\uc18c',
                    message: '\uc5c5\ub85c\ub4dc \uc694\uccad\uc744 \ucde8\uc18c\ud588\uc2b5\ub2c8\ub2e4.',
                });
                notify('warning', '\uc5c5\ub85c\ub4dc\ub97c \ucde8\uc18c\ud588\uc2b5\ub2c8\ub2e4.');
                refreshAfterUploadCancel();
                return;
            }
            progress?.set(refs.excelModal, {
                percent: 100,
                title: '\uc5c5\ub85c\ub4dc \uc2e4\ud328',
                message: error.message || '\uc5d1\uc140 \uc5c5\ub85c\ub4dc \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.',
            });
            throw error;
        } finally {
            stopSaveTimer();
            uploadingExcel = false;
            excelUploadAbortController = null;
            excelUploadCancelToken = '';
            excelUploadPreviewToken = '';
            progress?.lock?.(refs.excelModal, false);
            button.disabled = false;
            button.textContent = originalText;
        }
    }    async function rebuildTable() {
        const baseConfig = currentConfig();
        activeFormat = await loadActiveFormat(currentType);
        const hasFormat = Boolean(activeFormat);
        const hasFormatColumns = Boolean(activeFormat && Array.isArray(activeFormat.columns) && activeFormat.columns.length > 0);
        const config = {
            ...baseConfig,
            format: activeFormat,
            excelTemplate: activeFormat?.format_name || baseConfig.excelTemplate,
            evidenceColumns: hasFormatColumns ? formatColumnsFromTemplate(activeFormat) : genericEvidenceColumns(),
        };
        if (refs.excelLabel) {
            refs.excelLabel.textContent = hasFormat
                ? `${config.label} / 양식 ${config.excelTemplate}`
                : `${config.label} / 양식 없음`;
        }
        syncExcelManager(config, hasFormat);
        const selector = '#evidenceStatusTable';
        const $ = window.jQuery;
        selectedIds = new Set();

        if ($?.fn?.DataTable?.isDataTable(selector)) {
            $(selector).DataTable().destroy();
            $(selector).empty();
            document.querySelector(selector).innerHTML = '<thead><tr></tr></thead><tbody></tbody>';
        }
        document.querySelector(selector)?.classList.add('evidence-status-table', 'nowrap');

        const columns = commonColumns(config);

        table = createDataTable({
            tableSelector: selector,
            api: config.api,
            pageLength: 100,
            defaultOrder: defaultOrderForConfig(config),
            scrollX: false,
            autoWidth: false,
            paging: true,
            searching: true,
            info: true,
            showColumnVisibility: true,
            showCopyButton: true,
            searchTableId: 'evidenceStatus',
            selectable: true,
            rowIdField: (row) => (row?.processing_is_child ? '' : row?.id),
            deleteButton: true,
            deleteApi: API.deleteRows,
            bulkDelete: true,
            columns,
            tableSettings: {
                enabled: true,
                pageKey: 'ledger.data.status',
                tableKey: 'evidence-status',
                storageKey: 'ledger.data.status.evidence-status.v1',
                tableLabel: 'Evidence Status',
                columns,
                requiredColumns: [],
                defaultVisibleColumns: [],
            },
            dataSrc(json) {
                lastRows = Array.isArray(json.data) ? json.data : [];
                updateSummary(lastRows);
                if (currentType) {
                    evidenceTypeCounts[currentType] = lastRows.length;
                    renderEvidenceTypeTabs();
                }
                return lastRows;
            },
            buttons: [
                {
                    text: '휴지통',
                    className: 'btn btn-outline-danger btn-sm evidence-status-trash-btn',
                    action: openEvidenceTrash,
                },
                ...(hasFormat ? [
                {
                    text: '엑셀관리',
                    className: 'btn btn-outline-dark btn-sm',
                    action: () => showModal('dataExcelModal'),
                }] : []),
                ...(hasFormat ? [{
                    text: '선택 일괄보정',
                    className: 'btn btn-primary btn-sm evidence-bulk-edit-btn',
                    action: openBulkEditModal,
                },
                {
                    text: '새 증빙',
                    className: 'btn btn-outline-primary btn-sm evidence-new-btn',
                    action: openNewEvidenceModal,
                }] : []),
            ],
        });
        void updateTrashButtonState();
        table.on('draw.dt xhr.dt', () => {
            applyProcessingRowState();
        });
        table.on('column-visibility.dt responsive-resize.dt', () => {
            window.setTimeout(applyProcessingRowState, 0);
        });
        applyProcessingRowState();

        bindRowReorder(table, {
            api: API.reorder,
            sortNoField: 'row_no',
            includeAppliedRows: true,
            changedRowsOnly: false,
            sortableItems: '> tr:not(.processing-child-row)',
            isReorderableRow: (row) => !row?.processing_is_child,
            extraData: () => ({
                scope: 'status',
                import_type: currentType,
                data_type: currentType,
            }),
            onSuccess(json) {
                notify('success', json?.message || '증빙원본 순서가 변경되었습니다.');
                table?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '증빙원본 순서 변경에 실패했습니다.');
                table?.ajax.reload(null, false);
            },
        });

        SearchForm({
            table,
            apiList: config.api,
            tableId: 'evidenceStatus',
            defaultSearchField: 'client_name',
            dateOptions: [
                ...(config.dateOptions || []),
                { value: 'created_at', label: '업로드일시' },
                { value: 'processed_at', label: '처리일시' },
                { value: 'updated_at', label: '수정일시' },
            ],
            excludeFields: ['', 'voucher_status', 'review_status', 'recommend_status', 'user_modified'],
        });

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
        if (!refs.trashModal) return;
        const selectedLabel = refs.typeSelect?.selectedOptions?.[0]?.textContent?.trim() || currentConfig()?.label || '증빙원본';
        refs.trashModal.dataset.listUrl = `${API.trash}?import_type=${encodeURIComponent(currentType)}`;
        refs.trashModal.dataset.importType = currentType;
        refs.trashModal.dataset.trashTitle = `${selectedLabel} 휴지통`;
        bootstrap.Modal.getOrCreateInstance(refs.trashModal, { focus: false }).show();
    }

    function applyProcessingRowState() {
        if (!table) return;
        const body = table.table?.().body?.();
        if (!body) return;
        body.querySelectorAll('tr.processing-child-display-row').forEach((tr) => tr.remove());
        Array.from(body.querySelectorAll('tr')).forEach((tr) => {
            const row = table.row(tr).data();
            tr.classList.toggle('processing-child-row', Boolean(row?.processing_is_child));
            tr.classList.toggle('processing-parent-row', Boolean(row?.processing_has_children));
            if (row?.processing_has_children && Array.isArray(row.processing_children) && row.processing_children.length > 0) {
                insertProcessingChildRows(tr, row.processing_children);
            }
        });
    }

    function dataTableColumnValue(row = {}, dataSrc = null) {
        if (typeof dataSrc === 'function') return dataSrc(row, 'display', row);
        if (dataSrc === null || dataSrc === undefined) return row;
        if (typeof dataSrc !== 'string' || dataSrc === '') return row[dataSrc] ?? '';
        return dataSrc.split('.').reduce((value, key) => (
            value && typeof value === 'object' ? value[key] : undefined
        ), row);
    }

    function renderProcessingChildCell(column = {}, row = {}, columnIndex = 0) {
        const data = dataTableColumnValue(row, column.mData);
        const meta = {
            row: 0,
            col: columnIndex,
            settings: table?.settings?.()[0] || {},
        };
        if (typeof column.mRender === 'function') {
            return column.mRender(data, 'display', row, meta);
        }
        if (typeof column.mRender === 'object' && typeof column.mRender?.display === 'function') {
            return column.mRender.display(data, 'display', row, meta);
        }
        return escapeHtml(data ?? '');
    }

    function insertProcessingChildRows(parentTr, children = []) {
        const settings = table?.settings?.()[0];
        const columns = settings?.aoColumns || [];
        if (!parentTr || columns.length === 0) return;

        const visibleIndexes = columns
            .map((column, index) => (column.bVisible === false ? null : index))
            .filter((index) => index !== null);
        let anchor = parentTr;
        children.forEach((child) => {
            const tr = document.createElement('tr');
            tr.className = 'processing-child-row processing-child-display-row';
            tr.__processingRowData = child;
            visibleIndexes.forEach((columnIndex) => {
                const column = columns[columnIndex] || {};
                const td = document.createElement('td');
                const className = String(column.sClass || '').trim();
                if (className !== '') {
                    td.className = className;
                }
                td.innerHTML = renderProcessingChildCell(column, child, columnIndex);
                tr.appendChild(td);
            });
            anchor.insertAdjacentElement('afterend', tr);
            anchor = tr;
        });
    }

    function rowDataFromTableNode(rowNode) {
        if (!rowNode || !table) return null;
        if (rowNode.__processingRowData) {
            return rowNode.__processingRowData;
        }
        return table.row(rowNode).data() || null;
    }

    async function updateTrashButtonState() {
        const button = document.querySelector('.evidence-status-trash-btn');
        if (!button || !currentType) return;
        const listUrl = `${API.trash}?import_type=${encodeURIComponent(currentType)}`;
        try {
            if (refs.trashModal) {
                refs.trashModal.dataset.listUrl = listUrl;
                refs.trashModal.dataset.importType = currentType;
                refs.trashModal.dataset.trashTitle = `${selectedTypeLabel() || currentType} 휴지통`;
            }
            const rows = refs.trashModal && window.TrashManager?.preloadTrash
                ? await window.TrashManager.preloadTrash(refs.trashModal)
                : [];
            const hasTrash = rows.length > 0;
            button.classList.toggle('btn-trash-has-data', hasTrash);
            button.classList.toggle('btn-outline-danger', !hasTrash);
            button.setAttribute('aria-label', hasTrash ? `휴지통 ${rows.length}건` : '휴지통');
            button.title = hasTrash ? `휴지통 ${rows.length}건` : '휴지통';
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
        button.setAttribute('aria-label', `휴지통 ${safeCount}건 이상`);
        button.title = `휴지통 ${safeCount}건 이상`;
    }

    function changeType(nextType) {
        nextType = normalizeEvidenceType(nextType);
        if (!nextType || String(nextType).startsWith('__') || !isEvidenceUploadType(nextType)) {
            return;
        }
        if (nextType === currentType) {
            return;
        }
        currentType = nextType;
        syncTypeControls();
        void rebuildTable().catch((error) => {
            notify('error', error.message);
        });
    }

    function handleTypeSelectChanged() {
        const value = String(refs.typeSelect?.value || '').trim();
        changeType(value);
    }

    async function initTypeSelect() {
        if (!refs.typeSelect) {
            currentType = 'TAX_INVOICE';
            return;
        }

        await initCodeSelectControls(document.getElementById('ledgerDataStatusPage') || document);
        filterEvidenceTypeSelect();
        currentType = refs.typeSelect.value || firstAvailableType();
        if (refs.typeSelect.value !== currentType) {
            refs.typeSelect.value = currentType;
            if (window.jQuery?.fn?.select2 && window.jQuery(refs.typeSelect).hasClass('select2-hidden-accessible')) {
                window.jQuery(refs.typeSelect).val(currentType).trigger('change.select2');
            }
        }
    }

    function bindEvents() {
        refs.typeSelect?.addEventListener('change', handleTypeSelectChanged);
        if (window.jQuery?.fn?.select2 && refs.typeSelect) {
            window.jQuery(refs.typeSelect)
                .off('change.dataStatusType select2:select.dataStatusType')
                .on('change.dataStatusType select2:select.dataStatusType', handleTypeSelectChanged);
        }
        refs.typeTabs?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-evidence-type]');
            if (button) {
                changeType(button.dataset.evidenceType || '');
            }
        });

        refs.trashBtn?.addEventListener('click', openEvidenceTrash);
        refs.excelModal?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-upload-excel');
            if (!button) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            void uploadExcelFromModal(button).catch((error) => notify('error', error.message));
        }, true);
        refs.excelModal?.addEventListener('hide.bs.modal', (event) => {
            if (!uploadingExcel) return;
            const confirmed = window.confirm('\uc5c5\ub85c\ub4dc\uac00 \uc9c4\ud589 \uc911\uc785\ub2c8\ub2e4. \uc694\uccad\uc744 \ucde8\uc18c\ud558\uace0 \ub2eb\uc744\uae4c\uc694?');
            if (!confirmed) {
                event.preventDefault();
                return;
            }
            excelUploadCanceled = true;
            window.ExcelManagerProgress?.abort(refs.excelModal);
            excelUploadAbortController?.abort();
            requestExcelUploadCancel();
            refreshAfterUploadCancel();
        });
        window.addEventListener('beforeunload', () => {
            if (uploadingExcel) {
                excelUploadCanceled = true;
                window.ExcelManagerProgress?.abort(refs.excelModal);
                excelUploadAbortController?.abort();
                requestExcelUploadCancel();
            }
        });
        refs.editSaveBtn?.addEventListener('click', () => {
            void saveEditingRow().catch((error) => notify('error', error.message));
        });
        refs.bulkSaveBtn?.addEventListener('click', () => {
            void saveBulkEdit().catch((error) => notify('error', error.message));
        });
        refs.editModal?.addEventListener('hidden.bs.modal', () => {
            clearEditPickerLayers();
        });
        refs.bulkFields?.addEventListener('change', (event) => {
            const toggle = event.target.closest('.evidence-bulk-toggle');
            if (toggle) toggleBulkField(toggle);
        });

        const evidenceTableEl = document.getElementById('evidenceStatusTable');
        evidenceTableEl?.addEventListener('datatable:selection-changed', (event) => {
            selectedIds = new Set((event.detail?.ids || []).map((id) => String(id)));
        });
        evidenceTableEl?.addEventListener('datatable:soft-delete-completed', (event) => {
            markTrashButtonHasData(event.detail?.ids?.length || 1);
            void updateTrashButtonState();
        });

        evidenceTableEl?.addEventListener('dblclick', (event) => {
            if (event.target.closest('a, button, input, select, textarea, .dt-select-column')) return;
            const rowNode = event.target.closest('tr');
            if (!rowNode || !table) return;
            const row = rowDataFromTableNode(rowNode);
            if (row?.processing_is_child) {
                openProcessingChildEditModal(row);
                return;
            }
            openEditModal(row);
        });
        evidenceTableEl?.addEventListener('mouseover', (event) => {
            const cell = event.target.closest('td, th');
            const rowNode = cell?.closest('tr.processing-child-display-row');
            if (!cell || !rowNode || !evidenceTableEl.contains(rowNode)) return;
            const wrapper = evidenceTableEl.closest('.dataTables_wrapper');
            const columnIndex = Array.from(rowNode.children).indexOf(cell);
            wrapper?.querySelectorAll('tbody tr').forEach((tr) => tr.classList.remove('row-highlight'));
            rowNode.classList.add('row-highlight');
            wrapper?.querySelectorAll('td, th').forEach((node) => node.classList.remove('col-highlight'));
            if (columnIndex >= 0) {
                wrapper?.querySelectorAll('tbody tr').forEach((tr) => tr.children[columnIndex]?.classList.add('col-highlight'));
                wrapper?.querySelector(`.dataTables_scrollHead th:nth-child(${columnIndex + 1})`)?.classList.add('col-highlight');
            }
        });
        evidenceTableEl?.addEventListener('click', (event) => {
            const deleteChildButton = event.target.closest('.evidence-delete-child-row-btn');
            if (deleteChildButton) {
                void deleteProcessingChild(deleteChildButton).catch((error) => notify('error', error.message));
                return;
            }
            const childButton = event.target.closest('.evidence-add-child-row-btn');
            if (childButton && table) {
                const rowNode = childButton.closest('tr');
                const row = rowDataFromTableNode(rowNode);
                openProcessingSplitModal(row || {});
                return;
            }
            const button = event.target.closest('.evidence-edit-row-btn');
            if (!button || !table) return;
            const rowNode = button.closest('tr');
            const row = rowDataFromTableNode(rowNode);
            if (row?.processing_is_child) {
                openProcessingChildEditModal(row);
                return;
            }
            openEditModal(row);
        });

        document.addEventListener('trash:changed', (event) => {
            if (event.detail?.type === 'evidenceStatus') {
                table?.ajax.reload(() => updateSummary(lastRows), false);
                void refreshEvidenceTypeCounts().catch(() => {});
                void updateTrashButtonState();
            }
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || event.data?.type !== 'data-format:saved') {
                return;
            }
            const dataType = String(event.data?.dataType || '').trim();
            if (dataType && dataType !== currentType) {
                return;
            }
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
                        <dt class="col-4">자료유형</dt><dd class="col-8">${escapeHtml(row.import_type_name || row.import_type || row.source_type || '-')}</dd>
                        <dt class="col-4">거래처</dt><dd class="col-8">${escapeHtml(clientName(row) || '-')}</dd>
                        <dt class="col-4">합계</dt><dd class="col-8">${escapeHtml(formatNumber(mapped(row).total_amount))}</dd>
                        <dt class="col-4">오류</dt><dd class="col-8">${escapeHtml(row.error_message || '-')}</dd>
                        <dt class="col-4">삭제일시</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
                    </dl>
                </div>
            `;
        });
    }

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.evidenceStatus = function (row = {}) {
        return `
            <td class="text-center">${escapeHtml(row.row_no || '-')}</td>
            <td>${escapeHtml(row.import_type_name || row.import_type || row.source_type || '-')}</td>
            <td>${escapeHtml(clientName(row) || '-')}</td>
            <td class="text-end">${escapeHtml(formatNumber(mapped(row).total_amount))}</td>
            <td>${escapeHtml(row.deleted_at || '-')}</td>
            <td>
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id)}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id)}">삭제</button>
            </td>
        `;
    };

    onCodeOptionsLoaded((options) => {
        codeOptions = { ...(options || {}) };
        codeOptions.IMPORT_TYPE = (codeOptions.IMPORT_TYPE || [])
            .map((row) => ({ ...row, code: normalizeEvidenceType(row.code || row.value) }))
            .filter((row) => row.code && !row.code.startsWith('__'))
            .filter((row, index, list) => list.findIndex((item) => item.code === row.code) === index);
    });

    bindEvents();
    initTypeSelect()
        .then(async () => {
            await loadDisplayCodeOptions();
            await refreshEvidenceTypeCounts();
            syncTypeControls();
            return rebuildTable();
        })
        .catch((error) => {
            console.error(error);
            currentType = currentType || 'TAX_INVOICE';
            syncTypeControls();
            void rebuildTable().catch((rebuildError) => {
                notify('error', rebuildError.message);
            });
        });
})();

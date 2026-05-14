import { createDataTable } from '/public/assets/js/components/data-table.js';
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
import '/public/assets/js/components/trash-manager.js';
import '/public/assets/js/components/excel-manager.js';

(() => {
    'use strict';

    const API = {
        seedRows: '/api/import/evidences',
        formats: '/api/import/formats',
        preview: '/api/import/preview',
        upload: '/api/import/evidence-upload',
        download: '/api/import/evidences/download',
        trash: '/api/import/evidences/trash',
        deleteRows: '/api/import/evidences/delete',
        reorder: '/api/import/evidences/reorder',
        saveSeedRow: '/api/import/evidence/save',
        createEvidence: '/api/import/evidence/create',
        bulkSaveSeedRows: '/api/import/evidences/bulk-save',
        clientSearch: '/api/settings/base-info/client/search-picker',
        projectSearch: '/api/settings/base-info/project/search-picker',
        employeeSearch: '/api/settings/organization/employee/search-picker',
        bankAccountSearch: '/api/settings/base-info/bank-account/search-picker',
        cardSearch: '/api/settings/base-info/card/search-picker',
        codeList: '/api/settings/system/code/list',
    };

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

    function clientCompanyText(row = {}) {
        return row.company_name || row.client_name || row.text || '';
    }

    function clientAutofillPayload(row = {}) {
        return {
            business_number: formatBizNumber(row.business_number || ''),
            ceo_name: row.ceo_name || '',
            address: [row.address || '', row.address_detail || ''].filter(Boolean).join(' '),
            email: row.email || '',
            phone: formatPhone(row.phone || ''),
        };
    }

    const BANK_REF_PICKERS = {
        SUPPLIER_COMPANY: {
            picker: 'supplierCompany',
            url: API.clientSearch,
            idKey: '',
            nameKey: 'supplier_company_name',
            placeholder: '공급자 상호 선택',
            keys: ['supplier_company_name', 'supplier_name', '공급자상호', '공급자명'],
            allowText: true,
            label: clientCompanyText,
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
            url: API.clientSearch,
            idKey: '',
            nameKey: 'customer_company_name',
            placeholder: '공급받는자 상호 선택',
            keys: ['customer_company_name', 'customer_name', '공급받는자상호', '공급받는자명'],
            allowText: true,
            label: clientCompanyText,
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
            url: API.clientSearch,
            idKey: 'client_id',
            nameKey: 'client_name',
            placeholder: '거래처 선택',
            keys: ['client_id', 'client_name', 'client_company_name', '거래처명', '거래처'],
            allowText: true,
            label: (row) => row.text || row.client_name || row.company_name || '',
        },
        PROJECT: {
            picker: 'project',
            url: API.projectSearch,
            idKey: 'project_id',
            nameKey: 'project_name',
            placeholder: '프로젝트 선택',
            keys: ['project_id', 'project_name', 'project_code', '프로젝트명', '프로젝트'],
            label: (row) => row.text || row.project_name || row.construction_name || row.project_code || '',
        },
        EMPLOYEE: {
            picker: 'employee',
            url: API.employeeSearch,
            idKey: 'employee_id',
            nameKey: 'employee_name',
            placeholder: '직원 선택',
            keys: ['employee_id', 'employee_name', 'user_name', 'user_id', '직원명', '직원'],
            label: (row) => row.text || row.employee_name || row.name || row.username || '',
        },
        ACCOUNT: {
            picker: 'bankAccount',
            url: API.bankAccountSearch,
            idKey: 'bank_account_id',
            nameKey: 'bank_account_name',
            placeholder: '계좌 선택',
            keys: ['bank_account_id', 'bank_account_name', 'bank_account', 'account_name', 'payment_account_name', 'account_number', 'payment_account_number', '계좌명', '계좌'],
            label: (row) => row.text || row.account_name || row.bank_account_name || row.account_number || row.bank_name || '',
        },
        CARD: {
            picker: 'card',
            url: API.cardSearch,
            idKey: 'card_id',
            nameKey: 'card_name',
            placeholder: '카드 선택',
            keys: ['card_id', 'card_name', 'card_number', 'card_company_name', '카드명', '카드'],
            label: (row) => row.text || row.card_name || row.card_number || row.card_company_name || '',
        },
    };
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

    function firstPayloadValue(payload = {}, keys = []) {
        for (const key of keys) {
            if (!key) continue;
            const value = payload[key];
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
        const number = Number(String(value ?? '0').replaceAll(',', ''));
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
        return row.client_name
            || payload.client_company_name
            || payload.supplier_name
            || payload.customer_name
            || payload.supplier_company_name
            || payload.customer_company_name
            || payload['공급자 상호']
            || payload['공급받는자 상호']
            || payload.employee_name
            || payload.client_business_number
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
            render: (_value, _type, row) => escapeHtml(renderer(row)),
            ...options,
        };
    }

    function moneyColumn(data, title, renderer) {
        return {
            data,
            title,
            className: 'text-end',
            render: (_value, _type, row) => formatNumber(renderer(row)),
        };
    }

    function normalizeCodeKey(value) {
        return String(value ?? '').trim().toUpperCase();
    }

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
            if (explicit !== undefined && explicit !== null && String(explicit) !== '') {
                const explicitText = String(explicit);
                if (/\d{1,2}:\d{2}/.test(explicitText)) {
                    return explicitText;
                }
                const rawDateTime = raw[excelName] ?? raw[systemField] ?? '';
                if (rawDateTime !== '' && /\d{1,2}:\d{2}/.test(String(rawDateTime))) {
                    return rawDateTime;
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

            if (String(dateValue) !== '' && String(timeValue) !== '') {
                return `${dateValue} ${timeValue}`;
            }
            if (explicit !== undefined && explicit !== null && String(explicit) !== '') {
                return explicit;
            }
        }
        const value = firstPayloadValue(payload, columnAliasKeys(column));
        if (value !== undefined && value !== null && String(value) !== '') {
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
        const raw = String(value ?? '').trim();
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
        const raw = String(value ?? '').trim();
        if (raw === '' || raw === '-') return '-';

        const date = formatDateValue(raw);
        const timeMatch = raw.match(/(?:\s|T)(\d{1,2}):(\d{2})(?::(\d{2}))?/);
        if (!timeMatch || date === '-') {
            return date;
        }

        return `${date} ${pad2(timeMatch[1])}:${timeMatch[2]}${timeMatch[3] ? `:${timeMatch[3]}` : ''}`;
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

    function parseEditDateValue(value, keepTime = false) {
        const raw = String(value ?? '').trim();
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
            if (dateTimeValue !== undefined && dateTimeValue !== null && String(dateTimeValue).trim() !== '') {
                return dateTimeValue;
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
            if (dateValue !== undefined && dateValue !== null && String(dateValue).trim() !== '') {
                return `${dateValue}${timeValue !== undefined && timeValue !== null && String(timeValue).trim() !== '' ? ` ${timeValue}` : ''}`;
            }
        }
        const value = firstPayloadValue(payload, columnAliasKeys(column));
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            return value;
        }

        return raw[column.excel_column_name]
            ?? raw[column.system_field_name]
            ?? '';
    }

    function editInputType(column = {}, value = '') {
        const key = editFieldKey(column).toLowerCase();
        if (businessRefPickerForColumn(column)) return 'ref';
        if (bankCodePickerForColumn(column)) return 'code';
        if (isDateColumn(column)) return 'date';
        if (isAmountColumn(column)) return 'number';
        if (isBusinessNumberColumn(column)) return 'business_number';
        if (isPhoneColumn(column)) return 'phone';
        if (String(value ?? '').length > 80 || /memo|note|description|address|비고|메모|주소|적요/.test(key)) return 'textarea';
        return 'text';
    }

    function businessRefPickerForColumn(column = {}) {
        const field = String(column.system_field_name || '').trim().toLowerCase();
        const excel = String(column.excel_column_name || '').trim().replace(/\s+/g, '').toLowerCase();
        const key = editFieldKey(column).toLowerCase();
        const tokens = [field, excel, key].filter(Boolean);

        return Object.values(BANK_REF_PICKERS).find((config) => (
            config.keys.some((candidate) => tokens.includes(candidate.toLowerCase()))
        )) || null;
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
            if (value !== undefined && value !== null && String(value).trim() !== '') {
                return String(value).trim();
            }
        }
        return '';
    }

    function renderRefSelect(column = {}, value = '') {
        const config = businessRefPickerForColumn(column);
        const key = editFieldKey(column);
        const payload = mapped(editingRow);
        const selectedId = firstPayloadText(payload, [config.idKey]);
        const selectedText = firstPayloadText(payload, [config.nameKey, ...config.keys, key]) || String(value ?? '').trim();
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
                    : String(value ?? '');
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

        const config = Object.values(BANK_REF_PICKERS).find((item) => item.picker === select.dataset.refPicker);
        if (!config) return;

        AdminPicker.select2Ajax(select, {
            url: config.url,
            placeholder: config.placeholder,
            allowClear: true,
            tags: !!config.allowText,
            minimumInputLength: 0,
            dropdownParent: window.jQuery(refs.bulkModal),
            width: '100%',
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
                            const text = config.label(row);
                            return {
                                id: String(row.id ?? text ?? ''),
                                text,
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
                this.dataset.refSelectedText = event.params?.data?.text || '';
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
                const selectedId = String(input.value || '').trim();
                const selectedOption = input.selectedOptions?.[0] || null;
                const selectedText = String(input.dataset.refSelectedText || selectedOption?.textContent || '').trim();
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
            patch[key] = input.value;
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
                    seed_row_ids: ids,
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
        return String(value ?? '').trim();
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
        if (!select || select.dataset.refSelectBound === 'true') return;
        if (!window.jQuery?.fn?.select2) return;

        const config = Object.values(BANK_REF_PICKERS).find((item) => item.picker === select.dataset.refPicker);
        if (!config) return;

        AdminPicker.select2Ajax(select, {
            url: config.url,
            placeholder: config.placeholder,
            allowClear: true,
            tags: !!config.allowText,
            minimumInputLength: 0,
            dropdownParent: window.jQuery(refs.editModal),
            width: '100%',
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
                            const text = config.label(row);
                            return {
                                id: String(row.id ?? text ?? ''),
                                text,
                                ...(typeof config.result === 'function' ? config.result(row) : {}),
                            };
                        }).filter((item) => item.id !== '' && item.text !== ''),
                    ],
                };
            },
        });

        window.jQuery(select)
            .off('select2:select.evidenceEditRef')
            .on('select2:select.evidenceEditRef', function (event) {
                const data = event.params?.data || {};
                this.dataset.refSelectedText = data.text || '';
                this.dataset.refCurrentTextOnly = '0';
                applyRefAutofill(config, data);
            })
            .off('select2:clear.evidenceEditRef')
            .on('select2:clear.evidenceEditRef', function () {
                this.dataset.refSelectedText = '';
                this.dataset.refCurrentTextOnly = '0';
            });

        select.dataset.refSelectBound = 'true';
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

    function collectEditPayload() {
        const next = { ...mapped(editingRow) };
        refs.editFields?.querySelectorAll('.evidence-edit-input').forEach((input) => {
            const key = input.dataset.key || '';
            if (!key) return;
            if (input.dataset.valueKind === 'ref') {
                const idKey = input.dataset.refIdKey || '';
                const nameKey = input.dataset.refNameKey || key;
                const allowsText = input.dataset.refAllowText === '1';
                const selectedId = String(input.value || '').trim();
                const selectedOption = input.selectedOptions?.[0] || null;
                let selectedText = String(
                    input.dataset.refSelectedText
                    || selectedOption?.textContent
                    || input.dataset.refCurrentText
                    || ''
                ).trim();
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
            next[key] = input.value;
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
                    ? String(input.dataset.refSelectedText || input.selectedOptions?.[0]?.textContent || input.value || '').trim()
                    : String(input.value ?? '').trim())
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
        const isNew = editingRow.__isNew === true;
        const json = await fetch(isNew ? API.createEvidence : API.saveSeedRow, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: editingRow.id || '',
                format_id: activeFormat?.id || editingRow.format_id || '',
                import_type: currentType,
                parsed_json: collectEditPayload(),
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
                    className: isAmount ? 'text-end' : '',
                    render: (_value, type, row) => {
                        const value = formatValue(row, column);
                        if (type === 'sort' || type === 'type') {
                            if (isDate) return normalizeDateInputValue(value, isDateTimeColumn(column)) || '';
                            if (isAmount) return parseCommonNumber(value);
                            return String(value ?? '');
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
        return String(value ?? '')
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
        return [[0, 'asc']];
    }

    function applyDefaultTableOrder(tableInstance, config = {}) {
        if (!tableInstance) return;

        const order = defaultOrderForConfig(config);
        if (!Array.isArray(order) || order.length === 0) return;

        tableInstance.order(order.map((item) => {
            if (!Array.isArray(item) || typeof item[0] !== 'number') return item;
            return [item[0] + 1, ...item.slice(1)];
        })).draw();
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
                defaultContent: '<i class="bi bi-list"></i>',
            },
            {
                data: 'row_no',
                title: '순번',
                className: 'text-center text-nowrap',
                render(value, _type, _row, meta) {
                    return escapeHtml(value || (meta.row + meta.settings._iDisplayStart + 1));
                },
            },
            ...config.evidenceColumns,
            {
                data: null,
                title: '관리',
                className: 'text-center no-colvis',
                headerClassName: 'text-center no-colvis',
                orderable: false,
                searchable: false,
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

    function updateSummary(rows = []) {
        const summary = {
            total: rows.length,
            transactionPending: rows.filter((row) => String(row.transaction_id || '').trim() === '').length,
            transactionCreated: rows.filter((row) => String(row.transaction_id || '').trim() !== '').length,
            voucherReview: rows.filter((row) => String(row.transaction_id || '').trim() !== '').length,
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
        if (!type) return null;
        const response = await fetch(`${API.formats}?data_type=${encodeURIComponent(type)}&include_columns=1`, { cache: 'no-store' });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '양식 정보를 불러오지 못했습니다.');
        }
        const formats = Array.isArray(json.data) ? json.data : [];
        return formats.find((format) => Number(format.is_default || 0) === 1) || formats[0] || null;
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

    async function postJson(url, payload = {}) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '요청 처리에 실패했습니다.');
        }
        return json;
    }

    async function uploadExcelFromModal(button) {
        if (uploadingExcel) return;
        const form = refs.excelForm;
        const fileInput = refs.excelModal?.querySelector('input[type="file"]');
        const file = fileInput?.files?.[0] || null;
        const formatId = String(form?.dataset.formatId || activeFormat?.id || '').trim();
        const progress = window.ExcelManagerProgress;

        if (!activeFormat || !formatId) {
            notify('warning', '\uba3c\uc800 \uc790\ub8cc\uc720\ud615\uc758 \uc591\uc2dd\uc744 \uc0dd\uc131\ud558\uc138\uc694.');
            return;
        }
        if (!file) {
            notify('warning', '\uc5c5\ub85c\ub4dc\ud560 \uc5d1\uc140 \ud30c\uc77c\uc744 \uc120\ud0dd\ud558\uc138\uc694.');
            return;
        }

        const formData = new FormData();
        formData.append('format_id', formatId);
        formData.append('file', file);

        uploadingExcel = true;
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
            const uploadJson = progress?.request
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
            progress?.set(refs.excelModal, {
                percent: 70,
                title: '\ub370\uc774\ud130 \ucc98\ub9ac \uc911',
                message: '\uc11c\ubc84\uc5d0\uc11c \uc5d1\uc140 \ub370\uc774\ud130\ub97c \uc77d\uace0 \uc2e0\uaddc/\ubcc0\uacbd/\ub3d9\uc77c \uac74\uc744 \uc815\ub9ac\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.',
                indeterminate: true,
            });
            if (uploadJson.success === false) {
                throw new Error(uploadJson.message || '\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.');
            }
            const result = uploadJson.data || {};
            progress?.set(refs.excelModal, {
                percent: 100,
                title: '\uc5c5\ub85c\ub4dc \uc644\ub8cc',
                message: uploadJson.message || '\uc5c5\ub85c\ub4dc\uac00 \uc644\ub8cc\ub418\uc5b4 \ubaa9\ub85d\uc744 \uc0c8\ub85c\uace0\uce68\ud569\ub2c8\ub2e4.',
            });
            notify(
                'success',
                uploadJson.message
                    || `\uc5c5\ub85c\ub4dc \uc644\ub8cc: \uc2e0\uaddc ${Number(result.new_count || 0).toLocaleString('ko-KR')}\uac74, \ubcc0\uacbd ${Number(result.updated_count || 0).toLocaleString('ko-KR')}\uac74, \ub3d9\uc77c ${Number(result.unchanged_count || 0).toLocaleString('ko-KR')}\uac74`
            );

            setTimeout(() => bootstrap.Modal.getInstance(refs.excelModal)?.hide(), 250);
            table?.ajax.reload(() => updateSummary(lastRows), false);
            void refreshEvidenceTypeCounts().catch(() => {});
            document.dispatchEvent(new CustomEvent('excel:uploaded', {
                detail: { type: 'evidenceStatus', importType: currentType, result },
            }));
        } catch (error) {
            progress?.set(refs.excelModal, {
                percent: 100,
                title: '\uc5c5\ub85c\ub4dc \uc2e4\ud328',
                message: error.message || '\uc5d1\uc140 \uc5c5\ub85c\ub4dc \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.',
            });
            throw error;
        } finally {
            uploadingExcel = false;
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

        table = createDataTable({
            tableSelector: selector,
            api: config.api,
            pageLength: 100,
            defaultOrder: defaultOrderForConfig(config),
            scrollX: true,
            autoWidth: false,
            paging: true,
            searching: true,
            info: true,
            showColumnVisibility: true,
            showCopyButton: true,
            searchTableId: 'evidenceStatus',
            selectable: true,
            deleteButton: true,
            deleteApi: API.deleteRows,
            bulkDelete: true,
            columns: commonColumns(config),
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
                {
                    text: '양식관리',
                    className: 'btn btn-outline-secondary btn-sm',
                    action: () => showModal('dataFormatModal'),
                },
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
            void updateTrashButtonState();
        });

        bindRowReorder(table, {
            api: API.reorder,
            sortNoField: 'row_no',
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

        applyDefaultTableOrder(table, config);

        SearchForm({
            table,
            apiList: config.api,
            tableId: 'evidenceStatus',
            defaultSearchField: 'client_name',
            initialCollapsed: true,
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

    async function updateTrashButtonState() {
        const button = document.querySelector('.evidence-status-trash-btn');
        if (!button || !currentType) return;
        try {
            const res = await fetch(`${API.trash}?import_type=${encodeURIComponent(currentType)}`, {
                credentials: 'same-origin',
            });
            const json = await res.json();
            const rows = json?.success ? (json.data || []) : [];
            const hasTrash = rows.length > 0;
            button.classList.toggle('btn-trash-has-data', hasTrash);
            button.classList.toggle('btn-outline-danger', !hasTrash);
            button.setAttribute('aria-label', hasTrash ? `휴지통 ${rows.length}건` : '휴지통');
            button.title = hasTrash ? `휴지통 ${rows.length}건` : '휴지통';
        } catch (error) {
            console.error('[data-status] trash state failed:', error);
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
            const row = table.row(rowNode).data();
            openEditModal(row);
        });
        evidenceTableEl?.addEventListener('click', (event) => {
            const button = event.target.closest('.evidence-edit-row-btn');
            if (!button || !table) return;
            const rowNode = button.closest('tr');
            const row = rowNode ? table.row(rowNode).data() : null;
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

import { createDataTable } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { bindNumberInput, formatDateInputValue } from '/public/assets/js/common/format.js';
import { initCodeSelectControls } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import '/public/assets/js/components/trash-manager.js';

(() => {
    'use strict';

    const API = {
        rows: '/api/import/evidences',
        format: '/api/import/format',
        formats: '/api/import/formats',
        reorder: '/api/import/evidences/reorder',
        trash: '/api/import/evidences/trash',
        changeStatus: '/api/import/evidences/status',
        deleteRows: '/api/import/evidences/delete',
        restoreRows: '/api/import/evidences/restore',
        purgeRows: '/api/import/evidences/purge',
        purgeAll: '/api/import/evidences/purge-all',
        saveSeedRow: '/api/import/evidence/save',
        evidenceSummarySearch: '/api/import/evidence/summary-search',
        createTransactions: '/api/import/create-transactions',
        createVoucherFromTransaction: '/api/ledger/transaction/create-voucher',
        accountList: '/api/ledger/account/list',
        subAccountList: '/api/account/sub-accounts',
        clientList: '/api/settings/base-info/client/list',
        projectList: '/api/settings/base-info/project/list',
        employeeList: '/api/settings/organization/employee/list',
        bankAccountList: '/api/settings/base-info/bank-account/list',
        cardList: '/api/settings/base-info/card/list',
        clientSearch: '/api/settings/base-info/client/search-picker',
        projectSearch: '/api/settings/base-info/project/search-picker',
        employeeSearch: '/api/settings/organization/employee/search-picker',
        bankAccountSearch: '/api/settings/base-info/bank-account/search-picker',
        cardSearch: '/api/settings/base-info/card/search-picker',
        codeList: '/api/settings/system/code/list',
    };

    const DISPLAY_CODE_FIELDS = {
        business_unit: 'BUSINESS_UNIT',
        transaction_type: 'TRANSACTION_TYPE',
        transaction_direction: 'TRANSACTION_DIRECTION',
    };
    const codeOptions = {};
    const selectedIds = new Set();
    let evidenceTable = null;
    let isCreating = false;
    let selectedTypeFilter = '';
    let seedRowsTypeSearchRegistered = false;
    const formatCache = new Map();
    let voucherAccountOptions = null;
    const voucherAccountById = new Map();
    const voucherAccountByCode = new Map();
    const voucherRefOptionCache = {};
    const voucherAccountPolicyCache = {};
    const readinessPickerMeta = new WeakMap();
    let readinessTransactionLineHot = null;
    let readinessSummaryAutocompleteTimer = null;
    let readinessSummaryAutocompleteItems = [];
    let readinessSummaryAutocompleteActiveIndex = -1;
    let readinessSummaryAutocompleteAbort = null;
    let readinessSummaryAutocompleteInput = null;
    let readinessSummaryAutocompleteDocumentBound = false;

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
            window.AppCore.notify(type, message);
            return;
        }
        console[type === 'error' ? 'error' : 'warn'](message);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            cache: 'no-store',
            ...options,
            headers: {
                ...(options.headers || {}),
            },
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '요청 처리에 실패했습니다.');
        }
        return json;
    }

    async function requestCreateTransactions(ids, extraPayload = {}) {
        const response = await fetch(API.createTransactions, {
            method: 'POST',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seed_row_ids: ids, evidence_ids: ids, ...extraPayload }),
        });
        const json = await response.json().catch(() => ({}));
        if (json.requires_confirmation) {
            return json;
        }
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '거래/전표 생성 요청 처리에 실패했습니다.');
        }
        return json;
    }

    function mapped(row) {
        return row?.mapped_payload || {};
    }

    function numericValue(value) {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : null;
        }
        const normalized = String(value ?? '')
            .trim()
            .replace(/[,\s원₩]/g, '');
        if (!normalized || normalized === '-' || normalized.toLowerCase() === 'nan') {
            return null;
        }
        const number = Number(normalized);
        return Number.isFinite(number) ? number : null;
    }

    function formatNumber(value) {
        const number = numericValue(value);
        return number === null ? '-' : number.toLocaleString('ko-KR');
    }

    function formatDate(value) {
        if (!value) return '-';
        const normalized = formatDateInputValue(value);
        return normalized || String(value).trim();
    }

    function standardDate(row) {
        const payload = mapped(row);
        return payload.transaction_date
            || payload.evidence_date
            || payload.purchase_datetime
            || payload.purchase_date
            || payload.approval_datetime
            || payload.approval_date
            || row?.evidence_date
            || '';
    }

    function rowClientName(row) {
        const payload = mapped(row);
        return row?.client_name
            || payload.client_name
            || payload.client_company_name
            || payload.merchant_company_name
            || payload.supplier_name
            || payload.supplier_company_name
            || payload.customer_name
            || payload.customer_company_name
            || payload.counterparty_name
            || '';
    }

    function rowProjectName(row) {
        const payload = mapped(row);
        return row?.project_name
            || payload.project_name
            || payload.project_code
            || '';
    }

    function inputValueKind(key, label = '') {
        const text = `${key || ''} ${label || ''}`;
        if (/amount|price|qty|quantity|balance|금액|수량|합계|공급|부가|입금|출금|잔액|봉사료|수수료/i.test(text)) {
            return 'amount';
        }
        if (/datetime|date_time|일시/i.test(text)) {
            return 'datetime';
        }
        if (/time|시간|시각/i.test(text)) {
            return 'time';
        }
        if (/date|일자|날짜/i.test(text)) {
            return 'date';
        }
        return 'text';
    }

    function valueForInput(kind, value) {
        if (kind === 'date') {
            return formatDateInputValue(value);
        }
        if (kind === 'datetime') {
            return normalizeDateTimeInputValue(value);
        }
        if (kind === 'time') {
            return normalizeTimeInputValue(value);
        }
        return value ?? '';
    }

    function valueForSave(input) {
        if (input.type === 'checkbox') {
            return input.checked ? '1' : '0';
        }
        const kind = input.dataset.valueKind || '';
        if (kind === 'amount') {
            return String(input.value ?? '').replace(/,/g, '').trim();
        }
        if (kind === 'date') {
            return formatDateInputValue(input.value);
        }
        if (kind === 'datetime') {
            return normalizeDateTimeInputValue(input.value);
        }
        if (kind === 'time') {
            return normalizeTimeInputValue(input.value);
        }
        return input.value;
    }

    function dateFromInputValue(value) {
        const normalized = formatDateInputValue(value);
        const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return null;
        const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function pad2(value) {
        return String(value ?? '').padStart(2, '0');
    }

    function normalizeTimeInputValue(value) {
        const raw = String(value ?? '').trim();
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

    function normalizeDateTimeInputValue(value) {
        const raw = String(value ?? '').trim();
        const dateText = formatDateInputValue(raw);
        if (!dateText) return '';
        const timeSource = raw.replace(/^\s*\d{4}[-/.]?\d{1,2}[-/.]?\d{1,2}/, '').trim();
        const timeText = normalizeTimeInputValue(timeSource);
        return timeText ? `${dateText} ${timeText}` : dateText;
    }

    function formatPickerDate(date) {
        if (!(date instanceof Date)) return '';
        return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
    }

    function formatPickerDateTime(date) {
        if (!(date instanceof Date)) return '';
        return `${formatPickerDate(date)} ${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
    }

    function dateTimeFromInputValue(value) {
        const date = dateFromInputValue(value);
        if (!date) return null;
        const raw = String(value ?? '').trim();
        const timeSource = raw.replace(/^\s*\d{4}[-/.]?\d{1,2}[-/.]?\d{1,2}/, '').trim();
        const time = normalizeTimeInputValue(timeSource);
        if (time) {
            const [hour, minute] = time.split(':').map((item) => Number(item));
            date.setHours(hour, minute, 0, 0);
        }
        return date;
    }

    function applyDateTimeToPicker(picker, value, keepTime = false) {
        if (!picker) return;
        const date = keepTime ? dateTimeFromInputValue(value) : dateFromInputValue(value);
        const target = date || new Date();
        picker.setDate?.(target);
        if (!keepTime) {
            picker.toggleTime?.(false);
            picker.setTime?.({ hour: null, minute: null, meridiem: null });
            return;
        }

        const hour24 = target.getHours();
        const hour12 = hour24 === 0 ? 12 : (hour24 > 12 ? hour24 - 12 : hour24);
        picker.toggleTime?.(true);
        picker.setTime?.({
            hour: hour12,
            minute: target.getMinutes(),
            meridiem: hour24 >= 12 ? 'PM' : 'AM',
        });
    }

    function importSourceLabel(value) {
        const key = String(value || '').toUpperCase();
        return {
            TAX: '홈택스',
            HOMETAX: '홈택스',
            CARD: '카드',
            CARD_COMPANY: '카드사',
            BANK: '은행',
            SHOPPING: '쇼핑몰',
            TRADE: '무역/수입',
            IMPORT: '무역/수입',
            MANUAL: '직접입력',
        }[key] || value || '-';
    }

    function importTypeLabel(value) {
        const key = String(value || '').toUpperCase();
        return {
            TAX_INVOICE: '세금계산서(홈택스)',
            CASH_RECEIPT: '현금영수증(홈택스)',
            CARD_HOMETAX: '카드(홈택스)',
            CARD_STATEMENT: '카드(카드사)',
            CARD_APPROVAL: '카드승인',
            BANK_TRANSACTION: '입출금(은행)',
            SHOPPING_ORDER: '쇼핑몰주문',
            IMPORT_INVOICE: '수입신고/인보이스',
            ETC: '기타',
        }[key] || value || '-';
    }

    function directionLabel(value) {
        const key = String(value || '').toUpperCase();
        return {
            PURCHASE: '매입',
            SALES: '매출',
            IN: '입금',
            OUT: '출금',
            BANK: '입출금',
            GENERAL: '일반',
        }[key] || value || '-';
    }

    function normalizeCodeKey(value) {
        return String(value ?? '').trim().toUpperCase();
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

    function codeDisplayName(field, value) {
        const raw = String(value ?? '').trim();
        if (raw === '') return '';
        const group = DISPLAY_CODE_FIELDS[field] || '';
        if (group === '') return raw;
        const found = (codeOptions[group] || []).find((row) => normalizeCodeKey(row.code) === normalizeCodeKey(raw));
        return found?.code_name || raw;
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

    function statusBadge(status) {
        const meta = {
            READY: ['READY', 'text-bg-success', '거래 생성 가능'],
            PROCESSED: ['PROCESSED', 'text-bg-primary', '거래 생성 완료'],
            ERROR: ['ERROR', 'text-bg-danger', '처리 오류'],
            VERIFY_ONLY: ['VERIFY_ONLY', 'text-bg-info', '원본 확인 전용'],
            NOT_READY: ['NOT_READY', 'text-bg-warning', '생성 필수값 보정 필요'],
            REVIEW_REQUIRED: ['REVIEW_REQUIRED', 'text-bg-info', '검토 필요'],
            PROCESSING: ['PROCESSING', 'text-bg-warning', '처리 중'],
            DUPLICATED: ['DUPLICATED', 'text-bg-secondary', '중복 검토'],
            UNCHANGED: ['UNCHANGED', 'text-bg-light text-dark border', '변경 없음'],
            UPDATED: ['UPDATED', 'text-bg-info', '업데이트됨'],
            DELETED: ['DELETED', 'text-bg-dark', '삭제됨'],
        }[status] || [status || '-', 'text-bg-secondary', status || '-'];
        return `<span class="badge ${meta[1]}" title="${escapeHtml(meta[2])}">${escapeHtml(meta[0])}</span>`;
    }
    function labelBadge(label) {
        return `<span class="badge text-bg-light border text-dark">${escapeHtml(label || '-')}</span>`;
    }

    function normalizedStatus(row) {
        if (row?.deleted_at) return 'DELETED';
        return String(row?.process_status || row?.status || '').trim().toUpperCase();
    }

    function readinessStatus(row) {
        return String(row?.readiness_status || normalizedStatus(row)).trim().toUpperCase();
    }

    function canonicalReadinessField(field) {
        const key = String(field || '').trim();
        return {
            counterparty_account_holder_name: 'counterparty_name',
            counterparty_account_holder: 'counterparty_name',
            account_holder: 'counterparty_name',
            counterparty_bank_name: 'counterparty_bank',
            counterparty_account_no: 'counterparty_account_number',
        }[key] || key;
    }

    function readinessMessages(row) {
        return readinessIssueItems(row).map((item) => item.message);
    }

    function readinessIssueItems(row) {
        const items = [];
        const seenFields = new Set();
        const seenMessages = new Set();
        const addIssue = (field, message) => {
            const normalizedField = canonicalReadinessField(field);
            const normalizedMessage = friendlyCorrectionMessage(row, normalizedField, message);
            if (!normalizedMessage) return;
            if (['counterparty_name', 'counterparty_account_number', 'counterparty_bank'].includes(normalizedField)
                && String(readinessValue(row, normalizedField) ?? '').trim() !== '') {
                return;
            }
            if (normalizedField && seenFields.has(normalizedField)) return;
            if (seenMessages.has(normalizedMessage)) return;
            items.push({ field: normalizedField, message: normalizedMessage });
            if (normalizedField) seenFields.add(normalizedField);
            seenMessages.add(normalizedMessage);
        };

        (Array.isArray(row?.readiness_errors) ? row.readiness_errors : []).forEach((message) => {
            const normalized = normalizeReadinessMessage(message);
            if (!normalized) return;
            addIssue(readinessFieldFromMessage(message), normalized);
        });

        derivedMissingFields(row).forEach((field) => {
            addIssue(field, missingFieldMessage(field));
        });

        return items;
    }

    function readinessCorrectionFieldSet(row) {
        return new Set(readinessIssueItems(row)
            .map((item) => item.field)
            .filter(Boolean));
    }

    function bankAccountSourceText(row = {}) {
        const payload = mapped(row);
        return String(
            payload.payment_account_name
            || payload.payment_account_number
            || payload.bank_account_name
            || payload.account_name
            || payload.bank_account
            || payload.bank_account_id
            || payload.account_number
            || ''
        ).trim();
    }

    function friendlyCorrectionMessage(row = {}, field = '', message = '') {
        const text = String(message || '').trim();
        if (field === 'bank_account_name') {
            const source = bankAccountSourceText(row);
            return source
                ? `원본 결제계좌 "${source}"를 ERP 은행계좌에서 찾지 못했습니다. 아래 ERP 계좌 필드에서 실제 은행계좌를 선택해 주세요.`
                : '원본 결제계좌를 ERP 은행계좌에서 찾지 못했습니다. 아래 ERP 계좌 필드에서 실제 은행계좌를 선택해 주세요.';
        }
        return text;
    }

    function readinessFieldFromMessage(message) {
        const text = String(message || '').trim();
        if (/결제계좌|은행계좌|ERP\s*계좌|bank[_\s-]*account|account_name|payment[_\s-]*account/i.test(text)) return 'bank_account_name';
        if (/결제계좌|은행계좌|ERP\s*계좌|bank[_\s-]*account|account_name/i.test(text)) return 'bank_account_name';
        if (/사업부|사업구분|business_unit/i.test(text)) return 'business_unit';
        if (/거래내용|전표\s*적요|적요|description|summary/i.test(text)) return 'voucher_summary_text';
        if (/품목명|품명|item_name/i.test(text)) return 'item_name';
        if (/거래일자|입출금.*일자|transaction_date/i.test(text)) return 'transaction_date';
        if (/거래유형|transaction_type/i.test(text)) return 'transaction_type';
        if (/거래방향|거래구분|transaction_direction/i.test(text)) return 'transaction_direction';
        if (/거래처|client/i.test(text)) return 'client_id';
        if (/공급가|supply/i.test(text)) return 'supply_amount';
        if (/부가세|vat/i.test(text)) return 'vat_amount';
        if (/합계|금액|total_amount/i.test(text)) return 'total_amount';
        if (/입금|deposit/i.test(text)) return 'deposit_amount';
        if (/출금|withdraw/i.test(text)) return 'withdraw_amount';
        if (/예금주|상대.*계좌|counterparty_name/i.test(text)) return 'counterparty_name';
        if (/상대계좌번호|counterparty_account_number|counterparty_account_no/i.test(text)) return 'counterparty_account_number';
        if (/상대은행|counterparty_bank/i.test(text)) return 'counterparty_bank';
        return '';
    }

    function normalizeReadinessMessage(message) {
        const text = String(message || '').trim();
        if (!text || /^missing\s*:/i.test(text)) return '';
        if (/결제계좌|은행계좌|ERP\s*계좌|bank[_\s-]*account|account_name|payment[_\s-]*account/i.test(text)) {
            return '원본 결제계좌가 ERP 은행계좌와 매칭되지 않았습니다. ERP 계좌를 선택해 주세요.';
        }
        if (/결제계좌|은행계좌|ERP\s*계좌|bank[_\s-]*account|account_name/i.test(text)) return text;
        if (/사업부|사업구분|business_unit/i.test(text)) return missingFieldMessage('business_unit');
        if (/거래내용|전표\s*적요|적요|description|summary/i.test(text)) return missingFieldMessage('voucher_summary_text');
        if (/품목명|품명|item_name/i.test(text)) return missingFieldMessage('item_name');
        if (/사업부|business_unit/i.test(text)) return missingFieldMessage('business_unit');
        if (/거래내용|적요|description/i.test(text)) return missingFieldMessage('description');
        if (/품목명|품명|item_name/i.test(text)) return missingFieldMessage('item_name');
        return text;
    }

    function missingFieldMessage(field) {
        return {
            business_unit: '사업구분이 확정되지 않았습니다.',
            description: '적요가 없습니다.',
            item_name: '품목명이 없습니다.',
            item_date: '발생일이 없습니다.',
            line_type: '라인유형이 없습니다.',
            item_spec: '규격이 없습니다.',
            unit_name: '단위가 없습니다.',
            item_qty: '수량이 없습니다.',
            item_price: '단가가 없습니다.',
            amount: '금액이 없습니다.',
            item_note: '라인 적요가 없습니다.',
            transaction_date: '거래일자가 확정되지 않았습니다.',
            transaction_type: '거래유형이 확정되지 않았습니다.',
            client_id: '거래처가 확정되지 않았습니다.',
            supply_amount: '공급가액이 확정되지 않았습니다.',
            vat_amount: '부가세가 확정되지 않았습니다.',
            total_amount: '합계금액이 확정되지 않았습니다.',
        }[field] || `${readinessFieldLabel(field)}이(가) 확정되지 않았습니다.`;
    }

    function readinessValue(row, field) {
        const payload = mapped(row);
        const normalizedField = canonicalReadinessField(field);
        const aliases = {
            client_id: ['client_id', 'client_name', 'client_company_name'],
            amount: ['amount', 'item_supply_amount', 'total_amount', 'supply_amount'],
            item_date: ['item_date', 'transaction_date'],
            item_note: ['item_note', 'description'],
            voucher_date: ['voucher_date', 'transaction_date', 'evidence_date'],
            voucher_summary_text: ['voucher_summary_text', 'voucher_description', 'description'],
            bank_account_name: ['bank_account_name', 'account_name', 'payment_account_name', 'bank_account_id', 'account_number', 'payment_account_number'],
            counterparty_name: ['counterparty_name', 'counterparty_account_holder_name', 'counterparty_account_holder', 'account_holder', 'client_company_name'],
            counterparty_account_number: ['counterparty_account_number', 'counterparty_account_no'],
            counterparty_bank: ['counterparty_bank', 'counterparty_bank_name'],
            source_key: ['source_key', 'approval_number', 'approval_no'],
        }[normalizedField] || [normalizedField, field].filter(Boolean);
        for (const key of aliases) {
            const value = payload[key] ?? row?.[key];
            if (String(value ?? '').trim() !== '') return value;
        }
        return '';
    }

    function derivedMissingFields(row) {
        const configuredCorrection = configuredCorrectionFieldSet(row);
        const hasConfiguredColumns = formatColumnsForRow(row).length > 0;
        const serverMissing = (Array.isArray(row?.missing_fields) ? row.missing_fields : [])
            .filter((field) => !hasConfiguredColumns || configuredCorrection.has(String(field || '').trim()));
        const required = readinessRequiredFieldSet(row);
        const derived = [...serverMissing];
        required.forEach((field) => {
            if (String(readinessValue(row, field) ?? '').trim() === '') {
                derived.push(field);
            }
        });
        return Array.from(new Set(derived));
    }

    function processingType(row) {
        const explicit = String(row?.processing_type || '').trim().toUpperCase();
        if (explicit) return explicit;
        const type = String(row?.import_type || row?.source_type || '').trim().toUpperCase();
        if (type === 'CARD_HOMETAX') return 'VERIFY_ONLY';
        if (['CARD_STATEMENT', 'CARD_APPROVAL'].includes(type)) return 'TRANSACTION';
        if (type === 'BANK_TRANSACTION') return 'BANK_FLOW';
        return 'TRANSACTION';
    }

    function processingLabel(row) {
        const explicit = String(row?.processing_label || '').trim();
        if (explicit) return explicit;
        return {
            TRANSACTION: '거래 생성',
            RECONCILIATION: '카드/계좌 대사',
            VERIFY_ONLY: '원본 확인',
            BANK_FLOW: '은행 흐름',
            VOUCHER: '전표 생성',
        }[processingType(row)] || '-';
    }

    function generationTarget(row) {
        const explicit = String(row?.generation_target || '').trim().toUpperCase();
        if (explicit) return explicit;
        const type = String(row?.import_type || row?.source_type || '').trim().toUpperCase();
        if (type === 'CARD_STATEMENT' || type === 'CARD_APPROVAL') return 'TRANSACTION_AND_VOUCHER';
        if (type === 'CARD_HOMETAX') return 'VERIFY_ONLY';
        if (type === 'BANK_TRANSACTION') return 'RECONCILIATION_ONLY';
        if (processingType(row) === 'TRANSACTION') return 'TRANSACTION_HEADER';
        return processingType(row);
    }

    function generationLabel(row) {
        const explicit = String(row?.generation_label || '').trim();
        if (explicit) return explicit;
        return {
            TRANSACTION_HEADER: '거래',
            TRANSACTION_FULL: '거래',
            VOUCHER_HEADER: '전표',
            VOUCHER_FULL: '전표',
            TRANSACTION_AND_VOUCHER: '거래 + 전표',
            RECONCILIATION_ONLY: '대사 전용',
            VERIFY_ONLY: '확인 전용',
            BUSINESS_DATA: '업무정보',
            UNSUPPORTED: '미지원',
        }[generationTarget(row)] || processingLabel(row);
    }

    function generationObjects(row) {
        const objects = Array.isArray(row?.generation_objects)
            ? row.generation_objects
            : (Array.isArray(row?.processing_objects) ? row.processing_objects : []);
        if (objects.length > 0) return objects;
        return {
            TRANSACTION_HEADER: ['TRANSACTION_HEADER'],
            TRANSACTION_FULL: ['TRANSACTION_HEADER', 'TRANSACTION_LINE'],
            VOUCHER_HEADER: ['VOUCHER_HEADER'],
            VOUCHER_FULL: ['VOUCHER_HEADER', 'VOUCHER_LINE'],
            TRANSACTION_AND_VOUCHER: ['TRANSACTION_HEADER', 'TRANSACTION_LINE', 'VOUCHER_HEADER', 'VOUCHER_LINE'],
            RECONCILIATION_ONLY: ['RECONCILIATION'],
            VERIFY_ONLY: ['TAX_VERIFY', 'RECONCILIATION'],
        }[generationTarget(row)] || [];
    }

    function generationObjectText(row) {
        return Array.from(new Set(generationObjects(row).map((object) => ({
            TRANSACTION_HEADER: '거래',
            TRANSACTION_LINE: '거래',
            VOUCHER_HEADER: '전표',
            VOUCHER_LINE: '전표',
            RECONCILIATION: '대사',
            TAX_VERIFY: '원본검증',
            BANK_FLOW: '은행',
        }[object] || object)))).join(' / ') || '-';
    }

    function correctionRequired(row) {
        return !['READY', 'VERIFY_ONLY'].includes(readinessStatus(row));
    }

    function transactionCreated(row) {
        const transactionId = String(row?.transaction_id || '').trim();
        const status = String(row?.transaction_status || row?.process_status || '').trim().toUpperCase();
        return transactionId !== '' || ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED'].includes(status);
    }

    function transactionCreateStatusText(row) {
        if (transactionCreated(row)) return '생성됨';
        const status = String(row?.transaction_status || '').trim().toUpperCase();
        if (status === 'PROCESSING') return '처리중';
        if (status === 'ERROR') return '오류';
        if (status === 'DUPLICATED') return '중복';
        return '대기';
    }

    function transactionStateBadge(state) {
        if (state === 'CREATED') return '<span class="badge text-bg-success">완료</span>';
        if (state === 'NOT_REQUIRED') return '<span class="badge text-bg-light text-dark border">해당없음</span>';
        return '<span class="badge text-bg-secondary">미생성</span>';
    }

    function voucherStateBadge(state) {
        if (state === 'CREATED') return '<span class="badge text-bg-success">발행</span>';
        return '<span class="badge text-bg-primary">준비</span>';
    }

    function transactionCreateState(row) {
        if (String(row?.import_type || row?.source_type || '').toUpperCase() === 'BANK_TRANSACTION') return 'NOT_REQUIRED';
        if (transactionCreated(row)) return 'CREATED';
        const status = String(row?.transaction_status || row?.process_status || '').trim().toUpperCase();
        if (status === 'NOT_REQUIRED') return 'NOT_REQUIRED';
        if (['PROCESSING', 'READY'].includes(status) && readinessStatus(row) === 'READY') return 'READY';
        if (readinessStatus(row) === 'READY' && !['ERROR', 'FAILED', 'DUPLICATED', 'DUPLICATE', 'DELETED'].includes(status)) return 'READY';
        return 'NOT_READY';
    }

    function voucherCreateState(row) {
        const status = String(row?.voucher_status || '').trim().toUpperCase();
        if (['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'].includes(status)) return 'CREATED';
        if (status === 'READY') return 'READY';
        return 'NOT_READY';
    }

    function sourceEditable(row = {}) {
        return voucherCreateState(row) !== 'CREATED';
    }

    function isBankTransactionRow(row = {}) {
        const payload = mapped(row);
        return String(row.import_type || row.seed_source_type || row.source_type || payload.import_type || '').trim().toUpperCase() === 'BANK_TRANSACTION';
    }

    function currentReadinessRow(modal = document.getElementById('seedRowReadinessModal')) {
        if (modal?.__readinessRow) return modal.__readinessRow;
        const rowId = modal?.dataset.rowId || '';
        if (!rowId) return null;
        return (evidenceTable?.rows().data().toArray() || []).find((item) => String(item.id) === String(rowId)) || null;
    }

    function voucherCreateStatusText(row) {
        const state = voucherCreateState(row);
        if (state === 'CREATED') return '전표 생성 완료';
        if (state === 'READY') return '전표 생성 가능';
        return '전표 생성 대기';
    }

    function transactionCreateStatusBadge(row) {
        const state = transactionCreateState(row);
        const reason = state === 'NOT_READY' ? readinessMessages(row).join('\n') : '';
        return `<span title="${escapeHtml(reason)}">${transactionStateBadge(state)}</span>`;
    }

    function voucherCreateStatusBadge(row) {
        const state = voucherCreateState(row);
        const raw = String(row?.voucher_status || '').trim();
        const reason = state === 'NOT_READY'
            ? (raw || (readinessStatus(row) === 'READY' ? '분개라인 미확정' : readinessMessages(row).join('\n')))
            : raw;
        return `<span title="${escapeHtml(reason)}">${voucherStateBadge(state)}</span>`;
    }

    function correctionMissingSummary(row) {
        const issueCount = correctionIssueItems(row).length;
        if (issueCount === 0) {
            return '<span class="badge text-bg-success">완료</span>';
        }
        return `<span class="badge text-bg-warning">보정필요 ${issueCount.toLocaleString('ko-KR')}건</span>`;

        const reasons = [];
        reasons.push(...evidenceRequiredMissing(row));
        if (transactionCreateState(row) === 'NOT_READY') {
            reasons.push(...readinessMessages(row));
            derivedMissingFields(row).forEach((field) => reasons.push(readinessFieldLabel(field)));
        }
        if (voucherCreateState(row) === 'NOT_READY') {
            const voucherStatus = String(row?.voucher_status || '').trim().toUpperCase();
            if (readinessStatus(row) !== 'READY') {
                reasons.push(...readinessMessages(row));
            } else if (['', 'WAITING', 'NONE'].includes(voucherStatus)) {
                reasons.push('분개라인 미확정');
            } else if (['ERROR', 'FAILED'].includes(voucherStatus)) {
                reasons.push(row?.error_message || '전표 생성 오류');
            } else {
                reasons.push(row?.voucher_status || '전표 생성 상태');
            }
        }

        const uniqueReasons = Array.from(new Set(reasons.map((item) => String(item || '').trim()).filter(Boolean)));
        if (uniqueReasons.length === 0) {
            return '<span class="badge text-bg-success">완료</span>';
        }
        const text = uniqueReasons.join(', ');
        return `<span class="seed-missing-summary-wrap" title="${escapeHtml(text)}"><span class="badge text-bg-warning">보정필요</span><span class="seed-missing-summary-text">${escapeHtml(text)}</span></span>`;
    }
    function explicitRequiredMissing(row = {}) {
        const candidates = [
            row.evidence_missing_required,
            row.required_missing_fields,
            row.required_missing_columns,
            row.missing_required_fields,
            row.missing_required_columns,
        ];

        return candidates
            .flatMap((value) => {
                if (Array.isArray(value)) return value;
                if (typeof value === 'string') return value.split(/[,\n]/);
                return [];
            })
            .map((value) => String(value || '').trim())
            .filter(Boolean);
    }

    function isBlankEvidenceValue(value) {
        if (value === null || value === undefined) return true;
        if (Array.isArray(value)) return value.length === 0;
        if (typeof value === 'object') {
            return isBlankEvidenceValue(value.value ?? value.raw_value ?? value.display_value ?? '');
        }
        return String(value).trim() === '';
    }

    function isOptionalBankBalanceField(field) {
        return canonicalReadinessField(field) === 'balance_amount';
    }

    function evidenceRequiredEntries(row = {}) {
        const raw = rawPayload(row);
        const entries = Object.entries(raw).map(([key, value]) => sourceEntry(key, value, null, row));
        const formatColumns = Array.isArray(row?.format_columns) ? row.format_columns : [];

        formatColumns.forEach((column) => {
            const systemField = String(column.system_field_name || '').trim();
            const excelName = String(column.excel_column_name || column.column_name || '').trim();
            const exists = entries.some((entry) => {
                return String(entry.systemField || '').trim() === systemField
                    || String(entry.label || '').trim() === excelName;
            });
            if (exists) return;
            entries.push(sourceEntry(excelName || systemField, {
                column_name: excelName,
                system_field_name: systemField,
                is_required: Number(column.is_required || 0),
                requirement_mode: Number(column.requirement_mode || column.is_required || 0),
                value: '',
            }, column, row));
        });

        return entries.filter((entry) => entry.required || Number(entry.requirementMode || 0) > 0);
    }

    function evidenceRequiredEntryResolvedValue(row = {}, entry = {}) {
        const field = canonicalReadinessField(entry.systemField || entry.key || '');
        const directValue = readinessValue(row, field);
        if (String(directValue ?? '').trim() !== '') return directValue;
        return entry.value;
    }

    function evidenceRequiredMissing(row = {}) {
        return Array.from(new Set(evidenceRequiredIssueItems(row).map((item) => item.message).filter(Boolean)));
    }

    function evidenceRequiredIssueItems(row = {}) {
        const explicit = explicitRequiredMissing(row);
        if (explicit.length > 0) {
            const configuredCorrection = configuredCorrectionFieldSet(row);
            const hasConfiguredColumns = formatColumnsForRow(row).length > 0;
            return explicit
                .map((message) => {
                    const field = readinessFieldFromMessage(message) || fallbackSystemFieldForLabel(message);
                    return { field, message };
                })
                .filter((item) => !isOptionalBankBalanceField(item.field || item.message))
                .filter((item) => !hasConfiguredColumns || item.field === '' || configuredCorrection.has(item.field));
        }

        const entries = evidenceRequiredEntries(row);
        if (entries.length === 0) {
            return readinessIssueItems(row);
        }
        return entries
            .filter((entry) => !isOptionalBankBalanceField(entry.systemField || entry.key || ''))
            .filter((entry) => isBlankEvidenceValue(evidenceRequiredEntryResolvedValue(row, entry)))
            .map((entry) => {
                const field = canonicalReadinessField(entry.systemField || entry.key || '');
                return {
                    field,
                    message: entry.label || readinessFieldLabel(field),
                };
            });
    }

    function correctionIssueItems(row = {}) {
        const items = [];
        const seenFields = new Set();
        const seenMessages = new Set();
        const addIssue = (field, message) => {
            const normalizedField = canonicalReadinessField(field);
            const normalizedMessage = friendlyCorrectionMessage(row, normalizedField, message);
            if (!normalizedMessage) return;
            if (normalizedField && String(readinessValue(row, normalizedField) ?? '').trim() !== '') return;
            if (normalizedField && seenFields.has(normalizedField)) return;
            if (seenMessages.has(normalizedMessage)) return;
            items.push({ field: normalizedField, message: normalizedMessage });
            if (normalizedField) seenFields.add(normalizedField);
            seenMessages.add(normalizedMessage);
        };

        evidenceRequiredIssueItems(row).forEach((item) => addIssue(item.field, item.message));
        readinessIssueItems(row).forEach((item) => addIssue(item.field, item.message));

        if (voucherCreateState(row) === 'NOT_READY') {
            const voucherStatus = String(row?.voucher_status || '').trim().toUpperCase();
            if (readinessStatus(row) === 'READY' && ['', 'WAITING', 'NONE'].includes(voucherStatus)) {
                addIssue('line_no', '분개라인 미확정');
            } else if (['ERROR', 'FAILED'].includes(voucherStatus)) {
                const message = row?.error_message || '전표 생성 오류';
                addIssue(readinessFieldFromMessage(message), message);
            } else if (readinessStatus(row) === 'READY') {
                addIssue('', row?.voucher_status || '전표 생성 상태 확인 필요');
            }
        }

        return items;
    }

    function evidenceStatusBadge(row = {}) {
        const statusIssues = correctionIssueItems(row).map((item) => item.message);
        if (statusIssues.length === 0) {
            return '<span class="badge text-bg-success">완료</span>';
        }
        const statusTitle = statusIssues.join(', ');
        return `<span class="badge text-bg-secondary" title="${escapeHtml(statusTitle)}">미완료</span>`;

        const missing = correctionIssueItems(row).map((item) => item.message);
        if (missing.length === 0) {
            return '<span class="badge text-bg-success">완료</span>';
        }

        const title = missing.join(', ');
        return `<span class="badge text-bg-secondary" title="${escapeHtml(title)}">미완료</span>`;
    }

    function manageButton(row = {}) {
        return `<button type="button" class="btn btn-outline-primary btn-sm seed-row-edit-btn" data-id="${escapeHtml(row.id || '')}">수정</button>`;
    }
    function correctionIssueLinksHtml(row = {}) {
        return correctionIssueItems(row).map((item) => `
            <li>
                <button type="button"
                        class="readiness-correction-link"
                        data-correction-field="${escapeHtml(item.field || '')}">
                    ${escapeHtml(item.message)}
                </button>
            </li>
        `).join('');
    }
    function selectedReadyIds() {
        const rows = evidenceTable?.rows().data().toArray() || [];
        return Array.from(selectedIds).filter((id) => {
            const row = rows.find((item) => String(item.id) === String(id));
            if (!row || normalizedStatus(row) === 'DELETED') return false;
            if (readinessStatus(row) !== 'READY') return false;
            if (correctionIssueItems(row).length > 0) return false;
            return transactionCreateState(row) === 'READY' || voucherCreateState(row) === 'READY';
        });
    }

    function isSelectableForBulk(row) {
        return normalizedStatus(row) !== 'DELETED';
    }

    function updateButtons() {
        const createCount = selectedReadyIds().length;
        const wrapper = evidenceTable?.table().container();

        wrapper?.querySelector('.btn-create-selected-evidences')?.toggleAttribute('disabled', isCreating || createCount === 0);
    }

    function updateSummary(rows = []) {
        const transactionRows = rows.filter((row) => processingType(row) === 'TRANSACTION');
        const reconciliationRows = rows.filter((row) => ['RECONCILIATION', 'VERIFY_ONLY'].includes(processingType(row)));
        const bankFlowRows = rows.filter((row) => processingType(row) === 'BANK_FLOW');
        const summary = {
            total: rows.length,
            transactionPending: transactionRows.filter((row) => !transactionCreated(row)).length,
            transactionCreated: transactionRows.filter((row) => transactionCreated(row)).length,
            voucherReview: transactionRows.filter((row) => transactionCreated(row)).length,
            reconciliationPending: reconciliationRows.filter((row) => normalizedStatus(row) === 'READY').length,
            bankFlowPending: bankFlowRows.filter((row) => normalizedStatus(row) === 'READY').length,
            errors: rows.filter((row) => normalizedStatus(row) === 'ERROR' || row.error_message).length,
            duplicates: rows.filter((row) => normalizedStatus(row) === 'DUPLICATED').length,
        };

        Object.entries(summary).forEach(([key, value]) => {
            const el = document.querySelector(`[data-seed-summary="${key}"]`);
            if (!el) return;
            const label = el.dataset.seedSummaryLabel || '';
            const countText = `${value.toLocaleString('ko-KR')}건`;
            el.textContent = label ? `${label} ${countText}` : countText;
        });

        renderTypeSummary(rows);
    }

    function rowTypeKey(row) {
        return String(row?.import_type || row?.source_type || row?.seed_source_type || '').trim().toUpperCase();
    }

    function rowTypeLabel(row, type = rowTypeKey(row)) {
        return row?.import_type_name || importTypeLabel(type || row?.seed_source_type || row?.source_type);
    }

    function renderTypeSummary(rows = []) {
        const container = document.getElementById('seedRowsTypeSummary');
        if (!container) return;

        const typeMap = new Map();
        rows.forEach((row) => {
            const type = rowTypeKey(row) || 'UNKNOWN';
            const current = typeMap.get(type) || { type, label: rowTypeLabel(row, type), count: 0 };
            current.count += 1;
            typeMap.set(type, current);
        });

        const items = Array.from(typeMap.values())
            .sort((a, b) => a.label.localeCompare(b.label, 'ko-KR'));

        const allActive = selectedTypeFilter === '' ? 'active' : '';
        const chips = [
            `<button type="button" class="btn btn-sm btn-outline-secondary ${allActive}" data-seed-type-filter="">전체 ${rows.length.toLocaleString('ko-KR')}건</button>`,
            ...items.map((item) => {
                const active = selectedTypeFilter === item.type ? 'active' : '';
                return `<button type="button" class="btn btn-sm btn-outline-secondary ${active}" data-seed-type-filter="${escapeHtml(item.type)}">${escapeHtml(item.label)} ${item.count.toLocaleString('ko-KR')}건</button>`;
            }),
        ];

        container.innerHTML = `<div class="d-flex flex-wrap gap-2">${chips.join('')}</div>`;
    }

    function registerTypeFilterSearch() {
        if (seedRowsTypeSearchRegistered || !window.jQuery?.fn?.dataTable?.ext?.search) {
            return;
        }

        seedRowsTypeSearchRegistered = true;
        window.jQuery.fn.dataTable.ext.search.push((settings, _data, _dataIndex, rowData) => {
            if (!selectedTypeFilter) {
                return true;
            }
            const tableNode = evidenceTable?.table?.().node?.();
            if (settings.nTable !== tableNode) {
                return true;
            }
            return rowTypeKey(rowData) === selectedTypeFilter;
        });
    }

    function applyTypeFilter(type) {
        selectedTypeFilter = String(type || '').trim().toUpperCase();
        selectedIds.clear();
        evidenceTable?.clearSelectedIds?.();
        evidenceTable?.draw(false);
        updateButtons();
        updateSummary(evidenceTable?.rows().data().toArray() || []);
    }

    function reloadRows() {
        selectedIds.clear();
        evidenceTable?.clearSelectedIds?.();
        evidenceTable?.ajax.reload(() => {
            updateButtons();
            updateSummary(evidenceTable?.rows().data().toArray() || []);
        }, false);
    }

    async function postSelected(url, extraPayload = {}) {
        const ids = Array.from(selectedIds);
        if (ids.length === 0) {
            notify('warning', '증빙 데이터를 선택하세요.');
            return null;
        }

        return fetchJson(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seed_row_ids: ids, evidence_ids: ids, ...extraPayload }),
        });
    }

    function ensureReadinessModal() {
        let modal = document.getElementById('seedRowReadinessModal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'seedRowReadinessModal';
        modal.tabIndex = -1;
        modal.innerHTML = `
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">거래/전표 생성 Workspace</h5>
                            <div class="small text-muted" id="seedRowReadinessSubtitle"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                    </div>
                    <div class="modal-body">
                        <div class="readiness-modal-summary mb-3" id="seedRowReadinessSummary"></div>
                        <div id="seedRowReadinessAlerts" class="mb-3"></div>
                        <div class="readiness-workbench" id="seedRowReadinessWorkbench">
                            <div class="readiness-main-pane">
                                <ul class="nav nav-tabs readiness-stage-tabs" id="seedRowReadinessTabs" role="tablist"></ul>
                                <form id="seedRowReadinessForm" class="readiness-stage-content"></form>
                            </div>
                            <aside class="readiness-source-pane" id="seedRowSourcePane">
                                <div class="readiness-source-head">
                                    <div>
                                        <strong>증빙 원본</strong>
                                        <div class="small text-muted" id="seedRowSourceSubtitle">업로드 원본 데이터</div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="seedRowSourceToggleBtn">접기</button>
                                </div>
                                <div class="readiness-source-fields" id="seedRowSourceFields"></div>
                            </aside>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2 d-none" id="seedRowSourceShowBtn">원본 데이터 다시 열기</button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success btn-sm d-none" id="seedRowReadinessCreateTransactionBtn" data-readiness-create="transaction">거래생성</button>
                        <button type="button" class="btn btn-success btn-sm d-none" id="seedRowReadinessCreateVoucherBtn" data-readiness-create="voucher">전표생성</button>
                        <button type="button" class="btn btn-primary btn-sm" id="seedRowReadinessSaveBtn">저장</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
    }

    function legacyReadinessFieldLabel(key) {
        return readinessFieldLabel(key);
    }

    const BANK_VOUCHER_LINE_FIELDS = new Set([
        'header_row_no',
        'line_no',
        'line_row_type',
        'account_id',
        'debit',
        'credit',
        'line_summary',
        'line_ref_type',
        'line_ref_id',
    ]);

    function legacyReadinessEditFields(row) {
        const missing = Array.isArray(row?.missing_fields) ? row.missing_fields : [];
        const base = [
            'transaction_date',
            'transaction_direction',
            'transaction_type',
            'business_unit',
            'client_id',
            'client_company_name',
            'client_business_number',
            'description',
            'total_amount',
            'supply_amount',
            'vat_amount',
            'item_name',
            'item_date',
            'project_id',
            'account_id',
        ];
        if (String(row?.import_type || row?.source_type || '').toUpperCase() === 'BANK_TRANSACTION') {
            base.push('bank_account_name', 'counterparty_name', 'counterparty_account_number', 'counterparty_bank', 'deposit_amount', 'withdraw_amount', 'balance_amount');
        }
        return Array.from(new Set([...missing, ...base]));
    }

    function readinessFieldLabel(key) {
        return {
            client_name: '거래처명',
            project_name: '프로젝트명',
            employee_id: '직원',
            employee_name: '직원명',
            bank_account_name: '계좌명',
            card_id: '카드',
            card_name: '카드명',
            source_type: '자료출처',
            import_type: '자료유형',
            generation_target: '생성대상',
            source_key: '원본 식별키',
            evidence_date: '증빙일자',
            transaction_date: '거래일자',
            transaction_direction: '거래구분',
            transaction_type: '거래유형',
            business_unit: '사업구분',
            client_id: '거래처',
            project_id: '프로젝트',
            description: '적요',
            currency: '통화',
            exchange_rate: '환율',
            supply_amount: '공급가액',
            vat_amount: '부가세',
            total_amount: '합계금액',
            line_type: '라인유형',
            item_date: '발생일',
            item_name: '품목명',
            item_spec: '규격',
            specification: '규격',
            unit_name: '단위',
            item_qty: '수량',
            quantity: '수량',
            item_price: '단가',
            unit_price: '단가',
            amount: '금액',
            item_supply_amount: '금액',
            item_note: '적요',
            voucher_date: '전표일자',
            voucher_summary_text: '전표 적요',
            voucher_description: '전표 적요',
            voucher_line_type: '차대구분',
            voucher_account_id: '계정과목',
            debit_amount: '차변금액',
            credit_amount: '대변금액',
            line_summary: '라인 적요',
            note: '비고',
            memo: '메모',
            account_id: '계정과목',
            bank_account_id: 'ERP 계좌',
            counterparty_name: '상대계좌 예금주',
            counterparty_account_number: '상대계좌 번호',
            counterparty_bank: '상대은행',
            deposit_amount: '입금액',
            withdraw_amount: '출금액',
            balance_amount: '잔액',
            header_row_no: '헤더번호',
            line_no: '라인번호',
            line_row_type: '행유형',
            debit: '차변',
            credit: '대변',
            line_ref_type: '보조계정유형',
            line_ref_id: '보조계정',
        }[key] || key;
    }

    function readinessFieldConfig(key) {
        return {
            employee_id: { kind: 'employee', placeholder: '직원 검색' },
            bank_account_id: { kind: 'bankAccount', placeholder: '계좌 검색' },
            bank_account_name: { kind: 'bankAccount', placeholder: '계좌명 검색' },
            card_id: { kind: 'card', placeholder: '카드 검색' },
            business_unit: { kind: 'code', codeGroup: 'BUSINESS_UNIT', emptyLabel: '사업구분 선택' },
            transaction_type: { kind: 'code', codeGroup: 'TRANSACTION_TYPE', emptyLabel: '거래유형 선택' },
            transaction_direction: { kind: 'code', codeGroup: 'TRANSACTION_DIRECTION', emptyLabel: '거래구분 선택' },
            currency: { kind: 'code', codeGroup: 'CURRENCY', emptyLabel: '선택(없음)' },
            line_type: { kind: 'code', codeGroup: 'TRANSACTION_LINE_TYPE', emptyLabel: '라인유형 선택' },
            unit_name: { kind: 'code', codeGroup: 'UNIT', emptyLabel: '선택(없음)', searchable: true },
            voucher_line_type: { kind: 'code', codeGroup: 'VOUCHER_LINE_TYPE', emptyLabel: '차대구분 선택' },
            source_type: { kind: 'code', codeGroup: 'SOURCE_TYPE', emptyLabel: '자료출처 선택' },
            import_type: { kind: 'code', codeGroup: 'IMPORT_TYPE', emptyLabel: '자료유형 선택' },
            client_id: { kind: 'client', placeholder: '거래처 검색' },
            project_id: { kind: 'project', placeholder: '프로젝트 검색' },
            description: { kind: 'text', placeholder: '거래 내용을 입력하세요' },
            voucher_summary_text: { kind: 'text', placeholder: '전표 적요를 입력하세요' },
            voucher_description: { kind: 'text', placeholder: '전표 적요를 입력하세요' },
            line_summary: { kind: 'text', placeholder: '분개라인 적요를 입력하세요' },
            note: { kind: 'text', placeholder: '비고를 입력하세요' },
            memo: { kind: 'textarea', placeholder: '메모를 입력하세요' },
        }[key] || {};
    }

    function readinessStageDefinitions(row = {}) {
        const payload = mapped(row);
        const type = String(row.import_type || row.seed_source_type || row.source_type || payload.import_type || '').trim().toUpperCase();
        const missing = Array.isArray(row?.missing_fields) ? row.missing_fields : [];
        const isBankTransaction = type === 'BANK_TRANSACTION';
        const transactionState = transactionCreateState(row);
        const voucherState = voucherCreateState(row);
        const needsTransaction = transactionState !== 'CREATED';
        const needsVoucher = voucherState !== 'CREATED';
        const needsVoucherLines = needsVoucher;
        const needsReconciliation = readinessStatus(row) !== 'READY' || missing.length > 0;

        const transactionHeader = [
            'transaction_date',
            'transaction_direction',
            'transaction_type',
            'business_unit',
            'client_id',
            'project_id',
            'employee_id',
            'description',
            'currency',
            'exchange_rate',
            'supply_amount',
            'vat_amount',
            'total_amount',
            'note',
            'memo',
        ];
        const transactionLines = [
            'line_type',
            'item_date',
            'item_name',
            'item_spec',
            'unit_name',
            'item_qty',
            'item_price',
            'amount',
            'item_note',
        ];
        const voucherHeader = [
            'voucher_date',
            'voucher_summary_text',
            'note',
            'memo',
        ];
        const voucherLines = [
            'voucher_line_type',
            'account_id',
            'debit_amount',
            'credit_amount',
            'line_summary',
            'client_id',
            'project_id',
        ];
        const reconciliationFields = type === 'BANK_TRANSACTION'
            ? [
                'bank_account_name',
                'counterparty_name',
                'counterparty_account_number',
                'counterparty_bank',
            ]
            : ['source_key', 'evidence_date', 'total_amount', 'description'];

        if (type === 'BANK_TRANSACTION') {
            reconciliationFields.push('deposit_amount', 'withdraw_amount', 'balance_amount');
        }

        const transactionHeaderRequired = [
            'transaction_date',
            'business_unit',
            'transaction_type',
            'client_id',
            'supply_amount',
            'vat_amount',
            'total_amount',
        ];
        const voucherRequired = ['voucher_date', 'voucher_summary_text'];
        const reconciliationRequired = type === 'BANK_TRANSACTION'
            ? ['bank_account_name', 'counterparty_name', 'deposit_amount', 'withdraw_amount']
            : ['source_key', 'evidence_date'];

        const includeMissing = (fields) => Array.from(new Set([...missing.filter((key) => fields.includes(key)), ...fields]));
        if (isBankTransaction) {
            return [{
                id: 'bank-workspace',
                label: '생성 작업',
                description: '은행 거래 증빙을 전표 입력에 필요한 형태로 보정하고, 계정과목 정책에 따라 보조계정을 선택합니다.',
                enabled: true,
                fields: [],
                requiredFields: Array.from(new Set([...voucherRequired, 'bank_account_name'])),
                workspace: 'bank-voucher',
            }];
        }

        const stages = [
            {
                id: 'transaction',
                label: '거래',
                description: '거래 생성에 필요한 거래처, 금액, 거래유형 정보를 확인합니다.',
                enabled: true,
                fields: includeMissing(transactionHeader),
                requiredFields: transactionHeaderRequired,
                workspace: 'transaction-entry',
            },
            {
                id: 'voucher',
                label: '전표',
                description: '전표 입력에 필요한 전표 헤더와 분개라인 정보를 확인합니다.',
                enabled: true,
                fields: includeMissing(isBankTransaction ? voucherHeader : (needsVoucherLines ? [...voucherHeader, ...voucherLines] : voucherHeader)),
                requiredFields: voucherRequired,
                workspace: 'voucher-entry',
                extraHtml: isBankTransaction ? bankVoucherLineTableHtml(mapped(row)) : '',
                note: '전표일자와 적요를 확인합니다. 차변/대변, 계정과목, 보조계정은 분개라인에서 직접 조정할 수 있습니다.',
            },
            {
                id: 'reconciliation',
                label: '정합성',
                description: '정산과 원천 데이터 검증에 필요한 값을 확인합니다.',
                enabled: false,
                fields: includeMissing(reconciliationFields),
                requiredFields: reconciliationRequired,
            },
        ];

        const available = stages.filter((stage) => stage.enabled);
        return available.length > 0 ? available : [{
            id: 'verify-only',
            label: '원본 확인',
            description: '거래와 전표 생성 전 원본 데이터의 주요 값을 확인합니다.',
            enabled: true,
            fields: includeMissing(['source_key', 'evidence_date', 'total_amount', 'description']),
        }];
    }

    function readinessEditFields(row) {
        return readinessStageDefinitions(row).flatMap((stage) => stage.fields);
    }

    function configuredRequiredFieldKeys(row = {}, format = null) {
        return formatColumnsForRow(row, format)
            .map((column) => ({
                key: String(column?.system_field_name || column?.system_field || '').trim(),
                required: Number(column?.is_required || column?.requirement_mode || 0) === 1,
            }))
            .filter((item) => item.key !== '' && item.required)
            .map((item) => item.key);
    }

    function configuredCorrectionFieldSet(row = {}, format = null) {
        return new Set(formatColumnsForRow(row, format)
            .map((column) => ({
                key: String(column?.system_field_name || column?.system_field || '').trim(),
                mode: Number(column?.is_required || column?.requirement_mode || 0),
            }))
            .filter((item) => item.key !== '' && item.mode > 0)
            .map((item) => item.key));
    }

    function readinessRequiredFieldSet(row, format = null) {
        if (formatColumnsForRow(row, format).length > 0) {
            return new Set(configuredRequiredFieldKeys(row, format));
        }

        return new Set([
            ...readinessStageDefinitions(row).flatMap((stage) => stage.requiredFields || []),
            ...configuredRequiredFieldKeys(row, format),
        ]);
    }

    function formatColumnsForRow(row = {}, format = null) {
        if (Array.isArray(format?.columns)) return format.columns;
        if (Array.isArray(row?.format_columns)) return row.format_columns;
        return [];
    }

    function readinessFieldAliases(key) {
        return {
            client_id: ['client_id', 'client_name', 'client_company_name'],
            project_id: ['project_id', 'project_name'],
            employee_id: ['employee_id', 'employee_name'],
            bank_account_name: ['bank_account_name', 'bank_account_id', 'account_name'],
            bank_account_id: ['bank_account_id', 'bank_account_name', 'account_name'],
            card_id: ['card_id', 'card_name', 'card_number'],
        }[key] || [key];
    }

    function readinessFallbackGroup(key) {
        if (['business_unit', 'transaction_type', 'transaction_direction'].includes(key)) return '기준정보';
        if (['client_id', 'project_id', 'employee_id', 'bank_account_name', 'bank_account_id', 'card_id'].includes(key)) return '기초정보';
        return '';
    }

    function readinessGroupTone(group = '') {
        const normalized = String(group || '').replace(/\s+/g, '').trim();
        if (normalized.includes('기준정보')) return 'standard';
        if (normalized.includes('기초정보')) return 'basic';
        return '';
    }

    function formatColumnForReadinessField(row = {}, format = null, key = '') {
        const aliases = readinessFieldAliases(key).map((item) => String(item || '').trim()).filter(Boolean);
        if (aliases.length === 0) return null;
        return formatColumnsForRow(row, format).find((column) => {
            const field = String(column?.system_field_name || column?.system_field || '').trim();
            return aliases.includes(field);
        }) || null;
    }

    function readinessFieldMeta(row = {}, format = null, key = '') {
        const column = formatColumnForReadinessField(row, format, key);
        const group = String(column?.system_field_group || '').trim() || readinessFallbackGroup(key);
        const requirementMode = Number(column?.is_required || column?.requirement_mode || 0);
        return {
            column,
            configured: !!column,
            requirementMode,
            group,
            label: String(column?.excel_column_name || column?.column_name || column?.system_field_label || '').trim(),
            tone: readinessGroupTone(group),
        };
    }

    function configuredReadinessFields(row = {}, format = null, fields = []) {
        if (!format && !Array.isArray(row?.format_columns)) return fields;
        return fields.filter((key) => readinessFieldMeta(row, format, key).configured);
    }

    function readinessFieldHtml(row, payload, key, format = null) {
        const displayValue = readinessValue(row, key);
        const value = key === 'client_id' ? (payload.client_id ?? '') : displayValue;
        const meta = readinessFieldMeta(row, format, key);
        const requirementMode = meta.requirementMode || (readinessRequiredFieldSet(row, format).has(key) ? 1 : 0);
        const displayLabel = key === 'description' ? readinessFieldLabel(key) : (meta.label || readinessFieldLabel(key));
        const requirementStar = requirementMode === 1
            ? '<span class="readiness-required-star">*</span>'
            : (requirementMode === 2 ? '<span class="readiness-optional-star">*</span>' : '');
        const fieldToneClass = meta.tone ? ` readiness-field-${meta.tone}` : '';
        const correctionIssue = correctionIssueItems(row).find((item) => item.field === key)
            || readinessIssueItems(row).find((item) => item.field === key)
            || evidenceRequiredIssueItems(row).find((item) => item.field === key);
        const correctionClass = correctionIssue ? ' readiness-field-correction' : '';
        const correctionTitle = correctionIssue ? ` title="${escapeHtml(correctionIssue.message)}"` : '';
        const config = readinessFieldConfig(key);
        const kind = inputValueKind(key, displayLabel);
        const commonAttrs = `class="form-control form-control-sm ${kind === 'amount' ? 'number-input' : ''}" data-readiness-key="${escapeHtml(key)}" data-value-kind="${escapeHtml(kind)}"`;
        const pickerPlaceholder = requirementMode === 1 ? `${displayLabel}필수` : (config.placeholder || '');
        let control = '';
        if (config.kind === 'code') {
            const emptyLabel = requirementMode === 1 ? `${displayLabel}필수` : (config.emptyLabel || `${displayLabel}선택`);
            control = `
                    <select class="form-select form-select-sm" data-readiness-key="${escapeHtml(key)}" data-code-group="${escapeHtml(config.codeGroup)}" data-empty-label="${escapeHtml(emptyLabel)}" ${config.searchable ? 'data-code-searchable="true"' : ''}>
                    <option value="${escapeHtml(value)}" selected>${escapeHtml(value || '')}</option>
                </select>
            `;
        } else if (config.kind === 'client') {
            const clientId = String(payload.client_id || '').trim();
            const externalName = String(payload.client_company_name || payload.merchant_company_name || payload.client_name || row.client_name || '').trim();
            if (clientId === '' && externalName !== '') {
                control = `
                    <div class="readiness-client-auto">
                        <div class="readiness-client-auto-main">
                            <span class="badge text-bg-info">자동 거래처 후보</span>
                            <strong>${escapeHtml(externalName)}</strong>
                        </div>
                        <div class="small text-muted">거래처가 확정되지 않았습니다. 후보명을 확인하고 실제 거래처를 선택하세요.</div>
                        <select class="form-select form-select-sm mt-2" data-readiness-key="${escapeHtml(key)}" data-readiness-picker="client" data-placeholder="${escapeHtml(pickerPlaceholder || '거래처 선택')}">
                            <option value="">직접 선택</option>
                        </select>
                    </div>
                `;
            } else {
                const optionText = clientId !== '' ? (row.client_name || payload.client_name || payload.client_company_name || clientId) : '';
                control = `
                    <select class="form-select form-select-sm" data-readiness-key="${escapeHtml(key)}" data-readiness-picker="client" data-placeholder="${escapeHtml(pickerPlaceholder)}">
                        <option value="${escapeHtml(clientId)}" selected>${escapeHtml(optionText || '')}</option>
                    </select>
                `;
            }
        } else if (config.kind === 'project') {
            const optionText = payload.project_name || row.project_name || value;
            control = `
                <select class="form-select form-select-sm" data-readiness-key="${escapeHtml(key)}" data-readiness-picker="${escapeHtml(config.kind)}" data-placeholder="${escapeHtml(pickerPlaceholder)}">
                    <option value="${escapeHtml(value)}" selected>${escapeHtml(optionText || '')}</option>
                </select>
            `;
        } else if (['employee', 'bankAccount', 'card'].includes(config.kind)) {
            const nameKey = {
                employee: 'employee_name',
                bankAccount: 'bank_account_name',
                card: 'card_name',
            }[config.kind];
            const optionText = payload[nameKey] || row[nameKey] || value;
            control = `
                <select class="form-select form-select-sm" data-readiness-key="${escapeHtml(key)}" data-readiness-picker="${escapeHtml(config.kind)}" data-placeholder="${escapeHtml(pickerPlaceholder)}">
                    <option value="${escapeHtml(value)}" selected>${escapeHtml(optionText || '')}</option>
                </select>
            `;
        } else if (config.kind === 'textarea') {
            control = `<textarea ${commonAttrs} rows="2" placeholder="${escapeHtml(config.placeholder || '')}">${escapeHtml(value)}</textarea>`;
        } else if (kind === 'date' || kind === 'datetime') {
            control = `
                <div class="readiness-date-control">
                    <input type="text" ${commonAttrs} value="${escapeHtml(valueForInput(kind, value))}" placeholder="${kind === 'datetime' ? 'yyyy-mm-dd hh:mm' : 'yyyy-mm-dd'}" autocomplete="off" inputmode="numeric">
                    <button type="button" class="btn btn-outline-secondary btn-sm readiness-date-picker-btn" data-date-picker-target="${escapeHtml(key)}" title="날짜 선택">
                        <i class="bi bi-calendar3"></i>
                    </button>
                </div>
            `;
        } else if (kind === 'time') {
            control = `
                <div class="readiness-date-control">
                    <input type="text" ${commonAttrs} value="${escapeHtml(valueForInput(kind, value))}" placeholder="hh:mm" autocomplete="off" inputmode="numeric">
                    <button type="button" class="btn btn-outline-secondary btn-sm readiness-time-picker-btn" data-time-picker-target="${escapeHtml(key)}" title="시간 선택">
                        <i class="bi bi-clock"></i>
                    </button>
                </div>
            `;
        } else if (key === 'voucher_summary_text') {
            control = `
                <div class="summary-autocomplete-wrap readiness-summary-autocomplete-wrap">
                    <input type="text"
                           ${commonAttrs}
                           value="${escapeHtml(valueForInput(kind, value))}"
                           placeholder="${escapeHtml(config.placeholder || '')}"
                           autocomplete="off"
                           data-readiness-summary-autocomplete="1">
                    <div class="summary-autocomplete-list d-none" role="listbox"></div>
                </div>
            `;
        } else {
            control = `<input type="text" ${commonAttrs} value="${escapeHtml(valueForInput(kind, value))}" placeholder="${escapeHtml(config.placeholder || '')}" ${kind === 'amount' ? 'inputmode="decimal"' : ''}>`;
        }
        return `
            <label class="readiness-field${fieldToneClass}${correctionClass}" data-readiness-system-field="${escapeHtml(key)}"${correctionTitle}>
                <span class="form-label small mb-1">${escapeHtml(displayLabel)}${requirementStar}</span>
                ${control}
                ${correctionIssue ? `<div class="readiness-field-correction-message">${escapeHtml(correctionIssue.message)}</div>` : ''}
            </label>
        `;
    }

    const BANK_VOUCHER_REF_TYPES = [
        ['CLIENT', '거래처'],
        ['ACCOUNT', '계좌'],
        ['PROJECT', '프로젝트'],
        ['CARD', '카드'],
        ['EMPLOYEE', '직원'],
    ];

    function normalizeVoucherLineRowType(value) {
        const raw = String(value ?? '').trim();
        const upper = raw.toUpperCase();
        if (raw === '보조' || ['AUX', 'AUXILIARY', 'REF', 'REFERENCE'].includes(upper)) return 'AUX';
        return 'JOURNAL';
    }

    function normalizeVoucherSourceLineNo(value, fallback) {
        const parsed = Number.parseInt(String(value ?? '').replace(/[^\d-]/g, ''), 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
    }

    function voucherRefFromLine(line = {}) {
        const type = String(line.line_ref_type ?? '').trim();
        const id = String(line.line_ref_id ?? '').trim();
        const label = String(line.line_ref_label ?? line.ref_label ?? '').trim();
        if (type === '' && id === '') return null;
        return { line_ref_type: type, line_ref_id: id, line_ref_label: label };
    }

    function jsonArrayValue(value) {
        if (Array.isArray(value)) return value;
        if (typeof value !== 'string' || value.trim() === '') return [];
        try {
            const decoded = JSON.parse(value);
            return Array.isArray(decoded) ? decoded : [];
        } catch (error) {
            return [];
        }
    }

    function businessRefCandidates(payload = {}) {
        const candidates = [
            {
                type: 'CLIENT',
                id: payload.client_id || '',
                label: payload.client_name || payload.client_company_name || payload.counterparty_name || payload.merchant_company_name || payload.supplier_company_name || '',
            },
            {
                type: 'PROJECT',
                id: payload.project_id || '',
                label: payload.project_name || payload.project_code || '',
            },
            {
                type: 'EMPLOYEE',
                id: payload.employee_id || '',
                label: payload.employee_name || payload.user_name || '',
            },
            {
                type: 'ACCOUNT',
                id: payload.bank_account_id || '',
                label: payload.bank_account_name || payload.account_name || payload.account_number || payload.payment_account_number || '',
            },
            {
                type: 'CARD',
                id: payload.card_id || '',
                label: payload.card_name || payload.card_number || payload.card_company_name || '',
            },
        ];
        return candidates
            .map((ref) => ({
                line_ref_type: ref.type,
                line_ref_id: String(ref.id || ref.label || '').trim(),
                line_ref_label: String(ref.label || ref.id || '').trim(),
                auto: true,
            }))
            .filter((ref) => ref.line_ref_id !== '' || ref.line_ref_label !== '');
    }

    function recommendationRefsForRole(payload = {}, role = 'business') {
        const refs = businessRefCandidates(payload);
        if (role === 'payment') {
            return refs.filter((ref) => ref.line_ref_type === 'ACCOUNT' || ref.line_ref_type === 'CARD');
        }
        return refs.filter((ref) => ['CLIENT', 'PROJECT', 'EMPLOYEE'].includes(ref.line_ref_type));
    }

    function voucherRefTypeAliases(type) {
        const normalized = String(type || '').trim().toUpperCase();
        return {
            CLIENT: ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'],
            CUSTOMER: ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'],
            VENDOR: ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'],
            COUNTERPARTY: ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'],
            ACCOUNT: ['ACCOUNT', 'BANK', 'BANK_ACCOUNT'],
            BANK: ['ACCOUNT', 'BANK', 'BANK_ACCOUNT'],
            BANK_ACCOUNT: ['ACCOUNT', 'BANK', 'BANK_ACCOUNT'],
            CARD: ['CARD'],
            PROJECT: ['PROJECT'],
            EMPLOYEE: ['EMPLOYEE'],
        }[normalized] || [normalized];
    }

    function voucherLineGroups(payload = {}) {
        const rawLines = Array.isArray(payload?._voucher_lines) ? payload._voucher_lines : [];
        const groups = [];
        const groupBySourceNo = new Map();
        const pendingRefs = new Map();
        let fallbackNo = 1;

        rawLines.forEach((line) => {
            if (!line || typeof line !== 'object' || Array.isArray(line)) return;
            const rowType = normalizeVoucherLineRowType(line.line_row_type);
            const sourceLineNo = normalizeVoucherSourceLineNo(line.line_no, fallbackNo++);
            if (rowType === 'AUX') {
                const ref = voucherRefFromLine(line);
                if (ref) {
                    const key = String(sourceLineNo);
                    const refs = pendingRefs.get(key) || [];
                    refs.push(ref);
                    pendingRefs.set(key, refs);
                }
                return;
            }

            const group = {
                sourceLineNo,
                headerRowNo: String(line.header_row_no ?? '').trim(),
                account_id: String(line.account_id ?? '').trim(),
                account_text: String(line.account_text ?? line.account_label ?? line.account_name ?? '').trim(),
                debit: line.debit ?? '',
                credit: line.credit ?? '',
                line_summary: String(line.line_summary ?? '').trim(),
                refs: [],
                auto_refs: jsonArrayValue(line.auto_refs),
                recommended_refs: jsonArrayValue(line.recommended_refs),
                recommend_source: String(line.recommend_source ?? line.source ?? '').trim(),
                recommend_confidence: String(line.recommend_confidence ?? line.confidence ?? '').trim(),
                recommend_reason: String(line.recommend_reason ?? line.reason ?? '').trim(),
                recommended_account_id: String(line.recommended_account_id ?? line.account_id ?? '').trim(),
            };
            const inlineRef = voucherRefFromLine(line);
            if (inlineRef) group.refs.push(inlineRef);
            groups.push(group);
            if (!groupBySourceNo.has(String(sourceLineNo))) {
                groupBySourceNo.set(String(sourceLineNo), group);
            }
        });

        pendingRefs.forEach((refs, sourceLineNo) => {
            const group = groupBySourceNo.get(String(sourceLineNo));
            if (group) {
                group.refs.push(...refs);
            } else {
                groups.push({
                    sourceLineNo: normalizeVoucherSourceLineNo(sourceLineNo, groups.length + 1),
                    headerRowNo: '',
                    account_id: '',
                    debit: '',
                    credit: '',
                    line_summary: '',
                    refs,
                });
            }
        });

        return groups;
    }

    function normalizeRows(payload) {
        return Array.isArray(payload?.data) ? payload.data : [];
    }

    function refPickerConfig(type) {
        return {
            CLIENT: {
                url: API.clientList,
                placeholder: '거래처 선택',
                requiredPlaceholder: '거래처필수',
                label: (row) => row.client_name || row.business_name || row.name || row.company_name || row.id || '',
            },
            PROJECT: {
                url: API.projectList,
                placeholder: '프로젝트 선택',
                requiredPlaceholder: '프로젝트필수',
                label: (row) => row.project_name || row.name || row.project_code || row.id || '',
            },
            EMPLOYEE: {
                url: API.employeeList,
                placeholder: '직원 선택',
                label: (row) => row.employee_name || row.name || row.user_name || row.id || '',
            },
            ACCOUNT: {
                url: API.bankAccountList,
                placeholder: '계좌 선택',
                requiredPlaceholder: '계좌필수',
                label: (row) => row.account_name || row.bank_name || row.account_no || row.account_number || row.id || '',
            },
            BANK_ACCOUNT: {
                url: API.bankAccountList,
                placeholder: '계좌 선택',
                requiredPlaceholder: '계좌필수',
                label: (row) => row.account_name || row.bank_name || row.account_no || row.account_number || row.id || '',
            },
            CARD: {
                url: API.cardList,
                placeholder: '카드 선택',
                label: (row) => row.card_name || row.card_no || row.card_number || row.client_name || row.id || '',
            },
        }[String(type || '').toUpperCase()] || null;
    }

    async function ensureVoucherRefOptions(refType, force = false) {
        const type = String(refType || '').toUpperCase();
        const config = refPickerConfig(type);
        if (!config) return [{ id: '', text: '선택' }];
        if (!force && voucherRefOptionCache[type]) return voucherRefOptionCache[type];

        try {
            const json = await fetchJson(config.url);
            voucherRefOptionCache[type] = [
                { id: '', text: config.placeholder },
                ...normalizeRows(json).map((row) => ({
                    id: String(row.id ?? row.value ?? '').trim(),
                    text: String(config.label(row)).trim(),
                })).filter((item) => item.id !== ''),
            ];
        } catch (error) {
            console.error('[data-create] ref picker load failed', type, error);
            voucherRefOptionCache[type] = [{ id: '', text: config.placeholder }];
        }

        return voucherRefOptionCache[type];
    }

    async function initVoucherRefPicker(selectEl, refType, selectedValue = '') {
        if (!selectEl || !window.jQuery?.fn?.select2) return;

        const type = String(refType || '').toUpperCase();
        const config = refPickerConfig(type);
        const required = selectEl.dataset.required === '1';
        AdminPicker.select2(selectEl, {
            placeholder: selectEl.dataset.placeholder || (required ? config?.requiredPlaceholder : config?.placeholder) || '선택',
            dropdownParent: window.jQuery(selectEl.closest('.modal') || document.body),
            width: '100%',
        });

        const placeholder = selectEl.dataset.placeholder || (required ? config?.requiredPlaceholder : config?.placeholder) || '선택';
        const options = (await ensureVoucherRefOptions(type)).map((item, index) => (
            index === 0 && String(item.id || '') === '' ? { ...item, text: placeholder } : item
        ));
        let resolvedValue = String(selectedValue || '').trim();
        if (resolvedValue !== '' && !options.some((item) => String(item.id) === resolvedValue)) {
            const normalizedValue = normalizeCodeKey(resolvedValue);
            const matched = options.find((item) => (
                normalizeCodeKey(item.text) === normalizedValue
                || normalizeCodeKey(item.text).includes(normalizedValue)
            ));
            if (matched?.id) {
                resolvedValue = String(matched.id);
            }
        }
        if (AdminPicker.reloadSelect2) {
            AdminPicker.reloadSelect2(selectEl, options, 'id', 'text', resolvedValue);
        } else {
            selectEl.innerHTML = options.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.text)}</option>`).join('');
            selectEl.value = resolvedValue;
        }
    }

    async function resolveVoucherAccountId(value) {
        const rawValue = String(value || '').trim();
        if (rawValue === '') return '';
        await loadVoucherAccountOptions();
        return voucherAccountById.get(rawValue)?.id
            || voucherAccountByCode.get(rawValue)?.id
            || rawValue;
    }

    async function loadVoucherAccountPolicies(accountValue) {
        const accountId = await resolveVoucherAccountId(accountValue);
        if (!accountId) return [];
        if (voucherAccountPolicyCache[accountId]) return voucherAccountPolicyCache[accountId];

        try {
            const json = await fetchJson(`${API.subAccountList}?account_id=${encodeURIComponent(accountId)}`);
            voucherAccountPolicyCache[accountId] = normalizeRows(json)
                .map((row) => {
                    const rawRefType = String(row.ref_type || '').toUpperCase();
                    const subCode = String(row.sub_code || row.code || '').toUpperCase();
                    const refType = rawRefType === 'REF_TARGET' ? subCode : (rawRefType || subCode);

                    return {
                        ref_type: refType,
                        is_required: Number(row.is_required || 0),
                    };
                })
                .filter((policy) => policy.ref_type !== '');
        } catch (error) {
            console.error('[data-create] account policy load failed', error);
            voucherAccountPolicyCache[accountId] = [];
        }

        return voucherAccountPolicyCache[accountId];
    }

    function selectedRefsFromRow(row) {
        const refs = [];
        try {
            refs.push(...jsonArrayValue(decodeURIComponent(row.dataset.lineRefs || '')));
        } catch (error) {
            // Ignore malformed inline state.
        }
        try {
            refs.push(...jsonArrayValue(decodeURIComponent(row.dataset.recommendedRefs || '')));
        } catch (error) {
            // Ignore malformed recommendation state.
        }

        const selected = new Map();
        refs.forEach((ref) => {
            const type = String(ref.line_ref_type || ref.ref_type || '').trim().toUpperCase();
            const id = String(ref.line_ref_id || ref.ref_id || '').trim();
            if (type && id) {
                voucherRefTypeAliases(type).forEach((alias) => {
                    if (alias && !selected.has(alias)) {
                        selected.set(alias, id);
                    }
                });
            }
        });

        return selected;
    }

    async function renderVoucherLineSubAccountControls(row) {
        const container = row?.querySelector('.journal-line-subaccounts');
        const account = row?.querySelector('.line-account-code-picker')?.value?.trim() || '';
        if (!container) return;

        if (!account) {
            row.dataset.hasSubAccounts = '0';
            container.className = 'journal-line-subaccounts';
            container.textContent = '';
            return;
        }

        row.dataset.hasSubAccounts = '0';
        container.className = 'journal-line-subaccounts';
        container.innerHTML = '';

        const policies = await loadVoucherAccountPolicies(account);
        if (!policies.length) {
            container.innerHTML = '<span class="journal-subaccount-empty">보조계정 없음</span>';
            return;
        }

        row.dataset.hasSubAccounts = '1';
        container.className = 'journal-line-subaccounts journal-line-subaccount-grid';
        container.innerHTML = policies.map((policy, index) => {
            const label = voucherRefTypeLabel(policy.ref_type);
            const required = !!policy.is_required;
            const placeholder = required ? `${label}필수` : `${label}선택`;
            return `
            <label class="journal-line-subaccount-field">
                <span>${escapeHtml(label)}${required ? '<b class="journal-line-subaccount-required">*</b>' : ''}</span>
                <select class="form-select form-select-sm line-ref-picker"
                        data-ref-type="${escapeHtml(policy.ref_type)}"
                        data-required="${required ? '1' : '0'}"
                        data-placeholder="${escapeHtml(placeholder)}"
                        data-policy-index="${index}">
                    <option value="">${escapeHtml(placeholder)}</option>
                </select>
            </label>
        `;
        }).join('');

        const selectedMap = selectedRefsFromRow(row);
        for (const selectEl of container.querySelectorAll('.line-ref-picker')) {
            const refType = selectEl.dataset.refType || '';
            const selectedValue = voucherRefTypeAliases(refType)
                .map((alias) => selectedMap.get(alias))
                .find((value) => String(value || '').trim() !== '') || '';
            await initVoucherRefPicker(selectEl, refType, selectedValue);
        }
    }


    function voucherAmountInputValue(value) {
        const number = numericValue(value);
        if (number === null || number === 0) return '';
        return formatNumber(number);
    }

    function voucherRefTypeLabel(value) {
        const key = String(value || '').trim().toUpperCase();
        return {
            CLIENT: '거래처',
            CUSTOMER: '거래처',
            VENDOR: '거래처',
            PROJECT: '프로젝트',
            ACCOUNT: '계좌',
            BANK: '계좌',
            BANK_ACCOUNT: '계좌',
            CARD: '카드',
            EMPLOYEE: '직원',
        }[key] || value || '참조';
    }

    function voucherAccountDisplayText(group = {}) {
        const accountId = String(group.account_id || '').trim();
        return String(group.account_text || group.account_label || group.account_name || '').trim()
            || voucherAccountById.get(accountId)?.text
            || voucherAccountByCode.get(accountId)?.text
            || accountId
            || '-';
    }

    function voucherRefDisplayText(ref = {}) {
        const type = String(ref.line_ref_type || ref.ref_type || '').trim().toUpperCase();
        const id = String(ref.line_ref_id || ref.ref_id || '').trim();
        return String(ref.line_ref_label || ref.ref_label || '').trim()
            || (voucherRefOptionCache[type] || []).find((item) => String(item.id) === id)?.text
            || id
            || '-';
    }


    function voucherLineRefChipsHtml(refs = []) {
        const cleanRefs = refs
            .map((ref) => ({
                type: String(ref.line_ref_type || ref.ref_type || '').trim(),
                id: String(ref.line_ref_label || ref.line_ref_id || ref.ref_label || ref.ref_id || '').trim(),
                auto: !!ref.auto,
            }))
            .filter((ref) => ref.type !== '' || ref.id !== '');
        if (cleanRefs.length === 0) {
            return '<span class="voucher-ref-chip auto">자동 참조</span>';
        }
        const visible = cleanRefs.slice(0, 2);
        const hiddenCount = cleanRefs.length - visible.length;
        return [
            ...visible.map((ref) => `<span class="voucher-ref-chip ${ref.auto ? 'auto' : ''}">${escapeHtml(voucherRefTypeLabel(ref.type))}:${escapeHtml(ref.id || '-')}</span>`),
            hiddenCount > 0 ? `<span class="voucher-ref-chip more">+${hiddenCount}</span>` : '',
        ].join('');
    }

    function hasJournalVoucherLineGroups(groups = []) {
        return groups.some((group) => {
            const amount = Math.abs(numericValue(group.debit) || 0) + Math.abs(numericValue(group.credit) || 0);
            return String(group.account_id || '').trim() !== '' || String(group.line_summary || '').trim() !== '' || amount > 0;
        });
    }

    function recommendationAmount(payload = {}) {
        const amount = Math.abs(numericValue(payload.withdraw_amount) || numericValue(payload.deposit_amount) || numericValue(payload.total_amount) || 0);
        return amount;
    }

    function recommendationDirection(payload = {}) {
        const withdraw = Math.abs(numericValue(payload.withdraw_amount) || 0);
        const deposit = Math.abs(numericValue(payload.deposit_amount) || 0);
        if (withdraw > 0) return 'OUT';
        if (deposit > 0) return 'IN';
        return String(payload.transaction_direction || payload.transaction_type || '').toUpperCase();
    }

    function recommendationReasons(payload = {}, baseReason = '') {
        const reasons = [];
        if (String(payload.client_id || payload.client_name || payload.counterparty_name || '').trim() !== '') {
            reasons.push('거래처 정보 확인');
        }
        if (String(payload.project_id || payload.project_name || '').trim() !== '') {
            reasons.push('프로젝트 참조 포함');
        }
        if (String(payload.bank_account_id || payload.bank_account_name || '').trim() !== '') {
            reasons.push('계좌 정보 확인');
        }
        if (String(payload.description || payload.summary_text || '').trim() !== '') {
            reasons.push('거래내용 반영');
        }
        reasons.push('기본 분개 후보 포함');
        if (baseReason !== '') {
            reasons.unshift(baseReason);
        }
        return Array.from(new Set(reasons)).slice(0, 4);
    }

    function recommendedLine(line, payload, role, meta) {
        const autoRefs = recommendationRefsForRole(payload, role);
        return {
            ...line,
            recommend_role: role,
            auto_refs: autoRefs,
            recommended_refs: autoRefs,
            recommended_account_id: line.account_id || '',
            recommend_source: meta.source,
            recommend_confidence: meta.confidence,
            recommend_reason: meta.reason,
        };
    }

    function buildVoucherLineRecommendations(payload = {}) {
        const amount = recommendationAmount(payload);
        const summary = String(payload.description || payload.summary_text || payload.counterparty_name || '').trim();
        const baseSummary = summary || '원본 거래 추천';
        const direction = recommendationDirection(payload);
        const bankAccount = { account_id: '111200', account_text: '111200 보통예금' };
        if (!amount) return [];
        const commonMeta = {
            source: 'EVIDENCE_CONTEXT',
            confidence: businessRefCandidates(payload).length > 0 ? 78 : 55,
            reason: 'Evidence 원본 정보 기준 추천',
        };
        const candidates = direction === 'IN' ? [
            {
                title: '매출 수금',
                meta: '보통예금 / 매출계정',
                reasons: recommendationReasons(payload, '입금 거래'),
                lines: [
                    recommendedLine({ ...bankAccount, debit: amount, credit: '', line_summary: baseSummary }, payload, 'payment', commonMeta),
                    recommendedLine({ account_id: '412100', account_text: '412100 매출', debit: '', credit: amount, line_summary: baseSummary }, payload, 'business', commonMeta),
                ],
            },
        ] : [
            {
                title: '부가세 대급금',
                meta: '부가세대급금 / 보통예금',
                reasons: recommendationReasons(payload, '거래내용/출금액 기준'),
                lines: [
                    recommendedLine({ account_id: '215900', account_text: '215900 부가세대급금', debit: amount, credit: '', line_summary: baseSummary }, payload, 'business', commonMeta),
                    recommendedLine({ ...bankAccount, debit: '', credit: amount, line_summary: baseSummary }, payload, 'payment', commonMeta),
                ],
            },
            {
                title: '지급 수수료',
                meta: '지급수수료 / 보통예금',
                reasons: recommendationReasons(payload, '거래내용 반영'),
                lines: [
                    recommendedLine({ account_id: '831300', account_text: '831300 지급수수료', debit: amount, credit: '', line_summary: baseSummary }, payload, 'business', commonMeta),
                    recommendedLine({ ...bankAccount, debit: '', credit: amount, line_summary: baseSummary }, payload, 'payment', commonMeta),
                ],
            },
            {
                title: '복리후생비',
                meta: '복리후생비 / 보통예금',
                reasons: recommendationReasons(payload, '프로젝트/거래처 정보 포함'),
                lines: [
                    recommendedLine({ account_id: '511100', account_text: '511100 복리후생비', debit: amount, credit: '', line_summary: baseSummary }, payload, 'business', commonMeta),
                    recommendedLine({ ...bankAccount, debit: '', credit: amount, line_summary: baseSummary }, payload, 'payment', commonMeta),
                ],
            },
        ];
        return candidates;
    }

    function voucherLineRecommendationsHtml(payload = {}) {
        const recommendations = buildVoucherLineRecommendations(payload);
        if (recommendations.length === 0) {
            return `
                <div class="voucher-line-recommendations empty">
                    <div>
                        <strong>추천 분개</strong>
                        <p>원본 거래내용, 금액, 거래처, 프로젝트/계좌 정보를 기준으로 추천할 분개가 없습니다.</p>
                    </div>
                </div>
            `;
        }
        return `
            <div class="voucher-line-recommendations">
                <div class="voucher-line-recommendations-head">
                    <strong>추천 분개</strong>
                    <span>원본 거래내용과 금액, 거래처/프로젝트/계좌 정보를 기준으로 후보를 제안합니다.</span>
                </div>
                <div class="voucher-line-recommendation-list">
                    ${recommendations.map((item, index) => `
                        <div class="voucher-line-recommendation-item">
                            <div>
                                <strong>${escapeHtml(item.title)}</strong>
                                <span>${escapeHtml(item.meta)}</span>
                                <div class="voucher-line-recommendation-reasons">
                                    ${(item.reasons || []).map((reason) => `<em>${escapeHtml(reason)}</em>`).join('')}
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm voucher-line-recommend-apply-btn" data-recommendation="${escapeHtml(encodeURIComponent(JSON.stringify(item.lines)))}">
                                선택
                            </button>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }


    function applyVoucherLineRecommendation(table, lines = []) {
        const tbody = table?.querySelector('tbody');
        if (!table || !tbody || !Array.isArray(lines) || lines.length === 0) return;
        tbody.querySelector('.voucher-line-empty-row')?.remove();
        let sourceLineNo = nextVoucherSourceLineNo(table);
        lines.forEach((line) => {
            tbody.insertAdjacentHTML('beforeend', voucherLineRowHtml({ ...line, sourceLineNo }, tbody.querySelectorAll('tr[data-voucher-line-row]').length));
            sourceLineNo += 1;
        });
        table.querySelector('.voucher-line-recommendations')?.remove();
        renumberVoucherLineRows(table);
        initReadinessValueInputs(table);
        initVoucherLineSortable(table);
        void initVoucherLineGridControls(table).catch((error) => notify('error', error.message));
    }

    function recommendationPayloadFromTable(table) {
        try {
            return JSON.parse(decodeURIComponent(table?.dataset.recommendationPayload || '{}'));
        } catch (error) {
            return {};
        }
    }

    function payloadWithCurrentReadinessValues(modal, basePayload = {}) {
        const payload = { ...basePayload };
        if (!modal) return payload;

        modal.querySelectorAll('[data-readiness-key]').forEach((input) => {
            const key = input.dataset.readinessKey;
            if (!key) return;
            const value = valueForSave(input);
            payload[key] = value;

            const selectedText = input.tagName === 'SELECT'
                ? String(input.options[input.selectedIndex]?.text || '').trim()
                : '';
            const nameKey = {
                client_id: 'client_name',
                project_id: 'project_name',
                employee_id: 'employee_name',
                bank_account_id: 'bank_account_name',
                card_id: 'card_name',
            }[key];
            if (nameKey && selectedText !== '' && !selectedText.includes('선택')) {
                payload[nameKey] = selectedText;
            }

            if (key === 'bank_account_name' && input.dataset.readinessPicker === 'bankAccount') {
                const selectedMeta = readinessPickerMeta.get(input) || {};
                payload.bank_account_id = value;
                payload.bank_account_name = selectedMeta.account_name || selectedText || value;
                if (selectedMeta.account_number) payload.account_number = selectedMeta.account_number;
                if (selectedMeta.account_number) payload.payment_account_number = selectedMeta.account_number;
            }
        });

        serializeBankPaymentsFromModal(modal, payload);
        return payload;
    }

    function recommendationRoleForLine(line = {}) {
        const role = String(line.recommend_role || '').trim();
        if (role !== '') return role;
        const accountId = String(line.account_id || '').trim();
        return accountId === '111200' ? 'payment' : 'business';
    }

    function hydrateRecommendationLinesWithCurrentRefs(lines = [], payload = {}) {
        return lines.map((line) => {
            const refs = recommendationRefsForRole(payload, recommendationRoleForLine(line));
            return {
                ...line,
                auto_refs: refs,
                recommended_refs: refs,
            };
        });
    }

    function journalLikeRefRowsHtml(refs = [], autoRefs = []) {
        const cleanRefs = refs
            .map((ref) => ({
                type: String(ref.line_ref_type || ref.ref_type || '').trim().toUpperCase(),
                id: String(ref.line_ref_id || ref.ref_id || '').trim(),
                label: String(ref.line_ref_label || ref.ref_label || '').trim(),
                auto: false,
            }))
            .filter((ref) => ref.type !== '' || ref.id !== '');
        const cleanAutoRefs = autoRefs
            .map((ref) => ({
                type: String(ref.line_ref_type || ref.ref_type || '').trim().toUpperCase(),
                id: String(ref.line_ref_id || ref.ref_id || '').trim(),
                label: String(ref.line_ref_label || ref.ref_label || '').trim(),
                auto: true,
            }))
            .filter((ref) => ref.type !== '' || ref.id !== '' || ref.label !== '');
        const displayRefs = cleanRefs.length > 0 ? cleanRefs : cleanAutoRefs;
        const chips = displayRefs.length > 0
            ? displayRefs.map((ref) => `<span class="voucher-ref-chip ${ref.auto ? 'auto' : ''}">${escapeHtml(voucherRefTypeLabel(ref.type))}:${escapeHtml(ref.label || ref.id || '-')}</span>`).join('')
            : '<span class="voucher-ref-chip auto">자동 refs</span>';
        return `
            <div class="journal-line-subaccounts voucher-line-ref-compact">
                ${chips ? `<div class="voucher-line-ref-summary">${chips}</div>` : ''}
            </div>
        `;
    }

    function voucherLineEmptyRowHtml() {
        return `
            <tr class="voucher-line-empty voucher-line-empty-row">
                <td colspan="7" class="text-center text-muted py-3">분개라인이 없습니다. 추천 분개를 적용하거나 라인을 추가하세요.</td>
            </tr>
        `;
    }

    function voucherLineRowHtml(group = {}, displayIndex = 0) {
        const sourceLineNo = normalizeVoucherSourceLineNo(group.sourceLineNo, displayIndex + 1);
        const refs = Array.isArray(group.refs) ? group.refs : [];
        const autoRefs = Array.isArray(group.auto_refs) ? group.auto_refs : [];
        const recommendedRefs = Array.isArray(group.recommended_refs) && group.recommended_refs.length > 0 ? group.recommended_refs : autoRefs;
        const recommendReason = group.recommend_reason || '';
        return `
            <tr data-voucher-line-row
                data-source-line-no="${escapeHtml(sourceLineNo)}"
                data-header-row-no="${escapeHtml(group.headerRowNo || '')}"
                data-recommend-source="${escapeHtml(group.recommend_source || '')}"
                data-recommend-confidence="${escapeHtml(group.recommend_confidence || '')}"
                data-recommend-reason="${escapeHtml(recommendReason)}"
                data-recommended-account-id="${escapeHtml(group.recommended_account_id || group.account_id || '')}"
                data-line-refs="${escapeHtml(encodeURIComponent(JSON.stringify(refs)))}"
                data-recommended-refs="${escapeHtml(encodeURIComponent(JSON.stringify(recommendedRefs)))}">
                <td class="text-center line-no">
                    <span class="voucher-line-order-cell">
                        <button type="button" class="voucher-line-drag-handle" title="순서 변경" aria-label="순서 변경">
                            <i class="bi bi-grip-vertical"></i>
                        </button>
                        <span class="voucher-line-display-no">${displayIndex + 1}</span>
                    </span>
                </td>
                <td>
                    <select class="form-select form-select-sm line-account-code-picker voucher-line-account" data-selected-value="${escapeHtml(group.account_id ?? '')}" data-selected-label="${escapeHtml(group.account_text || group.account_label || group.account_name || group.account_id || '')}">
                        <option value="${escapeHtml(group.account_id ?? '')}" selected>${escapeHtml(group.account_text || group.account_label || group.account_name || group.account_id || '계정과목 선택')}</option>
                    </select>
                </td>
                <td class="line-ref-cell">
                    ${journalLikeRefRowsHtml(refs, recommendedRefs)}
                </td>
                <td>
                    <input type="text"
                           inputmode="numeric"
                           class="form-control form-control-sm line-debit input-amount number-input voucher-line-amount voucher-line-debit"
                           data-value-kind="amount"
                           value="${escapeHtml(voucherAmountInputValue(group.debit ?? ''))}"
                           placeholder="0">
                </td>
                <td>
                    <input type="text"
                           inputmode="numeric"
                           class="form-control form-control-sm line-credit input-amount number-input voucher-line-amount voucher-line-credit"
                           data-value-kind="amount"
                           value="${escapeHtml(voucherAmountInputValue(group.credit ?? ''))}"
                           placeholder="0">
                </td>
                <td>
                    <input type="text"
                           class="form-control form-control-sm line-summary voucher-line-summary"
                           value="${escapeHtml(group.line_summary ?? '')}"
                           placeholder="라인 적요">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-link btn-sm btn-remove-line voucher-line-delete-btn">-삭제</button>
                </td>
            </tr>
        `;
    }

    function bankVoucherLineTableHtml(payload = {}) {
        const groups = voucherLineGroups(payload);
        const hasJournal = hasJournalVoucherLineGroups(groups);
        return `
            <div class="journal-lines-wrap readiness-voucher-lines" data-voucher-lines-table data-recommendation-payload="${escapeHtml(encodeURIComponent(JSON.stringify(payload)))}">
                <div class="table-responsive journal-lines-table-wrap">
                    <table class="table table-bordered align-middle mb-0 readiness-voucher-line-table has-subaccounts" id="seed-voucher-line-table">
                        <thead class="table-light">
                            <tr>
                                <th width="64">순번</th>
                                <th width="280">계정과목</th>
                                <th width="260" class="line-ref-cell">보조계정</th>
                                <th width="150">차변</th>
                                <th width="150">대변</th>
                                <th>라인 적요</th>
                                <th width="64" class="journal-table-action-head">
                                    <button type="button" class="btn btn-link btn-sm voucher-line-add-btn">+추가</button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            ${hasJournal ? groups.map((group, index) => voucherLineRowHtml(group, index)).join('') : voucherLineEmptyRowHtml()}
                        </tbody>
                    </table>
                </div>
                ${hasJournal ? '' : voucherLineRecommendationsHtml(payload)}
            </div>
        `;
    }

    async function loadVoucherAccountOptions() {
        if (voucherAccountOptions) return voucherAccountOptions;
        try {
            const json = await fetchJson(API.accountList);
            const rows = Array.isArray(json?.data) ? json.data : [];
            voucherAccountById.clear();
            voucherAccountByCode.clear();
            voucherAccountOptions = [
                { id: '', text: '계정과목 선택' },
                ...rows.filter((row) => (
                    Number(row.is_active ?? 1) === 1
                    && String(row.is_postable ?? (Number(row.is_posting ?? 1) === 1 ? 'Y' : 'N')).toUpperCase() === 'Y'
                )).map((row) => {
                    const id = String(row.id ?? row.account_id ?? row.value ?? '').trim();
                    const code = String(row.account_code ?? '').trim();
                    const name = String(row.account_name ?? row.name ?? '').trim();
                    const path = String(row.full_path ?? '').trim();
                    const item = {
                        id,
                        text: path ? `[${path}]` : [code, name].filter(Boolean).join(' - '),
                        account_code: code,
                    };
                    if (id) voucherAccountById.set(id, item);
                    if (code) voucherAccountByCode.set(code, item);
                    return item;
                }).filter((item) => item.id !== ''),
            ];
        } catch (error) {
            voucherAccountOptions = [{ id: '', text: '계정과목 선택' }];
        }
        return voucherAccountOptions;
    }

    async function initVoucherLineGridControls(scope) {
        initReadinessValueInputs(scope);
        initVoucherLineSortable(scope);
        if (!window.jQuery?.fn?.select2) return;
        const options = await loadVoucherAccountOptions();
        for (const select of scope.querySelectorAll('.line-account-code-picker')) {
            const row = select.closest('tr[data-voucher-line-row]');
            if (select.dataset.seedLinePickerBound !== 'true') {
                AdminPicker.select2(select, {
                    placeholder: '계정과목 선택',
                    dropdownParent: window.jQuery(select.closest('.modal') || document.body),
                    width: '100%',
                });
                const selectedValue = await resolveVoucherAccountId(select.dataset.selectedValue || select.value || '');
                const selectedLabel = select.dataset.selectedLabel || selectedValue;
                const selectOptions = selectedValue && !options.some((item) => String(item.id) === String(selectedValue))
                    ? [{ id: selectedValue, text: selectedLabel || selectedValue }, ...options]
                    : options;
                if (AdminPicker.reloadSelect2) {
                    AdminPicker.reloadSelect2(select, selectOptions, 'id', 'text', selectedValue);
                } else {
                    select.innerHTML = selectOptions.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.text)}</option>`).join('');
                    select.value = selectedValue;
                }
                window.jQuery(select)
                    .off('change.seedLineAccount select2:select.seedLineAccount')
                    .on('change.seedLineAccount select2:select.seedLineAccount', () => {
                        void renderVoucherLineSubAccountControls(row).catch((error) => notify('error', error.message));
                    });
                select.dataset.seedLinePickerBound = 'true';
            }
            await renderVoucherLineSubAccountControls(row);
        }
    }

    function initVoucherLineSortable(scope) {
        if (!window.jQuery?.fn?.sortable) return;
        scope.querySelectorAll('[data-voucher-lines-table]').forEach((container) => {
            const tbody = container.querySelector('tbody');
            if (!tbody || tbody.dataset.voucherLineSortableBound === 'true') return;
            window.jQuery(tbody).sortable({
                handle: '.voucher-line-drag-handle',
                items: '> tr[data-voucher-line-row]',
                axis: 'y',
                tolerance: 'pointer',
                helper(_, tr) {
                    const $originals = tr.children();
                    const $helper = tr.clone().addClass('voucher-line-sort-helper');
                    $helper.children().each(function (index) {
                        window.jQuery(this).width($originals.eq(index).outerWidth());
                    });
                    return $helper;
                },
                start(_, ui) {
                    ui.placeholder.height(ui.item.outerHeight());
                    ui.placeholder.addClass('voucher-line-sort-placeholder');
                },
                stop() {
                    renumberVoucherLineRows(container);
                },
            });
            tbody.dataset.voucherLineSortableBound = 'true';
        });
    }

    function workspaceSectionHtml(title, subtitle, bodyHtml, extraClass = '') {
        return `
            <section class="workspace-section ${escapeHtml(extraClass)}">
                <div class="workspace-section-head">
                    <div>
                        <h6>${escapeHtml(title)}</h6>
                        ${subtitle ? `<p>${escapeHtml(subtitle)}</p>` : ''}
                    </div>
                </div>
                ${bodyHtml}
            </section>
        `;
    }

    function bankPaymentSummary(payload = {}) {
        const deposit = numericValue(payload.deposit_amount);
        const withdraw = numericValue(payload.withdraw_amount);
        if (withdraw && Math.abs(withdraw) > 0) return `출금 ${formatNumber(Math.abs(withdraw))}`;
        if (deposit && Math.abs(deposit) > 0) return `입금 ${formatNumber(Math.abs(deposit))}`;
        return '입금/출금 금액 없음';
    }

    function normalizePaymentDirection(value, fallback = 'OUT') {
        const text = String(value || '').trim().toUpperCase();
        if (['IN', 'DEPOSIT', 'RECEIVE', 'RECEIPT', '입금'].includes(text)) return 'IN';
        if (['OUT', 'WITHDRAW', 'WITHDRAWAL', 'PAY', 'PAYMENT', '출금'].includes(text)) return 'OUT';
        if (/입금|DEPOSIT|RECEIVE|RECEIPT/i.test(text)) return 'IN';
        if (/출금|WITHDRAW|PAY/i.test(text)) return 'OUT';
        return fallback === 'IN' ? 'IN' : 'OUT';
    }

    function paymentDirectionFromPayload(payload = {}) {
        const withdraw = Math.abs(numericValue(payload.withdraw_amount) || 0);
        const deposit = Math.abs(numericValue(payload.deposit_amount) || 0);
        const amountFallback = withdraw > 0 ? 'OUT' : (deposit > 0 ? 'IN' : 'OUT');
        return normalizePaymentDirection(payload.transaction_direction || payload.transaction_type, amountFallback);
    }

    function bankVoucherPaymentRows(payload = {}) {
        const direction = paymentDirectionFromPayload(payload);
        if (Array.isArray(payload._voucher_payments) && payload._voucher_payments.length > 0) {
            return payload._voucher_payments.map((payment) => ({
                ...payment,
                payment_direction: direction,
            }));
        }
        const withdraw = Math.abs(numericValue(payload.withdraw_amount) || 0);
        const deposit = Math.abs(numericValue(payload.deposit_amount) || 0);
        return [{
            payment_direction: direction,
            payment_type: 'ACCOUNT',
            payment_id: payload.bank_account_id || payload.bank_account_name || '',
            payment_label: payload.bank_account_name || payload.account_name || payload.payment_account_name || payload.bank_account_id || '',
            amount: direction === 'OUT' ? (withdraw || deposit) : (deposit || withdraw),
        }];
    }

    function bankVoucherPaymentRowHtml(payment = {}, index = 0, issues = {}) {
        const direction = String(payment.payment_direction || payment.direction || 'OUT').toUpperCase() === 'IN' ? 'IN' : 'OUT';
        const type = String(payment.payment_type || 'ACCOUNT').toUpperCase() === 'CARD' ? 'CARD' : 'ACCOUNT';
        const paymentId = payment.payment_id || payment.bank_account_id || payment.card_id || '';
        const paymentLabel = payment.payment_label || payment.bank_account_name || payment.card_name || payment.account_name || paymentId;
        const amount = numericValue(payment.amount) || 0;
        const accountIssue = index === 0 ? issues.accountIssue : null;
        const amountIssue = index === 0 ? issues.amountIssue : null;

        return `
            <tr data-bank-payment-row>
                <td class="text-center bank-payment-no">${index + 1}</td>
                <td>
                    <select class="form-select form-select-sm bank-payment-type" data-bank-payment-type>
                        <option value="ACCOUNT" ${type === 'ACCOUNT' ? 'selected' : ''}>계좌</option>
                        <option value="CARD" ${type === 'CARD' ? 'selected' : ''}>카드</option>
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm bank-payment-direction" data-bank-payment-direction>
                        <option value="IN" ${direction === 'IN' ? 'selected' : ''}>입금</option>
                        <option value="OUT" ${direction === 'OUT' ? 'selected' : ''}>출금</option>
                    </select>
                </td>
                <td class="${accountIssue ? 'readiness-field-correction' : ''}">
                    <select class="form-select form-select-sm bank-payment-id"
                            data-bank-payment-id
                            data-readiness-picker="${type === 'CARD' ? 'card' : 'bankAccount'}"
                            data-placeholder="${type === 'CARD' ? '카드 선택' : '계좌 선택'}">
                        <option value="${escapeHtml(paymentId)}" selected>${escapeHtml(paymentLabel || '')}</option>
                    </select>
                    ${accountIssue ? `<div class="readiness-field-correction-message">${escapeHtml(accountIssue.message)}</div>` : ''}
                </td>
                <td class="${amountIssue ? 'readiness-field-correction' : ''}">
                    <input type="text"
                           class="form-control form-control-sm number-input bank-payment-amount"
                           data-bank-payment-amount="1"
                           data-value-kind="amount"
                           inputmode="decimal"
                           value="${escapeHtml(formatNumber(amount || 0))}">
                    ${amountIssue ? `<div class="readiness-field-correction-message">${escapeHtml(amountIssue.message)}</div>` : ''}
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-link btn-sm bank-payment-delete-btn">-삭제</button>
                </td>
            </tr>
        `;
    }

    function bankVoucherPaymentTableHtml(row, payload = {}) {
        const accountIssue = correctionIssueItems(row).find((item) => item.field === 'bank_account_name')
            || readinessIssueItems(row).find((item) => item.field === 'bank_account_name')
            || evidenceRequiredIssueItems(row).find((item) => item.field === 'bank_account_name');
        const amountIssue = correctionIssueItems(row).find((item) => ['deposit_amount', 'withdraw_amount'].includes(item.field))
            || readinessIssueItems(row).find((item) => ['deposit_amount', 'withdraw_amount'].includes(item.field))
            || evidenceRequiredIssueItems(row).find((item) => ['deposit_amount', 'withdraw_amount'].includes(item.field));
        const rows = bankVoucherPaymentRows(payload);

        return `
            <div class="journal-lines-wrap readiness-voucher-payments bank-voucher-payment-wrap">
                <div class="table-responsive journal-lines-table-wrap">
                <table class="table table-bordered align-middle mb-0 readiness-payment-table bank-voucher-payment-table" data-bank-payment-table>
                    <thead class="table-light">
                        <tr>
                            <th width="64">순번</th>
                            <th width="160">결제유형</th>
                            <th width="100">입/출금</th>
                            <th>결제수단</th>
                            <th width="160">금액</th>
                            <th width="64" class="journal-table-action-head">
                                <button type="button" class="btn btn-link btn-sm bank-payment-add-btn">+추가</button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((payment, index) => bankVoucherPaymentRowHtml(payment, index, { accountIssue, amountIssue })).join('')}
                    </tbody>
                </table>
                </div>
            </div>
        `;
    }
    function bankCounterpartyInfoHtml(row, payload = {}, format = null) {
        const fields = configuredReadinessFields(row, format, [
            'counterparty_name',
            'counterparty_account_number',
            'counterparty_bank',
        ]);
        if (fields.length === 0) return '';
        return workspaceSectionHtml(
            '상대계좌정보',
            '은행 원본의 상대 계좌 정보를 확인합니다.',
            `<div class="readiness-field-grid workspace-field-grid payment-field-grid">${fields.map((key) => readinessFieldHtml(row, payload, key, format)).join('')}</div>`,
            'workspace-section-counterparty'
        );
    }

    function recommendedTransactionLine(payload = {}) {
        const amount = numericValue(payload.total_amount)
            ?? numericValue(payload.supply_amount)
            ?? numericValue(payload.amount)
            ?? 0;
        return normalizeTransactionLine({
            line_type: '품목',
            item_date: payload.item_date || payload.transaction_date || '',
            item_name: payload.item_name || payload.description || payload.client_name || payload.client_company_name || '거래내역',
            item_spec: payload.item_spec || '',
            unit_name: payload.unit_name || '',
            item_qty: payload.item_qty || (amount ? 1 : ''),
            item_price: payload.item_price || amount || '',
            amount: payload.amount || amount || '',
            item_note: payload.item_note || payload.description || '',
            foreign_unit_price: payload.foreign_unit_price || '',
            foreign_amount: payload.foreign_amount || '',
        }, payload);
    }

    function transactionLineRecommendationsHtml(payload = {}) {
        const line = recommendedTransactionLine(payload);
        const encoded = escapeHtml(encodeURIComponent(JSON.stringify([line])));
        const title = line.item_name || '거래내역';
        const amount = numericValue(line.amount) || 0;
        return `
            <div class="transaction-line-recommendations">
                <div class="transaction-line-recommendations-head">
                    <strong>추천거래</strong>
                    <span>기본정보와 금액을 기준으로 거래내역 1라인을 제안합니다.</span>
                </div>
                <div class="transaction-line-recommendation-item">
                    <div>
                        <div class="transaction-line-recommendation-title">${escapeHtml(title)}</div>
                        <div class="transaction-line-recommendation-meta">${escapeHtml(line.item_date || '-')} / ${escapeHtml(formatNumber(amount))}</div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm transaction-line-recommend-apply-btn" data-recommendation="${encoded}">적용</button>
                </div>
            </div>
        `;
    }

    function transactionLineWorkspaceHtml(row, payload = {}, format = null) {
        return `
            <div class="readiness-transaction-lines">
                <div class="transaction-hot-wrap">
                    <div class="transaction-line-hot readiness-transaction-line-hot" data-readiness-transaction-line-hot></div>
                </div>
                <div class="transaction-lines-footer">
                    <div class="transaction-summary-grid">
                        <div class="transaction-summary-row total">
                            <span>합계금액</span>
                            <input type="text" class="form-control form-control-sm" data-readiness-transaction-line-total readonly>
                        </div>
                    </div>
                </div>
                ${transactionLineRecommendationsHtml(payload)}
            </div>
        `;
    }

    function transactionWorkspaceHtml(row, payload = {}, format = null) {
        const hasForeign = String(payload.currency || '').trim().toUpperCase() !== ''
            && String(payload.currency || '').trim().toUpperCase() !== 'KRW'
            || (numericValue(payload.exchange_rate) || 0) > 0
            || transactionLineRows(payload).some((line) => (numericValue(line.foreign_unit_price) || 0) !== 0 || (numericValue(line.foreign_amount) || 0) !== 0);
        const hasFiles = Array.isArray(payload._transaction_files) && payload._transaction_files.length > 0;
        const mainFields = [
            'transaction_date',
            'business_unit',
            'transaction_direction',
            'transaction_type',
            'client_id',
            'project_id',
            'employee_id',
        ];
        const summaryFields = ['description', 'currency', 'exchange_rate'];
        const amountFields = ['supply_amount', 'vat_amount', 'total_amount'];
        const noteFields = ['note', 'memo'];

        return `
            <div class="workspace-flow transaction-entry-workspace">
                ${workspaceSectionHtml(
                    '거래개요',
                    '',
                    `
                        <div class="transaction-toggle-row readiness-transaction-toggle-row">
                            <div class="form-check form-switch transaction-switch">
                                <input class="form-check-input" type="checkbox" role="switch" data-readiness-foreign-toggle value="1" ${hasForeign ? 'checked' : ''}>
                                <label class="form-check-label">외화사용여부</label>
                            </div>
                            <div class="form-check form-switch transaction-switch">
                                <input class="form-check-input" type="checkbox" role="switch" data-readiness-file-toggle value="1" ${hasFiles ? 'checked' : ''}>
                                <label class="form-check-label">파일참조</label>
                            </div>
                        </div>
                        <div class="readiness-field-grid workspace-field-grid transaction-main-field-grid">
                            ${mainFields.map((key) => readinessFieldHtml(row, payload, key, format)).join('')}
                        </div>
                        <div class="readiness-field-grid workspace-field-grid transaction-summary-field-grid">
                            ${summaryFields.map((key) => readinessFieldHtml(row, payload, key, format)).join('')}
                        </div>
                        <div class="readiness-field-grid workspace-field-grid transaction-amount-field-grid">
                            ${amountFields.map((key) => readinessFieldHtml(row, payload, key, format)).join('')}
                        </div>
                        <div class="readiness-field-grid workspace-field-grid transaction-note-field-grid">
                            ${noteFields.map((key) => readinessFieldHtml(row, payload, key, format)).join('')}
                        </div>
                        <div class="transaction-file-panel readiness-transaction-file-panel d-none" data-readiness-file-panel>
                            <div class="transaction-file-upload-row">
                                <div class="transaction-field transaction-file-input-field mb-0">
                                    <span class="transaction-field-label">파일</span>
                                    <input type="file"
                                           class="transaction-file-native-input"
                                           data-readiness-transaction-files
                                           accept=".pdf,.jpg,.jpeg,.png,.zip"
                                           multiple>
                                    <span class="transaction-file-dropzone" data-readiness-file-dropzone>
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <span class="transaction-file-dropzone-text">파일을 드래그해서 첨부하세요</span>
                                    </span>
                                </div>
                            </div>
                            <div class="transaction-file-list" data-readiness-file-list></div>
                        </div>
                    `,
                    'workspace-section-transaction-overview'
                )}
                ${workspaceSectionHtml(
                    '거래내역',
                    '거래입력 모달의 거래내역 영역과 같은 용도의 상세 입력입니다.',
                    transactionLineWorkspaceHtml(row, payload, format),
                    'workspace-section-transaction-lines'
                )}
            </div>
        `;
    }

    function bankVoucherWorkspaceHtml(row, payload = {}, format = null) {
        const businessFields = configuredReadinessFields(row, format, [
            'business_unit',
            'transaction_direction',
            'transaction_type',
            'client_id',
            'project_id',
            'employee_id',
            'bank_account_name',
            'card_id',
        ]);
        const headerFields = ['voucher_date', 'voucher_summary_text', 'note', 'memo'];
        return `
            <div class="workspace-flow bank-voucher-workspace">
                ${workspaceSectionHtml(
                    '기준정보 및 기초정보',
                    '거래/전표 생성에 필요한 기준정보와 기초정보를 확인합니다.',
                    businessFields.length > 0
                        ? `<div class="readiness-field-grid workspace-field-grid business-ref-field-grid">${businessFields.map((key) => readinessFieldHtml(row, payload, key, format)).join('')}</div>`
                        : '<div class="text-muted small">이 형식에서 설정된 기준정보 및 기초정보 항목이 없습니다.</div>',
                    'workspace-section-business'
                )}
                ${workspaceSectionHtml(
                    '전표 헤더',
                    '전표 생성에 필요한 기본 정보를 확인합니다.',
                    `<div class="readiness-field-grid workspace-field-grid">${headerFields.map((key) => readinessFieldHtml(row, payload, key, format)).join('')}</div>`,
                    'workspace-section-header'
                )}
                ${workspaceSectionHtml(
                    '분개라인',
                    '각 라인별 계정과목을 선택하면 계정과목 정책에 따른 보조계정을 선택할 수 있습니다.',
                    bankVoucherLineTableHtml(payload),
                    'workspace-section-lines'
                )}
                ${workspaceSectionHtml(
                    '결제정보',
                    bankPaymentSummary(payload),
                    bankVoucherPaymentTableHtml(row, payload),
                    'workspace-section-payment'
                )}
                ${bankCounterpartyInfoHtml(row, payload, format)}
            </div>
        `;
    }

    function transactionLineMoveRenderer(_instance, td) {
        window.Handsontable?.dom?.empty?.(td);
        td.className = `${td.className || ''} transaction-line-move-cell`.trim();
        td.innerHTML = '<span class="transaction-line-move-handle" aria-label="순서 변경"><i class="bi bi-list"></i></span>';
        return td;
    }

    function transactionLineRowNoRenderer(_instance, td, row) {
        window.Handsontable?.dom?.empty?.(td);
        td.className = `${td.className || ''} transaction-line-row-no-cell`.trim();
        td.textContent = String(row + 1);
        return td;
    }

    function transactionLineActionRenderer(_instance, td) {
        window.Handsontable?.dom?.empty?.(td);
        td.className = `${td.className || ''} transaction-line-action-cell`.trim();
        td.innerHTML = '<button type="button" class="transaction-line-delete-action">-삭제</button>';
        return td;
    }

    function readinessUsesForeignCurrency(modal = document.getElementById('seedRowReadinessModal')) {
        return Boolean(modal?.querySelector('[data-readiness-foreign-toggle]')?.checked);
    }

    function transactionLineColumns(modal = document.getElementById('seedRowReadinessModal')) {
        const columns = [
            { data: '__move', title: '<i class="bi bi-arrows-move"></i>', readOnly: true, width: 24, renderer: transactionLineMoveRenderer },
            { data: '__row_no', title: '순번', readOnly: true, width: 32, renderer: transactionLineRowNoRenderer },
            { data: 'line_type', title: '라인유형', type: 'dropdown', source: ['품목', '부가세', '봉사료'], width: 88 },
            { data: 'item_date', title: '발생일', type: 'date', dateFormat: 'YYYY-MM-DD', correctFormat: true, width: 74 },
            { data: 'item_name', title: '품명', width: 88 },
            { data: 'item_spec', title: '규격', width: 58 },
            { data: 'unit_name', title: '단위', width: 44 },
            { data: 'item_qty', title: '수량', type: 'numeric', numericFormat: { pattern: '0,0.000' }, width: 56 },
            { data: 'item_price', title: '단가', type: 'numeric', numericFormat: { pattern: '0,0' }, width: 62 },
            { data: 'amount', title: '금액', type: 'numeric', numericFormat: { pattern: '0,0' }, width: 68 },
            { data: 'item_note', title: '적요', width: 84 },
            { data: '__actions', title: '+추가', readOnly: true, width: 40, renderer: transactionLineActionRenderer },
        ];
        if (!readinessUsesForeignCurrency(modal)) return columns;
        const priceIndex = columns.findIndex((column) => column.data === 'item_price');
        columns.splice(
            priceIndex,
            0,
            { data: 'foreign_unit_price', title: '외화단가', type: 'numeric', numericFormat: { pattern: '0,0.00' }, width: 58 },
            { data: 'foreign_amount', title: '외화금액', type: 'numeric', numericFormat: { pattern: '0,0.00' }, width: 72 },
        );
        return columns.filter((column) => !['item_price', 'amount'].includes(column.data));
    }

    function blankTransactionLine(payload = {}) {
        return {
            line_type: '품목',
            item_date: payload.item_date || payload.transaction_date || '',
            item_name: '',
            item_spec: '',
            unit_name: '',
            item_qty: '',
            foreign_unit_price: '',
            foreign_amount: '',
            item_price: '',
            amount: '',
            item_note: payload.item_note || payload.description || '',
        };
    }

    function normalizeTransactionLine(line = {}, payload = {}) {
        const amount = numericValue(line.amount ?? line.total_amount ?? line.supply_amount ?? '');
        const qty = numericValue(line.item_qty ?? line.quantity ?? '');
        const price = numericValue(line.item_price ?? line.unit_price ?? '');
        const foreignPrice = numericValue(line.foreign_unit_price ?? '');
        const foreignAmount = numericValue(line.foreign_amount ?? '');
        return {
            line_type: line.line_type || line.amount_type || '품목',
            item_date: line.item_date || payload.transaction_date || '',
            item_name: line.item_name || '',
            item_spec: line.item_spec || line.specification || '',
            unit_name: line.unit_name || '',
            item_qty: qty ?? '',
            foreign_unit_price: foreignPrice ?? '',
            foreign_amount: foreignAmount ?? '',
            item_price: price ?? '',
            amount: amount ?? '',
            item_note: line.item_note || line.description || '',
        };
    }

    function transactionLineRows(payload = {}) {
        if (Array.isArray(payload._transaction_lines) && payload._transaction_lines.length > 0) {
            return payload._transaction_lines.map((line) => normalizeTransactionLine(line, payload));
        }
        const hasManualLine = ['line_type', 'item_date', 'item_name', 'item_spec', 'unit_name', 'item_qty', 'item_price', 'amount', 'item_note']
            .some((key) => String(payload[key] ?? '').trim() !== '');
        if (hasManualLine) {
            return [normalizeTransactionLine(payload, payload)];
        }
        return [blankTransactionLine(payload)];
    }

    function syncReadinessTransactionLineTotal(modal) {
        const totalEl = modal?.querySelector('[data-readiness-transaction-line-total]');
        syncReadinessTransactionLineEmptyState(modal);
        if (!totalEl || !readinessTransactionLineHot) return;
        const total = readinessTransactionLineHot.getSourceData()
            .reduce((sum, row) => sum + (numericValue(row?.amount) || 0), 0);
        totalEl.value = total ? formatNumber(total) : '';
    }

    function syncReadinessTransactionLineEmptyState(modal) {
        const host = modal?.querySelector('[data-readiness-transaction-line-hot]');
        if (!host || !readinessTransactionLineHot) return;
        const rows = readinessTransactionLineHot.getSourceData()
            .filter((row) => Object.values(row || {}).some((value) => String(value ?? '').trim() !== ''));
        host.classList.toggle('is-empty', rows.length === 0);
    }

    function activateReadinessPanelForTarget(modal, target) {
        const panel = target?.closest('[data-readiness-stage-panel]');
        const stageId = panel?.dataset.readinessStagePanel || '';
        if (!modal || !stageId) return;
        modal.querySelectorAll('[data-readiness-stage]').forEach((item) => {
            item.classList.toggle('active', item.dataset.readinessStage === stageId);
        });
        modal.querySelectorAll('[data-readiness-stage-panel]').forEach((item) => {
            item.classList.toggle('active', item === panel);
        });
        if (stageId === 'transaction') {
            window.requestAnimationFrame(() => {
                readinessTransactionLineHot?.render();
                syncReadinessTransactionLineTotal(modal);
            });
        }
    }

    function focusReadinessTarget(modal, target) {
        if (!target) return false;
        activateReadinessPanelForTarget(modal, target);
        const scrollHost = modal.querySelector('.readiness-stage-content');
        if (scrollHost) {
            const hostRect = scrollHost.getBoundingClientRect();
            const targetRect = target.getBoundingClientRect();
            const nextTop = scrollHost.scrollTop + (targetRect.top - hostRect.top) - (hostRect.height / 2) + (targetRect.height / 2);
            scrollHost.scrollTo({ top: Math.max(0, nextTop), behavior: 'smooth' });
        }
        target.classList.add('readiness-correction-target');
        window.setTimeout(() => target.classList.remove('readiness-correction-target'), 1400);
        const focusable = target.matches('input, select, textarea, button')
            ? target
            : target.querySelector('input:not([type="hidden"]), select, textarea, button, .select2-selection');
        window.setTimeout(() => focusable?.focus?.({ preventScroll: true }), 250);
        return true;
    }

    function ensureVoucherLineForCorrection(modal) {
        const table = modal?.querySelector('[data-voucher-lines-table]');
        const tbody = table?.querySelector('tbody');
        if (!table || !tbody) return null;
        let row = table.querySelector('tr[data-voucher-line-row]');
        if (!row) {
            tbody.querySelector('.voucher-line-empty-row')?.remove();
            table.querySelector('.voucher-line-recommendations')?.remove();
            tbody.insertAdjacentHTML('beforeend', voucherLineRowHtml({ sourceLineNo: 1 }, 0));
            renumberVoucherLineRows(table);
            initVoucherLineSortable(table);
            void initVoucherLineGridControls(table).catch((error) => notify('error', error.message));
            row = table.querySelector('tr[data-voucher-line-row]');
        }
        return row;
    }

    function focusVoucherLineCorrection(modal) {
        const row = ensureVoucherLineForCorrection(modal);
        const target = row?.querySelector('.voucher-line-account')?.closest('td')
            || modal?.querySelector('[data-voucher-lines-table]');
        focusReadinessTarget(modal, target);
    }

    function focusReadinessHotColumn(modal, field) {
        if (!readinessTransactionLineHot) return false;
        const columns = transactionLineColumns(modal);
        const colIndex = columns.findIndex((column) => column.data === field);
        if (colIndex < 0) return false;
        const hotHost = modal.querySelector('[data-readiness-transaction-line-hot]');
        activateReadinessPanelForTarget(modal, hotHost);
        window.requestAnimationFrame(() => {
            readinessTransactionLineHot.selectCell(0, colIndex);
            readinessTransactionLineHot.scrollViewportTo(0, colIndex);
            focusReadinessTarget(modal, hotHost);
        });
        return true;
    }

    function focusReadinessCorrectionField(modal, field) {
        const key = String(field || '').trim();
        if (!modal) return;
        if (key === 'line_no') {
            focusVoucherLineCorrection(modal);
            return;
        }
        if (!key) {
            focusReadinessTarget(modal, modal.querySelector('.readiness-field-correction, .readiness-voucher-lines, .transaction-line-hot'));
            return;
        }

        const aliases = {
            bank_account_name: ['bank_account_name', 'bank_account_id'],
            deposit_amount: ['deposit_amount', 'withdraw_amount'],
            withdraw_amount: ['withdraw_amount', 'deposit_amount'],
            line_no: ['account_id', 'debit_amount', 'credit_amount', 'line_summary'],
            account_id: ['account_id'],
            debit_amount: ['debit_amount'],
            credit_amount: ['credit_amount'],
            line_summary: ['line_summary'],
        }[key] || [key];

        for (const alias of aliases) {
            const target = modal.querySelector(`[data-readiness-system-field="${CSS.escape(alias)}"]`)
                || modal.querySelector(`[data-readiness-key="${CSS.escape(alias)}"]`)?.closest('.readiness-field')
                || modal.querySelector(`[data-source-system-field="${CSS.escape(alias)}"]`)?.closest('.readiness-source-field');
            if (focusReadinessTarget(modal, target)) return;
        }

        if (['item_date', 'line_type', 'item_name', 'item_spec', 'unit_name', 'item_qty', 'item_price', 'amount', 'item_note', 'foreign_unit_price', 'foreign_amount'].includes(key)
            && focusReadinessHotColumn(modal, key)) {
            return;
        }

        const voucherTarget = {
            account_id: '.voucher-line-account',
            debit_amount: '.voucher-line-debit',
            credit_amount: '.voucher-line-credit',
            line_summary: '.voucher-line-summary',
        }[key];
        if (voucherTarget && focusReadinessTarget(modal, modal.querySelector(voucherTarget)?.closest('td'))) return;

        focusReadinessTarget(modal, modal.querySelector('.readiness-field-correction'));
    }

    function modalReadinessFieldValue(modal, field) {
        const key = String(field || '').trim();
        if (!modal || !key) return '';
        const aliases = {
            counterparty_name: ['counterparty_name', 'counterparty_account_holder_name', 'counterparty_account_holder', 'account_holder'],
            counterparty_account_number: ['counterparty_account_number', 'counterparty_account_no'],
            counterparty_bank: ['counterparty_bank', 'counterparty_bank_name'],
        }[key] || [key];
        for (const alias of aliases) {
            const input = modal.querySelector(`[data-readiness-key="${CSS.escape(alias)}"]`);
            if (!input) continue;
            const value = valueForSave(input);
            if (String(value ?? '').trim() !== '') return value;
        }
        return '';
    }

    function pruneResolvedCorrectionLinks(modal) {
        const panel = modal?.querySelector('.readiness-correction-panel');
        if (!panel) return;
        panel.querySelectorAll('.readiness-correction-link').forEach((link) => {
            const field = link.dataset.correctionField || '';
            if (['counterparty_name', 'counterparty_account_number', 'counterparty_bank'].includes(field)
                && String(modalReadinessFieldValue(modal, field) ?? '').trim() !== '') {
                link.closest('li')?.remove();
            }
        });
        const count = panel.querySelectorAll('.readiness-correction-link').length;
        const countEl = panel.querySelector('[data-correction-count]');
        if (countEl) countEl.textContent = `${count}개 항목`;
        if (count === 0) {
            panel.outerHTML = '<div class="alert alert-success py-2 mb-0">생성 준비가 완료되었습니다.</div>';
        }
    }

    function initReadinessTransactionLineHot(modal, row = {}) {
        const host = modal?.querySelector('[data-readiness-transaction-line-hot]');
        if (!host) {
            readinessTransactionLineHot?.destroy?.();
            readinessTransactionLineHot = null;
            return;
        }
        if (!window.Handsontable) {
            host.innerHTML = '<div class="alert alert-warning py-2 mb-0">거래내역 표 라이브러리를 불러오지 못했습니다.</div>';
            return;
        }
        const payload = mapped(row);
        const rows = transactionLineRows(payload);
        if (readinessTransactionLineHot) {
            const currentHost = readinessTransactionLineHot.rootElement;
            if (currentHost !== host) {
                readinessTransactionLineHot.destroy();
                readinessTransactionLineHot = null;
            }
        }
        if (!readinessTransactionLineHot) {
            readinessTransactionLineHot = new window.Handsontable(host, {
                data: rows,
                columns: transactionLineColumns(modal),
                colHeaders: transactionLineColumns(modal).map((column) => column.title),
                rowHeaders: false,
                minRows: 0,
                stretchH: 'all',
                width: '100%',
                height: 'auto',
                renderAllRows: true,
                licenseKey: 'non-commercial-and-evaluation',
                contextMenu: ['row_above', 'row_below', 'remove_row'],
                manualColumnResize: true,
                afterGetColHeader(column, th) {
                    if (column === 0) {
                        th.classList.add('transaction-line-move-head');
                        th.innerHTML = '<i class="bi bi-arrows-move"></i>';
                    }
                    if (column === transactionLineColumns(modal).length - 1) {
                        th.classList.add('transaction-line-add-head');
                        th.innerHTML = '<button type="button" class="transaction-line-add-action">+추가</button>';
                    }
                },
                afterChange(changes, source) {
                    if (!changes || source === 'loadData') return;
                    syncReadinessTransactionLineTotal(modal);
                },
                afterRemoveRow() {
                    syncReadinessTransactionLineTotal(modal);
                },
                afterCreateRow() {
                    syncReadinessTransactionLineTotal(modal);
                },
            });
            host.addEventListener('click', (event) => {
                if (event.target.closest('.transaction-line-add-action')) {
                    const count = readinessTransactionLineHot?.countRows() || 0;
                    readinessTransactionLineHot?.alter('insert_row_below', Math.max(count - 1, 0), 1);
                    Object.entries(blankTransactionLine(payload)).forEach(([key, value]) => {
                        readinessTransactionLineHot?.setSourceDataAtCell(Math.max(count, 0), key, value, 'add-line');
                    });
                    syncReadinessTransactionLineTotal(modal);
                    return;
                }
                if (event.target.closest('.transaction-line-delete-action')) {
                    const cell = event.target.closest('td');
                    const coords = cell ? readinessTransactionLineHot?.getCoords(cell) : null;
                    if (coords && coords.row >= 0) {
                        readinessTransactionLineHot?.alter('remove_row', coords.row, 1);
                        syncReadinessTransactionLineTotal(modal);
                    }
                }
            });
        } else {
            readinessTransactionLineHot.loadData(rows);
        }
        syncReadinessTransactionLineEmptyState(modal);
        window.requestAnimationFrame(() => {
            readinessTransactionLineHot?.render();
            syncReadinessTransactionLineTotal(modal);
        });
    }

    function serializeTransactionLinesFromModal(modal, next) {
        if (!modal?.querySelector('[data-readiness-transaction-line-hot]') || !readinessTransactionLineHot) return;
        next.is_import = readinessUsesForeignCurrency(modal) ? '1' : '0';
        next.auto_create_lines_1set = '0';
        const rows = readinessTransactionLineHot.getSourceData()
            .map((row) => normalizeTransactionLine(row, next))
            .filter((row) => ['item_name', 'item_spec', 'unit_name', 'item_note'].some((key) => String(row[key] ?? '').trim() !== '')
                || (numericValue(row.item_qty) || 0) !== 0
                || (numericValue(row.item_price) || 0) !== 0
                || (numericValue(row.amount) || 0) !== 0);
        next._transaction_lines = rows;
        const first = rows[0] || {};
        ['line_type', 'item_date', 'item_name', 'item_spec', 'unit_name', 'item_qty', 'foreign_unit_price', 'foreign_amount', 'item_price', 'amount', 'item_note'].forEach((key) => {
            next[key] = first[key] ?? '';
        });
    }

    function syncReadinessTransactionPanels(modal) {
        if (!modal) return;
        const foreignEnabled = readinessUsesForeignCurrency(modal);
        modal.querySelectorAll('[data-readiness-system-field="currency"], [data-readiness-system-field="exchange_rate"]').forEach((field) => {
            field.classList.toggle('d-none', !foreignEnabled);
        });
        readinessTransactionLineHot?.updateSettings({
            columns: transactionLineColumns(modal),
            colHeaders: transactionLineColumns(modal).map((column) => column.title),
        });
        readinessTransactionLineHot?.render();

        const fileEnabled = Boolean(modal.querySelector('[data-readiness-file-toggle]')?.checked);
        modal.querySelector('[data-readiness-file-panel]')?.classList.toggle('d-none', !fileEnabled);
        const fileInput = modal.querySelector('[data-readiness-transaction-files]');
        if (fileInput) fileInput.disabled = !fileEnabled;
    }

    function selectedReadinessFiles(modal) {
        const input = modal?.querySelector('[data-readiness-transaction-files]');
        return Array.from(input?.files || []).map((file) => ({
            name: file.name,
            size: file.size,
            type: file.type,
        }));
    }

    function renderReadinessFileList(modal) {
        const list = modal?.querySelector('[data-readiness-file-list]');
        if (!list) return;
        const files = selectedReadinessFiles(modal);
        list.innerHTML = files.length > 0
            ? files.map((file) => `
                <div class="transaction-file-item">
                    <span>${escapeHtml(file.name)}</span>
                    <small>${escapeHtml(formatNumber(file.size))} bytes</small>
                </div>
            `).join('')
            : '<div class="transaction-file-empty">첨부된 파일이 없습니다.</div>';
        const text = modal?.querySelector('.transaction-file-dropzone-text');
        if (text) {
            text.textContent = files.length > 0 ? `파일 ${files.length}개 선택됨` : '파일을 드래그해서 첨부하세요';
        }
        modal?.querySelector('[data-readiness-file-dropzone]')?.classList.toggle('has-files', files.length > 0);
    }

    function serializeTransactionFilesFromModal(modal, next) {
        if (!modal?.querySelector('[data-readiness-file-panel]')) return;
        next.use_file_reference = modal.querySelector('[data-readiness-file-toggle]')?.checked ? '1' : '0';
        next._transaction_files = selectedReadinessFiles(modal);
    }

    function renumberVoucherLineRows(container) {
        const rows = Array.from(container.querySelectorAll('tr[data-voucher-line-row]'));
        rows.forEach((row, index) => {
            const display = row.querySelector('.voucher-line-display-no');
            if (display) display.textContent = String(index + 1);
            row.dataset.sourceLineNo = String(index + 1);
        });
        const tbody = container.querySelector('tbody');
        if (!tbody) return;
        tbody.querySelectorAll('.voucher-line-empty-row').forEach((row, index) => {
            if (rows.length > 0 || index > 0) {
                row.remove();
            }
        });
        if (rows.length === 0 && !tbody.querySelector('.voucher-line-empty-row')) {
            tbody.insertAdjacentHTML('beforeend', voucherLineEmptyRowHtml());
        }
    }

    function nextVoucherSourceLineNo(table) {
        const numbers = Array.from(table.querySelectorAll('tr[data-voucher-line-row]'))
            .map((row) => Number.parseInt(row.dataset.sourceLineNo || '0', 10))
            .filter((value) => Number.isFinite(value) && value > 0);
        return numbers.length > 0 ? Math.max(...numbers) + 1 : 1;
    }

    function serializeVoucherLinesFromModal(modal, next) {
        const table = modal.querySelector('[data-voucher-lines-table]');
        if (!table) return true;

        const lines = [];
        table.querySelectorAll('tr[data-voucher-line-row]').forEach((row, index) => {
            const sourceLineNo = normalizeVoucherSourceLineNo(row.dataset.sourceLineNo, index + 1);
            const headerRowNo = row.dataset.headerRowNo || next.header_row_no || '';
            const account = row.querySelector('.voucher-line-account')?.value?.trim() || '';
            const debit = valueForSave(row.querySelector('.voucher-line-debit'));
            const credit = valueForSave(row.querySelector('.voucher-line-credit'));
            const summary = row.querySelector('.voucher-line-summary')?.value?.trim() || '';
            const refPickers = Array.from(row.querySelectorAll('.line-ref-picker'));
            const missingRequired = refPickers.find((selectEl) => (
                selectEl.dataset.required === '1'
                && !String(selectEl.value || '').trim()
            ));
            if (missingRequired) {
                notify('warning', `${index + 1}번 라인의 필수 보조계정을 선택해주세요.`);
                missingRequired.focus();
                lines.length = 0;
                next._voucher_lines = null;
                next._voucher_line_error = 'required_ref_missing';
                return;
            }
            const refs = refPickers.map((selectEl) => ({
                line_ref_type: selectEl.dataset.refType || '',
                line_ref_id: selectEl.value?.trim() || '',
                is_required: selectEl.dataset.required === '1' ? 1 : 0,
                is_primary: selectEl.dataset.policyIndex === '0' ? 1 : 0,
            })).filter((ref) => ref.line_ref_type !== '' && ref.line_ref_id !== '');
            const recommendedRefs = jsonArrayValue(decodeURIComponent(row.dataset.recommendedRefs || ''));
            const recommendedAccountId = String(row.dataset.recommendedAccountId || '').trim();
            const recommendationChanged = (recommendedAccountId !== '' && account !== '' && recommendedAccountId !== account) || refs.length > 0;
            const hasJournalValue = [account, debit, credit, summary].some((value) => String(value ?? '').trim() !== '') || refs.length > 0;
            if (!hasJournalValue) return;

            lines.push({
                header_row_no: headerRowNo,
                line_no: String(sourceLineNo),
                line_row_type: '분개',
                account_id: account,
                debit,
                credit,
                line_summary: summary,
                line_ref_type: '',
                line_ref_id: '',
                recommend_source: row.dataset.recommendSource || '',
                recommend_confidence: row.dataset.recommendConfidence || '',
                recommend_reason: row.dataset.recommendReason || '',
                recommended_account_id: recommendedAccountId,
                recommended_refs: recommendedRefs,
                is_user_modified: recommendationChanged ? 1 : 0,
            });
            refs.forEach((ref) => {
                lines.push({
                    header_row_no: headerRowNo,
                    line_no: String(sourceLineNo),
                    line_row_type: '보조',
                    account_id: '',
                    debit: '',
                    credit: '',
                    line_summary: '',
                    line_ref_type: ref.line_ref_type,
                    line_ref_id: ref.line_ref_id,
                });
            });
        });

        if (next._voucher_line_error) {
            delete next._voucher_line_error;
            return false;
        }
        next._voucher_lines = lines;
        return true;
    }

    function renumberBankPaymentRows(table) {
        table?.querySelectorAll('tr[data-bank-payment-row]').forEach((row, index) => {
            const numberCell = row.querySelector('.bank-payment-no');
            if (numberCell) numberCell.textContent = String(index + 1);
        });
    }

    function emptyBankPaymentRow(direction = 'OUT') {
        return {
            payment_direction: normalizePaymentDirection(direction),
            payment_type: 'ACCOUNT',
            payment_id: '',
            payment_label: '',
            amount: 0,
        };
    }

    function syncBankPaymentDirectionFields(modal, directionValue) {
        const direction = normalizePaymentDirection(directionValue);
        modal?.querySelectorAll('tr[data-bank-payment-row]').forEach((row) => {
            const directionSelect = row.querySelector('[data-bank-payment-direction]');
            const amountInput = row.querySelector('[data-bank-payment-amount]');
            if (directionSelect) {
                directionSelect.value = direction;
            }
            if (amountInput) {
                amountInput.dataset.readinessKey = direction === 'IN' ? 'deposit_amount' : 'withdraw_amount';
            }
        });
    }

    function serializeBankPaymentsFromModal(modal, next) {
        const table = modal?.querySelector('[data-bank-payment-table]');
        if (!table) return;

        const criteriaDirection = modal?.querySelector('[data-readiness-key="transaction_direction"]')?.value
            || next.transaction_direction
            || next.transaction_type
            || '';
        if (criteriaDirection !== '') {
            syncBankPaymentDirectionFields(modal, criteriaDirection);
        }

        const payments = [];
        table.querySelectorAll('tr[data-bank-payment-row]').forEach((row) => {
            const type = String(row.querySelector('[data-bank-payment-type]')?.value || 'ACCOUNT').trim().toUpperCase();
            const direction = String(row.querySelector('[data-bank-payment-direction]')?.value || 'OUT').trim().toUpperCase();
            const paymentSelect = row.querySelector('[data-bank-payment-id]');
            const paymentId = String(paymentSelect?.value || '').trim();
            const paymentLabel = String(paymentSelect?.options[paymentSelect.selectedIndex]?.text || '').trim();
            const amount = valueForSave(row.querySelector('[data-bank-payment-amount]'));
            if (type === '' && paymentId === '' && Number(amount || 0) <= 0) return;
            payments.push({
                payment_direction: direction === 'IN' ? 'IN' : 'OUT',
                payment_type: type === 'CARD' ? 'CARD' : 'ACCOUNT',
                payment_id: paymentId,
                payment_label: paymentLabel,
                amount,
            });
        });

        next._voucher_payments = payments;
        next.deposit_amount = '';
        next.withdraw_amount = '';
        const firstAccount = payments.find((payment) => payment.payment_type === 'ACCOUNT' && payment.payment_id !== '');
        if (firstAccount) {
            next.bank_account_id = firstAccount.payment_id;
            next.bank_account_name = firstAccount.payment_label || firstAccount.payment_id;
        }
        payments.forEach((payment) => {
            const amount = numericValue(payment.amount) || 0;
            if (payment.payment_direction === 'IN') {
                next.deposit_amount = String((numericValue(next.deposit_amount) || 0) + amount);
            } else {
                next.withdraw_amount = String((numericValue(next.withdraw_amount) || 0) + amount);
            }
        });
    }

    function sourceVoucherLinesHtml(payload = {}) {
        const groups = voucherLineGroups(payload);
        if (groups.length === 0) return '';

        return `
            <div class="readiness-source-voucher-lines">
                <div class="readiness-source-voucher-title">원본 분개라인</div>
                <div class="table-responsive">
                    <table class="table table-sm readiness-source-voucher-table">
                        <thead>
                            <tr>
                                <th>순번</th>
                                <th>계정과목</th>
                                <th>차변</th>
                                <th>대변</th>
                                <th>적요</th>
                                <th>참조</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${groups.map((group) => `
                                <tr>
                                    <td>${escapeHtml(group.sourceLineNo)}</td>
                                    <td><span data-source-account-id="${escapeHtml(group.account_id || '')}">${escapeHtml(voucherAccountDisplayText(group))}</span></td>
                                    <td class="text-end">${escapeHtml(formatNumber(group.debit || 0))}</td>
                                    <td class="text-end">${escapeHtml(formatNumber(group.credit || 0))}</td>
                                    <td>${escapeHtml(group.line_summary || '-')}</td>
                                    <td>${[...(group.refs || []), ...(group.auto_refs || [])].length > 0
                                        ? [...(group.refs || []), ...(group.auto_refs || [])].map((ref) => `<span class="readiness-source-ref" data-source-ref-type="${escapeHtml(ref.line_ref_type || ref.ref_type || '')}" data-source-ref-id="${escapeHtml(ref.line_ref_id || ref.ref_id || '')}">${escapeHtml(voucherRefTypeLabel(ref.line_ref_type || ref.ref_type || '-'))}: ${escapeHtml(voucherRefDisplayText(ref))}</span>`).join('')
                                        : 'Evidence 기준 자동 참조'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    async function hydrateSourceVoucherLineLabels(modal) {
        const sourcePane = modal?.querySelector('#seedRowSourcePane');
        if (!sourcePane) return;

        const accountNodes = Array.from(sourcePane.querySelectorAll('[data-source-account-id]'));
        if (accountNodes.length > 0) {
            await loadVoucherAccountOptions();
            accountNodes.forEach((node) => {
                const accountId = String(node.dataset.sourceAccountId || '').trim();
                const text = voucherAccountById.get(accountId)?.text || voucherAccountByCode.get(accountId)?.text || '';
                if (text) node.textContent = text;
            });
        }

        const refNodes = Array.from(sourcePane.querySelectorAll('[data-source-ref-type][data-source-ref-id]'));
        const refTypes = Array.from(new Set(refNodes.map((node) => String(node.dataset.sourceRefType || '').trim().toUpperCase()).filter(Boolean)));
        await Promise.all(refTypes.map((type) => ensureVoucherRefOptions(type)));
        refNodes.forEach((node) => {
            const type = String(node.dataset.sourceRefType || '').trim().toUpperCase();
            const id = String(node.dataset.sourceRefId || '').trim();
            const text = (voucherRefOptionCache[type] || []).find((item) => String(item.id) === id)?.text || '';
            if (text) node.textContent = `${voucherRefTypeLabel(type)}: ${text}`;
        });
    }

    function closeReadinessDatePickers(modal) {
        document.querySelectorAll('.readiness-date-picker-host').forEach((host) => {
            host.__pickerInstance?.close?.();
        });
    }

    function bindReadinessDateInput(input, modal) {
        if (!input || input.dataset.dateInputBound === 'true') return;
        const normalize = () => {
            input.value = input.dataset.valueKind === 'datetime'
                ? normalizeDateTimeInputValue(input.value)
                : formatDateInputValue(input.value);
        };
        input.addEventListener('focus', () => closeReadinessDatePickers(modal));
        input.addEventListener('change', normalize);
        input.addEventListener('blur', normalize);
        input.dataset.dateInputBound = 'true';
    }

    function bindReadinessDatePicker(button, modal) {
        if (!button || button.dataset.datePickerBound === 'true') return;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const control = button.closest('.readiness-date-control');
            const input = control?.querySelector('input[data-value-kind="date"], input[data-value-kind="datetime"]');
            if (!input) return;
            const keepTime = input.dataset.valueKind === 'datetime';

            closeReadinessDatePickers(modal);
            const host = document.createElement('div');
            host.className = 'readiness-date-picker-host is-hidden';
            document.body.appendChild(host);

            const picker = AdminPicker.create({ type: keepTime ? 'datetime' : 'date', container: host });
            const closePicker = picker.close?.bind(picker);
            picker.close = () => {
                closePicker?.();
                window.setTimeout(() => host.remove(), 0);
            };
            applyDateTimeToPicker(picker, input.value, keepTime);
            picker.subscribe((state, finalDate) => {
                if (!(finalDate instanceof Date) || Number.isNaN(finalDate.getTime())) return;
                input.value = keepTime && state?.timeEnabled
                    ? formatPickerDateTime(finalDate)
                    : formatPickerDate(finalDate);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                if (!keepTime) picker.close?.();
            });

            picker.open({ anchor: button });
        });
        button.dataset.datePickerBound = 'true';
    }

    function bindReadinessTimeInput(input, modal) {
        if (!input || input.dataset.timeInputBound === 'true') return;
        input.addEventListener('focus', () => closeReadinessDatePickers(modal));
        input.addEventListener('blur', () => {
            input.value = normalizeTimeInputValue(input.value);
        });
        input.dataset.timeInputBound = 'true';
    }

    function bindReadinessTimePicker(button, modal) {
        if (!button || button.dataset.timePickerBound === 'true') return;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const control = button.closest('.readiness-date-control');
            const input = control?.querySelector('input[data-value-kind="time"]');
            if (!input) return;

            closeReadinessDatePickers(modal);
            const host = document.createElement('div');
            host.className = 'readiness-date-picker-host is-hidden';
            document.body.appendChild(host);

            const picker = AdminPicker.create({ type: 'time-list', container: host, options: { step: 10, rows: 8 } });
            const closePicker = picker.close?.bind(picker);
            picker.close = () => {
                closePicker?.();
                window.setTimeout(() => host.remove(), 0);
            };
            const currentTime = normalizeTimeInputValue(input.value);
            if (currentTime) {
                const [hour, minute] = currentTime.split(':').map((item) => Number(item));
                picker.setTime?.({ hour, minute, meridiem: hour >= 12 ? 'PM' : 'AM' });
            }
            picker.subscribe((state) => {
                if (typeof state?.hour !== 'number' || typeof state?.minute !== 'number') return;
                input.value = `${pad2(state.hour)}:${pad2(state.minute)}`;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                picker.close?.();
            });
            picker.open({ anchor: button });
        });
        button.dataset.timePickerBound = 'true';
    }

    function normalizeReadinessSummaryKeyword(value) {
        return String(value ?? '').replace(/\s+/g, ' ').trim();
    }

    function closeReadinessSummaryAutocomplete() {
        readinessSummaryAutocompleteItems = [];
        readinessSummaryAutocompleteActiveIndex = -1;
        document.querySelectorAll('#seedRowReadinessModal .summary-autocomplete-list').forEach((list) => {
            list.innerHTML = '';
            list.classList.add('d-none');
        });
    }

    function setReadinessSummaryAutocompleteActive(index) {
        const list = readinessSummaryAutocompleteInput
            ?.closest('.summary-autocomplete-wrap')
            ?.querySelector('.summary-autocomplete-list');
        if (!list || readinessSummaryAutocompleteItems.length === 0) return;

        const maxIndex = readinessSummaryAutocompleteItems.length - 1;
        readinessSummaryAutocompleteActiveIndex = index < 0 ? maxIndex : (index > maxIndex ? 0 : index);

        list.querySelectorAll('.summary-autocomplete-item').forEach((item, itemIndex) => {
            item.classList.toggle('active', itemIndex === readinessSummaryAutocompleteActiveIndex);
        });
    }

    function applyReadinessSummaryAutocompleteItem(index) {
        const item = readinessSummaryAutocompleteItems[index];
        if (!item || !readinessSummaryAutocompleteInput) return;

        readinessSummaryAutocompleteInput.value = item.summary_text || '';
        readinessSummaryAutocompleteInput.dispatchEvent(new Event('input', { bubbles: true }));
        closeReadinessSummaryAutocomplete();
    }

    function renderReadinessSummaryAutocomplete(input, items = []) {
        const list = input
            ?.closest('.summary-autocomplete-wrap')
            ?.querySelector('.summary-autocomplete-list');
        if (!input || !list || input.disabled || input.readOnly) {
            closeReadinessSummaryAutocomplete();
            return;
        }

        readinessSummaryAutocompleteInput = input;
        readinessSummaryAutocompleteItems = items.filter((item) => String(item.summary_text || '').trim() !== '');
        readinessSummaryAutocompleteActiveIndex = -1;

        if (readinessSummaryAutocompleteItems.length === 0) {
            closeReadinessSummaryAutocomplete();
            return;
        }

        list.innerHTML = readinessSummaryAutocompleteItems.map((item, index) => `
            <button type="button"
                    class="summary-autocomplete-item"
                    role="option"
                    data-index="${index}"
                    title="${escapeHtml(item.summary_text || '')}">
                ${escapeHtml(item.summary_text || '')}
            </button>
        `).join('');
        list.classList.remove('d-none');
    }

    async function searchReadinessSummaryAutocomplete(input) {
        const normalizedKeyword = normalizeReadinessSummaryKeyword(input?.value || '');
        if (normalizedKeyword.length < 2 || !input || input.disabled || input.readOnly) {
            closeReadinessSummaryAutocomplete();
            return;
        }

        if (readinessSummaryAutocompleteAbort) {
            readinessSummaryAutocompleteAbort.abort();
        }
        readinessSummaryAutocompleteAbort = new AbortController();

        try {
            const json = await fetchJson(`${API.evidenceSummarySearch}?q=${encodeURIComponent(normalizedKeyword)}`, {
                signal: readinessSummaryAutocompleteAbort.signal,
            });

            if (normalizeReadinessSummaryKeyword(input.value) !== normalizedKeyword) return;
            renderReadinessSummaryAutocomplete(input, Array.isArray(json.items) ? json.items : []);
        } catch (error) {
            if (error?.name === 'AbortError') return;
            console.error('[ledger-data-create] evidence summary autocomplete failed', error);
            notify('error', '원본 전표적요 추천 목록을 불러오지 못했습니다.');
            closeReadinessSummaryAutocomplete();
        }
    }

    function queueReadinessSummaryAutocompleteSearch(input) {
        if (readinessSummaryAutocompleteTimer) {
            clearTimeout(readinessSummaryAutocompleteTimer);
        }

        readinessSummaryAutocompleteTimer = setTimeout(() => {
            void searchReadinessSummaryAutocomplete(input);
        }, 220);
    }

    function bindReadinessSummaryAutocomplete(input) {
        if (!input || input.dataset.summaryAutocompleteBound === 'true') return;
        const list = input.closest('.summary-autocomplete-wrap')?.querySelector('.summary-autocomplete-list');

        input.addEventListener('input', () => {
            readinessSummaryAutocompleteInput = input;
            queueReadinessSummaryAutocompleteSearch(input);
        });
        input.addEventListener('focus', () => {
            readinessSummaryAutocompleteInput = input;
            queueReadinessSummaryAutocompleteSearch(input);
        });
        input.addEventListener('keydown', (event) => {
            if (!list || list.classList.contains('d-none')) return;
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setReadinessSummaryAutocompleteActive(readinessSummaryAutocompleteActiveIndex + 1);
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setReadinessSummaryAutocompleteActive(readinessSummaryAutocompleteActiveIndex - 1);
                return;
            }
            if (event.key === 'Enter' && readinessSummaryAutocompleteActiveIndex >= 0) {
                event.preventDefault();
                applyReadinessSummaryAutocompleteItem(readinessSummaryAutocompleteActiveIndex);
                return;
            }
            if (event.key === 'Escape') {
                closeReadinessSummaryAutocomplete();
            }
        });

        list?.addEventListener('mousedown', (event) => {
            const item = event.target.closest('.summary-autocomplete-item');
            if (!item) return;
            event.preventDefault();
            readinessSummaryAutocompleteInput = input;
            applyReadinessSummaryAutocompleteItem(Number(item.dataset.index || -1));
        });

        if (!readinessSummaryAutocompleteDocumentBound) {
            document.addEventListener('mousedown', (event) => {
                if (event.target.closest('#seedRowReadinessModal .summary-autocomplete-wrap')) return;
                closeReadinessSummaryAutocomplete();
            });
            readinessSummaryAutocompleteDocumentBound = true;
        }

        input.dataset.summaryAutocompleteBound = 'true';
    }

    function initReadinessValueInputs(modal) {
        modal.querySelectorAll('[data-value-kind="amount"]').forEach((input) => {
            bindNumberInput(input);
        });
        modal.querySelectorAll('input[data-value-kind="date"], input[data-value-kind="datetime"]').forEach((input) => {
            bindReadinessDateInput(input, modal);
        });
        modal.querySelectorAll('input[data-value-kind="time"]').forEach((input) => {
            bindReadinessTimeInput(input, modal);
        });
        modal.querySelectorAll('.readiness-date-picker-btn').forEach((button) => {
            bindReadinessDatePicker(button, modal);
        });
        modal.querySelectorAll('.readiness-time-picker-btn').forEach((button) => {
            bindReadinessTimePicker(button, modal);
        });
        modal.querySelectorAll('[data-readiness-summary-autocomplete="1"]').forEach((input) => {
            bindReadinessSummaryAutocomplete(input);
        });
    }

    async function initReadinessModalControls(modal) {
        initReadinessValueInputs(modal);
        await initCodeSelectControls(modal);
        restoreUnmatchedSourceCodeSelects(modal);
        const rowId = modal?.dataset.rowId || '';
        const row = (evidenceTable?.rows().data().toArray() || []).find((item) => String(item.id) === String(rowId));
        initReadinessTransactionLineHot(modal, row || {});
        syncReadinessTransactionPanels(modal);
        renderReadinessFileList(modal);
        if (!window.jQuery?.fn?.select2) return;

        modal.querySelectorAll('[data-readiness-picker="client"]').forEach((select) => {
            AdminPicker.select2Ajax(select, {
                url: API.clientSearch,
                placeholder: select.dataset.placeholder || '거래처 선택',
                minimumInputLength: 0,
                dropdownParent: window.jQuery(modal),
                width: '100%',
                dataBuilder(params) {
                    return { q: params.term || '', limit: 20, is_active: 1 };
                },
                processResults(data) {
                    const rows = data?.results ?? data?.data ?? [];
                    return {
                        results: rows.map((row) => ({
                            id: String(row.id ?? ''),
                            text: row.text || row.client_name || row.company_name || '',
                        })).filter((item) => item.id !== ''),
                    };
                },
            });
        });

        modal.querySelectorAll('[data-readiness-picker="project"]').forEach((select) => {
            if (!select.querySelector('option[value=""]')) {
                select.insertBefore(new Option('선택(없음)', '', true, false), select.firstChild);
            }
            AdminPicker.select2Ajax(select, {
                url: API.projectSearch,
                placeholder: select.dataset.placeholder || '선택(없음)',
                allowClear: true,
                minimumInputLength: 0,
                dropdownParent: window.jQuery(modal),
                width: '100%',
                dataBuilder(params) {
                    return { q: params.term || '', limit: 20 };
                },
                processResults(data) {
                    const rows = data?.results ?? data?.data ?? [];
                    return {
                        results: [
                            { id: '', text: '선택(없음)' },
                            ...rows.map((row) => ({
                                id: String(row.id ?? ''),
                                text: row.text || row.project_name || row.construction_name || '',
                            })).filter((item) => item.id !== ''),
                        ],
                    };
                },
            });
        });

        [
            ['employee', API.employeeSearch, (row) => row.text || row.employee_name || row.name || ''],
            ['bankAccount', API.bankAccountSearch, (row) => row.text || row.account_name || row.account_number || row.bank_name || ''],
            ['card', API.cardSearch, (row) => row.text || row.card_name || row.card_number || row.card_company_name || ''],
        ].forEach(([picker, url, labelForRow]) => {
            modal.querySelectorAll(`[data-readiness-picker="${picker}"]`).forEach((select) => {
                if (!select.querySelector('option[value=""]')) {
                    select.insertBefore(new Option('선택(없음)', '', true, false), select.firstChild);
                }
                AdminPicker.select2Ajax(select, {
                    url,
                    placeholder: select.dataset.placeholder || '선택(없음)',
                    allowClear: true,
                    minimumInputLength: 0,
                    dropdownParent: window.jQuery(modal),
                    width: '100%',
                    dataBuilder(params) {
                        return { q: params.term || '', limit: 20, is_active: 1 };
                    },
                    processResults(data) {
                        const rows = data?.results ?? data?.data ?? [];
                        return {
                            results: [
                                { id: '', text: '선택(없음)' },
                                ...rows.map((row) => ({
                                    id: String(row.id ?? ''),
                                    text: labelForRow(row),
                                    account_number: row.account_number || '',
                                    account_name: row.account_name || '',
                                    bank_name: row.bank_name || '',
                                    account_holder: row.account_holder || '',
                                })).filter((item) => item.id !== ''),
                            ],
                        };
                    },
                });
                if (picker === 'bankAccount') {
                    window.jQuery(select).on('select2:select', (event) => {
                        readinessPickerMeta.set(select, event.params?.data || {});
                    });
                    window.jQuery(select).on('select2:clear', () => {
                        readinessPickerMeta.delete(select);
                    });
                }
            });
        });

        await initVoucherLineGridControls(modal);
    }

    function readinessStageBadge(row, stage) {
        if (stage?.workspace) return '';
        const missing = readinessCorrectionFieldSet(row);
        const targetFields = Array.isArray(stage.requiredFields) && stage.requiredFields.length > 0
            ? stage.requiredFields
            : stage.fields;
        const count = targetFields.filter((field) => missing.has(field)).length;
        if (count > 0) return `<span class="badge text-bg-warning ms-1">${count}</span>`;
        return '<span class="badge text-bg-success ms-1">OK</span>';
    }

    function rawPayload(row = {}) {
        if (row.raw_payload && typeof row.raw_payload === 'object' && !Array.isArray(row.raw_payload)) {
            return row.raw_payload;
        }
        const mappedRaw = mapped(row)._raw_payload;
        return mappedRaw && typeof mappedRaw === 'object' && !Array.isArray(mappedRaw) ? mappedRaw : {};
    }

    async function loadFormatForRow(row = {}) {
        const formatId = String(row.format_id || row.formatId || '').trim();
        if (formatId) {
            const cacheKey = `id:${formatId}`;
            if (formatCache.has(cacheKey)) return formatCache.get(cacheKey);

            const response = await fetch(`${API.format}?id=${encodeURIComponent(formatId)}`, { cache: 'no-store' });
            const json = await response.json().catch(() => ({}));
            if (!response.ok || json.success === false) {
                throw new Error(json.message || '형식 정보를 불러오지 못했습니다.');
            }
            const format = json.data || null;
            formatCache.set(cacheKey, format);
            return format;
        }

        const type = String(row.import_type || row.seed_source_type || row.source_type || '').trim().toUpperCase();
        if (!type) return null;
        if (formatCache.has(type)) return formatCache.get(type);

        const response = await fetch(`${API.formats}?data_type=${encodeURIComponent(type)}&include_columns=1`, { cache: 'no-store' });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '형식 정보를 불러오지 못했습니다.');
        }
        const formats = Array.isArray(json.data) ? json.data : [];
        const format = formats.find((item) => Number(item.is_default || 0) === 1) || formats[0] || null;
        formatCache.set(type, format);
        return format;
    }

    const SOURCE_SYSTEM_FIELD_KEYS = [
        'system_field_name',
        'system_field',
        'field_name',
        'db_column',
        'db_column_name',
        'target_field',
        'target_column',
        'mapped_field',
        'payload_key',
        'field',
    ];

    const SOURCE_LABEL_FIELD_FALLBACKS = {
        거래시간: 'transaction_time',
        거래시각: 'transaction_time',
        거래일자: 'transaction_date',
        승인시간: 'transaction_time',
        승인일자: 'transaction_date',
        승인시각: 'transaction_time',
        일자: 'transaction_date',
        작성일자: 'write_date',
        발행일자: 'issue_date',
        카드번호: 'card_number',
        카드명: 'card_name',
        카드유형: 'card_type',
        카드사: 'card_company_name',
        결제계좌번호: 'payment_account_number',
        결제계좌: 'payment_account_number',
        승인번호: 'approval_number',
        상호명: 'client_company_name',
        상호: 'client_company_name',
        거래처: 'client_company_name',
        공급가액: 'supply_amount',
        공급액: 'supply_amount',
        부가세: 'vat_amount',
        세액: 'vat_amount',
        합계금액: 'total_amount',
        청구금액: 'billing_amount',
        연회비: 'annual_fee_amount',
        수수료: 'fee_amount',
        할부개월: 'installment_months',
        출금: 'withdraw_amount',
        출금액: 'withdraw_amount',
        입금: 'deposit_amount',
        입금액: 'deposit_amount',
        거래후잔액: 'balance_amount',
        잔액: 'balance_amount',
        거래내용: 'description',
        적요: 'description',
        상대계좌: 'counterparty_account_number',
        상대계좌번호: 'counterparty_account_number',
        상대은행: 'counterparty_bank_name',
        상대계좌예금주: 'counterparty_name',
        상대거래처: 'counterparty_name',
        미결제금액: 'check_bill_amount',
        CMS번호: 'bank_reference_no',
        메모: 'memo',
        비고: 'note',
    };

    function firstTextFrom(...objects) {
        for (const object of objects) {
            if (!object || typeof object !== 'object' || Array.isArray(object)) continue;
            for (const key of SOURCE_SYSTEM_FIELD_KEYS) {
                const value = String(object[key] || '').trim();
                if (value) return value;
            }
        }
        return '';
    }

    function normalizedSourceLabel(label) {
        return String(label || '').replace(/[\s()[\]{}<>:_-]/g, '').trim();
    }

    function fallbackSystemFieldForLabel(label) {
        const exact = String(label || '').trim();
        if (SOURCE_LABEL_FIELD_FALLBACKS[exact]) return SOURCE_LABEL_FIELD_FALLBACKS[exact];
        return SOURCE_LABEL_FIELD_FALLBACKS[normalizedSourceLabel(exact)] || '';
    }

    function inferSystemField(key, value, column = null, row = {}) {
        const rawObject = value && typeof value === 'object' && !Array.isArray(value) ? value : null;
        const direct = firstTextFrom(column, rawObject);
        if (direct) return direct;

        const keyText = String(key || '').trim();
        if (/^[a-z][a-z0-9_]*$/i.test(keyText) && !/^\d+$/.test(keyText)) {
            return keyText;
        }

        const label = String(column?.excel_column_name || rawObject?.column_name || rawObject?.label || rawObject?.name || key || '').trim();
        const fallback = fallbackSystemFieldForLabel(label);
        if (fallback) return fallback;

        const payload = mapped(row);
        const rawValue = rawObject ? rawObject.value : value;
        const labelValue = payload[label];
        if (label && labelValue !== undefined && String(labelValue ?? '') === String(rawValue ?? '')) {
            const found = Object.entries(payload).find(([payloadKey, payloadValue]) => (
                /^[a-z][a-z0-9_]*$/i.test(payloadKey)
                && !payloadKey.startsWith('_')
                && String(payloadValue ?? '') === String(rawValue ?? '')
            ));
            if (found) return found[0];
        }

        return '';
    }

    function sourceEntry(key, value, column = null, row = {}) {
        if (column) {
            const rawObject = value && typeof value === 'object' && !Array.isArray(value) ? value : null;
            const requirementMode = Number(column.is_required || rawObject?.is_required || 0);
            return {
                key: String(key),
                label: String(column.excel_column_name || rawObject?.column_name || key).trim(),
                value: rawObject ? (rawObject.value ?? '') : (value ?? ''),
                order: Number(column.column_order ?? column.excel_column_index ?? rawObject?.column_index ?? key),
                columnIndex: Number(column.excel_column_index ?? rawObject?.column_index ?? column.column_order ?? key),
                systemField: inferSystemField(key, value, column, row),
                requirementMode,
                required: requirementMode === 1,
                isReference: Number(column.is_reference_column || rawObject?.is_reference_column || 0) === 1,
                meta: rawObject,
            };
        }
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            const label = String(value.column_name || value.label || value.name || key).trim();
            const systemField = inferSystemField(key, value, null, row);
            const requirementMode = Number(value.is_required || value.required || 0);
            const required = Number(value.is_required || value.required || 0) === 1
                || String(value.is_required || value.required || '').toUpperCase() === 'Y'
                || String(value.required || '').toLowerCase() === 'true';
            return {
                key: String(key),
                label: label || String(key),
                value: value.value ?? '',
                order: Number(value.column_index ?? key),
                columnIndex: Number(value.column_index ?? key),
                systemField,
                requirementMode,
                required,
                isReference: Number(value.is_reference_column || 0) === 1,
                meta: value,
            };
        }
        return {
            key: String(key),
            label: String(key),
            value: value ?? '',
            order: Number(key),
            columnIndex: Number(key),
            systemField: inferSystemField(key, value, null, row),
            requirementMode: 0,
            required: false,
            isReference: false,
            meta: null,
        };
    }

    function rawValueForColumn(raw, column) {
        const index = String(column.excel_column_index ?? column.column_order ?? '').trim();
        const excelName = String(column.excel_column_name || '').trim();
        const systemField = String(column.system_field_name || '').trim();
        if (index && Object.prototype.hasOwnProperty.call(raw, index)) {
            return [index, raw[index]];
        }
        const found = Object.entries(raw).find(([, value]) => {
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                return String(value.column_name || '').trim() === excelName
                    || String(value.system_field_name || '').trim() === systemField;
            }
            return false;
        });
        if (found) return found;
        return [index || excelName || systemField, {
            column_index: Number(column.excel_column_index ?? column.column_order ?? 0),
            column_name: excelName,
            system_field_name: systemField,
            is_required: Number(column.is_required || 0),
            is_reference_column: Number(column.is_reference_column || 0),
            value: '',
        }];
    }

    function sourceEntriesForRow(row = {}, format = null) {
        const raw = rawPayload(row);
        const payload = mapped(row);
        const usedKeys = new Set();
        const columns = Array.isArray(format?.columns) ? format.columns.slice() : [];
        const entries = columns
            .filter((column) => !BANK_VOUCHER_LINE_FIELDS.has(String(column.system_field_name || '').trim()))
            .sort((a, b) => Number(a.column_order || a.excel_column_index || 0) - Number(b.column_order || b.excel_column_index || 0))
            .map((column) => {
                const [key, value] = rawValueForColumn(raw, column);
                usedKeys.add(String(key));
                return sourceEntry(key, value, column, row);
            });

        Object.entries(raw).forEach(([key, value]) => {
            if (!usedKeys.has(String(key))) {
                entries.push(sourceEntry(key, value, null, row));
            }
        });
        if (false && Array.isArray(payload._voucher_lines)) {
            payload._voucher_lines.forEach((line, index) => {
                if (!line || typeof line !== 'object' || Array.isArray(line)) return;
                BANK_VOUCHER_LINE_FIELDS.forEach((field) => {
                    if (!Object.prototype.hasOwnProperty.call(line, field)) return;
                    const value = line[field] ?? '';
                    if (String(value ?? '').trim() === '') return;
                    entries.push({
                        key: `voucher_line_${index + 1}_${field}`,
                        label: `분개라인 ${index + 1} ${legacyReadinessFieldLabel(field)}`,
                        value,
                        order: 10000 + (index * 100) + Array.from(BANK_VOUCHER_LINE_FIELDS).indexOf(field),
                        columnIndex: '',
                        systemField: field,
                        required: ['header_row_no', 'account_id'].includes(field),
                        meta: line,
                    });
                });
            });
        }
        return entries;
    }

    function sourceFieldInputType(label, value, key = '') {
        const text = String(value ?? '');
        const kind = inputValueKind(key, label);
        if (['amount', 'date', 'datetime', 'time'].includes(kind)) return kind;
        if (text.length > 80 || /memo|note|description|비고|메모|적요/i.test(label)) return 'textarea';
        return 'text';
    }

    function restoreUnmatchedSourceCodeSelects(modal) {
        if (!modal) return;
        modal.querySelectorAll('select.readiness-source-input[data-code-group][data-source-raw-value]').forEach((select) => {
            const rawValue = String(select.dataset.sourceRawValue || '').trim();
            if (!rawValue || select.value) return;

            if (!Array.from(select.options).some((option) => option.value === rawValue)) {
                select.appendChild(new Option(rawValue, rawValue, true, true));
            }
            select.value = rawValue;

            if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
                window.jQuery(select).val(rawValue).trigger('change.select2');
            }
        });
    }

    function sourceFieldHtml(entry, editable = true) {
        const safeKey = escapeHtml(entry.key);
        const safeLabel = escapeHtml(entry.label);
        const safeSystemField = escapeHtml(entry.systemField || '');
        const safeColumnIndex = Number.isFinite(entry.columnIndex) ? String(entry.columnIndex) : '';
        const type = sourceFieldInputType(entry.label, entry.value, entry.systemField || entry.key);
        const safeValue = escapeHtml(valueForInput(type, entry.value));
        const config = readinessFieldConfig(entry.systemField || '');
        const requirementMode = Number(entry.requirementMode || 0);
        const requirementStar = requirementMode === 1
            ? '<span class="readiness-required-star">*</span>'
            : (requirementMode === 2 ? '<span class="readiness-optional-star">*</span>' : '');
        const requiredBadge = entry.required ? '<span class="badge text-bg-warning ms-1">필수</span>' : '';
        const fieldName = safeSystemField
            ? `<span class="readiness-source-system-field">${safeSystemField}</span>`
            : '<span class="readiness-source-system-field text-danger">system_field_name 없음</span>';
        const labelTitle = [entry.label, entry.required ? '필수' : '', entry.systemField].filter(Boolean).join(' ');
        let control = '';
        const lockAttrs = editable ? '' : ' readonly aria-readonly="true" tabindex="-1"';
        const sourceAttrs = `class="form-control form-control-sm readiness-source-input ${type === 'amount' ? 'number-input' : ''}" data-source-key="${safeKey}" data-source-column-name="${safeLabel}" data-source-column-index="${escapeHtml(safeColumnIndex)}" data-source-system-field="${safeSystemField}" data-source-required="${entry.required ? '1' : '0'}" data-value-kind="${escapeHtml(type)}"${lockAttrs}`;
        if (editable && config.kind === 'code' && config.codeGroup) {
            control = `
                <select class="form-select form-select-sm readiness-source-input"
                        data-source-key="${safeKey}"
                        data-source-column-name="${safeLabel}"
                        data-source-column-index="${escapeHtml(safeColumnIndex)}"
                        data-source-system-field="${safeSystemField}"
                        data-source-required="${entry.required ? '1' : '0'}"
                        data-source-raw-value="${safeValue}"
                        data-code-group="${escapeHtml(config.codeGroup)}"
                        data-empty-label="${escapeHtml(config.emptyLabel || '선택')}">
                    <option value="${safeValue}" selected>${safeValue || escapeHtml(config.emptyLabel || '선택')}</option>
                </select>
            `;
        } else if (type === 'textarea') {
            control = `<textarea ${sourceAttrs} rows="2">${safeValue}</textarea>`;
        } else if (type === 'date' || type === 'datetime') {
            control = `
                <div class="readiness-date-control">
                    <input type="text" ${sourceAttrs} value="${safeValue}" placeholder="${type === 'datetime' ? 'yyyy-mm-dd hh:mm' : 'yyyy-mm-dd'}" autocomplete="off" inputmode="numeric">
                    ${editable ? `<button type="button" class="btn btn-outline-secondary btn-sm readiness-date-picker-btn" data-date-picker-target="${safeKey}" title="${type === 'datetime' ? '일시 선택' : '날짜 선택'}"><i class="bi bi-calendar-event"></i></button>` : ''}
                </div>
            `;
        } else if (type === 'time') {
            control = `
                <div class="readiness-date-control">
                    <input type="text" ${sourceAttrs} value="${safeValue}" placeholder="hh:mm" autocomplete="off" inputmode="numeric">
                    ${editable ? `<button type="button" class="btn btn-outline-secondary btn-sm readiness-time-picker-btn" data-time-picker-target="${safeKey}" title="시간 선택"><i class="bi bi-clock"></i></button>` : ''}
                </div>
            `;
        } else {
            control = `<input type="text" ${sourceAttrs} value="${safeValue}" ${type === 'amount' ? 'inputmode="decimal"' : ''}>`;
        }
        return `
            <label class="readiness-source-field${entry.isReference ? ' readiness-source-field-reference' : ''}">
                <span class="form-label small mb-1 readiness-source-label" title="${escapeHtml(labelTitle)}">
                    <span>${safeLabel}${requirementStar}</span>
                    ${requiredBadge}
                    ${fieldName}
                </span>
                ${control}
            </label>
        `;
    }

    function renderSourceFields(row = {}, format = null) {
        const container = document.getElementById('seedRowSourceFields');
        if (!container) return;
        const entries = sourceEntriesForRow(row, format);
        const voucherLinesHtml = sourceVoucherLinesHtml(mapped(row));
        if (entries.length === 0 && voucherLinesHtml === '') {
            container.innerHTML = '<div class="text-muted small py-3 text-center">표시할 원본 데이터가 없습니다.</div>';
            return;
        }

        const editable = sourceEditable(row);
        const sourceFieldsHtml = entries.length > 0
            ? `<div class="readiness-source-block-title">원본 항목</div>` + entries
            .sort((a, b) => {
                const aOrder = Number.isFinite(a.order) ? a.order : Number.MAX_SAFE_INTEGER;
                const bOrder = Number.isFinite(b.order) ? b.order : Number.MAX_SAFE_INTEGER;
                if (aOrder !== bOrder) return aOrder - bOrder;
                return a.label.localeCompare(b.label, 'ko-KR');
            })
            .map((entry) => sourceFieldHtml(entry, editable))
            .join('')
            : '';
        container.innerHTML = sourceFieldsHtml + voucherLinesHtml;

        initReadinessValueInputs(document.getElementById('seedRowReadinessModal'));
    }

    function setSourcePaneVisible(visible) {
        const workbench = document.getElementById('seedRowReadinessWorkbench');
        const sourcePane = document.getElementById('seedRowSourcePane');
        const showBtn = document.getElementById('seedRowSourceShowBtn');
        workbench?.classList.toggle('source-collapsed', !visible);
        sourcePane?.classList.toggle('d-none', !visible);
        showBtn?.classList.toggle('d-none', visible);
    }

    function configureReadinessCreateButtons(modal, row = {}) {
        const transactionBtn = modal?.querySelector('#seedRowReadinessCreateTransactionBtn');
        const voucherBtn = modal?.querySelector('#seedRowReadinessCreateVoucherBtn');
        if (!transactionBtn || !voucherBtn) return;

        if (row.__isNew) {
            transactionBtn.classList.add('d-none');
            voucherBtn.classList.add('d-none');
            return;
        }

        const bankRow = isBankTransactionRow(row);
        transactionBtn.classList.toggle('d-none', bankRow);
        voucherBtn.classList.remove('d-none');
        transactionBtn.disabled = transactionCreateState(row) === 'CREATED';
        voucherBtn.disabled = voucherCreateState(row) === 'CREATED';
    }

    function renderReadinessModal(row, format = null) {
        const modal = ensureReadinessModal();
        const form = modal.querySelector('#seedRowReadinessForm');
        const alerts = modal.querySelector('#seedRowReadinessAlerts');
        const subtitle = modal.querySelector('#seedRowReadinessSubtitle');
        const sourceSubtitle = modal.querySelector('#seedRowSourceSubtitle');
        const summary = modal.querySelector('#seedRowReadinessSummary');
        const tabs = modal.querySelector('#seedRowReadinessTabs');
        const payload = mapped(row);
        const messages = correctionIssueItems(row).map((item) => item.message);
        const stages = readinessStageDefinitions(row);

        modal.dataset.rowId = row.id || '';
        modal.dataset.mode = row.__isNew ? 'create' : 'edit';
        modal.dataset.formatId = String(format?.id || row.format_id || '');
        modal.__readinessRow = row.__isNew ? row : null;
        configureReadinessCreateButtons(modal, row);
        if (subtitle) {
            subtitle.textContent = row.__isNew
                ? '새 증빙원본을 선택한 양식 기준으로 작성합니다.'
                : `${row.source_type_name || importSourceLabel(row.source_type)} / ${row.import_type_name || importTypeLabel(row.import_type || row.seed_source_type)} / ${generationLabel(row)} / ${voucherCreateStatusText(row)}`;
        }
        if (sourceSubtitle) {
            sourceSubtitle.textContent = `업로드 원본 데이터 / ${row.import_type_name || importTypeLabel(row.import_type || row.seed_source_type)}`;
        }
        if (summary) {
            summary.innerHTML = `
                <div class="readiness-summary-item">
                    <span>자료출처</span>
                    <strong>${escapeHtml(row.source_type_name || importSourceLabel(row.source_type))}</strong>
                </div>
                <div class="readiness-summary-item">
                    <span>자료유형</span>
                    <strong>${escapeHtml(row.import_type_name || importTypeLabel(row.import_type || row.seed_source_type))}</strong>
                </div>
                <div class="readiness-summary-item">
                    <span>생성방식</span>
                    <strong>${escapeHtml(generationLabel(row))}</strong>
                </div>
                <div class="readiness-summary-item">
                    <span>거래생성상태</span>
                    <strong>${transactionCreateStatusBadge(row)}</strong>
                </div>
                <div class="readiness-summary-item">
                    <span>전표생성상태</span>
                    <strong>${voucherCreateStatusBadge(row)}</strong>
                </div>
            `;
        }
        if (alerts) {
            alerts.innerHTML = row.__isNew
                ? '<div class="alert alert-info py-2 mb-0">양식을 선택한 뒤 원본 항목을 입력하고 저장하세요.</div>'
                : (messages.length
                ? `
                    <details class="readiness-correction-panel">
                        <summary>
                            <span class="badge text-bg-warning">보정 필요</span>
                            <strong data-correction-count>${messages.length}개 항목</strong>
                        </summary>
                        <div class="readiness-correction-body">
                            <ol>
                                ${correctionIssueLinksHtml(row)}
                            </ol>
                        </div>
                    </details>
                `
                : '<div class="alert alert-success py-2 mb-0">생성 준비가 완료되었습니다.</div>');
        }
        if (!form) return modal;
        closeReadinessSummaryAutocomplete();

        if (tabs) {
            tabs.innerHTML = stages.length > 1 ? stages.map((stage, index) => `
                <li class="nav-item" role="presentation">
                    <button class="nav-link ${index === 0 ? 'active' : ''}" type="button" data-readiness-stage="${escapeHtml(stage.id)}">
                        ${escapeHtml(stage.label)}${readinessStageBadge(row, stage)}
                    </button>
                </li>
            `).join('') : '';
        }

        const stageWorkspaceHtml = (stage) => {
            if (stage.workspace === 'bank-voucher') return bankVoucherWorkspaceHtml(row, payload, format);
            if (stage.workspace === 'transaction-entry') return transactionWorkspaceHtml(row, payload, format);
            if (stage.workspace === 'voucher-entry') return bankVoucherWorkspaceHtml(row, payload, format);
            return stage.extraHtml || '';
        };

        form.innerHTML = stages.map((stage, index) => `
            <section class="readiness-stage ${index === 0 ? 'active' : ''}" data-readiness-stage-panel="${escapeHtml(stage.id)}">
                <div class="readiness-stage-head">
                    <div>
                        <h6>${escapeHtml(stage.label)}</h6>
                        <p>${escapeHtml(stage.description)}</p>
                    </div>
                    ${readinessStageBadge(row, stage)}
                </div>
                ${stage.readonlyNote ? `<div class="alert alert-info py-2">${escapeHtml(stage.readonlyNote)}</div>` : ''}
                ${stage.note && !stage.workspace ? `<div class="alert alert-info py-2">${escapeHtml(stage.note)}</div>` : ''}
                ${stageWorkspaceHtml(stage)}
                ${stage.workspace ? '' : (stage.fields.length > 0
                    ? `<div class="readiness-field-grid">${stage.fields.map((key) => readinessFieldHtml(row, payload, key, format)).join('')}</div>`
                    : (!stage.readonlyNote ? '<div class="text-muted small">이 단계에서 보정할 항목이 없습니다.</div>' : ''))}
            </section>
        `).join('');
        renderSourceFields(row, format);
        setSourcePaneVisible(true);
        pruneResolvedCorrectionLinks(modal);
        void hydrateSourceVoucherLineLabels(modal);

        return modal;
    }

    async function saveReadinessModal(button, options = {}) {
        const { hide = true, reload = true, notifySuccess = true } = options;
        const modal = document.getElementById('seedRowReadinessModal');
        const rowId = modal?.dataset.rowId || '';
        if (!modal || !rowId) return;
        const row = (evidenceTable?.rows().data().toArray() || []).find((item) => String(item.id) === String(rowId));
        if (!row) return;
        const next = { ...mapped(row) };
        const rawNext = { ...rawPayload(row) };
        modal.querySelectorAll('[data-readiness-key]').forEach((input) => {
            const key = input.dataset.readinessKey;
            const value = valueForSave(input);
            next[key] = value;
            const nameKey = {
                client_id: 'client_name',
                project_id: 'project_name',
                employee_id: 'employee_name',
                bank_account_id: 'bank_account_name',
                card_id: 'card_name',
            }[key];
            const selectedText = input.tagName === 'SELECT'
                ? String(input.options[input.selectedIndex]?.text || '').trim()
                : '';
            if (nameKey && selectedText !== '' && selectedText !== '선택(없음)' && selectedText !== '직접 선택') {
                next[nameKey] = selectedText;
            }
            if (key === 'bank_account_name' && input.dataset.readinessPicker === 'bankAccount' && selectedText !== '' && selectedText !== '선택(없음)') {
                const selectedMeta = readinessPickerMeta.get(input) || {};
                next.bank_account_id = value;
                next.bank_account_name = selectedMeta.account_name || selectedText;
                if (selectedMeta.account_number) next.account_number = selectedMeta.account_number;
                if (selectedMeta.account_number) next.payment_account_number = selectedMeta.account_number;
                if (selectedMeta.bank_name) next.bank_name = selectedMeta.bank_name;
                if (selectedMeta.bank_name) next.payment_bank_name = selectedMeta.bank_name;
                if (selectedMeta.account_holder) next.account_holder = selectedMeta.account_holder;
            }
        });
        if (!serializeVoucherLinesFromModal(modal, next)) {
            return null;
        }
        serializeTransactionLinesFromModal(modal, next);
        serializeTransactionFilesFromModal(modal, next);
        serializeBankPaymentsFromModal(modal, next);
        modal.querySelectorAll('[data-source-key]').forEach((input) => {
            const key = input.dataset.sourceKey;
            const value = valueForSave(input);
            if (!key) return;
            const mappedKey = String(input.dataset.sourceSystemField || '').trim()
                || (/^[a-z][a-z0-9_]*$/i.test(key) && !String(key).startsWith('_') ? key : '');
            if (mappedKey) {
                next[mappedKey] = value;
            }
            if (rawNext[key] && typeof rawNext[key] === 'object' && !Array.isArray(rawNext[key])) {
                rawNext[key] = { ...rawNext[key], value };
                return;
            }
            if (input.dataset.sourceColumnName || input.dataset.sourceSystemField || input.dataset.sourceColumnIndex) {
                rawNext[key] = {
                    column_index: input.dataset.sourceColumnIndex ? Number(input.dataset.sourceColumnIndex) : null,
                    column_name: input.dataset.sourceColumnName || key,
                    system_field_name: input.dataset.sourceSystemField || '',
                    is_required: input.dataset.sourceRequired === '1' ? 1 : 0,
                    value,
                };
                return;
            }
            rawNext[key] = value;
        });

        const originalText = button?.textContent || '저장';
        if (button) {
            button.disabled = true;
            button.textContent = '저장 중';
        }
        try {
            const json = await fetchJson(API.saveSeedRow, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: rowId, parsed_json: next, raw_json: rawNext }),
            });
            notify('success', '수정사항을 저장했습니다.');
            if (hide) bootstrap.Modal.getOrCreateInstance(modal).hide();
            if (reload) reloadRows();
            return json;
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    }

    function modalVoucherLinesForTransaction(modal) {
        const accountId = modal?.querySelector('[data-readiness-key="account_id"]')?.value || '';
        const debit = modal?.querySelector('[data-readiness-key="debit_amount"]')?.value || '';
        const credit = modal?.querySelector('[data-readiness-key="credit_amount"]')?.value || '';
        const lineSummary = modal?.querySelector('[data-readiness-key="line_summary"]')?.value || '';
        const lines = [];
        if (accountId && debit) {
            lines.push({ line_type: 'DEBIT', account_id: accountId, amount: debit, line_summary: lineSummary });
        }
        if (accountId && credit) {
            lines.push({ line_type: 'CREDIT', account_id: accountId, amount: credit, line_summary: lineSummary });
        }
        return lines;
    }

    async function createTransactionVoucherFromModal(modal, row, button) {
        const transactionId = String(row?.transaction_id || '').trim();
        if (!transactionId) {
            notify('warning', '거래를 먼저 생성한 뒤 전표를 생성해 주세요.');
            return null;
        }

        return fetchJson(API.createVoucherFromTransaction, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                transaction_id: transactionId,
                header: {
                    transaction_date: modal?.querySelector('[data-readiness-key="voucher_date"]')?.value || mapped(row).voucher_date || mapped(row).transaction_date || '',
                    description: modal?.querySelector('[data-readiness-key="voucher_summary_text"]')?.value || mapped(row).voucher_summary_text || mapped(row).description || '',
                },
                lines: modalVoucherLinesForTransaction(modal),
            }),
        });
    }

    async function createFromReadinessModal(button, mode) {
        const modal = document.getElementById('seedRowReadinessModal');
        const row = currentReadinessRow(modal);
        if (!modal || !row?.id) return;

        const originalText = button?.textContent || '';
        if (button) {
            button.disabled = true;
            button.textContent = '생성 중';
        }
        try {
            const editable = !transactionCreated(row) && voucherCreateState(row) !== 'CREATED';
            if (editable) {
                const saved = await saveReadinessModal(null, { hide: false, reload: false, notifySuccess: false });
                if (saved === null) return;
            }

            let json = null;
            if (mode === 'voucher' && !isBankTransactionRow(row)) {
                json = await createTransactionVoucherFromModal(modal, row, button);
            } else {
                json = await requestCreateTransactions([row.id]);
                if (json.requires_confirmation && json.confirmation_code === 'EXISTING_VOUCHER') {
                    const confirmed = window.confirm(json.message || '이미 같은 유형의 전표가 생성되어 있습니다. 기존 전표를 연결할까요?');
                    if (!confirmed) return;
                    json = await requestCreateTransactions([row.id], { confirm_existing_voucher: true });
                }
            }
            if (json) notify('success', json.message || '생성 요청을 완료했습니다.');
            bootstrap.Modal.getOrCreateInstance(modal).hide();
            reloadRows();
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    }

    function openReadinessModal(row) {
        if (!row?.id) return;
        const modal = renderReadinessModal(row);
        bootstrap.Modal.getOrCreateInstance(modal, { focus: false }).show();
        void initReadinessModalControls(modal).catch((error) => notify('error', error.message));
        void loadFormatForRow(row)
            .then(async (format) => {
                renderReadinessModal(row, format);
                await initReadinessModalControls(modal);
            })
            .catch((error) => notify('error', error.message));
    }

    function buildLegacyColumns() {
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
            { data: 'process_status', title: '상태', visible: false, className: 'text-nowrap', render: (_value, _type, row) => statusBadge(normalizedStatus(row)) },
            { data: 'source_type', title: '자료출처', className: 'text-nowrap seed-compact-cell seed-source-cell', render: (value, _type, row) => labelBadge(row.source_type_name || importSourceLabel(value)) },
            { data: 'import_type', title: '자료유형', className: 'text-nowrap seed-compact-cell seed-type-cell', render: (value, _type, row) => labelBadge(row.import_type_name || importTypeLabel(value || row.seed_source_type)) },
            { data: null, title: '거래생성상태', className: 'text-nowrap seed-compact-cell', render: (_value, _type, row) => transactionCreateStatusBadge(row) },
            { data: 'voucher_status', title: '전표생성상태', className: 'text-nowrap seed-compact-cell', render: (_value, _type, row) => voucherCreateStatusBadge(row) },
            { data: null, title: '보정필요', className: 'text-nowrap seed-missing-summary-cell seed-correction-cell', render: (_value, _type, row) => correctionMissingSummary(row) },
            { data: 'processing_type', title: '처리방식', visible: false, className: 'text-nowrap', render: (_value, _type, row) => labelBadge(processingLabel(row)) },
            { data: 'source_type', title: '자료출처', className: 'text-nowrap', render: (value, _type, row) => labelBadge(row.source_type_name || importSourceLabel(value)) },
            { data: 'import_type', title: '자료유형', className: 'text-nowrap', render: (value, _type, row) => labelBadge(row.import_type_name || importTypeLabel(value || row.seed_source_type)) },
            { data: 'mapped_payload.transaction_direction', title: '거래구분', className: 'text-nowrap', render: (value) => escapeHtml(directionLabel(value || '')) },
            { data: null, title: '금액', className: 'text-nowrap', render: (_value, _type, row) => escapeHtml(primaryValue(row)) },
            {
                data: 'client_name',
                title: '거래처',
                render(value, _type, row) {
                    value = value || rowClient(row);
                    return `<span title="${escapeHtml(value)}">${escapeHtml(value || '-')}</span>`;
                },
            },
            { data: 'mapped_payload.transaction_date', title: '거래일자', className: 'text-nowrap', render: (value) => escapeHtml(formatDate(value)) },
            { data: 'mapped_payload.supply_amount', title: '공급가액', visible: false, className: 'text-end text-nowrap', render: (value) => formatNumber(value) },
            { data: 'mapped_payload.vat_amount', title: '부가세', visible: false, className: 'text-end text-nowrap', render: (value) => formatNumber(value) },
            { data: 'mapped_payload.total_amount', title: '합계금액', visible: false, className: 'text-end text-nowrap', render: (value) => formatNumber(value) },
            { data: 'mapped_payload.description', title: '적요', render: (value) => `<span title="${escapeHtml(value || '')}">${escapeHtml(value || '-')}</span>` },
            { data: null, title: '거래생성결과', className: 'text-nowrap', render: (_value, _type, row) => escapeHtml(transactionCreateStatusText(row)) },
            { data: 'voucher_status', title: '전표상태', className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
            { data: 'processed_at', title: '처리시간', className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
        ];
    }

    function buildColumns() {
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
            { data: null, title: '표준일자', className: 'text-nowrap seed-compact-cell', render: (_value, _type, row) => escapeHtml(formatDate(standardDate(row))) },
            { data: 'source_type', title: '자료출처', className: 'text-nowrap seed-compact-cell seed-source-cell', render: (value, _type, row) => labelBadge(row.source_type_name || importSourceLabel(value)) },
            { data: 'import_type', title: '자료유형', className: 'text-nowrap seed-compact-cell seed-type-cell', render: (value, _type, row) => labelBadge(row.import_type_name || importTypeLabel(value || row.seed_source_type)) },
            { data: null, title: '사업구분', className: 'text-nowrap seed-compact-cell seed-code-cell', render: (_value, _type, row) => escapeHtml(codeDisplayName('business_unit', mapped(row).business_unit || mapped(row).business_unit_code) || '-') },
            { data: null, title: '거래구분', className: 'text-nowrap seed-compact-cell seed-code-cell', render: (_value, _type, row) => escapeHtml(codeDisplayName('transaction_direction', mapped(row).transaction_direction) || directionLabel(mapped(row).transaction_direction || mapped(row).transaction_type || '')) },
            { data: null, title: '거래유형', className: 'text-nowrap seed-compact-cell seed-code-cell', render: (_value, _type, row) => escapeHtml(codeDisplayName('transaction_type', mapped(row).transaction_type) || '-') },
            { data: null, title: '거래처', className: 'text-nowrap seed-compact-cell seed-name-cell', render: (_value, _type, row) => `<span title="${escapeHtml(rowClientName(row))}">${escapeHtml(rowClientName(row) || '-')}</span>` },
            { data: null, title: '프로젝트', className: 'text-nowrap seed-compact-cell seed-name-cell', render: (_value, _type, row) => `<span title="${escapeHtml(rowProjectName(row))}">${escapeHtml(rowProjectName(row) || '-')}</span>` },
            { data: null, title: '증빙상태', className: 'text-nowrap seed-compact-cell seed-status-cell', render: (_value, _type, row) => evidenceStatusBadge(row) },
            { data: null, title: '거래생성상태', className: 'text-nowrap seed-compact-cell', render: (_value, _type, row) => transactionCreateStatusBadge(row) },
            { data: 'voucher_status', title: '전표생성상태', className: 'text-nowrap seed-compact-cell', render: (_value, _type, row) => voucherCreateStatusBadge(row) },
            { data: null, title: '보정필요', className: 'text-nowrap seed-missing-summary-cell seed-correction-cell', render: (_value, _type, row) => correctionMissingSummary(row) },
            {
                data: null,
                title: '관리',
                orderable: false,
                searchable: false,
                className: 'text-center text-nowrap no-colvis seed-manage-cell',
                headerClassName: 'no-colvis text-center seed-manage-cell',
                render: (_value, _type, row) => manageButton(row),
            },
            { data: 'processed_at', title: '처리시간', visible: false, className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
        ];
    }

    function openTrashModal() {
        const modal = document.getElementById('seedRowsTrashModal');
        if (!modal) {
            notify('warning', '휴지통 모달을 찾을 수 없습니다.');
            return;
        }
        modal.dataset.listUrl = API.trash;
        modal.dataset.restoreUrl = API.restoreRows;
        modal.dataset.deleteUrl = API.purgeRows;
        modal.dataset.deleteAllUrl = API.purgeAll;
        bootstrap.Modal.getOrCreateInstance(modal, { focus: false }).show();
    }

    function bindEvents() {
        const tableEl = document.getElementById('seedRowsTable');

        tableEl?.addEventListener('datatable:selection-changed', (event) => {
            selectedIds.clear();
            (event.detail?.ids || []).forEach((id) => selectedIds.add(String(id)));
            updateButtons();
        });

        tableEl?.addEventListener('click', (event) => {
            const button = event.target.closest('.seed-row-edit-btn');
            if (!button || !evidenceTable) return;
            event.preventDefault();
            event.stopPropagation();
            const tr = button.closest('tr');
            const row = tr ? evidenceTable.row(tr).data() : null;
            const fallbackRow = row || (evidenceTable.rows().data().toArray() || [])
                .find((item) => String(item.id) === String(button.dataset.id || ''));
            if (fallbackRow) openReadinessModal(fallbackRow);
        });

        document.addEventListener('trash:changed', (event) => {
            if (event.detail?.type === 'seedRows') {
                reloadRows();
            }
        });

        document.addEventListener('input', (event) => {
            const input = event.target.closest('#seedRowReadinessModal [data-readiness-key]');
            if (!input) return;
            pruneResolvedCorrectionLinks(input.closest('#seedRowReadinessModal'));
        });

        document.getElementById('seedRowsTypeSummary')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-seed-type-filter]');
            if (!button) return;
            applyTypeFilter(button.dataset.seedTypeFilter || '');
        });

        document.addEventListener('trash:detail-render', (event) => {
            if (event.detail?.type !== 'seedRows') return;
            const detailEl = event.detail.modal?.querySelector('.trash-detail');
            const row = event.detail.data || {};
            if (!detailEl) return;
            detailEl.innerHTML = `
                <div class="small">
                    <dl class="row mb-0">
                        <dt class="col-4">자료출처</dt><dd class="col-8">${escapeHtml(row.source_type_name || importSourceLabel(row.source_type))}</dd>
                        <dt class="col-4">자료유형</dt><dd class="col-8">${escapeHtml(row.import_type_name || importTypeLabel(row.import_type || row.seed_source_type))}</dd>
                        <dt class="col-4">상태</dt><dd class="col-8">${statusBadge(normalizedStatus(row))}</dd>
                        <dt class="col-4">거래처</dt><dd class="col-8">${escapeHtml(rowClient(row) || '-')}</dd>
                        <dt class="col-4">합계금액</dt><dd class="col-8">${escapeHtml(formatNumber(mapped(row).total_amount))}</dd>
                        <dt class="col-4">적요</dt><dd class="col-8">${escapeHtml(mapped(row).description || '-')}</dd>
                        <dt class="col-4">파일명</dt><dd class="col-8">${escapeHtml(row.file_name || '-')}</dd>
                        <dt class="col-4">삭제시간</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
                    </dl>
                </div>
            `;
        });

        document.addEventListener('click', (event) => {
            const correctionPanel = event.target.closest('#seedRowReadinessModal .readiness-correction-panel');
            if (correctionPanel && !event.target.closest('summary, a, button, input, select, textarea, .select2-container')) {
                correctionPanel.open = !correctionPanel.open;
                return;
            }

            if (event.target.closest('#seedRowReadinessModal .readiness-correction-panel summary')) {
                return;
            }

            const correctionLink = event.target.closest('#seedRowReadinessModal .readiness-correction-link');
            if (correctionLink) {
                focusReadinessCorrectionField(correctionLink.closest('#seedRowReadinessModal'), correctionLink.dataset.correctionField || '');
                return;
            }

            const createButton = event.target.closest('#seedRowReadinessModal [data-readiness-create]');
            if (createButton) {
                void createFromReadinessModal(createButton, createButton.dataset.readinessCreate || '').catch((error) => notify('error', error.message));
                return;
            }

            const button = event.target.closest('#seedRowReadinessSaveBtn');
            if (!button) return;
            void saveReadinessModal(button).catch((error) => notify('error', error.message));
        });

        document.addEventListener('change', (event) => {
            const foreignToggle = event.target.closest('#seedRowReadinessModal [data-readiness-foreign-toggle]');
            if (foreignToggle) {
                syncReadinessTransactionPanels(foreignToggle.closest('#seedRowReadinessModal'));
                return;
            }

            const fileToggle = event.target.closest('#seedRowReadinessModal [data-readiness-file-toggle]');
            if (fileToggle) {
                const modal = fileToggle.closest('#seedRowReadinessModal');
                syncReadinessTransactionPanels(modal);
                renderReadinessFileList(modal);
                return;
            }

            const fileInput = event.target.closest('#seedRowReadinessModal [data-readiness-transaction-files]');
            if (fileInput) {
                renderReadinessFileList(fileInput.closest('#seedRowReadinessModal'));
                return;
            }

            const readinessInput = event.target.closest('#seedRowReadinessModal [data-readiness-key]');
            if (readinessInput) {
                const modal = readinessInput.closest('#seedRowReadinessModal');
                if (readinessInput.dataset.readinessKey === 'transaction_direction') {
                    syncBankPaymentDirectionFields(modal, readinessInput.value);
                }
                pruneResolvedCorrectionLinks(modal);
            }

            const directionSelect = event.target.closest('#seedRowReadinessModal [data-bank-payment-direction]');
            if (!directionSelect) return;

            const row = directionSelect.closest('tr');
            const amountInput = row?.querySelector('[data-bank-payment-amount]');
            if (!amountInput) return;

            const nextDirection = String(directionSelect.value || '').toUpperCase() === 'IN' ? 'IN' : 'OUT';
            amountInput.dataset.readinessKey = nextDirection === 'IN' ? 'deposit_amount' : 'withdraw_amount';
        });

        document.addEventListener('click', (event) => {
            const addButton = event.target.closest('#seedRowReadinessModal .bank-payment-add-btn');
            if (!addButton) return;
            const table = addButton.closest('[data-bank-payment-table]');
            const tbody = table?.querySelector('tbody');
            if (!table || !tbody) return;
            const modal = document.getElementById('seedRowReadinessModal');
            const direction = modal?.querySelector('[data-readiness-key="transaction_direction"]')?.value || 'OUT';
            tbody.insertAdjacentHTML('beforeend', bankVoucherPaymentRowHtml(emptyBankPaymentRow(direction), tbody.querySelectorAll('tr[data-bank-payment-row]').length));
            renumberBankPaymentRows(table);
            void initReadinessModalControls(modal).catch((error) => notify('error', error.message));
        });

        document.addEventListener('click', (event) => {
            const deleteButton = event.target.closest('#seedRowReadinessModal .bank-payment-delete-btn');
            if (!deleteButton) return;
            const table = deleteButton.closest('[data-bank-payment-table]');
            const tbody = table?.querySelector('tbody');
            deleteButton.closest('tr[data-bank-payment-row]')?.remove();
            if (table && tbody && !tbody.querySelector('tr[data-bank-payment-row]')) {
                tbody.insertAdjacentHTML('beforeend', bankVoucherPaymentRowHtml(emptyBankPaymentRow(), 0));
            }
            renumberBankPaymentRows(table);
            const modal = document.getElementById('seedRowReadinessModal');
            void initReadinessModalControls(modal).catch((error) => notify('error', error.message));
        });

        document.addEventListener('change', (event) => {
            const typeSelect = event.target.closest('#seedRowReadinessModal [data-bank-payment-type]');
            if (!typeSelect) return;
            const row = typeSelect.closest('tr[data-bank-payment-row]');
            const paymentSelect = row?.querySelector('[data-bank-payment-id]');
            if (!paymentSelect) return;
            const type = String(typeSelect.value || '').toUpperCase() === 'CARD' ? 'CARD' : 'ACCOUNT';
            if (window.jQuery?.fn?.select2 && window.jQuery(paymentSelect).hasClass('select2-hidden-accessible')) {
                window.jQuery(paymentSelect).select2('destroy');
            }
            paymentSelect.dataset.readinessPicker = type === 'CARD' ? 'card' : 'bankAccount';
            paymentSelect.dataset.placeholder = type === 'CARD' ? '카드 선택' : '계좌 선택';
            paymentSelect.innerHTML = '<option value="" selected></option>';
            const modal = document.getElementById('seedRowReadinessModal');
            void initReadinessModalControls(modal).catch((error) => notify('error', error.message));
        });

        document.addEventListener('click', (event) => {
            const dropzone = event.target.closest('#seedRowReadinessModal [data-readiness-file-dropzone]');
            if (!dropzone) return;
            dropzone.closest('#seedRowReadinessModal')?.querySelector('[data-readiness-transaction-files]')?.click();
        });

        document.addEventListener('click', (event) => {
            const applyButton = event.target.closest('#seedRowReadinessModal .transaction-line-recommend-apply-btn');
            if (!applyButton || !readinessTransactionLineHot) return;
            let lines = [];
            try {
                lines = JSON.parse(decodeURIComponent(applyButton.dataset.recommendation || '[]'));
            } catch (error) {
                notify('error', '추천거래 정보를 읽을 수 없습니다.');
                return;
            }
            readinessTransactionLineHot.loadData(lines.map((line) => normalizeTransactionLine(line, {})));
            readinessTransactionLineHot.render();
            syncReadinessTransactionLineTotal(applyButton.closest('#seedRowReadinessModal'));
        });

        document.addEventListener('dragover', (event) => {
            const dropzone = event.target.closest('#seedRowReadinessModal [data-readiness-file-dropzone]');
            if (!dropzone) return;
            event.preventDefault();
            dropzone.classList.add('is-drag-over');
        });

        document.addEventListener('dragleave', (event) => {
            const dropzone = event.target.closest('#seedRowReadinessModal [data-readiness-file-dropzone]');
            if (!dropzone || dropzone.contains(event.relatedTarget)) return;
            dropzone.classList.remove('is-drag-over');
        });

        document.addEventListener('drop', (event) => {
            const dropzone = event.target.closest('#seedRowReadinessModal [data-readiness-file-dropzone]');
            if (!dropzone) return;
            event.preventDefault();
            dropzone.classList.remove('is-drag-over');
            const modal = dropzone.closest('#seedRowReadinessModal');
            const input = modal?.querySelector('[data-readiness-transaction-files]');
            if (input && event.dataTransfer?.files?.length) {
                input.files = event.dataTransfer.files;
                renderReadinessFileList(modal);
            }
        });

        document.addEventListener('click', (event) => {
            const addButton = event.target.closest('.voucher-line-add-btn');
            if (!addButton) return;
            const table = addButton.closest('[data-voucher-lines-table]');
            const tbody = table?.querySelector('tbody');
            if (!table || !tbody) return;
            tbody.querySelector('.voucher-line-empty-row')?.remove();
            table.querySelector('.voucher-line-recommendations')?.remove();
            const sourceLineNo = nextVoucherSourceLineNo(table);
            tbody.insertAdjacentHTML('beforeend', voucherLineRowHtml({ sourceLineNo }, tbody.querySelectorAll('tr[data-voucher-line-row]').length));
            renumberVoucherLineRows(table);
            initVoucherLineSortable(table);
            void initVoucherLineGridControls(table).catch((error) => notify('error', error.message));
        });

        document.addEventListener('click', (event) => {
            const deleteButton = event.target.closest('.voucher-line-delete-btn');
            if (!deleteButton) return;
            const table = deleteButton.closest('[data-voucher-lines-table]');
            deleteButton.closest('tr[data-voucher-line-row]')?.remove();
            if (table) {
                renumberVoucherLineRows(table);
                if (!table.querySelector('tr[data-voucher-line-row]') && !table.querySelector('.voucher-line-recommendations')) {
                    table.insertAdjacentHTML('beforeend', voucherLineRecommendationsHtml(recommendationPayloadFromTable(table)));
                }
            }
        });

        document.addEventListener('click', (event) => {
            const applyButton = event.target.closest('.voucher-line-recommend-apply-btn');
            if (!applyButton) return;
            const table = applyButton.closest('[data-voucher-lines-table]');
            let lines = [];
            try {
                lines = JSON.parse(decodeURIComponent(applyButton.dataset.recommendation || '[]'));
            } catch (error) {
                notify('error', '추천 분개 정보를 읽을 수 없습니다.');
                return;
            }
            const payload = payloadWithCurrentReadinessValues(
                table?.closest('#seedRowReadinessModal'),
                recommendationPayloadFromTable(table)
            );
            if (table) {
                table.dataset.recommendationPayload = encodeURIComponent(JSON.stringify(payload));
            }
            applyVoucherLineRecommendation(table, hydrateRecommendationLinesWithCurrentRefs(lines, payload));
        });

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-readiness-stage]');
            if (!button) return;
            const modal = button.closest('#seedRowReadinessModal');
            const stageId = button.dataset.readinessStage || '';
            modal?.querySelectorAll('[data-readiness-stage]').forEach((item) => {
                item.classList.toggle('active', item === button);
            });
            modal?.querySelectorAll('[data-readiness-stage-panel]').forEach((panel) => {
                panel.classList.toggle('active', panel.dataset.readinessStagePanel === stageId);
            });
            if (stageId === 'transaction') {
                window.requestAnimationFrame(() => {
                    readinessTransactionLineHot?.render();
                    syncReadinessTransactionLineTotal(modal);
                });
            }
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('#seedRowSourceToggleBtn')) {
                setSourcePaneVisible(false);
                return;
            }
            if (event.target.closest('#seedRowSourceShowBtn')) {
                setSourcePaneVisible(true);
            }
        });

        tableEl?.addEventListener('dblclick', (event) => {
            const tr = event.target.closest('tbody tr');
            if (!tr || !evidenceTable) return;
            const row = evidenceTable.row(tr).data();
            if (row) openReadinessModal(row);
        });
    }

    async function createSelectedTransactions(button) {
        const ids = selectedReadyIds();
        if (ids.length === 0) {
            notify('warning', '거래/전표 생성 가능한 READY 자료를 선택해주세요.');
            return;
        }

        isCreating = true;
        updateButtons();
        const originalText = button?.textContent || '거래/전표생성';
        if (button) button.textContent = '생성 중';
        try {
            let json = await requestCreateTransactions(ids);
            if (json.requires_confirmation && json.confirmation_code === 'EXISTING_VOUCHER') {
                const confirmed = window.confirm(json.message || '이미 같은 유형의 전표가 생성되어 있습니다. 기존 전표를 연결할까요?');
                if (!confirmed) {
                    notify('warning', '기존 전표 연결을 취소했습니다.');
                    return;
                }
                json = await requestCreateTransactions(ids, { confirm_existing_voucher: true });
            }
            notify('success', json.message || '선택한 자료의 거래/전표 생성을 요청했습니다.');
        } finally {
            isCreating = false;
            if (button) button.textContent = originalText;
            reloadRows();
        }
    }

    function initTable() {
        registerTypeFilterSearch();

        evidenceTable = createDataTable({
            tableSelector: '#seedRowsTable',
            api: API.rows,
            pageLength: 100,
            defaultOrder: [[1, 'asc']],
            scrollX: true,
            autoWidth: false,
            searchTableId: 'seedRows',
            deleteApi: API.deleteRows,
            bulkDelete: true,
            columns: buildColumns(),
            isRowSelectable: isSelectableForBulk,
            dataSrc(json) {
                const rows = Array.isArray(json.data) ? json.data : [];
                updateSummary(rows);
                return rows;
            },
            buttons: [
                {
                    text: '거래/전표생성',
                    className: 'btn btn-success btn-sm btn-create-selected-evidences',
                    action: (_event, _dt, node) => {
                        void createSelectedTransactions(node?.get(0)).catch((error) => notify('error', error.message));
                    },
                },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal,
                },
            ],
        });

        evidenceTable.on('draw.dt xhr.dt', () => {
            updateButtons();
            updateSummary(evidenceTable?.rows().data().toArray() || []);
        });

        SearchForm({
            table: evidenceTable,
            apiList: API.rows,
            tableId: 'seedRows',
            defaultSearchField: 'client_name',
            initialCollapsed: true,
            dateOptions: [
                { value: 'mapped_payload.transaction_date', label: '거래일자' },
                { value: 'created_at', label: '생성시간' },
                { value: 'processed_at', label: '처리시간' },
                { value: 'updated_at', label: '수정시간' },
            ],
            excludeFields: [''],
        });

        bindRowReorder(evidenceTable, {
            api: API.reorder,
            sortNoField: 'row_no',
            includeAppliedRows: true,
            extraData: () => ({ scope: 'create' }),
            onSuccess(json) {
                notify('success', json?.message || '생성센터 순서가 변경되었습니다.');
                evidenceTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '생성센터 순서 변경에 실패했습니다.');
                evidenceTable?.ajax.reload(null, false);
            },
        });

        bindEvents();
        updateButtons();
    }

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.seedRows = function (row = {}) {
        return `
            <td class="text-center">${escapeHtml(row.row_no || '-')}</td>
            <td>${statusBadge(normalizedStatus(row))}</td>
            <td>${escapeHtml(row.source_type_name || importSourceLabel(row.source_type))}</td>
            <td>${escapeHtml(row.import_type_name || importTypeLabel(row.import_type || row.seed_source_type))}</td>
            <td>${escapeHtml(rowClient(row) || '-')}</td>
            <td class="text-end">${escapeHtml(formatNumber(mapped(row).total_amount))}</td>
            <td>${escapeHtml(formatDate(mapped(row).transaction_date))}</td>
            <td>${escapeHtml(row.deleted_at || '-')}</td>
            <td>
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id)}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id)}">삭제</button>
            </td>
        `;
    };

    loadDisplayCodeOptions()
        .catch((error) => console.warn('[dataCreate] code labels failed', error))
        .finally(initTable);
})();

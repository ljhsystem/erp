import { escapeHtml } from './utils.js';

const __DATA_CREATE_CODE_OPTIONS__ = new Proxy({}, {
    get(_target, property) {
        const source = globalThis.__DATA_CREATE_CODE_OPTIONS__ || {};
        return source[property];
    },
});

const DISPLAY_CODE_FIELDS = {
    business_unit: 'BUSINESS_UNIT',
    transaction_direction: 'TRANSACTION_DIRECTION',
};

const codeOptions = __DATA_CREATE_CODE_OPTIONS__;

function importSourceLabel(value) {
    const key = String(value || '').toUpperCase();

    return {
        TAX: '홈택스',
        HOMETAX: '홈택스',
        CARD: '카드',
        CARD_COMPANY: '카드사',
        BANK: '은행',
        SHOPPING: '쇼핑몰',
        TRADE: '무역',
        IMPORT: '무역',
        MANUAL: '수기입력',
    }[key] || value || '-';
}

function importTypeLabel(value) {
    const key = String(value || '').toUpperCase();

    return {
        TAX_INVOICE: '세금계산서',
        CASH_RECEIPT: '현금영수증',
        CARD_HOMETAX: '카드 홈택스',
        CARD_STATEMENT: '카드 명세서',
        CARD_APPROVAL: '카드 승인',
        BANK_TRANSACTION: '입출금(은행)',
        SHOPPING_ORDER: '쇼핑몰 주문',
        IMPORT_INVOICE: '무역 수입송장',
        ETC: '기타',
    }[key] || value || '-';
}

function directionLabel(value) {
    const key = String(value || '').toUpperCase();

    return {
        FUND: '자금',
        IN: '입금',
        OUT: '출금',
        INCOME: '수익',
        EXPENSE: '비용',
        PURCHASE: '매입',
        SALES: '매출',
        BANK: '자금',
        GENERAL: '일반',
    }[key] || value || '-';
}

function normalizeCodeKey(value) {
    return String(value ?? '')
        .trim()
        .toUpperCase();
}

function normalizeCodeRows(rows = []) {
    return rows
        .map((row) => ({
            code: String(row.code ?? row.value ?? '').trim(),
            code_name: String(
                row.code_name
                ?? row.label
                ?? row.code
                ?? row.value
                ?? ''
            ).trim(),
            is_active: Number(row.is_active ?? 1),
        }))
        .filter((row) => row.code !== '' && row.is_active === 1);
}

function codeDisplayName(field, value) {
    const raw = String(value ?? '').trim();

    if (raw === '') return '';

    const group = DISPLAY_CODE_FIELDS[field] || '';
    if (group === '') return raw;

    const found = (codeOptions[group] || []).find((row) => (
        normalizeCodeKey(row.code) === normalizeCodeKey(raw)
    ));

    return found?.code_name || raw;
}

const STATUS_LABELS = {
    DRAFT: '임시저장',
    REVIEW_REQUESTED: '검토요청',
    REVIEWED: '검토완료',
    APPROVED: '승인',
    REJECTED: '반려',
    POSTED: '전표승인',
    CLOSED: '마감',
    DELETED: '삭제',
};

function normalizeStatus(value, fallback = 'DRAFT') {
    const normalized = String(value ?? '')
        .trim()
        .toUpperCase()
        .replace(/[\s-]+/g, '_');
    const alias = {
        CONFIRMED: 'REVIEW_REQUESTED',
    }[normalized] || normalized;

    return alias || fallback;
}

function statusBadge(status) {
    const normalizedStatus = normalizeStatus(status);
    const meta = {
        DRAFT: [STATUS_LABELS.DRAFT, 'text-bg-secondary', STATUS_LABELS.DRAFT],
        REVIEW_REQUESTED: [STATUS_LABELS.REVIEW_REQUESTED, 'text-bg-info', STATUS_LABELS.REVIEW_REQUESTED],
        REVIEWED: [STATUS_LABELS.REVIEWED, 'text-bg-primary', STATUS_LABELS.REVIEWED],
        APPROVED: [STATUS_LABELS.APPROVED, 'text-bg-success', STATUS_LABELS.APPROVED],
        REJECTED: [STATUS_LABELS.REJECTED, 'text-bg-danger', STATUS_LABELS.REJECTED],
        POSTED: [STATUS_LABELS.POSTED, 'text-bg-success', STATUS_LABELS.POSTED],
        CLOSED: [STATUS_LABELS.CLOSED, 'text-bg-dark', STATUS_LABELS.CLOSED],
        DELETED: [STATUS_LABELS.DELETED, 'text-bg-dark', STATUS_LABELS.DELETED],
        READY: ['READY', 'text-bg-success', '거래 생성 가능'],
        PROCESSED: ['PROCESSED', 'text-bg-primary', '거래 생성 완료'],
        ERROR: ['ERROR', 'text-bg-danger', '오류 발생'],
        VERIFY_ONLY: ['VERIFY_ONLY', 'text-bg-info', '원본 확인 전용'],
        NOT_READY: ['NOT_READY', 'text-bg-warning', '생성 필수값 보정 필요'],
        REVIEW_REQUIRED: ['REVIEW_REQUIRED', 'text-bg-info', '검토 필요'],
        PROCESSING: ['PROCESSING', 'text-bg-warning', '처리 중'],
        DUPLICATED: ['DUPLICATED', 'text-bg-secondary', '중복 검토'],
        UNCHANGED: ['UNCHANGED', 'text-bg-light text-dark border', '변경 없음'],
        UPDATED: ['UPDATED', 'text-bg-info', '수정 반영됨'],
    }[normalizedStatus] || [
        normalizedStatus || '-',
        'text-bg-secondary',
        normalizedStatus || '-',
    ];

    return `
        <span class="badge ${meta[1]}"
              title="${escapeHtml(meta[2])}">
            ${escapeHtml(meta[0])}
        </span>
    `;
}

function labelBadge(label) {
    return `
        <span class="badge text-bg-light border text-dark">
            ${escapeHtml(label || '-')}
        </span>
    `;
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
        source_key: '원본 키',
        evidence_date: '증빙일자',
        transaction_date: '거래일자',
        transaction_direction: '거래구분',
        business_unit: '사업구분',
        client_id: '거래처',
        project_id: '프로젝트',
        description: '적요',
        currency: '기준통화',
        exchange_rate: '환율',
        supply_amount: '공급가액',
        adjustment_amount: '조정금액',
        vat_amount: '부가세액',
        service_amount: '봉사료',
        withholding_amount: '원천세',
        total_amount: '합계금액',
        line_type: '라인유형',
        raw_item_date: '원본 품목일자',
        raw_item_name: '원본 품목명',
        raw_item_spec: '원본 규격',
        raw_item_quantity: '원본 수량',
        raw_item_unit_price: '원본 단가',
        raw_item_supply_amount: '원본 공급가액',
        raw_item_tax_amount: '원본 세액',
        raw_item_note: '원본 비고',
        item_date: '품목일자',
        item_name: '품목명',
        item_spec: '규격',
        specification: '규격',
        unit_name: '단위',
        unit_code: '단위코드',
        item_qty: '수량',
        quantity: '수량',
        item_price: '단가',
        unit_price: '단가',
        currency_code: '통화코드',
        foreign_unit_price: '외화단가',
        foreign_amount: '외화금액',
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
        counterparty_name: '상대 계좌명',
        counterparty_account_number: '상대 계좌번호',
        counterparty_bank: '상대 은행',
        deposit_amount: '입금액',
        withdraw_amount: '출금액',
        balance_amount: '잔액',
        header_row_no: '헤더 행번호',
        line_no: '라인 번호',
        line_row_type: '행 구분',
        debit: '차변',
        credit: '대변',
        line_ref_target: '참조대상',
        line_ref_id: '참조 ID',
    }[key] || key;
}

function voucherRefTypeLabel(value) {
    const key = String(value || '')
        .trim()
        .toUpperCase();

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
    }[key] || value || '미분류';
}

export {
    importSourceLabel,
    importTypeLabel,
    directionLabel,
    normalizeCodeKey,
    normalizeCodeRows,
    codeDisplayName,
    STATUS_LABELS,
    normalizeStatus,
    statusBadge,
    labelBadge,
    readinessFieldLabel,
    voucherRefTypeLabel,
};

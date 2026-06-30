import { escapeHtml } from './utils.js';

const __DATA_CREATE_CODE_OPTIONS__ = new Proxy({}, {
    get(_target, property) {
        const source = globalThis.__DATA_CREATE_CODE_OPTIONS__ || {};
        return source[property];
    },
});

const DISPLAY_CODE_FIELDS = {
    business_unit: 'BUSINESS_UNIT',
    transaction_type: 'TRANSACTION_TYPE',
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
        .filter((row) => (
            row.code !== ''
            && row.is_active === 1
        ));
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

function statusBadge(status) {
    const meta = {
        READY: [
            'READY',
            'text-bg-success',
            '거래 생성 가능',
        ],

        PROCESSED: [
            'PROCESSED',
            'text-bg-primary',
            '거래 생성 완료',
        ],

        ERROR: [
            'ERROR',
            'text-bg-danger',
            '처리 오류',
        ],

        VERIFY_ONLY: [
            'VERIFY_ONLY',
            'text-bg-info',
            '원본 확인 전용',
        ],

        NOT_READY: [
            'NOT_READY',
            'text-bg-warning',
            '생성 필수값 보정 필요',
        ],

        REVIEW_REQUIRED: [
            'REVIEW_REQUIRED',
            'text-bg-info',
            '검토 필요',
        ],

        PROCESSING: [
            'PROCESSING',
            'text-bg-warning',
            '처리 중',
        ],

        DUPLICATED: [
            'DUPLICATED',
            'text-bg-secondary',
            '중복 검출',
        ],

        UNCHANGED: [
            'UNCHANGED',
            'text-bg-light text-dark border',
            '변경 없음',
        ],

        UPDATED: [
            'UPDATED',
            'text-bg-info',
            '업데이트됨',
        ],

        DELETED: [
            'DELETED',
            'text-bg-dark',
            '삭제됨',
        ],
    }[status] || [
        status || '-',
        'text-bg-secondary',
        status || '-',
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

        source_key: '원본 연계키',

        evidence_date: '증빙일자',

        transaction_date: '거래일자',
        transaction_direction: '거래구분',
        transaction_type: '거래유형',

        business_unit: '사업구분',

        client_id: '거래처',

        project_id: '프로젝트',

        description: '적요',

        currency: '기본통화',

        exchange_rate: '기본환율',

        supply_amount: '공급가액',

        adjustment_amount: '가감금액',

        vat_amount: '세액(부가세)',

        service_amount: '비과세(봉사료)',

        withholding_amount: '원천세',

        total_amount: '합계금액',

        line_type: '라인유형',

        raw_item_date: '품목일자',

        raw_item_name: '품목명',

        raw_item_spec: '품목규격',

        raw_item_quantity: '품목수량',

        raw_item_unit_price: '품목단가',

        raw_item_supply_amount: '품목공급가액',

        raw_item_tax_amount: '품목세액',

        raw_item_note: '품목비고',

        item_date: '발생일',

        item_name: '품목명',

        item_spec: '규격',
        specification: '규격',

        unit_name: '단위',
        unit_code: '단위',

        item_qty: '수량',
        quantity: '수량',

        item_price: '단가',
        unit_price: '단가',

        currency_code: '통화',

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
    }[key] || value || '참조';
}

export {
    importSourceLabel,
    importTypeLabel,
    directionLabel,
    normalizeCodeKey,
    normalizeCodeRows,
    codeDisplayName,
    statusBadge,
    labelBadge,
    readinessFieldLabel,
    voucherRefTypeLabel,
};

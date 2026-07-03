export function importSourceLabel(value) {
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

export function importTypeLabel(value) {
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

export function directionLabel(value) {
    const key = String(value || '').toUpperCase();
    return {
        FUND: '자금',
        IN: '자금',
        OUT: '자금',
        INCOME: '수익',
        EXPENSE: '비용',
        PURCHASE: '비용',
        SALES: '수익',
        BANK: '자금',
        GENERAL: '일반',
    }[key] || value || '-';
}

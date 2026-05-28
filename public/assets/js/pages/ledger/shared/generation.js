export function processingType(row) {
    const explicit = String(row?.processing_type || '').trim().toUpperCase();
    if (explicit) return explicit;

    const type = String(row?.import_type || row?.source_type || '')
        .trim()
        .toUpperCase();

    if (type === 'CARD_HOMETAX') return 'VERIFY_ONLY';
    if (['CARD_STATEMENT', 'CARD_APPROVAL'].includes(type)) return 'TRANSACTION';
    if (type === 'BANK_TRANSACTION') return 'BANK_FLOW';

    return 'TRANSACTION';
}

export function processingLabel(row) {
    const explicit = String(row?.processing_label || '').trim();
    if (explicit) return explicit;

    return {
        TRANSACTION: '거래 생성',
        RECONCILIATION: '카드/계좌 대사',
        VERIFY_ONLY: '원본 확인',
        BANK_FLOW: '입출금 흐름',
        VOUCHER: '전표 생성',
    }[processingType(row)] || '-';
}

export function generationTarget(row) {
    const explicit = String(row?.generation_target || '')
        .trim()
        .toUpperCase();

    if (explicit) return explicit;

    const type = String(row?.import_type || row?.source_type || '')
        .trim()
        .toUpperCase();

    if (type === 'CARD_STATEMENT' || type === 'CARD_APPROVAL') {
        return 'TRANSACTION_AND_VOUCHER';
    }

    if (type === 'CARD_HOMETAX') return 'VERIFY_ONLY';
    if (type === 'BANK_TRANSACTION') return 'RECONCILIATION_ONLY';

    if (processingType(row) === 'TRANSACTION') {
        return 'TRANSACTION_HEADER';
    }

    return processingType(row);
}

export function generationLabel(row) {
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

export function generationObjects(row) {
    const objects = Array.isArray(row?.generation_objects)
        ? row.generation_objects
        : (Array.isArray(row?.processing_objects)
            ? row.processing_objects
            : []);

    if (objects.length > 0) return objects;

    return {
        TRANSACTION_HEADER: [
            'TRANSACTION_HEADER',
        ],

        TRANSACTION_FULL: [
            'TRANSACTION_HEADER',
            'TRANSACTION_LINE',
        ],

        VOUCHER_HEADER: [
            'VOUCHER_HEADER',
        ],

        VOUCHER_FULL: [
            'VOUCHER_HEADER',
            'VOUCHER_LINE',
        ],

        TRANSACTION_AND_VOUCHER: [
            'TRANSACTION_HEADER',
            'TRANSACTION_LINE',
            'VOUCHER_HEADER',
            'VOUCHER_LINE',
        ],

        RECONCILIATION_ONLY: [
            'RECONCILIATION',
        ],

        VERIFY_ONLY: [
            'TAX_VERIFY',
            'RECONCILIATION',
        ],
    }[generationTarget(row)] || [];
}

export function generationObjectText(row) {
    return Array.from(
        new Set(
            generationObjects(row).map((object) => ({
                TRANSACTION_HEADER: '거래',
                TRANSACTION_LINE: '거래',

                VOUCHER_HEADER: '전표',
                VOUCHER_LINE: '전표',

                RECONCILIATION: '대사',
                TAX_VERIFY: '원본검증',
                BANK_FLOW: '입출금',
            }[object] || object))
        )
    ).join(' / ') || '-';
}

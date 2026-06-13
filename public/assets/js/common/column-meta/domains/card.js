import { defineDomainRegistry } from '../helpers.js';

export const CardColumnRegistry = defineDomainRegistry('card', [
    { key: 'sort_no', label: '순번', tableVisible: true, tableDefault: true, excelDownload: true, searchable: false, width: 80, type: 'number' },
    { key: 'card_name', label: '카드명', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, searchDefault: true, width: 220, type: 'text' },
    { key: 'client_name', label: '카드사', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 180, type: 'text' },
    { key: 'client_id', label: '카드사 ID', tableVisible: false, tableDefault: false, searchable: false, width: 140, type: 'number' },
    { key: 'card_number', label: '카드번호', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 180, type: 'text' },
    { key: 'account_name', label: '결제계좌', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 180, type: 'text' },
    { key: 'account_id', label: '계좌 ID', tableVisible: false, tableDefault: false, searchable: false, width: 140, type: 'number' },
    { key: 'expiry_year', label: '유효기간년', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 110, type: 'number' },
    { key: 'expiry_month', label: '유효기간월', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 110, type: 'number' },
    { key: 'limit_amount', label: '한도금액', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 140, type: 'amount' },
    { key: 'card_file', label: '카드이미지', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
    { key: 'note', label: '비고', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 220, type: 'textarea' },
    { key: 'memo', label: '메모', tableVisible: false, tableDefault: false, excelTemplate: true, excelDownload: true, searchable: true, width: 220, type: 'textarea' },
    { key: 'is_active', label: '상태', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, sortable: true, width: 90, type: 'boolean' },
    { key: 'created_at', label: '등록일시', tableVisible: false, tableDefault: false, searchable: true, searchDate: true, width: 160, type: 'datetime' },
    { key: 'created_by_name', label: '등록자', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
    { key: 'updated_at', label: '수정일시', tableVisible: false, tableDefault: false, searchable: true, searchDate: true, width: 160, type: 'datetime' },
    { key: 'updated_by_name', label: '수정자', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
    { key: 'deleted_at', label: '삭제일시', tableVisible: false, tableDefault: false, searchable: false, width: 160, type: 'datetime' },
    { key: 'deleted_by_name', label: '삭제자', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
]);

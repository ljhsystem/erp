import { defineDomainRegistry } from '../helpers.js';

export const LedgerJournalRuleColumnRegistry = defineDomainRegistry('ledger-journal-rule', [
    { key: 'sort_no', label: '순번', tableVisible: true, tableDefault: true, excelDownload: true, searchable: false, width: 80, type: 'number' },
    { key: 'rule_code', label: '규칙코드', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, searchDefault: true, width: 140, type: 'text' },
    { key: 'rule_name', label: '규칙명', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 200, type: 'text' },
    { key: 'business_unit', label: '사업구분', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 120, type: 'code' },
    { key: 'transaction_type', label: '거래유형', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 120, type: 'code' },
    { key: 'transaction_direction', label: '거래구분', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 120, type: 'code' },
    { key: 'client_type', label: '거래처구분', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 120, type: 'code' },
    { key: 'import_type', label: '자료유형', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 120, type: 'code' },
    { key: 'debit_account_id', label: '차변계정', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 180, type: 'text' },
    { key: 'credit_account_id', label: '대변계정', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 180, type: 'text' },
    { key: 'vat_account_id', label: '부가세계정', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 180, type: 'text' },
    { key: 'description', label: '설명/적요', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 220, type: 'textarea' },
    { key: 'is_active', label: '상태', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 90, type: 'boolean' },
    { key: 'created_at', label: '생성일시', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 160, type: 'datetime' },
    { key: 'created_by', label: '생성자', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 120, type: 'text' },
    { key: 'updated_at', label: '수정일시', tableVisible: false, tableDefault: false, excelDownload: true, searchable: true, searchDate: true, width: 160, type: 'datetime' },
    { key: 'updated_by', label: '수정자', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 120, type: 'text' },
    { key: 'deleted_at', label: '삭제일시', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 160, type: 'datetime' },
    { key: 'deleted_by', label: '삭제자', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 120, type: 'text' },
]);

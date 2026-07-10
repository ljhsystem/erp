import { defineDomainRegistry } from '../helpers.js';

export const LedgerAccountColumnRegistry = defineDomainRegistry('account-subject-main', [
    { key: 'sort_no', label: '순번', tableVisible: true, tableDefault: true, excelDownload: true, searchable: false, width: 80, type: 'number' },
    { key: 'account_code', label: '계정코드', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, searchDefault: true, width: 140, type: 'text' },
    { key: 'account_name', label: '계정과목명', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 220, type: 'text' },
    { key: 'parent_name', label: '상위계정', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 180, type: 'text' },
    { key: 'account_group', label: '계정구분', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 120, type: 'text' },
    { key: 'normal_balance', label: '정상잔액', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 110, type: 'text' },
    { key: 'level', label: '레벨', tableVisible: true, tableDefault: true, excelDownload: true, searchable: false, width: 80, type: 'number' },
    { key: 'is_posting', label: '전표입력', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 100, type: 'boolean' },
    { key: 'allow_sub_account', label: '보조계정', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 100, type: 'boolean' },
    { key: 'is_active', label: '상태', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 90, type: 'boolean' },
    { key: 'note', label: '비고', tableVisible: false, tableDefault: false, excelTemplate: true, excelDownload: true, searchable: true, width: 220, type: 'textarea' },
    { key: 'memo', label: '메모', tableVisible: false, tableDefault: false, excelDownload: true, searchable: true, width: 220, type: 'textarea' },
    { key: 'created_at', label: '생성일시', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 160, type: 'datetime' },
    { key: 'created_by_name', label: '생성자', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 120, type: 'text' },
    { key: 'updated_at', label: '수정일시', tableVisible: false, tableDefault: false, excelDownload: true, searchable: true, searchDate: true, width: 160, type: 'datetime' },
    { key: 'updated_by_name', label: '수정자', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 120, type: 'text' },
    { key: 'deleted_at', label: '삭제일시', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 160, type: 'datetime' },
    { key: 'deleted_by_name', label: '삭제자', tableVisible: false, tableDefault: false, excelDownload: true, searchable: false, width: 120, type: 'text' },
]);

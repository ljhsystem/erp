import { defineDomainRegistry } from '../helpers.js';

export const CodeColumnRegistry = defineDomainRegistry('code', [
    { key: 'sort_no', label: '순번', tableVisible: true, tableDefault: true, excelDownload: true, searchable: false, width: 80, type: 'number' },
    { key: 'code_group', label: '코드그룹', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 140, type: 'text' },
    { key: 'group_name', label: '그룹명', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 160, type: 'text' },
    { key: 'code', label: '코드', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 120, type: 'text' },
    { key: 'code_name', label: '코드명', required: true, tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, searchDefault: true, width: 160, type: 'text' },
    { key: 'note', label: '비고', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, width: 220, type: 'textarea' },
    { key: 'memo', label: '메모', tableVisible: false, tableDefault: false, excelTemplate: true, excelDownload: true, searchable: true, width: 220, type: 'textarea' },
    { key: 'is_active', label: '상태', tableVisible: true, tableDefault: true, excelTemplate: true, excelDownload: true, searchable: true, sortable: true, width: 90, type: 'boolean' },
    { key: 'extra_data', label: '추가속성', tableVisible: false, tableDefault: false, excelTemplate: true, excelDownload: true, searchable: false, width: 220, type: 'json' },
    { key: 'created_at', label: '생성일', tableVisible: false, tableDefault: false, searchable: false, width: 160, type: 'datetime' },
    { key: 'created_by_name', label: '생성자', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
    { key: 'updated_at', label: '수정일', tableVisible: false, tableDefault: false, searchable: false, width: 160, type: 'datetime' },
    { key: 'updated_by_name', label: '수정자', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
    { key: 'deleted_at', label: '삭제일', tableVisible: false, tableDefault: false, searchable: false, width: 160, type: 'datetime' },
    { key: 'deleted_by_name', label: '삭제자', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
]);

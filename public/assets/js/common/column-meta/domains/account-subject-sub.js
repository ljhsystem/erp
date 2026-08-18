import { defineDomainRegistry } from '../helpers.js';

export const AccountSubjectSubColumnRegistry = defineDomainRegistry('account-subject-sub', [
    { key: 'id', label: 'ID', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
    { key: 'account_id', label: '계정과목 연결', tableVisible: false, tableDefault: false, searchable: false, width: 160, type: 'text' },
    { key: 'sub_code', label: '보조계정코드', required: true, tableVisible: true, tableDefault: true, searchable: true, width: 160, type: 'text' },
    { key: 'sub_name', label: '보조계정 대상', tableVisible: true, tableDefault: true, searchable: true, width: 180, type: 'text' },
    { key: 'is_required', label: '필수구분', tableVisible: true, tableDefault: true, searchable: true, width: 110, type: 'boolean' },
    { key: 'created_at', label: '등록일시', tableVisible: false, tableDefault: false, searchable: false, width: 160, type: 'datetime' },
    { key: 'created_by_name', label: '등록자', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
    { key: 'updated_at', label: '수정일시', tableVisible: false, tableDefault: false, searchable: false, width: 160, type: 'datetime' },
    { key: 'updated_by_name', label: '수정자', tableVisible: false, tableDefault: false, searchable: false, width: 120, type: 'text' },
]);

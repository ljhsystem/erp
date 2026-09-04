export const WORK_TEAM_API = {
    LIST: '/api/settings/base-info/work-team/list',
    DETAIL: '/api/settings/base-info/work-team/detail',
    SAVE: '/api/settings/base-info/work-team/save',
    DELETE: '/api/settings/base-info/work-team/delete',
    CLIENT_SEARCH: '/api/settings/base-info/client/search-picker',
    TRASH: '/api/settings/base-info/work-team/trash',
    RESTORE: '/api/settings/base-info/work-team/restore',
    RESTORE_BULK: '/api/settings/base-info/work-team/restore-bulk',
    RESTORE_ALL: '/api/settings/base-info/work-team/restore-all',
    PURGE: '/api/settings/base-info/work-team/purge',
    PURGE_BULK: '/api/settings/base-info/work-team/purge-bulk',
    PURGE_ALL: '/api/settings/base-info/work-team/purge-all',
    REORDER: '/api/settings/base-info/work-team/reorder',
    EXCEL_UPLOAD: '/api/settings/base-info/work-team/excel-upload',
    EXCEL_DOWNLOAD: '/api/settings/base-info/work-team/excel',
    EXCEL_TEMPLATE: '/api/settings/base-info/work-team/template'
};

export const WORK_TEAM_COLUMN_MAP = {
    sort_no: { label: '순번', visible: true, className: 'text-center' },
    team_name: { label: '팀명', visible: true },
    business_unit: { label: '사업구분', visible: true },
    team_leader_client_id: { label: '팀장', visible: true },
    note: { label: '비고', visible: true },
    memo: { label: '메모', visible: false },
    is_active: { label: '상태', visible: true, className: 'text-center' },
    created_at: { label: '등록일시', visible: false },
    created_by: { label: '등록자', visible: false, type: 'actor' },
    updated_at: { label: '수정일시', visible: false },
    updated_by: { label: '수정자', visible: false, type: 'actor' },
    deleted_at: { label: '삭제일시', visible: false },
    deleted_by: { label: '삭제자', visible: false, type: 'actor' }
};

export const WORK_TEAM_DATE_OPTIONS = [
    { value: 'created_at', label: '등록일시' },
    { value: 'updated_at', label: '수정일시' }
];

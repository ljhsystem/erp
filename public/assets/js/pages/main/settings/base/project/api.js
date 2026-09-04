export const API = {
    LIST: '/api/settings/base-info/project/list',
    SAVE: '/api/settings/base-info/project/save',
    DELETE: '/api/settings/base-info/project/delete',
    DETAIL: '/api/settings/base-info/project/detail',
    TRASH: '/api/settings/base-info/project/trash',
    RESTORE: '/api/settings/base-info/project/restore',
    PURGE: '/api/settings/base-info/project/purge',
    PURGE_ALL: '/api/settings/base-info/project/purge-all',
    REORDER: '/api/settings/base-info/project/reorder',
    EXCEL_UPLOAD: '/api/settings/base-info/project/excel-upload',
    EXCEL_TEMPLATE: '/api/settings/base-info/project/template',
    EXCEL_DOWNLOAD: '/api/settings/base-info/project/download',
    EMPLOYEE_SEARCH: '/api/settings/organization/employee/search-picker',
    CLIENT_SEARCH: '/api/settings/base-info/client/search-picker',
    CLIENT_SAVE: '/api/settings/base-info/client/save',
    PROJECT_VALUE_SEARCH: '/api/settings/base-info/project/distinct-values',
};

export const DATE_OPTIONS = [
    { value: 'start_date', label: '착공일자' },
    { value: 'completion_date', label: '준공일자' },
    { value: 'contract_date', label: '계약일자' },
    { value: 'permit_date', label: '허가일자' },
    { value: 'bid_notice_date', label: '입찰공고일' },
    { value: 'updated_at', label: '수정일자' },
];

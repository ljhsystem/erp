export const CLIENT_QUICK_API = '/api/settings/base-info/client/save';

export const API = {
    LIST: '/api/settings/base-info/client/list',
    DETAIL: '/api/settings/base-info/client/detail',
    SAVE: '/api/settings/base-info/client/save',
    DELETE: '/api/settings/base-info/client/delete',
    COMPANY_NAME_HISTORY_DELETE: '/api/settings/base-info/client/company-name-history/delete',
    TRASH: '/api/settings/base-info/client/trash',
    RESTORE: '/api/settings/base-info/client/restore',
    RESTORE_BULK: '/api/settings/base-info/client/restore-bulk',
    RESTORE_ALL: '/api/settings/base-info/client/restore-all',
    PURGE: '/api/settings/base-info/client/purge',
    PURGE_BULK: '/api/settings/base-info/client/purge-bulk',
    PURGE_ALL: '/api/settings/base-info/client/purge-all',
    REORDER: '/api/settings/base-info/client/reorder',
    EXCEL_UPLOAD: '/api/settings/base-info/client/excel-upload',
    EXCEL_DOWNLOAD: '/api/settings/base-info/client/download',
    EXCEL_TEMPLATE: '/api/settings/base-info/client/template',
    SEARCH_PICKER: '/api/settings/base-info/client/search-picker',
    ACCOUNTS: '/api/ledger/account/list',
    CODE_OPTIONS: '/api/settings/system/code/list',
};

export const DATE_OPTIONS = [
    { value: 'registration_date', label: '등록일자' },
    { value: 'updated_at', label: '수정일자' },
];

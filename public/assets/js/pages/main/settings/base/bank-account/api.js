export const API = {
    LIST: '/api/settings/base-info/bank-account/list',
    DETAIL: '/api/settings/base-info/bank-account/detail',
    SEARCH_PICKER: '/api/settings/base-info/bank-account/search-picker',
    SAVE: '/api/settings/base-info/bank-account/save',
    DELETE: '/api/settings/base-info/bank-account/delete',
    TRASH: '/api/settings/base-info/bank-account/trash',
    RESTORE: '/api/settings/base-info/bank-account/restore',
    RESTORE_BULK: '/api/settings/base-info/bank-account/restore-bulk',
    RESTORE_ALL: '/api/settings/base-info/bank-account/restore-all',
    PURGE: '/api/settings/base-info/bank-account/purge',
    PURGE_BULK: '/api/settings/base-info/bank-account/purge-bulk',
    PURGE_ALL: '/api/settings/base-info/bank-account/purge-all',
    REORDER: '/api/settings/base-info/bank-account/reorder',
    EXCEL_UPLOAD: '/api/settings/base-info/bank-account/excel-upload',
    EXCEL_DOWNLOAD: '/api/settings/base-info/bank-account/download',
    EXCEL_TEMPLATE: '/api/settings/base-info/bank-account/template',
};

export const ACCOUNT_COLUMN_MAP = {
    sort_no: { label: '순번', visible: true },
    account_name: { label: '계좌명', visible: true, width: '220px', className: 'account-nowrap' },
    bank_name: { label: '은행명', visible: true, width: '160px', className: 'account-nowrap' },
    account_number: { label: '계좌번호', visible: true, width: '165px', className: 'account-nowrap' },
    account_holder: { label: '예금주', visible: true, width: '130px', className: 'account-nowrap' },
    account_type: { label: '계좌구분', visible: true, width: '115px', className: 'account-nowrap' },
    currency: { label: '통화', visible: false },
    bank_file: { label: '통장사본', visible: false },
    note: { label: '비고', visible: true, className: 'account-note-cell' },
    memo: { label: '메모', visible: false },
    is_active: { label: '상태', visible: true },
    created_at: { label: '등록일시', visible: false },
    created_by_name: { label: '등록자', visible: false },
    updated_at: { label: '수정일시', visible: false },
    updated_by_name: { label: '수정자', visible: false },
    deleted_at: { label: '삭제일시', visible: false },
    deleted_by_name: { label: '삭제자', visible: false },
};

export const DATE_OPTIONS = [
    { value: 'created_at', label: '등록일자' },
    { value: 'updated_at', label: '수정일자' },
];

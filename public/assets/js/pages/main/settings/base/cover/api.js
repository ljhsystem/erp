export const API = {
    LIST: '/api/settings/base-info/cover/list',
    PUBLIC_LIST: '/api/settings/base-info/cover/public',
    DETAIL: '/api/settings/base-info/cover/detail',
    SAVE: '/api/settings/base-info/cover/save',
    DELETE: '/api/settings/base-info/cover/delete',
    TRASH: '/api/settings/base-info/cover/trash',
    RESTORE: '/api/settings/base-info/cover/restore',
    RESTORE_BULK: '/api/settings/base-info/cover/restore-bulk',
    RESTORE_ALL: '/api/settings/base-info/cover/restore-all',
    PURGE: '/api/settings/base-info/cover/purge',
    PURGE_BULK: '/api/settings/base-info/cover/purge-bulk',
    PURGE_ALL: '/api/settings/base-info/cover/purge-all',
    REORDER: '/api/settings/base-info/cover/reorder'
};

export const COVER_COLUMN_MAP = {
    sort_no: { label: '순번', visible: true },
    src: { label: '이미지경로', visible: true },
    year: { label: '해당연도(Year)', visible: true },
    title: { label: '타이틀(Title)', visible: true },
    alt: { label: '대체문구(Alt)', visible: true },
    description: { label: '설명(Description)', visible: true },
    created_at: { label: '등록일시', visible: false },
    created_by: { label: '등록자', visible: false, type: 'actor' },
    updated_at: { label: '수정일시', visible: false },
    updated_by: { label: '수정자', visible: false, type: 'actor' },
    is_active: { label: '상태', visible: true }
};

export const DATE_OPTIONS = [
    { value: 'year', label: '연도' }
];

export const BRAND_API = {
    ACTIVE: '/api/settings/base-info/brand/active-type',
    SAVE: '/api/settings/base-info/brand/save',
    LIST: '/api/settings/base-info/brand/list',
    ACTIVATE: '/api/settings/base-info/brand/updatestatus',
    PURGE: '/api/settings/base-info/brand/purge',
};

export const BRAND_ASSETS = {
    main_logo: {
        input: "[name='main_logo']",
        preview: '#preview_main_logo',
        emptyText: '등록된 메인 로고가 없습니다.'
    },
    print_logo: {
        input: "[name='print_logo']",
        preview: '#preview_print_logo',
        emptyText: '등록된 인쇄용 로고가 없습니다.'
    },
    favicon: {
        input: "[name='favicon']",
        preview: '#preview_favicon',
        emptyText: '등록된 파비콘이 없습니다.'
    }
};

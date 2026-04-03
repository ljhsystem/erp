// 경로: PROJECT_ROOT . '/assets/dashboard/settings/system/site.js'
console.log('[SYSTEM SITE] JS LOADED');
document.addEventListener('DOMContentLoaded', () => {
    loadSiteSettings();
    bindSiteSettingForm();
});

/* ============================================================
 * 1. 설정값 로드
 * ============================================================ */
function loadSiteSettings() {
    fetch('/api/settings/system/site/get')
        .then(res => res.json())
        .then(res => {
            if (!res.success) return;

            const data = res.data || {};

            /* =====================
               기본 정보
            ===================== */
            setValue('page_title', data.page_title);
            setValue('site_title', data.site_title);
            setValue('home_intro_title', data.home_intro_title);
            setValue('home_intro_description', data.home_intro_description);
            setValue('home_intro_url', data.home_intro_url);
            setValue('footer_text', data.footer_text);

            /* =====================
               UI / 표시 설정
            ===================== */
            setValue('ui_skin', data.ui_skin);
            setValue('theme_mode', data.theme_mode);
            setValue('site_font_family', data.site_font_family);
            setValue('font_scale', data.font_scale);

            setValue('table_density', data.table_density);
            setValue('card_density', data.card_density);

            setValue('radius_style', data.radius_style);
            setValue('button_style', data.button_style);

            setValue('row_focus', data.row_focus);
            setValue('link_underline', data.link_underline);

            setValue('icon_scale', data.icon_scale);
            setValue('alert_style', data.alert_style);

            setValue('sidebar_default', data.sidebar_default);
            setValue('motion_mode', data.motion_mode);
        })
        .catch(err => {
            console.error('[SITE] load failed', err);
        });
}

/* ============================================================
 * 2. 저장 이벤트 바인딩
 * ============================================================ */
function bindSiteSettingForm() {
    const form = document.getElementById('site-setting-form');
    if (!form) return;

    form.addEventListener('submit', e => {
        e.preventDefault();

        const payload = {
            /* =====================
               기본 정보
            ===================== */
            page_title: getValue('page_title'),
            site_title: getValue('site_title'),
            home_intro_title: getValue('home_intro_title'),
            home_intro_description: getValue('home_intro_description'),
            home_intro_url: getValue('home_intro_url'),
            footer_text: getValue('footer_text'),

            /* =====================
               UI / 표시 설정
            ===================== */
            ui_skin: getValue('ui_skin'),
            theme_mode: getValue('theme_mode'),
            site_font_family: getValue('site_font_family'),
            font_scale: getValue('font_scale'),

            table_density: getValue('table_density'),
            card_density: getValue('card_density'),

            radius_style: getValue('radius_style'),
            button_style: getValue('button_style'),

            row_focus: getValue('row_focus'),
            link_underline: getValue('link_underline'),

            icon_scale: getValue('icon_scale'),
            alert_style: getValue('alert_style'),

            sidebar_default: getValue('sidebar_default'),
            motion_mode: getValue('motion_mode')
        };

        saveSiteSettings(payload);
    });
}

/* ============================================================
 * 3. 저장 처리
 * ============================================================ */
function saveSiteSettings(payload) {
    fetch('/api/settings/system/site/save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if (!res.success) {
            alert(res.message || '저장 실패');
            return;
        }
        //toastSuccess('사이트 설정이 저장되었습니다.');
        location.reload();
    })
    .catch(err => {
        console.error('[SITE] save failed', err);
        alert('서버 오류가 발생했습니다.');
    });
}

/* ============================================================
 * 4. 유틸
 * ============================================================ */
function getValue(name) {
    const el = document.querySelector(`[name="${name}"]`);
    return el ? el.value.trim() : '';
}

function setValue(name, value) {
    const el = document.querySelector(`[name="${name}"]`);
    if (!el) return;

    if (value !== undefined && value !== null && value !== '') {
        el.value = value;
    }
}

function toastSuccess(message) {
    alert(message); // 추후 Toast 컴포넌트로 교체 가능
}

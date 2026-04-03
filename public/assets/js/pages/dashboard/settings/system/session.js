// 경로: PROJECT_ROOT . '/assets/dashboard/settings/system/session.js'
console.log('[SYSTEM SESSION] JS LOADED');

document.addEventListener('DOMContentLoaded', () => {
    bindPlusMinusButtons();
    loadSessionSettings();
    bindSessionSave();
});

/* =====================================
 * 1. + / - 버튼
 * ===================================== */
function bindPlusMinusButtons() {
    document.addEventListener('click', e => {
        const btn = e.target.closest('button[data-target]');
        if (!btn) return;

        const input = document.querySelector(btn.dataset.target);
        if (!input) return;

        const step = parseInt(btn.dataset.step, 10) || 0;
        const min  = parseInt(input.min || '1', 10);
        const max  = parseInt(input.max || '1440', 10);

        let value = parseInt(input.value || min, 10);
        value += step;

        if (value < min) value = min;
        if (value > max) value = max;

        input.value = value;

        // 세션 시간 변경 시 알림 최대값 동기화
        if (input.id === 'session_timeout') {
            const alertInput = document.getElementById('session_alert');
            if (alertInput) {
                alertInput.max = value;
                if (parseInt(alertInput.value || '1', 10) > value) {
                    alertInput.value = value;
                }
            }
        }
    });
}

/* =====================================
 * 2. 세션 설정 조회
 * ===================================== */
function loadSessionSettings() {
    fetch('/api/settings/system/session/get')
        .then(res => res.json())
        .then(res => {
            if (!res.success) return;

            const d = res.data || {};

            setValue('session_timeout', d.session_timeout !== undefined ? d.session_timeout : 30);
            setValue('session_alert', d.session_alert !== undefined ? d.session_alert : 5);
            setValue('session_sound', d.session_sound !== undefined ? d.session_sound : 'default.mp3');
            

            updateSoundPreview();
        })
        .catch(err => console.error('[SESSION] load failed', err));
}

/* =====================================
 * 3. 저장
 * ===================================== */
function bindSessionSave() {
    const form = document.getElementById('session-setting-form');
    if (!form) return;

    form.addEventListener('submit', e => {
        e.preventDefault();

        const payload = {
            session_timeout: getValue('session_timeout'),
            session_alert:   getValue('session_alert'),
            session_sound:   getValue('session_sound')
        };

        fetch('/api/settings/system/session/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert(res.message || '저장 실패');
                return;
            }
            alert('세션 설정이 저장되었습니다.\n다음 로그인부터 적용됩니다.');
        })
        .catch(err => {
            console.error('[SESSION] save error', err);
            alert('서버 오류');
        });
    });
}

/* =====================================
 * 4. 사운드
 * ===================================== */
function updateSoundPreview() {
    const select = document.getElementById('session_sound');
    const audio  = document.getElementById('sound-preview');
    if (!select || !audio) return;

    audio.src = '/assets/sounds/' + select.value;
}

function playSoundPreview() {
    const audio = document.getElementById('sound-preview');
    if (!audio) return;
    audio.currentTime = 0;
    audio.play().catch(() => {});
}

/* =====================================
 * Util
 * ===================================== */
function getValue(name) {
    const el = document.querySelector(`[name="${name}"]`);
    return el ? el.value.trim() : '';
}

function setValue(name, value) {
    const el = document.querySelector(`[name="${name}"]`);
    if (el) el.value = value;
}

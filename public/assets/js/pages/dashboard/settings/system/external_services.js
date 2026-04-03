/**
 * 경로: /assets/js/pages/dashboard/settings/system/external_services.js
 * 설명: 시스템 설정 > 외부 서비스 연동 (Synology Calendar 등)
 * ⚠️ submit 금지 / AJAX 전용
 */

document.addEventListener('DOMContentLoaded', () => {
    loadExternalServiceSettings();
    bindExternalServiceSave();
});

/* ============================================================
 * 1. 설정 조회
 * ============================================================ */
function loadExternalServiceSettings() {
    fetch('/api/settings/system/external-services/get', {
        credentials: 'same-origin'
    })
        .then(res => res.json())
        .then(json => {
            if (!json.success) {
                alert('외부 서비스 설정을 불러오지 못했습니다.');
                return;
            }

            const data = json.data || {};

            setCheckbox('synology_enabled', data.synology_enabled);
            setValue('synology_host', data.synology_host);
            setValue('synology_caldav_path', data.synology_caldav_path);
            setCheckbox('synology_ssl_verify', data.synology_ssl_verify);
        })
        .catch(err => {
            console.error('[external-services] load failed', err);
            alert('외부 서비스 설정 조회 중 오류 발생');
        });
}

/* ============================================================
 * 2. 설정 저장 (버튼 클릭)
 * ============================================================ */
function bindExternalServiceSave() {
    const btn = document.getElementById('btn-save-external-service');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const payload = {
            synology_enabled: isChecked('synology_enabled') ? 1 : 0,
            synology_host: getValue('synology_host'),
            synology_caldav_path: getValue('synology_caldav_path'),
            synology_ssl_verify: isChecked('synology_ssl_verify') ? 1 : 0
        };

        // 간단한 필수값 체크
        if (payload.synology_enabled) {
            if (!payload.synology_host) {
                alert('Synology 서버 주소를 입력하세요.');
                return;
            }
            if (!payload.synology_caldav_path) {
                alert('CalDAV 경로를 입력하세요.');
                return;
            }
        }

        btn.disabled = true;
        btn.innerText = '저장 중...';

        fetch('/api/settings/system/external-services/save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(json => {
                if (!json.success) {
                    throw new Error(json.message || '저장 실패');
                }

                alert('외부 서비스 연동 설정이 저장되었습니다.');
            })
            .catch(err => {
                console.error('[external-services] save failed', err);
                alert('외부 서비스 설정 저장 중 오류 발생');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerText = '저장';
            });
    });
}

/* ============================================================
 * 3. 유틸
 * ============================================================ */
function getValue(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el && value !== undefined && value !== null) {
        el.value = value;
    }
}

function isChecked(id) {
    const el = document.getElementById(id);
    return el ? el.checked : false;
}

function setCheckbox(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.checked = value === 1 || value === '1' || value === true;
    }
}

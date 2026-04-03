/**
 * 경로: /public/assets/js/pages/dashboard/settings/system/api.js
 * 설명: 시스템 설정 > 외부 연동(API) 설정
 */

document.addEventListener('DOMContentLoaded', () => {
    loadApiSettings();
    bindApiFormSubmit();
    bindRegenerateButtons();
    bindStepperButtons();
    bindApiPingTest();
    bindSecretToggle();
    bindCopyApiToPing();
});

/* ============================================================
 * 1. 설정 조회
 * ============================================================ */
function loadApiSettings() {
    fetch('/api/settings/system/api/get')
        .then(res => res.json())
        .then(json => {
            if (!json.success) {
                alert('API 설정을 불러오지 못했습니다.');
                return;
            }

            const data = json.data || {};

            setCheckbox('api_enabled', data.api_enabled);
            setValue('api_key', data.api_key);
            setValue('api_secret', data.api_secret);
            setValue('api_token_ttl', data.api_token_ttl || 3600);
            setValue('api_ratelimit', data.api_ratelimit || 60);
            setValue('api_ip_list', data.api_ip_whitelist);
            setValue('api_callback', data.api_callback_url);
        })
        .catch(err => {
            console.error(err);
            alert('API 설정 조회 중 오류 발생');
        });
}

/* ============================================================
 * 2. 설정 저장
 * ============================================================ */
function bindApiFormSubmit() {
    const form = document.getElementById('api-setting-form');
    if (!form) return;

    form.addEventListener('submit', e => {
        e.preventDefault();

        const payload = {
            api_enabled: isChecked('api_enabled') ? 1 : 0,
            api_key: getValue('api_key'),
            api_secret: getValue('api_secret'),
            api_token_ttl: getValue('api_token_ttl'),
            api_ratelimit: getValue('api_ratelimit'),
            api_ip_whitelist: getValue('api_ip_list'),
            api_callback_url: getValue('api_callback')
        };

        fetch('/api/settings/system/api/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(json => {
                if (!json.success) {
                    alert('저장에 실패했습니다.');
                    return;
                }
                alert('외부 API 설정이 저장되었습니다.');
            })
            .catch(err => {
                console.error(err);
                alert('API 설정 저장 중 오류 발생');
            });
    });
}

/* ============================================================
 * 3. API Key / Secret 재발급
 * ============================================================ */
function bindRegenerateButtons() {
    const keyBtn = document.getElementById('api_key_regenerate');
    const secretBtn = document.getElementById('api_secret_regenerate');

    if (keyBtn) {
        keyBtn.addEventListener('click', () => {
            if (!confirm('API Key를 재발급하면 기존 키는 즉시 무효화됩니다.\n계속하시겠습니까?')) return;
            setValue('api_key', generateRandomKey(32));
        });
    }

    if (secretBtn) {
        secretBtn.addEventListener('click', () => {
            if (!confirm('API Secret을 재발급하면 기존 Secret은 즉시 무효화됩니다.\n계속하시겠습니까?')) return;
            setValue('api_secret', generateRandomKey(64));
        });
    }
}

/* ============================================================
 * 4. +/- 스텝 버튼
 * ============================================================ */
function bindStepperButtons() {
    document.querySelectorAll('[data-target][data-step]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.querySelector(btn.dataset.target);
            if (!target) return;

            let value = parseInt(target.value || 0, 10);
            value += parseInt(btn.dataset.step, 10);

            if (target.min) value = Math.max(value, parseInt(target.min, 10));
            if (target.max) value = Math.min(value, parseInt(target.max, 10));

            target.value = value;
        });
    });
}

/* ============================================================
 * 5. 🔌 외부 API 핑 테스트 (입력 기반)
 * ============================================================ */
function bindApiPingTest() {
    const btn = document.getElementById('btn-api-ping');
    const resultEl = document.getElementById('api-ping-result');

    if (!btn || !resultEl) return;

    btn.addEventListener('click', () => {
        const apiKey = getValue('ping_api_key');
        const apiSecret = getValue('ping_api_secret');

        if (!apiKey || !apiSecret) {
            resultEl.innerHTML =
                '<span class="text-danger">API Key / Secret을 모두 입력하세요.</span>';
            return;
        }

        resultEl.innerHTML =
            '<span class="text-muted">연결 테스트 중...</span>';

        fetch('/api/external/ping', {
            method: 'GET',
            headers: {
                'X-API-KEY': apiKey,
                'X-API-SECRET': apiSecret
            }
        })
            .then(async res => {
                let json;
                try {
                    json = await res.json();
                } catch {
                    throw new Error('JSON 응답 아님');
                }

                if (json.success) {
                    resultEl.innerHTML =
                        '<span class="text-success">✔ 외부 API 연결 성공</span>';
                } else {
                    resultEl.innerHTML =
                        `<span class="text-danger">✖ ${json.message || '연결 실패'}</span>`;
                }
            })
            .catch(err => {
                console.error(err);
                resultEl.innerHTML =
                    '<span class="text-danger">✖ 서버 응답 없음</span>';
            });
    });
}

/* ============================================================
 * 6. API Secret 표시 / 숨김
 * ============================================================ */
function bindSecretToggle() {
    const btn = document.getElementById('api_secret_toggle');
    const input = document.getElementById('api_secret');

    if (!btn || !input) return;

    btn.addEventListener('click', () => {
        input.type = input.type === 'password' ? 'text' : 'password';
    });
}

/* ============================================================
 * 7. 유틸
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
    if (el) el.checked = value === 1 || value === '1' || value === true;
}

function generateRandomKey(length) {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let out = '';
    for (let i = 0; i < length; i++) {
        out += chars[Math.floor(Math.random() * chars.length)];
    }
    return out;
}

/* ============================================================
 * 설정 API Key / Secret → 핑 테스트 입력칸 복사
 * ============================================================ */
function bindCopyApiToPing() {
    const btn = document.getElementById('btn-copy-api-to-ping');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const apiKey = getValue('api_key');
        const apiSecret = getValue('api_secret');

        if (!apiKey || !apiSecret) {
            alert('API Key 또는 Secret이 설정되어 있지 않습니다.');
            return;
        }

        setValue('ping_api_key', apiKey);
        setValue('ping_api_secret', apiSecret);

        // Secret은 자동으로 text로 잠깐 보여줬다가 다시 숨겨도 됨 (선택)
        const secretInput = document.getElementById('ping_api_secret');
        if (secretInput) {
            secretInput.type = 'text';
            setTimeout(() => {
                secretInput.type = 'password';
            }, 1500);
        }
    });
}

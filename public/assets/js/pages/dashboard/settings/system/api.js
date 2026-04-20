/**
 * Path: /public/assets/js/pages/dashboard/settings/system/api.js
 */
(() => {
    'use strict';

    const API = {
        GET: '/api/settings/system/api/get',
        SAVE: '/api/settings/system/api/save',
        PING: '/api/external/ping'
    };

    const state = {
        apiSecretRaw: '',
        apiSecretMasked: '',
        regenerateApiKey: false,
        regenerateApiSecret: false,
        secretVisible: false
    };

    document.addEventListener('DOMContentLoaded', () => {
        loadApiSettings();
        bindApiFormSubmit();
        bindRegenerateButtons();
        bindStepperButtons();
        bindApiPingTest();
        bindSecretToggle();
        bindCopyApiToPing();
    });

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }

        if (type === 'error' || type === 'warning') {
            alert(message);
            return;
        }

        console.log(message);
    }

    async function loadApiSettings() {
        try {
            const response = await fetch(API.GET, {
                credentials: 'include'
            });
            const json = await response.json();

            if (!json?.success) {
                notify('error', json?.message || 'API 설정을 불러오지 못했습니다.');
                return;
            }

            const data = json.data || {};

            setCheckbox('api_enabled', data.api_enabled);
            setValue('api_key', data.api_key || '');
            setValue('api_token_ttl', data.api_token_ttl || 3600);
            setValue('api_ratelimit', data.api_ratelimit || 60);
            setValue('api_ip_list', data.api_ip_whitelist || '');
            setValue('api_callback', data.api_callback_url || '');

            state.apiSecretRaw = '';
            state.apiSecretMasked = data.api_secret_masked || '';
            state.secretVisible = false;
            state.regenerateApiKey = false;
            state.regenerateApiSecret = false;

            renderApiSecretField();
        } catch (error) {
            console.error('[api.js] load error', error);
            notify('error', 'API 설정 조회 중 오류가 발생했습니다.');
        }
    }

    function bindApiFormSubmit() {
        const form = document.getElementById('api-setting-form');
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const payload = {
                api_enabled: isChecked('api_enabled') ? 1 : 0,
                api_key: getValue('api_key'),
                api_secret: state.regenerateApiSecret ? '' : state.apiSecretRaw,
                api_token_ttl: getValue('api_token_ttl'),
                api_ratelimit: getValue('api_ratelimit'),
                api_ip_whitelist: normalizeIpList(getValue('api_ip_list')),
                api_callback_url: getValue('api_callback'),
                regenerate_api_key: state.regenerateApiKey ? 1 : 0,
                regenerate_api_secret: state.regenerateApiSecret ? 1 : 0
            };

            if (!validatePayload(payload)) {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');

            try {
                if (submitButton) submitButton.disabled = true;

                const response = await fetch(API.SAVE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });

                const json = await response.json();
                if (!json?.success) {
                    notify('error', json?.message || 'API 설정 저장에 실패했습니다.');
                    return;
                }

                const saved = json.data || {};
                setValue('api_key', saved.api_key || getValue('api_key'));

                state.apiSecretRaw = saved.api_secret || state.apiSecretRaw;
                state.apiSecretMasked = state.apiSecretRaw
                    ? '*'.repeat(Math.max(12, Math.min(state.apiSecretRaw.length, 24)))
                    : state.apiSecretMasked;
                state.secretVisible = false;
                state.regenerateApiKey = false;
                state.regenerateApiSecret = false;
                renderApiSecretField();

                notify('success', '외부 API 설정이 저장되었습니다.');
            } catch (error) {
                console.error('[api.js] save error', error);
                notify('error', 'API 설정 저장 중 오류가 발생했습니다.');
            } finally {
                if (submitButton) submitButton.disabled = false;
            }
        });
    }

    function bindRegenerateButtons() {
        const keyBtn = document.getElementById('api_key_regenerate');
        const secretBtn = document.getElementById('api_secret_regenerate');

        if (keyBtn) {
            keyBtn.addEventListener('click', () => {
                if (!confirm('API Key를 재발급하면 기존 Key는 즉시 무효화됩니다.\n계속하시겠습니까?')) return;
                state.regenerateApiKey = true;
                setValue('api_key', '저장 시 서버에서 새로 발급됩니다.');
                notify('warning', '저장하면 새 API Key가 발급됩니다.');
            });
        }

        if (secretBtn) {
            secretBtn.addEventListener('click', () => {
                if (!confirm('API Secret을 재발급하면 기존 Secret은 즉시 무효화됩니다.\n계속하시겠습니까?')) return;
                state.regenerateApiSecret = true;
                state.apiSecretRaw = '';
                state.apiSecretMasked = '저장 시 서버에서 새로 발급됩니다.';
                state.secretVisible = false;
                renderApiSecretField();
                notify('warning', '저장하면 새 API Secret이 발급됩니다.');
            });
        }
    }

    function bindStepperButtons() {
        document.querySelectorAll('[data-target][data-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = document.querySelector(button.dataset.target);
                if (!target) return;

                let value = parseInt(target.value || 0, 10);
                value += parseInt(button.dataset.step, 10);

                if (target.min) value = Math.max(value, parseInt(target.min, 10));
                if (target.max) value = Math.min(value, parseInt(target.max, 10));

                target.value = value;
            });
        });
    }

    function bindApiPingTest() {
        const button = document.getElementById('btn-api-ping');
        const resultEl = document.getElementById('api-ping-result');
        if (!button || !resultEl) return;

        button.addEventListener('click', async () => {
            const apiKey = getValue('ping_api_key');
            const apiSecret = getValue('ping_api_secret');

            if (!apiKey || !apiSecret) {
                resultEl.innerHTML = '<span class="text-danger">API Key와 Secret을 모두 입력해 주세요.</span>';
                return;
            }

            resultEl.innerHTML = '<span class="text-muted">연결 테스트 중...</span>';

            try {
                const response = await fetch(API.PING, {
                    method: 'GET',
                    headers: {
                        'X-API-KEY': apiKey,
                        'X-API-SECRET': apiSecret
                    }
                });

                const json = await response.json();
                if (json?.success) {
                    resultEl.innerHTML = '<span class="text-success">정상적으로 외부 API 연결에 성공했습니다.</span>';
                } else {
                    resultEl.innerHTML = `<span class="text-danger">${json?.message || '연결 테스트에 실패했습니다.'}</span>`;
                }
            } catch (error) {
                console.error('[api.js] ping error', error);
                resultEl.innerHTML = '<span class="text-danger">서버 응답이 없거나 연결에 실패했습니다.</span>';
            }
        });
    }

    function bindSecretToggle() {
        const button = document.getElementById('api_secret_toggle');
        if (!button) return;

        button.addEventListener('click', () => {
            if (!state.apiSecretRaw) {
                notify('warning', '보안상 기존 Secret은 자동으로 표시하지 않습니다. 새로 발급 후 저장하면 확인할 수 있습니다.');
                return;
            }

            state.secretVisible = !state.secretVisible;
            renderApiSecretField();
        });
    }

    function bindCopyApiToPing() {
        const button = document.getElementById('btn-copy-api-to-ping');
        if (!button) return;

        button.addEventListener('click', () => {
            const apiKey = getValue('api_key');
            if (apiKey) {
                setValue('ping_api_key', apiKey);
            }

            if (!state.apiSecretRaw) {
                notify('warning', '보안상 저장된 API Secret은 자동 복사하지 않습니다. 새로 발급 후 저장했거나 직접 입력해 주세요.');
                return;
            }

            setValue('ping_api_secret', state.apiSecretRaw);
            const secretInput = document.getElementById('ping_api_secret');
            if (secretInput) {
                secretInput.type = 'text';
                setTimeout(() => {
                    secretInput.type = 'password';
                }, 1500);
            }
        });
    }

    function renderApiSecretField() {
        const input = document.getElementById('api_secret');
        const toggleButton = document.getElementById('api_secret_toggle');
        if (!input || !toggleButton) return;

        if (state.secretVisible && state.apiSecretRaw) {
            input.type = 'text';
            input.value = state.apiSecretRaw;
            toggleButton.textContent = '숨기기';
            return;
        }

        input.type = 'password';
        input.value = state.apiSecretMasked || '';
        toggleButton.textContent = '보기';
    }

    function validatePayload(payload) {
        const ttl = parseInt(payload.api_token_ttl, 10);
        const rateLimit = parseInt(payload.api_ratelimit, 10);

        if (!Number.isInteger(ttl) || ttl < 300 || ttl > 604800) {
            notify('warning', 'Access Token 만료 시간은 300초~604800초 사이여야 합니다.');
            return false;
        }

        if (!Number.isInteger(rateLimit) || rateLimit < 1 || rateLimit > 10000) {
            notify('warning', '요청 제한은 1~10000 사이여야 합니다.');
            return false;
        }

        if (payload.api_callback_url) {
            try {
                const url = new URL(payload.api_callback_url);
                if (!/^https?:$/.test(url.protocol)) {
                    throw new Error('invalid_protocol');
                }
            } catch (_) {
                notify('warning', 'Callback URL 형식을 확인해 주세요.');
                return false;
            }
        }

        const ipList = splitIpList(payload.api_ip_whitelist);
        const ipPattern = /^(?:\d{1,3}\.){3}\d{1,3}$/;

        for (const ip of ipList) {
            if (!ipPattern.test(ip)) {
                notify('warning', `허용 IP 형식을 확인해 주세요: ${ip}`);
                return false;
            }

            const octets = ip.split('.').map(Number);
            if (octets.some((octet) => octet < 0 || octet > 255)) {
                notify('warning', `허용 IP 범위를 확인해 주세요: ${ip}`);
                return false;
            }
        }

        return true;
    }

    function splitIpList(value) {
        return String(value || '')
            .split(/[\n,]+/)
            .map((item) => item.trim())
            .filter(Boolean);
    }

    function normalizeIpList(value) {
        return splitIpList(value).join(', ');
    }

    function getValue(id) {
        const element = document.getElementById(id);
        return element ? String(element.value || '').trim() : '';
    }

    function setValue(id, value) {
        const element = document.getElementById(id);
        if (element && value !== undefined && value !== null) {
            element.value = value;
        }
    }

    function isChecked(id) {
        const element = document.getElementById(id);
        return element ? element.checked : false;
    }

    function setCheckbox(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.checked = value === 1 || value === '1' || value === true;
        }
    }
})();

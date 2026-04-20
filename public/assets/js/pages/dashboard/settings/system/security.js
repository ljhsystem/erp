/**
 * Path: /public/assets/js/pages/dashboard/settings/system/security.js
 */
(() => {
    'use strict';

    const API = {
        GET: '/api/settings/system/security/get',
        SAVE: '/api/settings/system/security/save'
    };

    const form = document.getElementById('security-setting-form');
    if (!form) return;

    const qs = (selector, parent = document) => parent.querySelector(selector);
    const qsa = (selector, parent = document) => [...parent.querySelectorAll(selector)];

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

    function setPolicyDisabled(container, disabled) {
        qsa('input, textarea, select', container).forEach((element) => {
            if (!element.classList.contains('form-check-input')) {
                element.disabled = disabled;
            }
        });
        container.style.opacity = disabled ? '0.5' : '1';
    }

    function bindPolicyToggles() {
        qsa('[id$="_policy_enabled"]').forEach((toggle) => {
            toggle.addEventListener('change', () => {
                const body = toggle.closest('.card')?.querySelector('.policy-group');
                if (!body) return;
                setPolicyDisabled(body, !toggle.checked);
            });
        });
    }

    async function loadSettings() {
        try {
            const response = await fetch(API.GET, { credentials: 'same-origin' });
            const result = await response.json();
            if (!result?.success) throw new Error('Load failed');

            const data = result.data || {};

            if (!('security_inactive_2fa_days' in data) && ('security_inactive_warn_days' in data)) {
                data.security_inactive_2fa_days = data.security_inactive_warn_days;
            }

            if (!('security_login_time_mode' in data)) {
                data.security_login_time_mode = '2fa';
            }

            if (!('security_password_min' in data)) data.security_password_min = '8';
            if (!('security_password_expire' in data)) data.security_password_expire = '90';
            if (!('security_login_fail_max' in data)) data.security_login_fail_max = '5';
            if (!('security_login_lock_minutes' in data)) data.security_login_lock_minutes = '30';
            if (!('security_login_time_start' in data)) data.security_login_time_start = '07:00';
            if (!('security_login_time_end' in data)) data.security_login_time_end = '20:00';
            if (!('security_inactive_2fa_days' in data)) data.security_inactive_2fa_days = '3';
            if (!('security_inactive_lock_days' in data)) data.security_inactive_lock_days = '10';

            Object.entries(data).forEach(([key, value]) => {
                const element = qs(`[name="${key}"]`);
                if (!element) return;

                if (element.type === 'checkbox') {
                    element.checked = ['1', 'true', 'yes', 'on'].includes(String(value));
                    return;
                }

                element.value = value ?? '';
            });

            [
                'security_password_policy_enabled',
                'security_login_fail_policy_enabled',
                'security_access_policy_enabled'
            ].forEach((name) => {
                if (!(name in data)) {
                    const element = qs(`[name="${name}"]`);
                    if (element) element.checked = true;
                }
            });

            qsa('[id$="_policy_enabled"]').forEach((toggle) => {
                toggle.dispatchEvent(new Event('change'));
            });
        } catch (error) {
            console.error('[security.js] load error', error);
            notify('error', '보안 정책을 불러오지 못했습니다.');
        }
    }

    function collectFormData() {
        const payload = {};

        qsa('[name]', form).forEach((element) => {
            if (element.name.endsWith('_policy_enabled')) {
                payload[element.name] = element.checked ? '1' : '0';
                return;
            }

            if (element.disabled) return;

            if (element.type === 'checkbox') {
                payload[element.name] = element.checked ? '1' : '0';
            } else {
                payload[element.name] = String(element.value ?? '').trim();
            }
        });

        if ('security_inactive_2fa_days' in payload) {
            payload.security_inactive_warn_days = payload.security_inactive_2fa_days;
        }

        return payload;
    }

    function validatePayload(payload) {
        if (payload.security_password_policy_enabled === '1') {
            const min = parseInt(payload.security_password_min || '0', 10);
            if (!Number.isInteger(min) || min < 4 || min > 64) {
                notify('warning', '비밀번호 최소 길이는 4~64 사이여야 합니다.');
                return false;
            }
        }

        if (payload.security_login_fail_policy_enabled === '1') {
            const maxFail = parseInt(payload.security_login_fail_max || '0', 10);
            const lockMinutes = parseInt(payload.security_login_lock_minutes || '0', 10);

            if (!Number.isInteger(maxFail) || maxFail < 3 || maxFail > 20) {
                notify('warning', '로그인 실패 허용 횟수는 3~20 사이여야 합니다.');
                return false;
            }

            if (!Number.isInteger(lockMinutes) || lockMinutes < 1 || lockMinutes > 120) {
                notify('warning', '로그인 잠금 시간은 1~120분 사이여야 합니다.');
                return false;
            }
        }

        if (payload.security_access_policy_enabled === '1') {
            const start = payload.security_login_time_start;
            const end = payload.security_login_time_end;
            const mode = payload.security_login_time_mode;
            const inactive2faDays = parseInt(payload.security_inactive_2fa_days || '0', 10);
            const inactiveLockDays = parseInt(payload.security_inactive_lock_days || '0', 10);

            if (!start || !end) {
                notify('warning', '로그인 허용 시작/종료 시간을 입력해 주세요.');
                return false;
            }

            if (!['2fa', 'block'].includes(mode)) {
                notify('warning', '시간 외 로그인 처리 방식을 확인해 주세요.');
                return false;
            }

            if (!Number.isInteger(inactive2faDays) || inactive2faDays < 1 || inactive2faDays > 365) {
                notify('warning', '장기 미접속 추가 인증 일수는 1~365 사이여야 합니다.');
                return false;
            }

            if (!Number.isInteger(inactiveLockDays) || inactiveLockDays < 1 || inactiveLockDays > 3650) {
                notify('warning', '장기 미접속 계정 잠금 일수는 1~3650 사이여야 합니다.');
                return false;
            }

            if (inactive2faDays > inactiveLockDays) {
                notify('warning', '추가 인증 일수는 계정 잠금 일수보다 클 수 없습니다.');
                return false;
            }
        }

        return true;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = collectFormData();
        if (!validatePayload(payload)) {
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');

        try {
            if (submitButton) submitButton.disabled = true;

            const response = await fetch(API.SAVE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            if (!result?.success) throw new Error(result?.message || 'Save failed');

            notify('success', '보안 정책이 저장되었습니다.');
        } catch (error) {
            console.error('[security.js] save error', error);
            notify('error', '보안 정책 저장 중 오류가 발생했습니다.');
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });

    function bindStepperButtons() {
        qsa('button[data-target][data-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = qs(button.dataset.target);
                if (!target || target.disabled) return;

                const step = parseInt(button.dataset.step, 10) || 0;
                const min = target.min !== '' ? parseInt(target.min, 10) : null;
                const max = target.max !== '' ? parseInt(target.max, 10) : null;

                let value = parseInt(target.value || 0, 10) + step;

                if (min !== null) value = Math.max(min, value);
                if (max !== null) value = Math.min(max, value);

                target.value = value;
            });
        });
    }

    bindPolicyToggles();
    bindStepperButtons();
    loadSettings();
})();

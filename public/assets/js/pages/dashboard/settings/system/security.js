/**
 * 경로: /public/assets/js/pages/dashboard/settings/system/security.js
 * 설명: 시스템 설정 > 보안 정책
 */
(() => {
    'use strict';

    console.log('[SYSTEM SECURITY] loaded');

    /* =========================================================
     * 1. API ENDPOINT
     * ========================================================= */
    const API = {
        GET:  '/api/settings/system/security/get',
        SAVE: '/api/settings/system/security/save'
    };

    const form = document.getElementById('security-setting-form');
    if (!form) {
        console.warn('[SECURITY] form not found');
        return;
    }

    /* =========================================================
     * 2. UTIL
     * ========================================================= */
    const qs  = (s, p = document) => p.querySelector(s);
    const qsa = (s, p = document) => [...p.querySelectorAll(s)];

    /**
     * 정책 그룹 enable / disable
     * - policy-group 내부만 제어
     * - form-check-input(스위치)는 항상 활성
     */
    const setPolicyDisabled = (container, disabled) => {
        qsa('input, textarea, select', container).forEach(el => {
            if (!el.classList.contains('form-check-input')) {
                el.disabled = disabled;
            }
        });
        container.style.opacity = disabled ? '0.5' : '1';
    };

    /* =========================================================
     * 3. 정책 그룹 토글 바인딩
     * ========================================================= */
    const bindPolicyToggles = () => {
        qsa('[id$="_policy_enabled"]').forEach(toggle => {
            toggle.addEventListener('change', () => {
                const body = toggle
                    .closest('.card')
                    ?.querySelector('.policy-group');

                if (!body) return;
                setPolicyDisabled(body, !toggle.checked);
            });
        });
    };

    /* =========================================================
     * 4. 초기 데이터 로딩
     * ========================================================= */
    const loadSettings = async () => {
        try {
            const res = await fetch(API.GET, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) throw new Error('Load failed');

            const data = json.data || {};

            /* 1️⃣ DB 값 → UI */
            Object.entries(data).forEach(([key, value]) => {
                const el = qs(`[name="${key}"]`);
                if (!el) return;

                if (el.type === 'checkbox') {
                    el.checked = ['1', 'true', 'yes', 'on'].includes(String(value));
                } else {
                    el.value = value;
                }
            });

            /* 2️⃣ 기본 정책 ON (DB에 없을 때만) */
            [
                'security_password_policy_enabled',
                'security_login_fail_policy_enabled',
                'security_access_policy_enabled'
            ].forEach(name => {
                if (!(name in data)) {
                    const el = qs(`[name="${name}"]`);
                    if (el) el.checked = true;
                }
            });

            /* 3️⃣ 토글 상태 반영 */
            qsa('[id$="_policy_enabled"]').forEach(toggle => {
                toggle.dispatchEvent(new Event('change'));
            });

        } catch (err) {
            console.error('[SECURITY] load error', err);
            alert('보안 설정을 불러오지 못했습니다.');
        }
    };

    /* =========================================================
     * 5. 저장 데이터 수집
     * ========================================================= */
    const collectFormData = () => {
        const payload = {};

        qsa('[name]', form).forEach(el => {

            // 정책 ON/OFF는 항상 저장
            if (el.name.endsWith('_policy_enabled')) {
                payload[el.name] = el.checked ? '1' : '0';
                return;
            }

            // disabled 항목은 저장 제외
            if (el.disabled) return;

            if (el.type === 'checkbox') {
                payload[el.name] = el.checked ? '1' : '0';
            } else {
                payload[el.name] = el.value;
            }
        });

        return payload;
    };

    /* =========================================================
     * 6. 저장 처리
     * ========================================================= */
    form.addEventListener('submit', async e => {
        e.preventDefault();

        const payload = collectFormData();

        try {
            const res = await fetch(API.SAVE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (!json.success) throw new Error('Save failed');

            alert('보안 정책이 저장되었습니다.');

        } catch (err) {
            console.error('[SECURITY] save error', err);
            alert('저장 중 오류가 발생했습니다.');
        }
    });

    /* =========================================================
     * 7. +/- 스텝 버튼 바인딩
     * ========================================================= */
    const bindStepperButtons = () => {
        qsa('button[data-target][data-step]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = qs(btn.dataset.target);
                if (!target || target.disabled) return;

                const step = parseInt(btn.dataset.step, 10) || 0;
                const min  = target.min !== '' ? parseInt(target.min, 10) : null;
                const max  = target.max !== '' ? parseInt(target.max, 10) : null;

                let value = parseInt(target.value || 0, 10) + step;

                if (min !== null) value = Math.max(min, value);
                if (max !== null) value = Math.min(max, value);

                target.value = value;
            });
        });
    };

    /* =========================================================
     * INIT
     * ========================================================= */
    bindPolicyToggles();
    bindStepperButtons();
    loadSettings();

})();

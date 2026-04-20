/**
 * Path: /public/assets/js/pages/dashboard/settings/system/external_services.js
 */
(() => {
    'use strict';

    const API = {
        GET: '/api/settings/system/external-services/get',
        SAVE: '/api/settings/system/external-services/save'
    };

    document.addEventListener('DOMContentLoaded', () => {
        loadExternalServiceSettings();
        bindExternalServiceSave();
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

    async function loadExternalServiceSettings() {
        try {
            const response = await fetch(API.GET, {
                credentials: 'same-origin'
            });
            const json = await response.json();

            if (!json?.success) {
                notify('error', json?.message || '외부 서비스 설정을 불러오지 못했습니다.');
                return;
            }

            const data = json.data || {};

            setCheckbox('synology_enabled', data.synology_enabled);
            setValue('synology_host', data.synology_host || '');
            setValue('synology_caldav_path', data.synology_caldav_path || '');
            setCheckbox('synology_ssl_verify', data.synology_ssl_verify);
        } catch (error) {
            console.error('[external_services.js] load failed:', error);
            notify('error', '외부 서비스 설정 조회 중 오류가 발생했습니다.');
        }
    }

    function bindExternalServiceSave() {
        const button = document.getElementById('btn-save-external-service');
        if (!button) return;

        button.addEventListener('click', async () => {
            const payload = {
                synology_enabled: isChecked('synology_enabled') ? 1 : 0,
                synology_host: normalizeHost(getValue('synology_host')),
                synology_caldav_path: normalizePath(getValue('synology_caldav_path')),
                synology_ssl_verify: isChecked('synology_ssl_verify') ? 1 : 0
            };

            if (!validatePayload(payload)) {
                return;
            }

            try {
                button.disabled = true;
                button.textContent = '저장중...';

                const response = await fetch(API.SAVE, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const json = await response.json();
                if (!json?.success) {
                    throw new Error(json?.message || '저장 실패');
                }

                setValue('synology_host', payload.synology_host);
                setValue('synology_caldav_path', payload.synology_caldav_path);
                notify('success', '외부 서비스 연동 경로가 저장되었습니다.');
            } catch (error) {
                console.error('[external_services.js] save failed:', error);
                notify('error', '외부 서비스 설정 저장 중 오류가 발생했습니다.');
            } finally {
                button.disabled = false;
                button.textContent = '저장';
            }
        });
    }

    function validatePayload(payload) {
        if (!payload.synology_enabled) {
            return true;
        }

        if (!payload.synology_host) {
            notify('warning', 'Synology 서버 주소를 입력해 주세요.');
            return false;
        }

        try {
            const url = new URL(payload.synology_host);
            if (!/^https?:$/.test(url.protocol)) {
                throw new Error('invalid_protocol');
            }
        } catch (_) {
            notify('warning', 'Synology 서버 주소 형식을 확인해 주세요.');
            return false;
        }

        if (!payload.synology_caldav_path) {
            notify('warning', 'CalDAV 경로를 입력해 주세요.');
            return false;
        }

        if (!payload.synology_caldav_path.startsWith('/')) {
            notify('warning', 'CalDAV 경로는 `/`로 시작해야 합니다.');
            return false;
        }

        return true;
    }

    function normalizeHost(value) {
        return String(value || '').trim().replace(/\/+$/, '');
    }

    function normalizePath(value) {
        const trimmed = String(value || '').trim();
        if (!trimmed) return '';

        let normalized = trimmed;
        if (!normalized.startsWith('/')) {
            normalized = `/${normalized}`;
        }
        if (!normalized.endsWith('/')) {
            normalized = `${normalized}/`;
        }
        return normalized;
    }

    function getValue(id) {
        const element = document.getElementById(id);
        return element ? element.value.trim() : '';
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

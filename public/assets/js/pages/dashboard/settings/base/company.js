import {
    formatBizNumber,
    formatCorpNumber,
    formatPhone
} from '/public/assets/js/common/format.js';

(() => {
    'use strict';

    const API_COMPANY_GET = '/api/settings/base-info/company/detail';
    const API_COMPANY_SAVE = '/api/settings/base-info/company/save';

    const wrapper = $('#company-settings-wrapper');
    const saveButton = $('#btn-save-all');

    $(document).ready(() => {
        loadCompanyInfo();
        bindEvents();

        if (window.KakaoAddress && typeof window.KakaoAddress.bind === 'function') {
            window.KakaoAddress.bind();
        }
    });

    function bindEvents() {
        saveButton.on('click', saveCompanyInfo);

        wrapper.on('input', "[name='biz_number']", function () {
            this.value = formatBizNumber(this.value);
        });

        wrapper.on('input', "[name='corp_number']", function () {
            this.value = formatCorpNumber(this.value);
        });

        wrapper.on('input', "[name='tel'], [name='fax']", function () {
            this.value = formatPhone(this.value);
        });
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }

        console[type === 'error' ? 'error' : 'log'](message);
    }

    function loadCompanyInfo() {
        $.ajax({
            url: API_COMPANY_GET,
            type: 'GET',
            dataType: 'json',
            success(res) {
                if (!res || res.success !== true) {
                    notify('error', res?.message || '회사정보를 불러오지 못했습니다.');
                    return;
                }

                if (!res.data) {
                    clearForm();
                    return;
                }

                fillForm(res.data);
            },
            error(xhr) {
                console.error('[company] load failed', xhr.status, xhr.responseText);
                notify('error', '회사정보를 불러오지 못했습니다.');
            }
        });
    }

    function fillForm(data) {
        setValue('company_name_ko', data.company_name_ko);
        setValue('company_name_en', data.company_name_en);
        setValue('ceo_name', data.ceo_name);
        setValue('biz_number', formatBizNumber(data.biz_number || ''));
        setValue('corp_number', formatCorpNumber(data.corp_number || ''));
        setValue('found_date', normalizeDate(data.found_date));
        setValue('biz_type', data.biz_type);
        setValue('biz_item', data.biz_item);
        setValue('addr_main', data.addr_main);
        setValue('addr_detail', data.addr_detail);
        setValue('tel', formatPhone(data.tel || ''));
        setValue('fax', formatPhone(data.fax || ''));
        setValue('tax_email', data.tax_email);
        setValue('sub_email', data.sub_email);
        setValue('company_website', data.company_website);
        setValue('sns_instagram', data.sns_instagram);
        setValue('company_about', data.company_about);
        setValue('company_history', data.company_history);
    }

    function saveCompanyInfo() {
        const data = collectFormData();
        const validationError = validateFormData(data);
        if (validationError) {
            notify('warning', validationError);
            return;
        }

        saveButton.prop('disabled', true);

        $.ajax({
            url: API_COMPANY_SAVE,
            type: 'POST',
            dataType: 'json',
            contentType: 'application/json; charset=utf-8',
            data: JSON.stringify(data),
            success(res) {
                if (res?.success) {
                    notify('success', res.message || '회사정보를 저장했습니다.');
                    loadCompanyInfo();
                    return;
                }

                notify('error', res?.message || '회사정보 저장에 실패했습니다.');
            },
            error(xhr) {
                console.error('[company] save failed', xhr.status, xhr.responseText);

                let message = '서버 통신 중 오류가 발생했습니다.';
                try {
                    const json = JSON.parse(xhr.responseText || '{}');
                    message = json?.message || message;
                } catch (_) {
                    // ignore parse error
                }

                notify('error', message);
            },
            complete() {
                saveButton.prop('disabled', false);
            }
        });
    }

    function collectFormData() {
        return {
            company_name_ko: getValue('company_name_ko'),
            company_name_en: getValue('company_name_en'),
            ceo_name: getValue('ceo_name'),
            biz_number: getValue('biz_number'),
            corp_number: getValue('corp_number'),
            found_date: getValue('found_date') || null,
            biz_type: getValue('biz_type'),
            biz_item: getValue('biz_item'),
            addr_main: getValue('addr_main'),
            addr_detail: getValue('addr_detail'),
            tel: getValue('tel'),
            fax: getValue('fax'),
            tax_email: getValue('tax_email'),
            sub_email: getValue('sub_email'),
            company_website: getValue('company_website'),
            sns_instagram: getValue('sns_instagram'),
            company_about: getValue('company_about'),
            company_history: getValue('company_history')
        };
    }

    function validateFormData(data) {
        if (!data.company_name_ko.trim()) {
            return '회사명(국문)은 필수입니다.';
        }

        const bizNumber = digitsOnly(data.biz_number);
        if (bizNumber && bizNumber.length !== 10) {
            return '사업자등록번호는 숫자 10자리여야 합니다.';
        }

        const corpNumber = digitsOnly(data.corp_number);
        if (corpNumber && corpNumber.length !== 13) {
            return '법인등록번호는 숫자 13자리여야 합니다.';
        }

        const tel = digitsOnly(data.tel);
        if (tel && (tel.length < 9 || tel.length > 11)) {
            return '전화번호 형식이 올바르지 않습니다.';
        }

        const fax = digitsOnly(data.fax);
        if (fax && (fax.length < 9 || fax.length > 11)) {
            return '팩스번호 형식이 올바르지 않습니다.';
        }

        if (data.tax_email && !isValidEmail(data.tax_email)) {
            return '세금계산서 이메일 형식이 올바르지 않습니다.';
        }

        if (data.sub_email && !isValidEmail(data.sub_email)) {
            return '서브 이메일 형식이 올바르지 않습니다.';
        }

        if (data.company_website && !isValidUrl(data.company_website)) {
            return '홈페이지 주소 형식이 올바르지 않습니다.';
        }

        if (data.sns_instagram && !isValidInstagram(data.sns_instagram)) {
            return '인스타그램은 URL 또는 계정명 형식으로 입력해주세요.';
        }

        return '';
    }

    function setValue(name, value) {
        wrapper.find(`[name='${name}']`).val(value || '');
    }

    function getValue(name) {
        return String(wrapper.find(`[name='${name}']`).val() || '').trim();
    }

    function normalizeDate(value) {
        if (!value || value === '0000-00-00') {
            return '';
        }

        return value;
    }

    function clearForm() {
        wrapper.find('input, textarea').val('');
    }

    function digitsOnly(value) {
        return String(value || '').replace(/[^0-9]/g, '');
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || ''));
    }

    function isValidUrl(value) {
        try {
            const url = new URL(value);
            return ['http:', 'https:'].includes(url.protocol);
        } catch (_) {
            return false;
        }
    }

    function isValidInstagram(value) {
        return isValidUrl(value) || /^@?[A-Za-z0-9._]{2,30}$/.test(String(value || ''));
    }
})();

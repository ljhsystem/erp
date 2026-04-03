/**
 * 회사 기본정보 설정 JS
 * 경로: /public/assets/js/pages/dashboard/settings/base/company.js
 */

(function () {
    "use strict";

    console.log('[base-company.js] loaded');

    /* =========================================================
     * API ENDPOINTS
     * ========================================================= */
    const API_COMPANY_GET  = "/api/settings/base-info/company/detail";
    const API_COMPANY_SAVE = "/api/settings/base-info/company/save";

    const $wrapper = $("#company-settings-wrapper");

    /* =========================================================
     * 초기 로딩
     * ========================================================= */
    $(document).ready(function () {
        loadCompanyInfo();
        bindEvents();

        // 공용 주소검색 바인딩
        if (window.KakaoAddress && typeof window.KakaoAddress.bind === "function") {
            window.KakaoAddress.bind();
        }
    });

    /* =========================================================
     * 이벤트 바인딩
     * ========================================================= */
    function bindEvents() {
        $("#btn-save-all").on("click", function () {
            saveCompanyInfo();
        });
    }

    /* =========================================================
     * 회사 정보 조회
     * ========================================================= */
    function loadCompanyInfo() {
        $.ajax({
            url: API_COMPANY_GET,
            type: "GET",
            dataType: "json",
            success: function (res) {

                // 정상 응답이 아닌 경우
                if (!res || res.success !== true) {
                    console.warn("회사 정보 조회 실패", res);
                    return;
                }

                // 아직 회사 정보가 없는 경우 (최초 상태)
                if (!res.data) {
                    console.info("회사 기본정보가 아직 등록되지 않았습니다.");
                    clearForm();
                    return;
                }

                fillForm(res.data);
            },
            error: function (xhr) {
                console.error(
                    "회사 정보 조회 AJAX 오류",
                    xhr.status,
                    xhr.responseText
                );
            }
        });
    }

    /* =========================================================
     * 폼 채우기
     * ========================================================= */
    function fillForm(data) {
        $wrapper.find("[name='company_name_ko']").val(data.company_name_ko || "");
        $wrapper.find("[name='company_name_en']").val(data.company_name_en || "");
        $wrapper.find("[name='ceo_name']").val(data.ceo_name || "");

        $wrapper.find("[name='biz_number']").val(data.biz_number || "");
        $wrapper.find("[name='corp_number']").val(data.corp_number || "");

        // ❗ 날짜 정규화 (0000-00-00 방지)
        $wrapper.find("[name='found_date']").val(normalizeDate(data.found_date));

        $wrapper.find("[name='biz_type']").val(data.biz_type || "");
        $wrapper.find("[name='biz_item']").val(data.biz_item || "");

        $wrapper.find("[name='addr_main']").val(data.addr_main || "");
        $wrapper.find("[name='addr_detail']").val(data.addr_detail || "");

        $wrapper.find("[name='tel']").val(data.tel || "");
        $wrapper.find("[name='fax']").val(data.fax || "");
        $wrapper.find("[name='tax_email']").val(data.tax_email || "");
        $wrapper.find("[name='sub_email']").val(data.sub_email || "");
        $wrapper.find("[name='company_website']").val(data.company_website || "");
        $wrapper.find("[name='sns_instagram']").val(data.sns_instagram || "");
        
        $wrapper.find("[name='company_about']").val(data.company_about || "");
        $wrapper.find("[name='company_history']").val(data.company_history || "");
    }

    /* =========================================================
     * 회사 정보 저장
     * ========================================================= */
    function saveCompanyInfo() {
        const data = collectFormData();

        // 필수값 체크
        if (!data.company_name_ko.trim()) {
            alert("회사명(한글)은 필수입니다.");
            return;
        }

        $.ajax({
            url: API_COMPANY_SAVE,
            type: "POST",
            dataType: "json",
            data: data,
            success: function (res) {
                if (res && res.success) {
                    alert("회사 기본정보가 저장되었습니다.");
                    loadCompanyInfo();
                } else {
                    alert(res.message || "저장 중 오류가 발생했습니다.");
                }
            },
            error: function (xhr) {
                console.error(
                    "회사 정보 저장 AJAX 오류",
                    xhr.status,
                    xhr.responseText
                );
                alert("서버 통신 중 오류가 발생했습니다.");
            }
        });
    }

    /* =========================================================
     * 폼 데이터 수집
     * ========================================================= */
    function collectFormData() {
        return {
            company_name_ko : $wrapper.find("[name='company_name_ko']").val(),
            company_name_en : $wrapper.find("[name='company_name_en']").val(),
            ceo_name        : $wrapper.find("[name='ceo_name']").val(),

            biz_number      : $wrapper.find("[name='biz_number']").val(),
            corp_number     : $wrapper.find("[name='corp_number']").val(),
            found_date      : $wrapper.find("[name='found_date']").val() || null,

            biz_type        : $wrapper.find("[name='biz_type']").val(),
            biz_item        : $wrapper.find("[name='biz_item']").val(),

            addr_main       : $wrapper.find("[name='addr_main']").val(),
            addr_detail     : $wrapper.find("[name='addr_detail']").val(),

            tel             : $wrapper.find("[name='tel']").val(),
            fax             : $wrapper.find("[name='fax']").val(),
            tax_email       : $wrapper.find("[name='tax_email']").val(),
            sub_email       : $wrapper.find("[name='sub_email']").val(),
            company_website : $wrapper.find("[name='company_website']").val(),
            sns_instagram   : $wrapper.find("[name='sns_instagram']").val(),

            company_about   : $wrapper.find("[name='company_about']").val(),
            company_history : $wrapper.find("[name='company_history']").val()
        };
    }

    /* =========================================================
     * 유틸
     * ========================================================= */

    // 날짜 정규화 (HTML5 date input 안전)
    function normalizeDate(value) {
        if (!value || value === "0000-00-00") return "";
        return value;
    }

    // 최초 상태용 폼 초기화
    function clearForm() {
        $wrapper.find("input, textarea").val("");
    }

})();

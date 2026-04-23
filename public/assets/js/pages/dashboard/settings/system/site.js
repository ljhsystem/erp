// Path: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/system/site.js'
(function () {
    "use strict";

    const API = {
        GET: "/api/settings/system/site/get",
        SAVE: "/api/settings/system/site/save"
    };

    document.addEventListener("DOMContentLoaded", () => {
        loadSiteSettings();
        bindSiteSettingForm();
    });

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }

        if (type === "error" || type === "warning") {
            alert(message);
            return;
        }

        console.log(message);
    }

    async function loadSiteSettings() {
        try {
            const response = await fetch(API.GET, {
                credentials: "include"
            });
            const result = await response.json();

            if (!result?.success) {
                notify("error", result?.message || "사이트 설정을 불러오지 못했습니다.");
                return;
            }

            const data = result.data || {};

            setValue("page_title", data.page_title);
            setValue("site_title", data.site_title);
            setValue("home_intro_title", data.home_intro_title);
            setValue("home_intro_description", data.home_intro_description);
            setValue("home_intro_url", data.home_intro_url);
            setValue("footer_text", data.footer_text);

            setValue("ui_skin", data.ui_skin);
            setValue("theme_mode", data.theme_mode);
            setValue("font_family", data.font_family || data.site_font_family || "");
            setValue("ui_density", data.ui_density || "normal");
            setValue("font_scale", data.font_scale);
            setValue("table_density", data.table_density);
            setValue("card_density", data.card_density);
            setValue("radius_style", data.radius_style);
            setValue("button_style", data.button_style);
            setValue("row_focus", data.row_focus);
            setValue("link_underline", data.link_underline);
            setValue("icon_scale", data.icon_scale);
            setValue("alert_style", data.alert_style);
            setValue("sidebar_default", data.sidebar_default);
            setValue("motion_mode", data.motion_mode);
        } catch (error) {
            console.error("[site.js] load failed:", error);
            notify("error", "사이트 설정을 불러오는 중 오류가 발생했습니다.");
        }
    }

    function bindSiteSettingForm() {
        const form = document.getElementById("site-setting-form");
        if (!form) return;

        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            const payload = {
                page_title: getValue("page_title"),
                site_title: getValue("site_title"),
                home_intro_title: getValue("home_intro_title"),
                home_intro_description: getValue("home_intro_description"),
                home_intro_url: getValue("home_intro_url"),
                footer_text: getValue("footer_text"),
                ui_skin: getValue("ui_skin"),
                theme_mode: getValue("theme_mode"),
                font_family: getValue("font_family"),
                site_font_family: getValue("font_family"),
                ui_density: getValue("ui_density") || "normal",
                font_scale: getValue("font_scale"),
                table_density: getValue("table_density"),
                card_density: getValue("card_density"),
                radius_style: getValue("radius_style"),
                button_style: getValue("button_style"),
                row_focus: getValue("row_focus"),
                link_underline: getValue("link_underline"),
                icon_scale: getValue("icon_scale"),
                alert_style: getValue("alert_style"),
                sidebar_default: getValue("sidebar_default"),
                motion_mode: getValue("motion_mode")
            };

            if (!validatePayload(payload)) {
                return;
            }

            await saveSiteSettings(payload);
        });
    }

    function validatePayload(payload) {
        if (!payload.page_title) {
            notify("warning", "브라우저 페이지 제목을 입력해 주세요.");
            return false;
        }

        if (!payload.site_title) {
            notify("warning", "사이트 제목을 입력해 주세요.");
            return false;
        }

        if (payload.home_intro_url) {
            try {
                const parsed = new URL(payload.home_intro_url, window.location.origin);
                if (!/^https?:$/.test(parsed.protocol)) {
                    throw new Error("invalid_protocol");
                }
            } catch (_) {
                notify("warning", "홈 소개 이동 URL 형식을 확인해 주세요.");
                return false;
            }
        }

        return true;
    }

    async function saveSiteSettings(payload) {
        const submitButton = document.querySelector('#site-setting-form button[type="submit"]');

        try {
            if (submitButton) {
                submitButton.disabled = true;
            }

            const response = await fetch(API.SAVE, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                credentials: "include",
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!result?.success) {
                notify("error", result?.message || "사이트 설정 저장에 실패했습니다.");
                return;
            }

            notify("success", "사이트 설정이 저장되었습니다.");

            window.setTimeout(() => {
                window.location.reload();
            }, 1400);
        } catch (error) {
            console.error("[site.js] save failed:", error);
            notify("error", "사이트 설정 저장 중 오류가 발생했습니다.");
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    }

    function getValue(name) {
        const element = document.querySelector(`[name="${name}"]`);
        return element ? String(element.value || "").trim() : "";
    }

    function setValue(name, value) {
        const element = document.querySelector(`[name="${name}"]`);
        if (!element) return;
        element.value = value ?? "";
    }
})();

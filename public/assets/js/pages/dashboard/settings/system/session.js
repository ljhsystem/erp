// Path: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/system/session.js'
(function () {
    "use strict";

    const API = {
        GET: "/api/settings/system/session/get",
        SAVE: "/api/settings/system/session/save"
    };

    const SOUND_BASE = "/public/assets/sounds/";

    document.addEventListener("DOMContentLoaded", () => {
        bindPlusMinusButtons();
        bindSoundPreviewEvents();
        loadSessionSettings();
        bindSessionSave();
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

    function bindPlusMinusButtons() {
        document.addEventListener("click", (event) => {
            const button = event.target.closest("button[data-target]");
            if (!button) return;

            const input = document.querySelector(button.dataset.target);
            if (!input) return;

            const step = parseInt(button.dataset.step, 10) || 0;
            const min = parseInt(input.min || "1", 10);
            const max = parseInt(input.max || "1440", 10);

            let value = parseInt(input.value || min, 10);
            value += step;

            if (value < min) value = min;
            if (value > max) value = max;

            input.value = value;
            normalizeAlertTime(input.id === "session_timeout" ? "timeout" : "alert");
        });
    }

    async function loadSessionSettings() {
        try {
            const response = await fetch(API.GET, {
                credentials: "include"
            });
            const result = await response.json();

            if (!result?.success) {
                notify("error", result?.message || "세션 설정을 불러오지 못했습니다.");
                return;
            }

            const data = result.data || {};

            setValue("session_timeout", data.session_timeout ?? 30);
            setValue("session_alert", data.session_alert ?? 5);
            setValue("session_sound", data.session_sound ?? "default.mp3");

            normalizeAlertTime("timeout");
            updateSoundPreview();
        } catch (error) {
            console.error("[session.js] load failed:", error);
            notify("error", "세션 설정을 불러오는 중 오류가 발생했습니다.");
        }
    }

    function bindSessionSave() {
        const form = document.getElementById("session-setting-form");
        if (!form) return;

        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            const payload = {
                session_timeout: getValue("session_timeout"),
                session_alert: getValue("session_alert"),
                session_sound: getValue("session_sound")
            };

            if (!validatePayload(payload)) {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');

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
                    notify("error", result?.message || "세션 설정 저장에 실패했습니다.");
                    return;
                }

                notify("success", "세션 설정이 저장되었습니다. 다음 로그인부터 적용됩니다.");
            } catch (error) {
                console.error("[session.js] save failed:", error);
                notify("error", "세션 설정 저장 중 오류가 발생했습니다.");
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });
    }

    function validatePayload(payload) {
        const timeout = parseInt(payload.session_timeout, 10);
        const alertTime = parseInt(payload.session_alert, 10);

        if (!Number.isInteger(timeout) || timeout < 1 || timeout > 1440) {
            notify("warning", "세션 유지 시간은 1~1440분 사이여야 합니다.");
            return false;
        }

        if (!Number.isInteger(alertTime) || alertTime < 1) {
            notify("warning", "만료 전 알림 시간은 1분 이상이어야 합니다.");
            return false;
        }

        if (alertTime > timeout) {
            notify("warning", "만료 전 알림 시간은 세션 유지 시간보다 클 수 없습니다.");
            return false;
        }

        return true;
    }

    function bindSoundPreviewEvents() {
        const soundSelect = document.getElementById("session_sound");
        const previewButton = document.getElementById("session-sound-preview-btn");
        const audio = document.getElementById("sound-preview");

        if (soundSelect) {
            soundSelect.addEventListener("change", updateSoundPreview);
        }

        if (previewButton) {
            previewButton.addEventListener("click", playSoundPreview);
        }

        if (audio) {
            audio.addEventListener("error", () => {
                notify("error", "사운드 파일을 불러오지 못했습니다.");
            });
        }
    }

    function updateSoundPreview() {
        const select = document.getElementById("session_sound");
        const audio = document.getElementById("sound-preview");
        if (!select || !audio) return;

        audio.pause();
        audio.currentTime = 0;
        audio.src = SOUND_BASE + select.value;
        audio.load();
    }

    async function playSoundPreview() {
        const audio = document.getElementById("sound-preview");
        if (!audio) return;

        if (!audio.src) {
            updateSoundPreview();
        }

        try {
            audio.pause();
            audio.currentTime = 0;
            await audio.play();
        } catch (error) {
            console.error("[session.js] preview play failed:", error);
            notify("error", "미리듣기 재생에 실패했습니다.");
        }
    }

    function normalizeAlertTime(source) {
        const timeoutInput = document.getElementById("session_timeout");
        const alertInput = document.getElementById("session_alert");
        if (!timeoutInput || !alertInput) return;

        const timeoutValue = parseInt(timeoutInput.value || timeoutInput.min || "1", 10);
        const alertValue = parseInt(alertInput.value || alertInput.min || "1", 10);

        alertInput.max = String(timeoutValue);

        if (source === "timeout" && alertValue > timeoutValue) {
            alertInput.value = timeoutValue;
        }

        if (source === "alert" && alertValue > timeoutValue) {
            alertInput.value = timeoutValue;
        }
    }

    function getValue(name) {
        const element = document.querySelector(`[name="${name}"]`);
        return element ? String(element.value || "").trim() : "";
    }

    function setValue(name, value) {
        const element = document.querySelector(`[name="${name}"]`);
        if (element) {
            element.value = value;
        }
    }
})();

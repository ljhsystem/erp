// // 경로: PROJECT_ROOT . '/public/assets/dashboard/settings/organization/common.utils.js'
// (function () {
//     'use strict';

//     console.log("common.utils.js Loaded");

//     window.EmployeeUtils = {
//         isImage(file) {
//             return file && file.type && file.type.match(/^image\//);
//         },

//         getVal(selector) {
//             const el = document.querySelector(selector);
//             if (!el) return '';
//             if (el.type === 'checkbox') return el.checked ? '1' : '0';
//             return el.value ?? '';
//         },

//         safeFocus(selector) {
//             const el = document.querySelector(selector);
//             if (el) el.focus();
//         },

//         hideAlertMessages() {
//             const success = document.querySelector(".alert-success");
//             const danger = document.querySelector(".alert-danger");
//             if (success) setTimeout(() => success.style.display = "none", 2500);
//             if (danger) setTimeout(() => danger.style.display = "none", 3500);
//         },

//         // 0/1, true/false → 1 또는 0
//         parseBool(v) {
//             return (v === 1 || v === true || v === "1") ? 1 : 0;
//         },

//         // null/undefined 안전한 텍스트 처리
//         safeText(v) {
//             return (v === null || v === undefined) ? "" : v;
//         },

//         // 숫자 정규화 (DataTables 정렬용)
//         toInt(v, defaultValue = 0) {
//             const n = Number(v);
//             return isNaN(n) ? defaultValue : n;
//         },

//         // 날짜 formatting (추후 필요 시)
//         formatDate(v) {
//             if (!v) return "";
//             const d = new Date(v);
//             if (isNaN(d.getTime())) return v;
//             return d.toISOString().split("T")[0];
//         },

//         // 알림용 공통 함수
//         alertError(msg) {
//             alert(msg || "오류가 발생했습니다.");
//         }

//     };
// })();



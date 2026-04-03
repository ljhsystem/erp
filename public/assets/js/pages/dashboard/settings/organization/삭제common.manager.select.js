// // 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/common.manager.select.js'
// (function () {
//     "use strict";

//     console.log("common.select.js Loaded");
    
//     // 각 API 정의
//     const API_MANAGERS = "/api/settings/employee/list"; // 직원 목록 API
//     const API_DEPARTMENTS = "/api/settings/department/list";  // 부서 목록 API
//     const API_POSITIONS   = "/api/settings/position/list";    // 직책 목록 API
//     const API_ROLES       = "/api/settings/role/list";        // 역할 목록 API
//     const API_CLIENTS     = "/api/settings/base-info/client/list";      // 거래처 목록 API
    
    
//     window.EmployeeManagerSelect = {

// // -------------------------
// // 생성 모달 Select2 초기화
// // -------------------------
// initCreate() {
//     const selectElements = [

//         // ---------------------------
//         // 직원 생성 모달
//         // ---------------------------
//         { selector: "#create_department_select", api: API_DEPARTMENTS, modal: "#employeeCreateModal" },
//         { selector: "#create_position_select",   api: API_POSITIONS,   modal: "#employeeCreateModal" },
//         { selector: "#create_role_select",       api: API_ROLES,       modal: "#employeeCreateModal" },
//         { selector: "#create_client_select",     api: API_CLIENTS,     modal: "#employeeCreateModal" },

//         // ---------------------------
//         // 부서 생성 모달 (부서장)
//         // ---------------------------
//         { selector: "#dept_create_manager_id", api: API_MANAGERS, modal: "#deptCreateModal" },

//         // ---------------------------
//         // 직책 생성 모달
//         // ---------------------------
//         { selector: "#position_create_id", api: API_POSITIONS, modal: "#positionCreateModal" },

//         // ---------------------------
//         // 역할 생성 모달
//         // ---------------------------
//         { selector: "#role_create_id", api: API_ROLES, modal: "#roleCreateModal" },
//         { selector: "#create_client_select", api: API_CLIENTS, modal: "#employeeCreateModal" }

//     ];

//     selectElements.forEach(item => {
//         const sel = $(item.selector);
//         if (!sel.length) return;

//         if (sel.hasClass("select2-hidden-accessible")) {
//             sel.select2("destroy");
//         }

//         this.buildSelect(sel, item.api, $(item.modal));
//     });
// },


// // -------------------------
// // 수정 모달 Select2 초기화
// // -------------------------
// initEdit(callback) {
//     const selectElements = [

//         // ---------------------------
//         // 직원 수정 모달
//         // ---------------------------
//         { selector: "#edit_department_select", api: API_DEPARTMENTS, modal: "#employeeEditModal" },
//         { selector: "#edit_position_select",   api: API_POSITIONS,   modal: "#employeeEditModal" },
//         { selector: "#edit_role_select",       api: API_ROLES,       modal: "#employeeEditModal" },
//         { selector: "#edit_client_select",     api: API_CLIENTS,     modal: "#employeeEditModal" },

//         // ---------------------------
//         // 부서 수정 모달 (부서장)
//         // ---------------------------
//         { selector: "#dept_edit_manager_id", api: API_MANAGERS, modal: "#deptEditModal" },

//         // ---------------------------
//         // 직책 수정 모달
//         // ---------------------------
//         { selector: "#position_edit_id", api: API_POSITIONS, modal: "#positionEditModal" },

//         // ---------------------------
//         // 역할 수정 모달
//         // ---------------------------
//         { selector: "#role_edit_id", api: API_ROLES, modal: "#roleEditModal" },

//         { selector: "#edit_client_select", api: API_CLIENTS, modal: "#employeeEditModal" }
//     ];

//     selectElements.forEach(item => {
//         const sel = $(item.selector);
//         if (!sel.length) return;

//         if (sel.hasClass("select2-hidden-accessible")) {
//             sel.select2("destroy");
//         }

//         this.buildSelect(sel, item.api, $(item.modal), callback);
//     });
// },

        

//         // -------------------------
// // Select2 공통 생성
// // -------------------------
// buildSelect($sel, api, $modal, callback) {
//     $.ajax({
//         url: api,
//         method: "POST",
//         dataType: "json"
//     })
//     .done(res => {
//         $sel.empty().append(`<option value=""></option>`);

//         let selectedValue = $sel.data("selected") || "";
//         let found = false;

//         // data가 없으면 빈 배열로 처리
//         const list = Array.isArray(res.data) ? res.data : [];

//         list.forEach(item => {
//             const value = item.id || item.user_id || item.code || null;
//             const name =
//                 item.name || item.employee_name || item.username || item.dept_name || item.position_name || item.role_name || item.client_name || item.title || "(이름 없음)";
//             if (!value) return;
//             if (String(value) === String(selectedValue)) found = true;
//             $sel.append(`<option value="${value}">${name}</option>`);
//         });

//         if (selectedValue && !found) {
//             $sel.append(`<option value="${selectedValue}">(이름 없음)</option>`);
//         }

//         $sel.select2({
//             placeholder: "선택해주세요",
//             allowClear: true,
//             width: "100%",
//             dropdownParent: $modal
//         });

//         $sel.val(String(selectedValue)).trigger("change");

//         if (typeof callback === "function") callback();
//     });
// },


// // -------------------------
// // 수정 모달 - 기존 값 세팅
// // -------------------------
// setEditValue(id, name, selector) {
//     const $sel = $(selector);
//     if (!$sel.length) return;

//     setTimeout(() => {

//         // 옵션이 없으면 추가
//         if (!$sel.find(`option[value="${id}"]`).length) {
//             const opt = new Option(name || "(이름 없음)", id, true, true);
//             $sel.append(opt);
//         }

//         $sel.val(id).trigger("change");

//     }, 150);
// }



//     };

// })();

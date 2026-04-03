// // 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/common.main.js'
// (function () {
//     "use strict";

//     console.log("common.main.js Loaded");

//     document.addEventListener("DOMContentLoaded", function () {

//         /* ----------------------------------------------------
//          * ⭐ 0. (삭제됨) 전역 AJAX 에러 처리 
//          * ---------------------------------------------------- */
//         // 전역 ajaxError 처리 코드는 제거되었습니다.

//         /* ----------------------------------------------------
//          * 1. EmployeeUtils 초기화
//          * ---------------------------------------------------- */


//         if (window.EmployeeUtils) {
//             console.log("EmployeeUtils loaded");
//             EmployeeUtils.hideAlertMessages();
//         } else {
//             console.warn("EmployeeUtils is missing");
//         }

//         /* ----------------------------------------------------
//          * 2. 직원 관련 모듈 초기화
//          * ---------------------------------------------------- */
//         if (window.EmployeePreview) {
//             window.EmployeePreview.initCreate?.();
//             window.EmployeePreview.initEdit?.();
//         }

//         window.EmployeeAddress?.bind?.();

//         // 직원 리스트 DataTable
//         window.EmployeeTable?.init?.();

//         // 부서 / 직책 / 권한 테이블
//         window.EmployeeDepartmentsTable?.init?.();
//         window.EmployeePositionsTable?.init?.();
//         window.EmployeeRolesTable?.init?.();

//         /* ----------------------------------------------------
//          * 3. 새 직원 생성 모달 열기
//          * ---------------------------------------------------- */
//         $(document)
//             .off("click.employeeMain", "#create-employee-btn")
//             .on("click.employeeMain", "#create-employee-btn", function () {
//                 if ($("#deptCreateModal").hasClass("show")) {
//                     return;
//                 }
//                 $(document).trigger("employee:create-open");
//             });

//         /* ----------------------------------------------------
//          * 4. 직원 수정 모달 열기 (별도 바인딩은 필요 없음)
//          * ---------------------------------------------------- */
//         // 이벤트는 employee.modal.js 에서 이미 처리됨

//         /* ----------------------------------------------------
//          * 5. backdrop 잔여 제거 로직 (modal.js에서 처리됨)
//          * ---------------------------------------------------- */
//     });

// })();

// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/departments.modal.js'

(function () {
    "use strict";

    console.log("departments.modal.js Loaded");

    const API_SAVE   = "/api/settings/department/save";
    const API_DELETE = "/api/settings/department/delete";    

    /* ----------------------------------------------------
     * Helper: 안전한 모달 닫기 + backdrop 제거
     * ---------------------------------------------------- */
    function closeModal(modalId) {
        const modalEl = document.getElementById(modalId);
        if (!modalEl) return;

        const instance = bootstrap.Modal.getInstance(modalEl);
        if (instance) instance.hide();

        // Bootstrap transition 이후 backdrop 제거
        setTimeout(() => {
            $(".modal-backdrop").remove();
            $("body").removeClass("modal-open");
        }, 250);
    }

    /* ----------------------------------------------------
     * 1) 새 부서 추가 모달 열기
     * ---------------------------------------------------- */
    $(document).on("dept:create-open", function () {

        const form = document.getElementById("dept-create-form");
        if (form) form.reset();

        // 🔹 Select2 초기화
        if (window.EmployeeManagerSelect?.initCreate) {
            EmployeeManagerSelect.initCreate();
        }

        const modalEl = document.getElementById("deptCreateModal");
        if (!modalEl) {
            console.error("❌ #deptCreateModal 요소 없음");
            return;
        }

        new bootstrap.Modal(modalEl).show();
    });

    /* ----------------------------------------------------
    * 2) 부서 수정 모달 열기
    * ---------------------------------------------------- */
    $(document).on("dept:edit-open", function (e, row) {

        const form = document.getElementById("dept-edit-form");
        if (form) form.reset();
    
        $("#dept_edit_id").val(row.id);
        $("#dept_edit_name").val(row.dept_name);
        $("#dept_edit_description").val(row.description || "");
        $("#dept_edit_is_active").prop("checked", row.is_active == 1);
    
        // 부서장 값 준비
        const mid = row.manager_id || row.user_id || "";
        const mname =
            row.manager_name ||
            row.employee_name ||
            row.username ||
            "(이름 없음)";
    
        // 모달 열기
        const modalEl = document.getElementById("deptEditModal");
        new bootstrap.Modal(modalEl).show();
    
        // Select2 초기화 → 선택값 data로 기록
        if (window.EmployeeManagerSelect?.initEdit) {
            $("#dept_edit_manager_id").data("selected", mid);
    
            EmployeeManagerSelect.initEdit(() => {
                const $sel = $("#dept_edit_manager_id");
                // 목록에 값이 없으면 추가
                if (mid && !$sel.find(`option[value="${mid}"]`).length) {
                    $sel.append(`<option value="${mid}">${mname}</option>`);
                    $sel.val(mid).trigger("change");
                }
            });
        }
    });

    /* ----------------------------------------------------
     * 3) 부서 생성 저장
     * ---------------------------------------------------- */
    $(document).on("submit", "#dept-create-form", function (e) {
        e.preventDefault();

        const payload = {
            action: "create",
            dept_name: $("#dept_create_name").val(),
            manager_id: $("#dept_create_manager_id").val(),
            description: $("#dept_create_description").val(),
            is_active: $("#dept_create_is_active").is(":checked") ? 1 : 0
        };

        $.post(API_SAVE, payload, function (res) {

            if (res?.success) {
                closeModal("deptCreateModal");
                EmployeeDepartmentsTable?.reload();
            } else if (res?.message === "duplicate") {
                alert("이미 존재하는 부서명입니다.");
            } else {
                alert("부서 저장에 실패했습니다.");
            }
            

        }, "json").fail((xhr) => {
            console.error("❌ 부서 저장 AJAX 실패:", xhr);
            console.error("🔥 서버 응답:", xhr.responseText);
            alert("서버 오류 / 저장 실패");
        });
    });

    /* ----------------------------------------------------
     * 4) 부서 수정 저장
     * ---------------------------------------------------- */
    $(document).on("submit", "#dept-edit-form", function (e) {
        e.preventDefault();

        let managerId = $("#dept_edit_manager_id").val();
        if (!managerId || managerId === "undefined" || typeof managerId === "undefined") {
            managerId = null;
        }

        const payload = {
            action: "update",
            id: $("#dept_edit_id").val(),
            dept_name: $("#dept_edit_name").val(),
            manager_id: managerId,
            description: $("#dept_edit_description").val(),
            is_active: $("#dept_edit_is_active").is(":checked") ? 1 : 0
        };

        $.post(API_SAVE, payload, function (res) {

            if (res?.success) {
                closeModal("deptEditModal");
                EmployeeDepartmentsTable?.reload();
            } else if (res?.message === "duplicate") {
                alert("이미 존재하는 부서명입니다.");
            } else {
                alert("부서 수정에 실패했습니다.");
            }
            

        }, "json").fail((xhr) => {
            console.error("❌ 부서 수정 AJAX 실패:", xhr);
            console.error("🔥 서버 오류 응답:", xhr.responseText);
            alert("서버 오류 / 수정 실패");
        });
    });

    /* ----------------------------------------------------
    * 5) 부서 삭제
    * ---------------------------------------------------- */
    $(document).on("click", "#dept_edit_delete_btn", function () {

        const id = $("#dept_edit_id").val();
        if (!id) return;

        if (!confirm("정말 이 부서를 삭제하시겠습니까?")) return;

        $.post("/api/settings/department/save", { 
            action: "delete", 
            id: id 
        }, function (res) {

            if (res?.success) {
                closeModal("deptEditModal");
                EmployeeDepartmentsTable?.reload();
            } else {
                alert("부서 삭제에 실패했습니다.");
                console.error("❌ 부서 삭제 실패:", res);
            }

        }, "json").fail((xhr) => {
            console.error("❌ 부서 삭제 AJAX 실패:", xhr);
            console.error("🔥 서버 오류 응답:", xhr.responseText);
            alert("서버 오류 / 삭제 실패");
        });
    });


})();

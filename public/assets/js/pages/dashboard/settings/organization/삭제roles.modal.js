// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/roles.modal.js'

(function () {
    "use strict";

    console.log("roles.modal.js Loaded");

    const API_SAVE = "/api/settings/role/save";


    /* ----------------------------------------------------
     * Helper: 안전한 모달 닫기 + backdrop 제거
     * ---------------------------------------------------- */
    function closeModal(modalId) {
        const instance = bootstrap.Modal.getInstance(document.getElementById(modalId));
        if (instance) instance.hide();

        setTimeout(() => {
            $(".modal-backdrop").remove();
            $("body").removeClass("modal-open");
        }, 250);
    }

    /* ----------------------------------------------------
     * 1) 새 역할(Role) 추가 모달 열기
     * ---------------------------------------------------- */
    $(document).on("roles:create-open", function () {

        const form = document.getElementById("role-create-form");
        if (form) form.reset();

        $("#role_create_is_active").prop("checked", true);

        new bootstrap.Modal(document.getElementById("roleCreateModal")).show();
    });

    /* ----------------------------------------------------
     * 2) 역할(Role) 수정 모달 열기
     * ---------------------------------------------------- */
    $(document).on("roles:edit-open", function (e, row) {

        const form = document.getElementById("role-edit-form");
        if (form) form.reset();

        $("#role_edit_id").val(row.id);
        $("#role_edit_key").val(row.role_key);
        $("#role_edit_name").val(row.role_name);
        $("#role_edit_desc").val(row.description ?? "");
        $("#role_edit_is_active").prop("checked", row.is_active == 1);

        new bootstrap.Modal(document.getElementById("roleEditModal")).show();
    });

    /* ----------------------------------------------------
     * 3) 역할 생성 저장
     * ---------------------------------------------------- */
    $(document).on("submit", "#role-create-form", function (e) {
        e.preventDefault();

        const payload = {
            action: "create",
            role_key: $("#role_create_key").val().trim(),
            role_name: $("#role_create_name").val().trim(),
            description: $("#role_create_desc").val().trim(),
            is_active: $("#role_create_is_active").is(":checked") ? 1 : 0
        };

        if (!payload.role_key || !payload.role_name) {
            alert("역할 Key와 Role 이름은 반드시 입력해야 합니다.");
            return;
        }

        $.post(API_SAVE, payload, function (res) {

            if (res?.success) {
                closeModal("roleCreateModal");
                EmployeeRolesTable?.reload();
            }
            else if (res?.message === "duplicate_key") {
                alert("이미 존재하는 Role Key 입니다.");
            }
            else {
                alert("역할 저장에 실패했습니다.");
            }

        }, "json").fail(xhr => {
            alert("서버 오류 / 저장 실패");
            console.error(xhr.responseText);
        });
    });

    /* ----------------------------------------------------
     * 4) 역할 수정 저장
     * ---------------------------------------------------- */
    $(document).on("submit", "#role-edit-form", function (e) {
        e.preventDefault();

        const payload = {
            action: "update",
            id: $("#role_edit_id").val(),
            role_key: $("#role_edit_key").val().trim(),
            role_name: $("#role_edit_name").val().trim(),
            description: $("#role_edit_desc").val().trim(),
            is_active: $("#role_edit_is_active").is(":checked") ? 1 : 0
        };

        if (!payload.role_key || !payload.role_name) {
            alert("역할 Key와 Role 이름은 반드시 입력해야 합니다.");
            return;
        }

        $.post(API_SAVE, payload, function (res) {

            if (res?.success) {
                closeModal("roleEditModal");
                EmployeeRolesTable?.reload();
            }
            else if (res?.message === "duplicate_key") {
                alert("이미 존재하는 Role Key 입니다.");
            }
            else {
                alert("역할 수정에 실패했습니다.");
            }

        }, "json").fail(xhr => {
            alert("서버 오류 / 수정 실패");
            console.error(xhr.responseText);
        });
    });

    /* ----------------------------------------------------
     * 5) 역할 삭제
     * ---------------------------------------------------- */
    $(document).on("click", "#role_edit_delete_btn", function () {
        const id = $("#role_edit_id").val();
        if (!id) return;

        if (!confirm("정말 이 역할을 삭제하시겠습니까?")) return;

        $.post(API_SAVE, { action: "delete", id }, function (res) {

            if (res?.success) {
                closeModal("roleEditModal");
                EmployeeRolesTable?.reload();
            } else {
                alert("역할 삭제에 실패했습니다.");
            }

        }, "json").fail(xhr => {
            alert("서버 오류 / 삭제 실패");
            console.error(xhr.responseText);
        });
    });

})();

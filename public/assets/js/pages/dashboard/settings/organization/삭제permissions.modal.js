// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/permissions.modal.js'

(function () {
    "use strict";

    console.log("permissions.modal.js Loaded");

    const API_SAVE = "/api/settings/permission/save";


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
     * 1) 새 권한(Permission) 추가 모달 열기
     * ---------------------------------------------------- */
    $(document).on("permissions:create-open", function () {

        const form = document.getElementById("permission-create-form");
        if (form) form.reset();

        // 필요 시 활성 체크박스 자동 체크
        $("#permission_create_is_active").prop("checked", true);

        new bootstrap.Modal(document.getElementById("permissionCreateModal")).show();
    });

    /* ----------------------------------------------------
     * 2) 권한(Permission) 수정 모달 열기
     * ---------------------------------------------------- */
    $(document).on("permissions:edit-open", function (e, row) {

        const form = document.getElementById("permission-edit-form");
        if (form) form.reset();

        $("#permission_edit_id").val(row.id);
        $("#permission_edit_key").val(row.permission_key);
        $("#permission_edit_name").val(row.permission_name);
        $("#permission_edit_desc").val(row.description ?? "");
        $("#permission_edit_category").val(row.category ?? "");

        // is_active 필드가 있는 경우만 사용
        $("#permission_edit_is_active").prop("checked", row.is_active == 1);

        new bootstrap.Modal(document.getElementById("permissionEditModal")).show();
    });

    /* ----------------------------------------------------
     * 3) 권한 생성 저장
     * ---------------------------------------------------- */
    $(document).on("submit", "#permission-create-form", function (e) {
        e.preventDefault();

        const payload = {
            action: "create",
            permission_key: $("#permission_create_key").val().trim(),
            permission_name: $("#permission_create_name").val().trim(),
            description: $("#permission_create_desc").val().trim(),
            category: $("#permission_create_category").val().trim(),
            is_active: $("#permission_create_is_active").is(":checked") ? 1 : 0
        };

        if (!payload.permission_key || !payload.permission_name) {
            alert("권한 Key와 권한명을 반드시 입력해야 합니다.");
            return;
        }

        $.post(API_SAVE, payload, function (res) {

            if (res?.success) {
                closeModal("permissionCreateModal");
                EmployeePermissionsTable?.reload();
            }
            else if (res?.message === "duplicate_key") {
                alert("이미 존재하는 권한 Key 입니다.");
            }
            else {
                alert("권한 저장에 실패했습니다.");
            }

        }, "json").fail(xhr => {
            alert("서버 오류 / 저장 실패");
            console.error(xhr.responseText);
        });
    });

    /* ----------------------------------------------------
     * 4) 권한 수정 저장
     * ---------------------------------------------------- */
    $(document).on("submit", "#permission-edit-form", function (e) {
        e.preventDefault();

        const payload = {
            action: "update",
            id: $("#permission_edit_id").val(),
            permission_key: $("#permission_edit_key").val().trim(),
            permission_name: $("#permission_edit_name").val().trim(),
            description: $("#permission_edit_desc").val().trim(),
            category: $("#permission_edit_category").val().trim(),
            is_active: $("#permission_edit_is_active").is(":checked") ? 1 : 0
        };

        if (!payload.permission_key || !payload.permission_name) {
            alert("권한 Key와 권한명은 반드시 입력해야 합니다.");
            return;
        }

        $.post(API_SAVE, payload, function (res) {

            if (res?.success) {
                closeModal("permissionEditModal");
                EmployeePermissionsTable?.reload();
            }
            else if (res?.message === "duplicate_key") {
                alert("이미 존재하는 권한 Key 입니다.");
            }
            else {
                alert("권한 수정에 실패했습니다.");
            }

        }, "json").fail(xhr => {
            alert("서버 오류 / 수정 실패");
            console.error(xhr.responseText);
        });
    });

    /* ----------------------------------------------------
     * 5) 권한 삭제
     * ---------------------------------------------------- */
    $(document).on("click", "#permission_edit_delete_btn", function () {
        const id = $("#permission_edit_id").val();
        if (!id) return;

        if (!confirm("정말 이 권한을 삭제하시겠습니까?")) return;

        $.post(API_SAVE, { action: "delete", id }, function (res) {

            if (res?.success) {
                closeModal("permissionEditModal");
                EmployeePermissionsTable?.reload();
            } else {
                alert("권한 삭제에 실패했습니다.");
            }

        }, "json").fail(xhr => {
            alert("서버 오류 / 삭제 실패");
            console.error(xhr.responseText);
        });
    });

})();

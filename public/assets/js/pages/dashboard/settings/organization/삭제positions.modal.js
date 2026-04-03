// 경로: PROJECT_ROOT/public/assets/js/pages/dashboard/settings/organization/positions.modal.js

(function () {
    "use strict";

    console.log("positions.modal.js Loaded");

    const API_SAVE = "/api/settings/position/save";


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
     * 1) 새 직책 추가 모달 열기
     * ---------------------------------------------------- */
    $(document).on("positions:create-open", function () {

        const form = document.getElementById("pos-create-form");
        if (form) form.reset();

        $("#pos_create_select").val("").trigger("change");

        new bootstrap.Modal(document.getElementById("positionCreateModal")).show();
    });



    /* ----------------------------------------------------
     * 2) 직책 수정 모달 열기 (DB 기준 자동 선택)
     * ---------------------------------------------------- */
    $(document).on("positions:edit-open", function (e, row) {

        const form = document.getElementById("pos-edit-form");
        if (form) form.reset();

        $("#pos_edit_id").val(row.id);
        $("#pos_edit_is_active").prop("checked", row.is_active == 1);

        // 설명 값 DB 기준으로 넣기
        $("#pos_edit_desc").val(row.description ?? "");

        // 🔥 DB 값 = row.position_name
        const dbName = row.position_name;

        // 먼저 시도 → 기존 option 중 동일한 value 선택
        $("#pos_edit_select").val(dbName);

        // 🔥 만약 없는 경우 → 동적으로 option 생성 후 선택
        if ($("#pos_edit_select").val() === null) {
            $("#pos_edit_select").append(`
                <option value="${dbName}" selected>${dbName}</option>
            `);
        }

        // 선택된 값 기준으로 rank/desc 자동 반영
        applyPositionInfo("#pos_edit_select");

        // DB의 랭크 값 표시
        $("#pos_edit_rank").val(row.level_rank ?? "");

        new bootstrap.Modal(document.getElementById("positionEditModal")).show();
    });



    /* ----------------------------------------------------
     * 3) 직책 생성 저장
     * ---------------------------------------------------- */
    $(document).on("submit", "#pos-create-form", function (e) {
        e.preventDefault();

        const payload = {
            action: "create",
            position_name: $("#pos_create_select").val(),
            level_rank: $("#pos_create_rank").val(),
            description: $("#pos_create_desc").val(),
            is_active: $("#pos_create_is_active").is(":checked") ? 1 : 0
        };

        $.post(API_SAVE, payload, function (res) {

            if (res?.success) {
                closeModal("positionCreateModal");
                EmployeePositionsTable?.reload();
            }
            else if (res?.message === "duplicate") {
                alert("이미 존재하는 직책명입니다.");
            }
            else {
                alert("직책 저장에 실패했습니다.");
            }

        }, "json").fail(xhr => {
            alert("서버 오류 / 저장 실패");
            console.error(xhr.responseText);
        });
    });



    /* ----------------------------------------------------
     * 4) 직책 수정 저장
     * ---------------------------------------------------- */
    $(document).on("submit", "#pos-edit-form", function (e) {
        e.preventDefault();

        const payload = {
            action: "update",
            id: $("#pos_edit_id").val(),
            position_name: $("#pos_edit_select").val(),
            level_rank: $("#pos_edit_rank").val(),
            description: $("#pos_edit_desc").val(),
            is_active: $("#pos_edit_is_active").is(":checked") ? 1 : 0
        };

        $.post(API_SAVE, payload, function (res) {

            if (res?.success) {
                closeModal("positionEditModal");
                EmployeePositionsTable?.reload();
            }
            else if (res?.message === "duplicate") {
                alert("이미 존재하는 직책명입니다.");
            }
            else {
                alert("직책 수정에 실패했습니다.");
            }

        }, "json").fail(xhr => {
            alert("서버 오류 / 수정 실패");
            console.error(xhr.responseText);
        });
    });



    /* ----------------------------------------------------
     * 5) 직책 삭제
     * ---------------------------------------------------- */
    $(document).on("click", "#pos_edit_delete_btn", function () {

        const id = $("#pos_edit_id").val();
        if (!id) return;

        if (!confirm("정말 이 직책을 삭제하시겠습니까?")) return;

        $.post(API_SAVE, { action: "delete", id }, function (res) {

            if (res?.success) {
                closeModal("positionEditModal");
                EmployeePositionsTable?.reload();
            } else {
                alert("직책 삭제에 실패했습니다.");
            }

        }, "json").fail(xhr => {
            alert("서버 오류 / 삭제 실패");
            console.error(xhr.responseText);
        });
    });



    /* ----------------------------------------------------
     * 6) select 변경 시 – rank 자동 반영
     * ---------------------------------------------------- */
    function applyPositionInfo(selectEl) {
        const $el = $(selectEl);
        const $opt = $el.find("option:selected");

        const mode = $el.attr("id").includes("create") ? "create" : "edit";

        // DB 값은 rank/description이 없을 수 있음 → 그대로 유지
        if ($opt.length === 0) return;

        $(`#pos_${mode}_rank`).val($opt.data("rank") ?? "");
    }



    /* ----------------------------------------------------
     * 7) select 변경 이벤트 바인딩 (공통)
     * ---------------------------------------------------- */
    $(document).on("change", "#pos_create_select, #pos_edit_select", function () {
        applyPositionInfo(this);
    });

})();

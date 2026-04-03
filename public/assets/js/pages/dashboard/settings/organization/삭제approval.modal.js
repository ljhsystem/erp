// PROJECT_ROOT/public/assets/js/pages/dashboard/settings/organization/approval.modal.js
(function ($) {
    "use strict";

    $(document).ready(function () {

        console.log("approval.modal.js Loaded");

        /* ============================================================
         * 🔧 Normalize Helper
         * ============================================================ */
        function normalize(str) {
            if (!str) return "";
            return str.trim().replace(/\s+/g, " ");
        }

        /* ============================================================
         * 템플릿 생성
         * ============================================================ */
        $("#btn-create-template").on("click", function () {
            $("#tpl-create-name").val("");
            $("#tpl-create-doc-type").val("");
            $("#tpl-create-desc").val("");
            $("#tpl-create-active").prop("checked", true);

            $("#modal-template-create").modal("show");
        });

        $("#btn-save-template-create").on("click", function () {

            $.post("/api/settings/approval/template/save", {
                name: normalize($("#tpl-create-name").val()),
                document_type: normalize($("#tpl-create-doc-type").val()),
                description: normalize($("#tpl-create-desc").val()),
                is_active: $("#tpl-create-active").is(":checked") ? 1 : 0
            }, function (res) {

                if (res.success) {
                    $("#modal-template-create").modal("hide");
                    $("#template-list-table").DataTable().ajax.reload();
                    return;
                }

                alert(res.message);

            }, "json");
        });

        /* ============================================================
         * 템플릿 수정 — 여기에서 normalize 적용이 매우 중요!
         * ============================================================ */
        window.onTemplateEditOpen = function (row) {
            $("#tpl-edit-id").val(row.id);
            $("#tpl-edit-name").val(normalize(row.template_name));
            $("#tpl-edit-doc-type").val(normalize(row.document_type));
            $("#tpl-edit-desc").val(normalize(row.description ?? ""));
            $("#tpl-edit-active").prop("checked", row.is_active == 1);

            $("#modal-template-edit").modal("show");
        };

        $("#btn-save-template-edit").on("click", function () {

            const payload = {
                id: $("#tpl-edit-id").val(),
                name: normalize($("#tpl-edit-name").val()),
                document_type: normalize($("#tpl-edit-doc-type").val()),
                description: normalize($("#tpl-edit-desc").val()),
                is_active: $("#tpl-edit-active").is(":checked") ? 1 : 0
            };
        
            // 🚨 전송 데이터 콘솔 출력
            console.log("🔎 [TEMPLATE EDIT] 전송 직전 데이터:", payload);
        
            $.post("/api/settings/approval/template/save", payload, function (res) {
        
                // 🚨 서버 응답 콘솔 출력
                console.log("📥 [TEMPLATE EDIT] 서버 응답:", res);
        
                if (res.success) {
                    $("#modal-template-edit").modal("hide");
                    $("#template-list-table").DataTable().ajax.reload(null, false);
                    reloadSteps();
                    return;
                }
        
                alert(res.message);
        
            }, "json");
        });
        

        /* ============================================================
         * 템플릿 삭제
         * ============================================================ */
        $("#btn-delete-template-edit").on("click", function () {
            if (!confirm("정말 삭제하시겠습니까?")) return;

            $.post("/api/settings/approval/template/delete", {
                id: $("#tpl-edit-id").val()
            }, function (res) {

                if (res.success) {
                    $("#modal-template-edit").modal("hide");
                    $("#template-list-table").DataTable().ajax.reload();
                    $("#steps-sortable").empty();

                    window.selectedTemplateId = null;
                    window.selectedStepId = null;
                    return;
                }

                alert(res.message || "삭제 실패");

            }, "json");
        });

        /* ============================================================
         * 스텝 생성
         * ============================================================ */
        $("#btn-add-step").on("click", async function () {

            if (!window.selectedTemplateId) {
                alert("먼저 템플릿을 선택하세요.");
                return;
            }

            await Promise.all([loadRoleList(), loadUserList()]);

            $("#step-create-name").val("");
            $("#step-create-active").prop("checked", true);

            fillRoleSelect("#step-create-role");
            fillUserSelect("#step-create-user");

            $("#modal-step-create").modal("show");
        });

        $("#btn-save-step-create").on("click", function () {

            $.post("/api/settings/approval/step/save", {
                template_id: window.selectedTemplateId,
                step_name: normalize($("#step-create-name").val()),
                role_id: $("#step-create-role").val(),
                approver_id: $("#step-create-user").val(),
                is_active: $("#step-create-active").is(":checked") ? 1 : 0
            }, function (res) {

                if (res.success) {
                    $("#modal-step-create").modal("hide");
                    reloadSteps();
                    return;
                }

                alert(res.message);

            }, "json");
        });

        /* ============================================================
         * 스텝 수정
         * ============================================================ */
        window.onStepEditOpen = async function (step) {

            await Promise.all([loadRoleList(), loadUserList()]);

            $("#step-edit-id").val(step.id);
            $("#step-edit-name").val(normalize(step.step_name));
            $("#step-edit-active").prop("checked", step.is_active == 1);

            fillRoleSelect("#step-edit-role", step.role_id);
            fillUserSelect("#step-edit-user", step.approver_id);

            $("#modal-step-edit").modal("show");
        };

        $("#btn-save-step-edit").on("click", function () {

            $.post("/api/settings/approval/step/save", {
                id: $("#step-edit-id").val(),
                template_id: window.selectedTemplateId,
                step_name: normalize($("#step-edit-name").val()),
                role_id: $("#step-edit-role").val(),
                approver_id: $("#step-edit-user").val(),
                is_active: $("#step-edit-active").is(":checked") ? 1 : 0
            }, function (res) {

                if (res.success) {
                    $("#modal-step-edit").modal("hide");
                    reloadSteps();
                    return;
                }

                alert(res.message);

            }, "json");
        });

        /* ============================================================
         * 스텝 삭제
         * ============================================================ */
        $("#btn-delete-step-edit").on("click", function () {

            if (!confirm("정말 삭제하시겠습니까?")) return;

            $.post("/api/settings/approval/step/delete", {
                step_id: $("#step-edit-id").val()
            }, function (res) {

                if (res.success) {
                    $("#modal-step-edit").modal("hide");
                    reloadSteps();
                    return;
                }

                alert(res.message || "삭제 실패");

            }, "json");
        });

        /* ============================================================
         * 스텝 재로드
         * ============================================================ */
        const reloadSteps = () => {
            if (typeof window.loadApprovalSteps === "function") {
                window.loadApprovalSteps();
            }
        };

        /* ============================================================
         * 역할 & 유저 목록
         * ============================================================ */
        let ROLE_LIST = [];
        let USER_LIST = [];

        function loadRoleList() {
            return $.post("/api/settings/role/list", {}, function (res) {
                if (res.success) ROLE_LIST = res.data;
            }, "json");
        }

        function loadUserList() {
            return $.post("/api/settings/employee/list", {}, function (res) {
                if (res.success) USER_LIST = res.data;
            }, "json");
        }

        Promise.all([loadRoleList(), loadUserList()]);

        /* ============================================================
         * Helper
         * ============================================================ */
        function fillRoleSelect(sel, selected = "") {
            const $el = $(sel);
            $el.empty().append(`<option value="">선택</option>`);

            ROLE_LIST.forEach(r => {
                $el.append(`
                    <option value="${r.id}" ${selected == r.id ? "selected" : ""}>
                        ${r.role_name}
                    </option>
                `);
            });
        }

        function fillUserSelect(sel, selected = "") {
            const $el = $(sel);
            $el.empty().append(`<option value="">선택 안함</option>`);

            USER_LIST.forEach(u => {
                $el.append(`
                    <option value="${u.user_id}" ${selected == u.user_id ? "selected" : ""}>
                        ${u.employee_name} (${u.username})
                    </option>
                `);
            });
        }

    });
})(jQuery);

// PROJECT_ROOT/public/assets/js/pages/dashboard/settings/organization/approval.templates.js
// 결재 템플릿 목록 & 스텝 리스트 로딩

(function ($) {
    "use strict";

    console.log("approval.templates.js Loaded");

    /* ============================================================
     * Helper: 문자열 Normalize
     * (템플릿 수정 시 공백 문제로 인해 중복검사 실패하는 현상 방지)
     * ============================================================ */
    function normalize(str) {
        if (!str) return "";
        return str.trim().replace(/\s+/g, " ");
    }

    /* ============================================================
     * 전역 상태값
     * ============================================================ */
    window.selectedTemplateId = null;
    window.selectedStepId = null;
    let isSorting = false;

    /* ============================================================
     * API 정의
     * ============================================================ */
    const API_TEMPLATE_LIST = "/api/settings/approval/template/list";
    const API_STEP_LIST     = "/api/settings/approval/step/list";
    const API_STEP_SAVE     = "/api/settings/approval/step/save";

    /* ============================================================
     * 템플릿 목록 DataTable
     * ============================================================ */
    let templateTable = $("#template-list-table").DataTable({
        paging: false,
        searching: false,
        info: false,
        ordering: false,
        ajax: {
            url: API_TEMPLATE_LIST,
            type: "POST",
            dataSrc: res => res.data || []
        },
        columns: [
            { data: "template_name" },
            { data: "document_type" },
            { data: "description" },
            {
                data: "is_active",
                render: d =>
                    d == 1
                        ? `<span class="badge bg-success">활성</span>`
                        : `<span class="badge bg-secondary">비활성</span>`
            }
        ]
    });

    /* ============================================================
     * 템플릿 클릭 → 스텝 로딩
     * ============================================================ */
    $("#template-list-table tbody").on("click", "tr", function () {

        let row = templateTable.row(this).data();
        if (!row) return;

        window.selectedTemplateId = row.id;
        window.selectedStepId = null;

        $("#template-list-table tbody tr").removeClass("table-active");
        $(this).addClass("table-active");

        $("#btn-add-step").prop("disabled", false);
        $("#ap-selected-template-name").text(`(${row.template_name})`);

        loadSteps();
    });

    /* ============================================================
     * 템플릿 더블클릭 → 수정 모달 (Normalize 적용)
     * ============================================================ */
    $("#template-list-table tbody").on("dblclick", "tr", function () {

        let row = templateTable.row(this).data();
        if (!row) return;

        window.selectedTemplateId = row.id;

        $("#tpl-edit-id").val(row.id);
        $("#tpl-edit-name").val(normalize(row.template_name));
        $("#tpl-edit-doc-type").val(normalize(row.document_type));
        $("#tpl-edit-desc").val(normalize(row.description ?? ""));

        $("#tpl-edit-active").prop("checked", row.is_active == 1);

        $("#modal-template-edit").modal("show");
    });

    /* ============================================================
     * 스텝 목록 로딩
     * ============================================================ */
    function loadSteps() {

        if (isSorting) return;
        if (!window.selectedTemplateId) return;

        $.post(API_STEP_LIST, { template_id: window.selectedTemplateId })
            .done(function (res) {

                if (!res.success || !Array.isArray(res.data)) return;

                let tbody = $("#steps-sortable");
                tbody.empty();

                res.data.forEach(step => tbody.append(buildStepRow(step)));

                initSortable();
            })
            .fail(function (xhr) {
                console.error("❌ loadSteps 오류:", xhr.responseText);
            });
    }

    window.loadApprovalSteps = loadSteps;

    /* ============================================================
     * Row 생성
     * ============================================================ */
    function buildStepRow(step) {
        return `
            <tr data-id="${step.id}"
                data-sequence="${step.sequence}"
                data-step_name="${step.step_name ?? ""}"
                data-role_id="${step.role_id ?? ""}"
                data-role_name="${step.role_name ?? ""}"
                data-user_id="${step.approver_id ?? ""}"
                data-user_name="${step.specific_employee_name ?? ""}"
                data-active="${step.is_active}">
                
                <td class="text-center drag-handle" style="cursor: grab;">${step.sequence}</td>
                <td>${step.step_name ?? ""}</td>
                <td>${step.role_name ?? "-"}</td>
                <td>${step.specific_employee_name ?? "-"}</td>
                <td>
                    ${step.is_active == 1
                        ? '<span class="badge bg-success">활성</span>'
                        : '<span class="badge bg-secondary">비활성</span>'}
                </td>
            </tr>
        `;
    }

    /* ============================================================
     * 스텝 클릭 → 선택 표시
     * ============================================================ */
    $(document).on("click", "#steps-sortable tr", function () {
        window.selectedStepId = $(this).data("id");
        $("#steps-sortable tr").removeClass("table-active");
        $(this).addClass("table-active");
    });

    /* ============================================================
     * 스텝 더블클릭 → 수정 모달
     * ============================================================ */
    $(document).on("dblclick", "#steps-sortable tr", function () {

        let row = $(this);

        let stepData = {
            id: row.data("id"),
            sequence: row.data("sequence"),
            step_name: row.data("step_name"),
            role_id: row.data("role_id"),
            role_name: row.data("role_name"),
            approver_id: row.data("user_id"),
            specific_employee_name: row.data("user_name"),
            is_active: row.data("active")
        };

        if (typeof window.onStepEditOpen === "function") {
            window.onStepEditOpen(stepData);
        }
    });

    /* ============================================================
     * Drag & Drop 정렬
     * ============================================================ */
    function initSortable() {

        if ($("#steps-sortable").data("ui-sortable")) {
            $("#steps-sortable").sortable("destroy");
        }

        $("#steps-sortable").sortable({
            handle: ".drag-handle",

            start: function () {
                isSorting = true;
            },

            stop: function () {

                let updateList = [];

                $("#steps-sortable tr").each(function (i) {
                    $(this).find("td:first").text(i + 1);
                    updateList.push({
                        id: $(this).data("id"),
                        sequence: i + 1
                    });
                });

                $.post(API_STEP_SAVE, {
                    reorder: 1,
                    template_id: window.selectedTemplateId,
                    steps: JSON.stringify(updateList)
                }).done(function (res) {
                    console.log("🔄 서버 순서 저장 완료", res);
                });

                setTimeout(() => {
                    isSorting = false;
                }, 200);
            }
        });
    }

})(jQuery);

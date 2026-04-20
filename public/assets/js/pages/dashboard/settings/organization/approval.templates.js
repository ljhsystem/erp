(function ($) {
    "use strict";

    console.log("[approval.templates.js] loaded");

    const API = {
        TEMPLATE_LIST: "/api/settings/organization/approval/template/list",
        TEMPLATE_SAVE: "/api/settings/organization/approval/template/save",
        TEMPLATE_DELETE: "/api/settings/organization/approval/template/delete",
        STEP_LIST: "/api/settings/organization/approval/step/list",
        STEP_SAVE: "/api/settings/organization/approval/step/save",
        STEP_DELETE: "/api/settings/organization/approval/step/delete",
        ROLE_LIST: "/api/settings/organization/role/list",
        EMPLOYEE_LIST: "/api/settings/organization/employee/list"
    };

    let selectedTemplateId = null;
    let selectedStepId = null;
    let templateTable = null;
    let templateModal = null;
    let stepModal = null;
    let isSorting = false;
    let roleList = [];
    let userList = [];

    function normalize(value) {
        return String(value ?? "").trim().replace(/\s+/g, " ");
    }

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

    function init() {
        initModals();
        initTemplateTable();
        bindTemplateEvents();
        bindStepEvents();
        bindLayoutEvents();
        adjustLayout();
        preloadSelectLists();
    }

    function initModals() {
        const templateModalEl = document.getElementById("modal-template-edit");
        const stepModalEl = document.getElementById("modal-step-edit");

        if (templateModalEl) {
            templateModal = new bootstrap.Modal(templateModalEl, { focus: false });
            templateModalEl.addEventListener("hidden.bs.modal", resetTemplateModal);
        }

        if (stepModalEl) {
            stepModal = new bootstrap.Modal(stepModalEl, { focus: false });
            stepModalEl.addEventListener("hidden.bs.modal", resetStepModal);
        }
    }

    function initTemplateTable() {
        templateTable = $("#template-list-table").DataTable({
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            ajax: {
                url: API.TEMPLATE_LIST,
                type: "POST",
                dataSrc: res => res?.data || []
            },
            columns: [
                { data: "template_name" },
                { data: "document_type" },
                { data: "description", defaultContent: "" },
                {
                    data: "is_active",
                    render: d => String(d) === "1"
                        ? '<span class="badge bg-success">활성</span>'
                        : '<span class="badge bg-secondary">비활성</span>'
                }
            ],
            createdRow: function (row, data) {
                if (selectedTemplateId && String(data.id) === String(selectedTemplateId)) {
                    $(row).addClass("table-active");
                }
            },
            initComplete: updateTemplateCount,
            drawCallback: updateTemplateCount
        });
    }

    function bindTemplateEvents() {
        $("#template-list-table tbody").on("click", "tr", function () {
            const row = templateTable.row(this).data();
            if (!row) return;

            selectedTemplateId = row.id;
            selectedStepId = null;

            $("#template-list-table tbody tr").removeClass("table-active");
            $(this).addClass("table-active");

            $("#btn-add-step").prop("disabled", false);
            $("#ap-selected-template-name").text(`[${row.template_name}]`);

            loadSteps();
        });

        $("#template-list-table tbody").on("dblclick", "tr", function () {
            const row = templateTable.row(this).data();
            if (!row) return;

            openTemplateModal("edit", row);
        });

        $("#btn-create-template").on("click", function () {
            openTemplateModal("create");
        });

        $("#btn-save-template-edit").on("click", async function () {
            await saveTemplate();
        });

        $("#btn-delete-template-edit").on("click", async function () {
            const id = $("#tpl-edit-id").val();
            if (!id) return;
            if (!confirm("템플릿을 삭제하시겠습니까?")) return;

            try {
                const res = await $.post(API.TEMPLATE_DELETE, { id });
                if (!res?.success) {
                    notify("error", res?.message || "삭제 실패");
                    return;
                }

                if (String(selectedTemplateId) === String(id)) {
                    selectedTemplateId = null;
                    selectedStepId = null;
                    $("#ap-selected-template-name").text("");
                    $("#approvalStepCount").text("");
                    $("#btn-add-step").prop("disabled", true);
                    $("#steps-sortable").empty();
                }

                templateModal?.hide();
                reloadTemplateTable();
                notify("success", "삭제되었습니다.");
            } catch (err) {
                console.error("[approval] delete template failed:", err);
                notify("error", "삭제 중 오류가 발생했습니다.");
            }
        });
    }

    function bindStepEvents() {
        $("#btn-add-step").on("click", async function () {
            if (!selectedTemplateId) {
                notify("warning", "먼저 템플릿을 선택하세요.");
                return;
            }

            await preloadSelectLists();
            openStepModal("create");
        });

        $(document).on("click", "#steps-sortable tr", function () {
            selectedStepId = $(this).data("id");
            $("#steps-sortable tr").removeClass("table-active");
            $(this).addClass("table-active");
        });

        $(document).on("dblclick", "#steps-sortable tr", async function () {
            await preloadSelectLists();

            const row = $(this);
            openStepModal("edit", {
                id: row.data("id"),
                step_name: row.data("step_name"),
                role_id: row.data("role_id"),
                approver_id: row.data("user_id"),
                is_active: row.data("active")
            });
        });

        $("#btn-save-step-edit").on("click", async function () {
            await saveStep();
        });

        $("#btn-delete-step-edit").on("click", async function () {
            const stepId = $("#step-edit-id").val();
            if (!stepId) return;
            if (!confirm("단계를 삭제하시겠습니까?")) return;

            try {
                const res = await $.post(API.STEP_DELETE, { step_id: stepId });
                if (!res?.success) {
                    notify("error", res?.message || "삭제 실패");
                    return;
                }

                stepModal?.hide();
                loadSteps();
                notify("success", "삭제되었습니다.");
            } catch (err) {
                console.error("[approval] delete step failed:", err);
                notify("error", "삭제 중 오류가 발생했습니다.");
            }
        });
    }

    function openTemplateModal(mode, row = null) {
        resetTemplateModal();

        const isCreate = mode === "create";
        $("#modal-template-edit .modal-title").text(isCreate ? "템플릿 등록" : "템플릿 수정");
        $("#btn-delete-template-edit").toggle(!isCreate);

        if (!isCreate && row) {
            $("#tpl-edit-id").val(row.id || "");
            $("#tpl-edit-name").val(normalize(row.template_name));
            $("#tpl-edit-doc-type").val(normalize(row.document_type));
            $("#tpl-edit-desc").val(normalize(row.description ?? ""));
            $("#tpl-edit-active").prop("checked", String(row.is_active) === "1");
        } else {
            $("#tpl-edit-active").prop("checked", true);
        }

        templateModal?.show();
    }

    function resetTemplateModal() {
        $("#tpl-edit-id").val("");
        $("#tpl-edit-name").val("");
        $("#tpl-edit-doc-type").val("");
        $("#tpl-edit-desc").val("");
        $("#tpl-edit-active").prop("checked", true);
        $("#btn-delete-template-edit").hide();
    }

    async function saveTemplate() {
        const id = $("#tpl-edit-id").val();
        const payload = {
            id,
            name: normalize($("#tpl-edit-name").val()),
            document_type: normalize($("#tpl-edit-doc-type").val()),
            description: normalize($("#tpl-edit-desc").val()),
            is_active: $("#tpl-edit-active").is(":checked") ? 1 : 0
        };

        if (!payload.name || !payload.document_type) {
            notify("warning", "템플릿명과 문서유형을 입력하세요.");
            return;
        }

        try {
            const res = await $.post(API.TEMPLATE_SAVE, payload);
            if (!res?.success) {
                notify("error", res?.message || "저장 실패");
                return;
            }

            templateModal?.hide();
            reloadTemplateTable(id || res?.id || null);
            notify("success", "저장되었습니다.");
        } catch (err) {
            console.error("[approval] save template failed:", err);
            notify("error", "저장 중 오류가 발생했습니다.");
        }
    }

    function openStepModal(mode, step = null) {
        resetStepModal();

        const isCreate = mode === "create";
        $("#modal-step-edit .modal-title").text(isCreate ? "단계 등록" : "단계 수정");
        $("#btn-delete-step-edit").toggle(!isCreate);

        fillRoleSelect("#step-edit-role", step?.role_id || "");
        fillUserSelect("#step-edit-user", step?.approver_id || "");

        if (!isCreate && step) {
            $("#step-edit-id").val(step.id || "");
            $("#step-edit-name").val(normalize(step.step_name || ""));
            $("#step-edit-active").prop("checked", String(step.is_active) === "1");
        } else {
            $("#step-edit-active").prop("checked", true);
        }

        stepModal?.show();
    }

    function resetStepModal() {
        $("#step-edit-id").val("");
        $("#step-edit-name").val("");
        $("#step-edit-role").empty();
        $("#step-edit-user").empty();
        $("#step-edit-active").prop("checked", true);
        $("#btn-delete-step-edit").hide();
    }

    async function saveStep() {
        if (!selectedTemplateId) {
            notify("warning", "먼저 템플릿을 선택하세요.");
            return;
        }

        const payload = {
            id: $("#step-edit-id").val(),
            template_id: selectedTemplateId,
            step_name: normalize($("#step-edit-name").val()),
            role_id: $("#step-edit-role").val(),
            approver_id: $("#step-edit-user").val(),
            is_active: $("#step-edit-active").is(":checked") ? 1 : 0
        };

        if (!payload.step_name || !payload.role_id) {
            notify("warning", "단계명과 결재역할을 입력하세요.");
            return;
        }

        try {
            const res = await $.post(API.STEP_SAVE, payload);
            if (!res?.success) {
                notify("error", res?.message || "저장 실패");
                return;
            }

            stepModal?.hide();
            loadSteps();
            notify("success", "저장되었습니다.");
        } catch (err) {
            console.error("[approval] save step failed:", err);
            notify("error", "저장 중 오류가 발생했습니다.");
        }
    }

    async function preloadSelectLists() {
        try {
            const [roleRes, userRes] = await Promise.all([
                $.get(API.ROLE_LIST),
                $.get(API.EMPLOYEE_LIST)
            ]);

            roleList = Array.isArray(roleRes?.data) ? roleRes.data : [];
            userList = Array.isArray(userRes?.data) ? userRes.data : [];
        } catch (err) {
            console.error("[approval] preload select lists failed:", err);
        }
    }

    function fillRoleSelect(selector, selected = "") {
        const $el = $(selector);
        $el.empty().append('<option value="">선택</option>');

        roleList.forEach(role => {
            $el.append(`
                <option value="${role.id}" ${String(selected) === String(role.id) ? "selected" : ""}>
                    ${role.role_name}
                </option>
            `);
        });
    }

    function fillUserSelect(selector, selected = "") {
        const $el = $(selector);
        $el.empty().append('<option value="">선택 안함</option>');

        userList.forEach(user => {
            const label = user.employee_name
                ? `${user.employee_name} (${user.username || ""})`
                : (user.username || user.user_id);

            $el.append(`
                <option value="${user.user_id}" ${String(selected) === String(user.user_id) ? "selected" : ""}>
                    ${label}
                </option>
            `);
        });
    }

    function updateTemplateCount() {
        if (!templateTable?.page) return;
        const info = templateTable.page.info();
        $("#approvalTemplateCount").text(`총 ${info?.recordsDisplay ?? 0}건`);
    }

    function updateStepCount(count) {
        $("#approvalStepCount").text(count ? `총 ${count}단계` : "");
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function reloadTemplateTable(preferredId = null) {
        templateTable?.ajax.reload(function () {
            updateTemplateCount();

            if (preferredId) {
                selectedTemplateId = preferredId;
            }

            if (!selectedTemplateId) return;

            $("#template-list-table tbody tr").each(function () {
                const data = templateTable.row(this).data();
                if (data && String(data.id) === String(selectedTemplateId)) {
                    $(this).addClass("table-active");
                    $("#ap-selected-template-name").text(`[${data.template_name}]`);
                    $("#btn-add-step").prop("disabled", false);
                    loadSteps();
                }
            });
        }, false);
    }

    function loadSteps() {
        if (isSorting || !selectedTemplateId) return;

        $.post(API.STEP_LIST, { template_id: selectedTemplateId })
            .done(function (res) {
                if (!res?.success || !Array.isArray(res.data)) {
                    updateStepCount(0);
                    return;
                }

                const $tbody = $("#steps-sortable");
                $tbody.empty();

                res.data.forEach(step => $tbody.append(buildStepRow(step)));
                updateStepCount(res.data.length);
                initSortable();
            })
            .fail(function (xhr) {
                console.error("[approval] loadSteps failed:", xhr.responseText);
            });
    }

    function buildStepRow(step) {
        const activeBadge = String(step.is_active) === "1"
            ? '<span class="badge bg-success">활성</span>'
            : '<span class="badge bg-secondary">비활성</span>';

        return `
            <tr data-id="${step.id}"
                data-sequence="${step.sequence}"
                data-step_name="${escapeHtml(step.step_name ?? "")}"
                data-role_id="${escapeHtml(step.role_id ?? "")}"
                data-user_id="${escapeHtml(step.approver_id ?? "")}"
                data-active="${step.is_active}">
                <td class="text-center drag-handle" title="드래그해서 순서 변경">
                    <i class="bi bi-list"></i>
                </td>
                <td class="text-center step-sequence">${step.sequence}</td>
                <td>${escapeHtml(step.step_name ?? "")}</td>
                <td>${escapeHtml(step.role_name ?? "-")}</td>
                <td>${escapeHtml(step.specific_employee_name ?? "-")}</td>
                <td>${activeBadge}</td>
            </tr>
        `;
    }

    function initSortable() {
        const $sortable = $("#steps-sortable");

        if (!$sortable.length) return;

        if (typeof $sortable.sortable !== "function") {
            console.error("[approval] jQuery UI sortable is not available.");
            return;
        }

        if ($sortable.data("ui-sortable")) {
            $sortable.sortable("destroy");
        }

        $sortable.sortable({
            handle: ".drag-handle",
            items: "> tr",
            axis: "y",
            containment: "parent",
            tolerance: "pointer",
            helper: function (_, tr) {
                const $originals = tr.children();
                const $helper = tr.clone();

                $helper.children().each(function (index) {
                    $(this).width($originals.eq(index).outerWidth());
                });

                return $helper;
            },
            placeholder: "approval-step-placeholder",
            start: function () {
                isSorting = true;
            },
            stop: function () {
                const updateList = [];

                $("#steps-sortable tr").each(function (index) {
                    $(this).attr("data-sequence", index + 1);
                    $(this).find(".step-sequence").text(index + 1);
                    updateList.push({
                        id: $(this).data("id"),
                        sequence: index + 1
                    });
                });

                $.post(API.STEP_SAVE, {
                    reorder: 1,
                    template_id: selectedTemplateId,
                    steps: JSON.stringify(updateList)
                })
                    .done(function (res) {
                        if (!res?.success) {
                            notify("error", res?.message || "단계 순서 저장에 실패했습니다.");
                        }
                    })
                    .fail(function () {
                        notify("error", "단계 순서 저장 중 오류가 발생했습니다.");
                    })
                    .always(function () {
                        setTimeout(() => {
                            isSorting = false;
                            loadSteps();
                        }, 120);
                    });
            }
        }).disableSelection();
    }

    function adjustLayout() {
        const page = document.getElementById("approvalPage");
        if (!page) return;

        const templateCard = document.getElementById("approvalTemplateCard");
        const stepCard = document.getElementById("approvalStepCard");
        const footer = document.querySelector("footer");

        if (!templateCard || !stepCard) return;

        const top = page.getBoundingClientRect().top;
        const footerHeight = footer ? footer.getBoundingClientRect().height : 56;
        const gap = 76;
        const available = Math.max(300, window.innerHeight - top - footerHeight - gap);

        templateCard.style.height = `${available}px`;
        stepCard.style.height = `${available}px`;
    }
 
    function bindLayoutEvents() {
        window.addEventListener("resize", adjustLayout);
        document.addEventListener("sidebar:toggled", () => {
            setTimeout(adjustLayout, 340);
        });
    }

    $(function () {
        init();
    });
})(jQuery);

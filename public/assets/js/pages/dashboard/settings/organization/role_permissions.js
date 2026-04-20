(function () {
    "use strict";

    console.log("[role_permissions.js] loaded");

    const API_ROLE_LIST = "/api/settings/organization/role/list";
    const API_PERM_LIST = "/api/settings/organization/permission/list";
    const API_ROLE_PERMISSIONS = "/api/settings/organization/role-permission/list";
    const API_ASSIGN = "/api/settings/organization/role-permission/assign";
    const API_REMOVE = "/api/settings/organization/role-permission/remove";

    let selectedRoleId = null;
    let permissionTable = null;
    let pendingChanges = {};

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

    function setRoleCount(count) {
        $("#roleListCount").text(`총 ${count}건`);
    }

    function buildRoleStatusBadge(value) {
        return String(value) === "1"
            ? '<span class="badge bg-success">활성</span>'
            : '<span class="badge bg-secondary">비활성</span>';
    }

    window.RolePermissionTable = {
        showSpinner() {
            const loading = document.getElementById("global-loading-overlay");
            if (loading) loading.style.display = "flex";
            document.body.style.pointerEvents = "none";
        },

        hideSpinner() {
            const loading = document.getElementById("global-loading-overlay");
            if (loading) loading.style.display = "none";
            document.body.style.pointerEvents = "auto";
        },

        init() {
            this.loadRoleList();
            this.bindSearchEvent();
            this.bindCheckAll();
            this.bindSaveButton();
            this.bindLayoutEvents();
            this.adjustLayout();
        },

        bindSearchEvent() {
            $("#permission-search").on("keyup", () => {
                if (!permissionTable) return;

                const keyword = $("#permission-search").val();
                permissionTable.search(keyword).draw();

                const rows = permissionTable.rows({ filter: "applied" }).data().toArray();
                const fullRows = permissionTable.rows().data().toArray();
                const stats = keyword.trim() === ""
                    ? this.calculateCounts(fullRows)
                    : this.calculateCounts(rows);

                const label = keyword.trim() === "" ? "총" : "검색결과";
                $("#permission-count").text(
                    `${label} ${stats.total}개 (api = ${stats.apiCount}, web = ${stats.webCount})`
                );

                this.syncCheckAll();
            });
        },

        bindCheckAll() {
            $("#permission-check-all").on("change", function () {
                if (!permissionTable) return;

                const checked = this.checked;
                const rows = permissionTable.rows({ filter: "applied" }).nodes();

                $(rows).find(".rp-toggle").each(function () {
                    $(this).prop("checked", checked).trigger("pending-change");
                });
            });
        },

        bindSaveButton() {
            $("#permission-save-btn").on("click", () => {
                if (!selectedRoleId) {
                    notify("warning", "역할을 먼저 선택해 주세요.");
                    return;
                }

                const changes = Object.entries(pendingChanges);
                if (changes.length === 0) {
                    notify("warning", "변경된 권한이 없습니다.");
                    return;
                }

                this.showSpinner();

                const tasks = changes.map(([permId, isChecked]) => {
                    const url = isChecked ? API_ASSIGN : API_REMOVE;
                    return $.post(url, {
                        role_id: selectedRoleId,
                        permission_id: permId
                    });
                });

                Promise.all(tasks)
                    .then(() => {
                        pendingChanges = {};
                        $("#permission-save-btn")
                            .removeClass("btn-primary")
                            .addClass("btn-secondary");
                        this.syncCheckAll();
                        notify("success", "권한이 저장되었습니다.");
                    })
                    .catch(() => {
                        notify("error", "권한 저장에 실패했습니다.");
                    })
                    .finally(() => {
                        this.hideSpinner();
                    });
            });
        },

        bindLayoutEvents() {
            window.addEventListener("resize", () => this.adjustLayout());
            document.addEventListener("sidebar:toggled", () => {
                setTimeout(() => this.adjustLayout(), 340);
            });
        },

        adjustLayout() {
            const page = document.getElementById("rolePermissionPage");
            if (!page) return;

            const roleCard = document.getElementById("roleListCard");
            const permissionCard = document.getElementById("permissionListCard");
            const footer = document.querySelector("footer");

            if (!roleCard || !permissionCard) return;

            const top = page.getBoundingClientRect().top;
            const footerHeight = footer ? footer.getBoundingClientRect().height : 56;
            const gap = 76;
            const available = Math.max(300, window.innerHeight - top - footerHeight - gap);

            roleCard.style.height = `${available}px`;
            permissionCard.style.height = `${available}px`;

            if (permissionTable) {
                setTimeout(() => {
                    permissionTable.columns.adjust().draw(false);
                    if (permissionTable.fixedHeader) {
                        permissionTable.fixedHeader.adjust();
                    }
                }, 50);
            }
        },

        loadRoleList() {
            $.post(API_ROLE_LIST, {}, (res) => {
                if (!res || res.success === false) return;

                const rows = Array.isArray(res.data) ? res.data : [];
                const tbody = $("#role-list-table tbody");
                tbody.empty();

                rows.forEach((role) => {
                    tbody.append(`
                        <tr class="rp-role-row" data-id="${role.id}" data-name="${role.role_name}">
                            <td>${role.code ?? ""}</td>
                            <td>${role.role_name ?? ""}</td>
                            <td class="text-center">${buildRoleStatusBadge(role.is_active)}</td>
                        </tr>
                    `);
                });

                setRoleCount(rows.length);
                this.bindRoleClick();
            });
        },

        bindRoleClick() {
            $("#role-list-table").off("click", ".rp-role-row");

            $("#role-list-table").on("click", ".rp-role-row", function () {
                $("#role-list-table tr").removeClass("table-active");
                $(this).addClass("table-active");

                selectedRoleId = $(this).data("id");
                $("#rp-selected-role-name").text(`[${$(this).data("name")}]`);

                pendingChanges = {};
                $("#permission-save-btn")
                    .removeClass("btn-primary")
                    .addClass("btn-secondary");

                $("#permission-header").show();
                window.RolePermissionTable.reloadPermissionTable();
            });
        },

        calculateCounts(list) {
            const total = list.length;
            const apiCount = list.filter((item) =>
                String(item.permission_key).toLowerCase().startsWith("api.")
            ).length;

            return {
                total,
                apiCount,
                webCount: total - apiCount
            };
        },

        reloadPermissionTable() {
            if (!selectedRoleId) return;

            $.when(
                $.post(API_PERM_LIST, {}),
                $.post(API_ROLE_PERMISSIONS, { role_id: selectedRoleId })
            ).done((permRes, assignedRes) => {
                const permissionsRes = permRes[0];
                const assignedPermissionsRes = assignedRes[0];

                if (!permissionsRes.success || !assignedPermissionsRes.success) return;

                const assigned = assignedPermissionsRes.data.map((item) => String(item.permission_id));
                const merged = permissionsRes.data.map((permission) => ({
                    ...permission,
                    assigned: assigned.includes(String(permission.id))
                }));

                const stats = this.calculateCounts(merged);
                $("#permission-count").text(
                    `총 ${stats.total}개 (api = ${stats.apiCount}, web = ${stats.webCount})`
                );

                if (permissionTable) {
                    permissionTable.clear();
                    permissionTable.rows.add(merged).draw();
                    this.bindToggleEvents();
                    this.syncCheckAll();
                    return;
                }

                permissionTable = $("#role-permissions-table").DataTable({
                    paging: false,
                    searching: true,
                    info: false,
                    ordering: false,
                    dom: "t",
                    rowGroup: { dataSrc: "category" },
                    scrollY: 500,
                    scrollCollapse: true,
                    columns: [
                        { data: "category" },
                        { data: "permission_name" },
                        { data: "permission_key" },
                        {
                            data: "id",
                            className: "text-center",
                            render: function (id, type, row) {
                                return `
                                    <input type="checkbox"
                                           class="rp-toggle"
                                           data-permission="${id}"
                                           ${row.assigned ? "checked" : ""}>
                                `;
                            }
                        }
                    ]
                });

                permissionTable.rows.add(merged).draw();
                this.bindToggleEvents();
                this.syncCheckAll();

                setTimeout(() => {
                    permissionTable.columns.adjust().draw(false);
                    if (permissionTable.fixedHeader) {
                        permissionTable.fixedHeader.adjust();
                    }
                }, 50);
            });
        },

        bindToggleEvents() {
            $("#role-permissions-table").off("pending-change", ".rp-toggle");
            $("#role-permissions-table").off("change", ".rp-toggle");

            const updatePending = function () {
                const permId = $(this).data("permission");
                const isChecked = $(this).prop("checked");

                pendingChanges[permId] = isChecked;

                $("#permission-save-btn")
                    .removeClass("btn-secondary")
                    .addClass("btn-primary");

                window.RolePermissionTable.syncCheckAll();
            };

            $("#role-permissions-table").on("pending-change", ".rp-toggle", updatePending);
            $("#role-permissions-table").on("change", ".rp-toggle", updatePending);
        },

        syncCheckAll() {
            if (!permissionTable) return;

            const rows = permissionTable.rows({ filter: "applied" }).nodes();
            const total = $(rows).find(".rp-toggle").length;
            const checked = $(rows).find(".rp-toggle:checked").length;
            const $all = $("#permission-check-all");

            if (checked === 0) {
                $all.prop("checked", false);
                $all.prop("indeterminate", false);
            } else if (checked === total) {
                $all.prop("checked", true);
                $all.prop("indeterminate", false);
            } else {
                $all.prop("checked", false);
                $all.prop("indeterminate", true);
            }
        }
    };

    $(function () {
        window.RolePermissionTable.init();
    });
})();

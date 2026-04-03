// 경로: PROJECT_ROOT/public/assets/js/pages/dashboard/settings/organization/role_permissions.table.js

(function () {
    "use strict";

    console.log("role_permissions.table.js Loaded");

    const API_ROLE_LIST         = "/api/settings/role/list";
    const API_PERM_LIST         = "/api/settings/permission/list";
    const API_ROLE_PERMISSIONS  = "/api/settings/role-permission/list";
    const API_ASSIGN            = "/api/settings/role-permission/assign";
    const API_REMOVE            = "/api/settings/role-permission/remove";

    let selectedRoleId = null;
    let permissionTable = null;

    // 변경된 항목들 (대기열)
    let pendingChanges = {};

    window.RolePermissionTable = {

        /* ------------------------------------------------------
           0) 스피너 표시 함수
        ------------------------------------------------------ */
        showSpinner() {
            const loading = document.getElementById("global-loading-overlay");
            if (loading) loading.style.display = "flex";

            // 화면 전체 클릭 차단
            document.body.style.pointerEvents = "none";
        },

        hideSpinner() {
            const loading = document.getElementById("global-loading-overlay");
            if (loading) loading.style.display = "none";

            document.body.style.pointerEvents = "auto";
        },

        /* ------------------------------------------------------
           초기화
        ------------------------------------------------------ */
        init() {
            this.loadRoleList();
            this.bindSearchEvent();
            this.bindCheckAll();
            this.bindSaveButton();
        },

        /* ------------------------------------------------------
           검색
        ------------------------------------------------------ */
        bindSearchEvent() {
            $("#permission-search").on("keyup", () => {
                if (!permissionTable) return;

                const keyword = $("#permission-search").val();
                permissionTable.search(keyword).draw();

                const rows = permissionTable.rows({ filter: "applied" }).data().toArray();
                const fullRows = permissionTable.rows().data().toArray();

                let countInfo;

                if (keyword.trim() === "") {
                    const full = this.calculateCounts(fullRows);
                    countInfo = `총 ${full.total}개 (api = ${full.apiCount}, web = ${full.webCount})`;
                } else {
                    const filtered = this.calculateCounts(rows);
                    countInfo = `검색결과 ${filtered.total}개 (api = ${filtered.apiCount}, web = ${filtered.webCount})`;
                }

                $("#permission-count").text(countInfo);
            });
        },

        /* ------------------------------------------------------
           전체 선택 체크박스
        ------------------------------------------------------ */
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

        /* ------------------------------------------------------
           저장 버튼
        ------------------------------------------------------ */
        bindSaveButton() {
            $("#permission-save-btn").on("click", () => {

                if (!selectedRoleId) {
                    alert("역할이 선택되지 않았습니다.");
                    return;
                }

                const changes = Object.entries(pendingChanges);

                if (changes.length === 0) {
                    alert("변경된 권한이 없습니다.");
                    return;
                }

                // 스피너 ON
                this.showSpinner();

                // API 호출 작업 생성
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

                        //alert("저장되었습니다.");
                    })
                    .catch(() => {
                        alert("일부 저장 실패");
                    })
                    .finally(() => {
                        // 스피너 OFF
                        this.hideSpinner();
                    });
            });
        },

        /* ------------------------------------------------------
           역할 목록 로딩
        ------------------------------------------------------ */
        loadRoleList() {
            $.post(API_ROLE_LIST, {}, (res) => {
                if (!res || res.success === false) return;

                const tbody = $("#role-list-table tbody");
                tbody.empty();

                res.data.forEach(r => {
                    tbody.append(`
                        <tr class="rp-role-row" data-id="${r.id}" data-name="${r.role_name}">
                            <td>${r.code}</td>
                            <td>${r.role_name}</td>
                        </tr>
                    `);
                });

                this.bindRoleClick();
            });
        },

        /* ------------------------------------------------------
           역할 선택 → 권한 목록 로드
        ------------------------------------------------------ */
        bindRoleClick() {

            $("#role-list-table").off("click", ".rp-role-row");

            $("#role-list-table").on("click", ".rp-role-row", function () {

                $("#role-list-table tr").removeClass("table-primary");
                $(this).addClass("table-primary");

                selectedRoleId = $(this).data("id");
                $("#rp-selected-role-name").text(`[${$(this).data("name")}]`);

                pendingChanges = {};

                $("#permission-save-btn")
                    .removeClass("btn-primary")
                    .addClass("btn-secondary");

                $("#permission-header").show();

                RolePermissionTable.reloadPermissionTable();
            });
        },

        /* ------------------------------------------------------
           카운트 계산
        ------------------------------------------------------ */
        calculateCounts(list) {
            const total = list.length;
            const apiCount = list.filter(x =>
                String(x.permission_key).toLowerCase().startsWith("api.")
            ).length;

            return {
                total,
                apiCount,
                webCount: total - apiCount
            };
        },

        /* ------------------------------------------------------
           테이블 로딩
        ------------------------------------------------------ */
        reloadPermissionTable() {

            if (!selectedRoleId) return;

            $.when(
                $.post(API_PERM_LIST, {}),
                $.post(API_ROLE_PERMISSIONS, { role_id: selectedRoleId })
            ).done((permRes, assignedRes) => {

                permRes = permRes[0];
                assignedRes = assignedRes[0];

                if (!permRes.success || !assignedRes.success) return;

                const assigned = assignedRes.data.map(a => String(a.permission_id));

                const merged = permRes.data.map(p => ({
                    ...p,
                    assigned: assigned.includes(String(p.id))
                }));

                const $count = $("#permission-count");
                const stats = this.calculateCounts(merged);
                $count.text(`총 ${stats.total}개 (api = ${stats.apiCount}, web = ${stats.webCount})`);

                /* 기존 테이블이면 갱신 */
                if (permissionTable) {
                    permissionTable.clear();
                    permissionTable.rows.add(merged).draw();
                    return this.bindToggleEvents();
                }

                /* 최초 테이블 생성 */
                permissionTable = $("#role-permissions-table").DataTable({
                    paging: false,
                    searching: true,
                    info: false,
                    ordering: false,
                    dom: "t",
                    rowGroup: { dataSrc: "category" },

                    columns: [
                        { data: "permission_key" },
                        { data: "permission_name" },
                        { data: "category" },
                        {
                            data: "id",
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
            });
        },

        /* ------------------------------------------------------
           개별 체크는 pendingChanges 에만 기록 (DB 즉시 저장 X)
        ------------------------------------------------------ */
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
            };

            $("#role-permissions-table").on("pending-change", ".rp-toggle", updatePending);
            $("#role-permissions-table").on("change", ".rp-toggle", updatePending);
        }
    };

    $(function () {
        RolePermissionTable.init();
    });

})();

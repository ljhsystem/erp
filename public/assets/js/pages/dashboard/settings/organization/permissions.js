// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/permissions.table.js'

(function () {
    "use strict";

    console.log("permissions.table.js Loaded");

    const API_LIST   = "/api/settings/permission/list";
    const API_SAVE   = "/api/settings/permission/save";
    const API_DELETE = "/api/settings/permission/delete";
    

    window.EmployeePermissionsTable = {
        instance: null,

        init() {
            const $wrapper = $("#permissions-table-wrapper");
            const $table   = $("#permissions-table");

            if (!$table.length) return;

            /* ----------------------------------
               1) 초기 깜빡임 제거
               ---------------------------------- */
            $wrapper.hide();
            $table.hide();

            /* ----------------------------------
               2) DataTable 초기화
               ---------------------------------- */
            this.instance = $table.DataTable({
                ajax: {
                    url: API_LIST,
                    type: "POST",
                    dataSrc(json) {
                        if (!json || json.success === false) {
                            console.error("❌ 권한 목록 로딩 실패:", json);
                            return [];
                        }
                        return json.data ?? [];
                    }
                },

                processing: true,
                deferRender: true,
                responsive: true,
                autoWidth: false,

                columns: [
                    { data: "code" },              // 권한 코드
                    { data: "permission_key" },    // 권한 Key
                    { data: "permission_name" },   // 권한명
                    { data: "description" },       // 설명
                    { data: "category" },          // 카테고리
                    {
                        data: "is_active",
                        render(data) {
                            return data == 1
                                ? `<span class="badge bg-success">활성</span>`
                                : `<span class="badge bg-secondary">비활성</span>`;
                        },
                        className: "text-center"
                    }
                ],

                dom: '<"top-bar"<"dt-search"f><"dt-buttons"B><"dt-length"l>>rt<"bottom"ip><"clear">',

                buttons: [
                    { extend: "colvis", text: "열 표시", className: "btn btn-secondary btn-sm" },
                    "copy",
                    "excel",
                    {
                        extend: "print",
                        text: "인쇄",
                        exportOptions: { stripHtml: false }
                    },
                    {
                        text: "새 권한 추가",
                        className: "btn btn-warning btn-sm ms-2",
                        action() {
                            $(document).trigger("permissions:create-open");
                        }
                    }
                ],

                order: [[0, "asc"]],
                pageLength: 10,
                lengthMenu: [5, 10, 20, 50, 100],

                language: {
                    lengthMenu: "페이지당 _MENU_ 개씩 보기",
                    zeroRecords: "데이터가 없습니다.",
                    info: "_PAGE_ 페이지 / 총 _PAGES_ 페이지",
                    infoEmpty: "데이터 없음",
                    infoFiltered: "(총 _MAX_개 중 필터링됨)",
                    search: "검색:",
                    paginate: {
                        first: "처음",
                        last: "끝",
                        next: "다음",
                        previous: "이전"
                    }
                }
            });

            /* ----------------------------------
               3) 초기화 완료 후 표시
               ---------------------------------- */
            this.instance.on("init.dt", function () {
                $table.show();
                $wrapper.show();
            });

            this.bindEvents();
        },

        /* ----------------------------------
           4) 더블클릭 → 수정 모달 열기
           ---------------------------------- */
        bindEvents() {
            const dt = this.instance;

            $("#permissions-table tbody").on("dblclick", "tr", function () {
                const row = dt.row(this).data();
                if (row) {
                    $(document).trigger("permissions:edit-open", [row]);
                }
            });
        },

        /* ----------------------------------
           5) 테이블 Reload
           ---------------------------------- */
        reload() {
            this.instance?.ajax.reload(null, false);
        }
    };

    $(function () {
        window.EmployeePermissionsTable?.init();
    });

})();

// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/departments.table.js'

(function () {
    "use strict";
    
    console.log("departments.table.js Loaded");

    const API_LIST   = "/api/settings/department/list";
    const API_SAVE   = "/api/settings/department/save";
    const API_DELETE = "/api/settings/department/delete";
    
    

    window.EmployeeDepartmentsTable = {
        instance: null,

        init() {
            const $wrapper = $("#dept-table-wrapper");
            const $table = $("#dept-table");

            if (!$table.length) return;

            /* ----------------------------------
               1) 초기 깜빡임 제거 (직원테이블과 동일)
               ---------------------------------- */
            $wrapper.hide();
            $table.hide();

            /* ----------------------------------
               2) DataTables 초기화
               ---------------------------------- */
            this.instance = $table.DataTable({
                ajax: {
                    url: API_LIST,
                    type: "POST",
                    dataSrc: function (json) {
                        if (!json || json.success === false) {
                            console.error("❌ 부서 목록 로딩 실패:", json);
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
                    {
                        data: null,
                        defaultContent: '',
                        className: "reorder-handle text-center",
                        orderable: false,
                        searchable: false,
                        width: "30px"
                    },
                    { data: "code", width: "5%" },
                    { data: "dept_name", width: "12%" },
                    {
                        data: "manager_name",
                        width: "8%",
                        render(d) {
                            return d || "-";
                        }
                    },
                    {
                        data: "description",
                        width: "60%",   // 🔥 설명칸 폭 줄임 (기존보다 좁게)
                        render(d) {
                            return d || "";
                        }
                    },
                    {
                        data: "is_active",
                        width: "15%",   // 🔥 활성칸 넓힘
                        className: "text-center",
                        render(val) {
                            return (val == 1)
                                ? `<span class="badge bg-success px-3 py-2">활성</span>`
                                : `<span class="badge bg-secondary px-3 py-2">비활성</span>`;
                        }
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
                        text: "새 부서 추가",
                        className: "btn btn-primary btn-sm ms-2",
                        action() {
                            $(document).trigger("dept:create-open");
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
               3) 초기화 완료 후 테이블 표시
               ---------------------------------- */
            this.instance.on("init.dt", function () {
                $table.show();
                $wrapper.show();
            });

            this.bindEvents();
        },

        bindEvents() {
            const dt = this.instance;

            // 더블클릭 → 수정 모달 띄우기
            $("#dept-table tbody").on("dblclick", "tr", function () {
                const row = dt.row(this).data();
                if (row) {
                    $(document).trigger("dept:edit-open", [row]);
                }
            });
        },

        reload() {
            this.instance?.ajax.reload(null, false);
        }
    };

    $(function () {
        window.EmployeeDepartmentsTable?.init();
    });
})();

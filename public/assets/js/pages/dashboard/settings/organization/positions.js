// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/settings/organization/positions.table.js'

(function () {
    "use strict";

    console.log("positions.table.js Loaded");

    const API_LIST   = "/api/settings/position/organization/list";
    const API_SAVE   = "/api/settings/position/organization/save";
    const API_DELETE = "/api/settings/position/organization/delete";
    

    window.EmployeePositionsTable = {
        instance: null,

        init() {
            const $wrapper = $("#positions-table-wrapper");
            const $table   = $("#positions-table");

            if (!$table.length) return;

            /* ----------------------------------
               1) 초기 깜빡임 제거 (부서 테이블과 동일)
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
                            console.error("❌ 직책 목록 로딩 실패:", json);
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
                        data: "code",
                        width: "7%"
                    },
                    { 
                        data: "position_name",
                        width: "7%"
                    },
                    { 
                        data: "level_rank",
                        width: "7%",
                        className: "text-center"
                    },
                    { 
                        data: "description",
                        width: "73%",
                        render(d) {
                            return d ? d : "-";
                        }
                    },
                    {
                        data: "is_active",
                        width: "8%",
                        className: "text-center",
                        render(val) {
                            return (val == 1)
                                ? `<span class="badge bg-success">활성</span>`
                                : `<span class="badge bg-secondary">비활성</span>`;
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
                        text: "새 직책 추가",
                        className: "btn btn-warning btn-sm ms-2",
                        action() {
                            $(document).trigger("positions:create-open");
                        }
                    }
                ],

                order: [[2, "asc"], [0, "asc"]],
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

        /* ----------------------------------
           4) 더블클릭 → 수정 모달 열기
           ---------------------------------- */
        bindEvents() {
            const dt = this.instance;

            $("#positions-table tbody").on("dblclick", "tr", function () {
                const row = dt.row(this).data();
                if (row) {
                    $(document).trigger("positions:edit-open", [row]);
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
        window.EmployeePositionsTable?.init();
    });

})();

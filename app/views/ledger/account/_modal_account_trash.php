<!-- 경로: PROJECT_ROOT . '/app/views/ledger/account/_modal_account_trash.php' -->

<div class="modal fade"
     id="accountTrashModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-xl">

        <div class="modal-content">

            <!-- =========================
                 HEADER
            ========================== -->
            <div class="modal-header">

                <h5 class="modal-title">
                    계정과목 휴지통 🗑
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>

            </div>


            <!-- =========================
                 BODY
            ========================== -->
            <div class="modal-body account-trash-layout">

                <!-- =========================
                     좌측 리스트
                ========================== -->
                <div class="trash-left">

                    <!-- 툴바 -->
                    <div class="trash-toolbar d-flex gap-2 mb-2">

                        <button type="button"
                                class="btn btn-success btn-sm"
                                id="btnRestoreSelectedAccount">
                            선택 복원
                        </button>

                        <button type="button"
                                class="btn btn-danger btn-sm"
                                id="btnDeleteSelectedAccount">
                            선택 영구삭제
                        </button>

                        <button type="button"
                                class="btn btn-outline-danger btn-sm ms-auto"
                                id="btnDeleteAllAccounts">
                            전체 영구삭제
                        </button>

                    </div>


                    <!-- 테이블 -->
                    <div class="table-responsive">

                        <table class="table table-hover align-middle"
                               id="account-trash-table">

                            <thead class="table-light">

                                <tr>

                                    <!-- 전체 선택 -->
                                    <th width="40" class="text-center">
                                        <input type="checkbox"
                                               id="trashCheckAllAccount">
                                    </th>

                                    <!-- 계정코드 -->
                                    <th width="100">
                                        계정코드
                                    </th>

                                    <!-- 계정명 -->
                                    <th>
                                        계정과목명
                                    </th>

                                    <!-- 구분 -->
                                    <th width="80">
                                        구분
                                    </th>

                                    <!-- 삭제일 -->
                                    <th width="150">
                                        삭제일
                                    </th>

                                    <!-- 삭제자 -->
                                    <th width="120">
                                        삭제자
                                    </th>

                                    <!-- 액션 -->
                                    <th width="140" class="text-center">
                                        관리
                                    </th>

                                </tr>

                            </thead>

                            <tbody>
                                <!-- JS에서 동적 렌더링 -->
                            </tbody>

                        </table>

                    </div>

                </div>


                <!-- =========================
                     우측 상세
                ========================== -->
                <div class="trash-right">

                    <div id="account-trash-detail"
                         class="p-3">

                        <div class="text-muted text-center mt-5">

                            삭제된 계정과목을 선택하세요

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
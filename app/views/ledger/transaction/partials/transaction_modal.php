<div class="modal fade"
     id="transactionModal"
     tabindex="-1"
     aria-labelledby="transactionModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable transaction-dialog">
        <div class="modal-content transaction-modal-card">
            <form id="transactionForm" autocomplete="off" enctype="multipart/form-data">
                <div class="modal-header transaction-modal-header">
                    <div>
                        <h5 class="modal-title" id="transactionModalLabel">거래 등록</h5>
                        <p class="transaction-modal-subtitle mb-0">원본 거래와 품목, 증빙 파일, 전표 연결 상태를 관리합니다.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>

                <div class="modal-body transaction-modal-body">
                    <input type="hidden" name="id" id="transaction_id">
                    <input type="hidden" name="status" id="transaction_status" value="draft">
                    <input type="hidden" name="match_status" id="transaction_match_status" value="none">

                    <section class="transaction-card transaction-business-card" aria-label="업무분류정보">
                        <div class="transaction-card-header">
                            <div class="transaction-card-heading">
                                <h6 class="transaction-card-title">업무분류정보</h6>
                                <p class="transaction-card-description">업무분류, 기초정보, 참조정보를 같은 카드 구조로 관리합니다.</p>
                            </div>
                        </div>

                        <div class="transaction-modal-grid transaction-business-grid">
                            <label class="transaction-field transaction-field-standard">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">자료출처</span>
                                    <span class="transaction-field-badge transaction-field-badge-code">코드관리</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="source_type"
                                        id="source_type"
                                        data-code-group="SOURCE_TYPE"
                                        data-empty-label="자료출처선택">
                                    <option value="">자료출처선택</option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-standard">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">자료유형</span>
                                    <span class="transaction-field-badge transaction-field-badge-code">코드관리</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="import_type"
                                        id="import_type"
                                        data-code-group="IMPORT_TYPE"
                                        data-empty-label="자료유형선택">
                                    <option value="">자료유형선택</option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-standard">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">사업구분</span>
                                    <span class="transaction-field-badge transaction-field-badge-code">코드관리</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="business_unit"
                                        id="business_unit"
                                        data-code-group="BUSINESS_UNIT"
                                        data-empty-label="사업구분선택"
                                        required>
                                    <option value="">사업구분선택</option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-standard">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">거래구분</span>
                                    <span class="transaction-field-badge transaction-field-badge-code">코드관리</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="transaction_direction"
                                        id="transaction_direction"
                                        data-code-group="TRANSACTION_DIRECTION"
                                        data-empty-label="거래구분선택">
                                    <option value="">거래구분선택</option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-standard">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">거래유형</span>
                                    <span class="transaction-field-badge transaction-field-badge-code">코드관리</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="transaction_type"
                                        id="transaction_type"
                                        data-code-group="TRANSACTION_TYPE"
                                        data-empty-label="거래유형선택"
                                        required>
                                    <option value="">거래유형선택</option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-standard">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">통화</span>
                                    <span class="transaction-field-badge transaction-field-badge-code">코드관리</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="currency"
                                        id="currency"
                                        data-code-group="CURRENCY"
                                        data-empty-label="통화선택">
                                    <option value="">통화선택</option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-basic">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">거래처</span>
                                    <span class="transaction-field-badge transaction-field-badge-basic">기초정보</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="client_id"
                                        id="client_id"
                                        data-placeholder="거래처검색"
                                        required>
                                    <option value=""></option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-basic">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">프로젝트</span>
                                    <span class="transaction-field-badge transaction-field-badge-basic">기초정보</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="project_id"
                                        id="project_id"
                                        data-placeholder="프로젝트 검색">
                                    <option value=""></option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-basic">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">계좌</span>
                                    <span class="transaction-field-badge transaction-field-badge-basic">기초정보</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="bank_account_id"
                                        id="bank_account_id">
                                    <option value="">계좌선택</option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-basic">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">카드</span>
                                    <span class="transaction-field-badge transaction-field-badge-basic">기초정보</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="card_id"
                                        id="card_id">
                                    <option value="">카드선택</option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-basic">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">팀</span>
                                    <span class="transaction-field-badge transaction-field-badge-basic">기초정보</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="team_id"
                                        id="team_id">
                                    <option value="">팀선택</option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-field-basic">
                                <span class="transaction-field-label">
                                    <span class="transaction-field-label-text">직원</span>
                                    <span class="transaction-field-badge transaction-field-badge-basic">기초정보</span>
                                </span>
                                <select class="form-select form-select-sm"
                                        name="employee_id"
                                        id="employee_id">
                                    <option value="">직원선택</option>
                                </select>
                            </label>
                        </div>
                    </section>

                    <section class="transaction-card transaction-overview-card" aria-label="거래개요">
                        <div class="transaction-card-header">
                            <div class="transaction-card-heading">
                                <h6 class="transaction-card-title">거래개요</h6>
                                <p class="transaction-card-description">거래일자, 설명, 통화 정보와 대표 금액을 한 화면에서 확인합니다.</p>
                            </div>
                            <div class="transaction-overview-header-actions">
                                <div class="form-check form-switch transaction-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="use_file_reference" name="use_file_reference" value="1">
                                    <label class="form-check-label" for="use_file_reference">파일첨부</label>
                                </div>
                            </div>
                        </div>

                        <div class="transaction-overview-layout">
                            <div class="transaction-overview-column transaction-overview-left">
                                <div class="transaction-modal-grid transaction-modal-grid-overview-left">
                                    <label class="transaction-field transaction-field-date transaction-overview-date-field">
                                        <span class="transaction-field-label">거래일</span>
                                        <div class="date-input">
                                            <input type="text"
                                                   class="form-control form-control-sm admin-date"
                                                   name="transaction_date"
                                                   id="transaction_date"
                                                   placeholder="날짜 선택"
                                                   autocomplete="off"
                                                   inputmode="numeric"
                                                   maxlength="10"
                                                   required>
                                            <i class="fa fa-calendar-days date-icon" aria-hidden="true"></i>
                                        </div>
                                    </label>

                                    <label class="transaction-field transaction-field-raw transaction-overview-description">
                                        <span class="transaction-field-label">적요</span>
                                        <input type="text" class="form-control form-control-sm" name="description" id="transaction_description" placeholder="거래 내용을 입력하세요">
                                    </label>

                                    <label class="transaction-field transaction-field-raw transaction-exchange-field d-none">
                                        <span class="transaction-field-label">환율</span>
                                        <input type="text"
                                               class="form-control form-control-sm number-input"
                                               name="exchange_rate"
                                               id="exchange_rate"
                                               inputmode="decimal"
                                               autocomplete="off">
                                    </label>

                                    <label class="transaction-field transaction-field-raw transaction-exchange-field d-none">
                                        <span class="transaction-field-label">외화금액</span>
                                        <input type="text"
                                               class="form-control form-control-sm number-input text-end"
                                               name="foreign_amount"
                                               id="transaction_foreign_amount"
                                               inputmode="numeric"
                                               autocomplete="off"
                                               readonly>
                                    </label>

                                    <label class="transaction-field transaction-field-raw transaction-overview-note-field">
                                        <span class="transaction-field-label">비고</span>
                                        <input type="text" class="form-control form-control-sm" name="note" id="transaction_note" maxlength="255" placeholder="비고를 입력하세요">
                                    </label>

                                    <label class="transaction-field transaction-field-raw transaction-overview-memo-field">
                                        <span class="transaction-field-label">메모</span>
                                        <textarea class="form-control form-control-sm" name="memo" id="transaction_memo" rows="1" placeholder="메모를 입력하세요"></textarea>
                                    </label>

                                    <div class="transaction-overview-files transaction-file-panel d-none" id="transactionFilePanel">
                                        <div class="transaction-file-upload-row">
                                            <div class="transaction-field transaction-file-input-field mb-0">
                                                <span class="transaction-field-label">파일</span>
                                                <input type="file"
                                                       class="transaction-file-native-input"
                                                       name="transaction_files[]"
                                                       id="transaction_files"
                                                       accept=".pdf,.jpg,.jpeg,.png,.zip"
                                                       multiple>
                                                <span class="transaction-file-dropzone" id="transaction_file_dropzone">
                                                    <i class="bi bi-cloud-arrow-up"></i>
                                                    <span class="transaction-file-dropzone-text">파일을 드래그해서 첨부하세요</span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="transaction-file-list" id="transaction_file_list"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="transaction-overview-column transaction-overview-right">
                                <div class="transaction-total-flow" aria-label="최종금액 계산 흐름">
                                    <div class="transaction-total-row">
                                        <span class="transaction-total-operator transaction-total-operator-placeholder" aria-hidden="true"></span>
                                        <label class="transaction-total-card">
                                            <span class="transaction-total-label">공급가액</span>
                                            <input type="text"
                                                   class="form-control form-control-sm number-input text-end transaction-total-input"
                                                   name="supply_amount"
                                                   id="transaction_supply_amount"
                                                   inputmode="numeric"
                                                   autocomplete="off"
                                                   readonly>
                                        </label>
                                    </div>

                                    <div class="transaction-total-row">
                                        <div class="transaction-total-operator" aria-hidden="true">+</div>
                                        <label class="transaction-total-card">
                                            <span class="transaction-total-label">정산금액</span>
                                            <input type="text"
                                                   class="form-control form-control-sm number-input text-end transaction-total-input"
                                                   name="settlement_amount"
                                                   id="transaction_settlement_amount"
                                                   inputmode="numeric"
                                                   autocomplete="off"
                                                   readonly>
                                        </label>
                                    </div>

                                    <div class="transaction-total-row">
                                        <div class="transaction-total-operator" aria-hidden="true">=</div>
                                        <label class="transaction-total-card transaction-total-card-final">
                                            <span class="transaction-total-label">최종금액</span>
                                            <input type="text"
                                                   class="form-control form-control-sm number-input text-end transaction-total-input"
                                                   name="final_amount"
                                                   id="transaction_final_amount"
                                                   inputmode="numeric"
                                                   autocomplete="off"
                                                   readonly>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="transaction-card transaction-lines-section" aria-label="거래내역">
                        <div class="transaction-card-header">
                            <div class="transaction-card-heading">
                                <h6 class="transaction-card-title">거래내역</h6>
                                <p class="transaction-card-description">세전 업무 품목을 AG Grid로 입력하고 공급가합계를 확인합니다.</p>
                            </div>
                            <div class="transaction-overview-header-actions">
                                <div class="form-check form-switch transaction-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_import" name="is_import" value="1">
                                    <label class="form-check-label" for="is_import">외화거래</label>
                                </div>
                            </div>
                        </div>

                        <div class="transaction-grid-wrap">
                            <div id="transactionLineGrid" class="transaction-line-grid"></div>
                        </div>

                        <div class="transaction-lines-footer">
                            <div class="transaction-summary-grid">
                                <input type="hidden" id="transaction_foreign_total" value="0">
                                <input type="hidden" id="transaction_supply_total" value="0">
                                <input type="hidden" id="transaction_settlement_total" value="0">

                                <label class="transaction-total-card transaction-total-card-footer">
                                    <span class="transaction-total-label">공급가합계</span>
                                    <input type="text" class="form-control form-control-sm text-end transaction-total-input" id="transaction_supply_total_view" value="0" readonly>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="transaction-card transaction-lines-section" aria-label="거래 정산">
                        <div class="transaction-card-header">
                            <div class="transaction-card-heading">
                                <h6 class="transaction-card-title" id="transactionSettlementTitle">거래정산</h6>
                                <p class="transaction-card-description" id="transactionSettlementSubtitle">거래 전체 또는 선택 품목 기준으로 정산을 관리합니다.</p>
                                <div class="transaction-current-selection" id="transactionCurrentSelection">현재 선택 ▶ 거래 전체</div>
                            </div>
                            <div class="transaction-settlement-target" id="transactionSettlementTarget">
                                <span class="transaction-settlement-target-label">정산 대상</span>
                                <label class="transaction-settlement-target-option">
                                    <input type="radio" name="settlement_target_scope" value="header" checked>
                                    <span>거래 전체</span>
                                </label>
                                <label class="transaction-settlement-target-option">
                                    <input type="radio" name="settlement_target_scope" value="item">
                                    <span>선택 품목</span>
                                </label>
                            </div>
                        </div>

                        <div class="transaction-grid-wrap">
                            <div id="transactionSettlementGrid" class="transaction-line-grid"></div>
                        </div>

                        <div class="transaction-lines-footer transaction-settlement-footer">
                            <div class="transaction-summary-grid">
                                <label class="transaction-total-card transaction-total-card-footer">
                                    <span class="transaction-total-label">정산합계</span>
                                    <input type="text" class="form-control form-control-sm text-end transaction-total-input" id="transaction_settlement_total_view" value="0" readonly>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="transaction-card transaction-system-card" aria-label="시스템처리정보">
                        <button type="button"
                                class="transaction-card-toggle collapsed"
                                data-bs-toggle="collapse"
                                data-bs-target="#transactionSystemInfoCollapse"
                                aria-expanded="false"
                                aria-controls="transactionSystemInfoCollapse">
                            <span class="transaction-card-heading">
                                <span class="transaction-card-title">시스템처리정보</span>
                                <span class="transaction-card-description">생성, 수정, 삭제 이력과 상태값은 접힘 영역에서만 확인합니다.</span>
                            </span>
                            <i class="bi bi-chevron-down transaction-card-icon" aria-hidden="true"></i>
                        </button>
                        <div id="transactionSystemInfoCollapse" class="collapse">
                            <div class="transaction-card-body">
                                <div class="transaction-modal-grid transaction-system-grid">
                                    <label class="transaction-field transaction-field-readonly">
                                        <span class="transaction-field-label">상태</span>
                                        <input type="text" class="form-control form-control-sm" id="transaction_status_display" value="입력" readonly>
                                    </label>
                                    <label class="transaction-field transaction-field-readonly">
                                        <span class="transaction-field-label">전표연결상태</span>
                                        <input type="text" class="form-control form-control-sm" id="transaction_match_status_display" value="미연결" readonly>
                                    </label>
                                    <label class="transaction-field transaction-field-readonly">
                                        <span class="transaction-field-label">생성일시</span>
                                        <input type="text" class="form-control form-control-sm" id="transaction_created_at_display" value="" readonly>
                                    </label>
                                    <label class="transaction-field transaction-field-readonly">
                                        <span class="transaction-field-label">생성자</span>
                                        <input type="text" class="form-control form-control-sm" id="transaction_created_by_display" value="" readonly>
                                    </label>
                                    <label class="transaction-field transaction-field-readonly">
                                        <span class="transaction-field-label">수정일시</span>
                                        <input type="text" class="form-control form-control-sm" id="transaction_updated_at_display" value="" readonly>
                                    </label>
                                    <label class="transaction-field transaction-field-readonly">
                                        <span class="transaction-field-label">수정자</span>
                                        <input type="text" class="form-control form-control-sm" id="transaction_updated_by_display" value="" readonly>
                                    </label>
                                    <label class="transaction-field transaction-field-readonly">
                                        <span class="transaction-field-label">삭제일시</span>
                                        <input type="text" class="form-control form-control-sm" id="transaction_deleted_at_display" value="" readonly>
                                    </label>
                                    <label class="transaction-field transaction-field-readonly">
                                        <span class="transaction-field-label">삭제자</span>
                                        <input type="text" class="form-control form-control-sm" id="transaction_deleted_by_display" value="" readonly>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="transaction-card transaction-voucher-card" aria-label="전표 등록 및 연결관리">
                        <button type="button"
                                class="transaction-card-toggle collapsed"
                                data-bs-toggle="collapse"
                                data-bs-target="#transactionVoucherCollapse"
                                aria-expanded="false"
                                aria-controls="transactionVoucherCollapse">
                            <span class="transaction-card-heading">
                                <span class="transaction-card-title">전표등록 및 연결관리</span>
                                <span class="transaction-card-description">현재 연결 전표와 전표 생성, 연결, 해제 기능만 별도 접힘 영역으로 관리합니다.</span>
                            </span>
                            <span class="transaction-card-toggle-meta">
                                <span class="transaction-status none" id="transactionVoucherStatus">미연결</span>
                                <i class="bi bi-chevron-down transaction-card-icon" aria-hidden="true"></i>
                            </span>
                        </button>
                        <div id="transactionVoucherCollapse" class="collapse">
                            <div class="transaction-card-body">
                                <div class="transaction-voucher-layout">
                                    <div class="transaction-voucher-summary" id="transaction_voucher_summary">
                                        저장 후 전표를 생성하거나 기존 전표와 연결할 수 있습니다.
                                    </div>

                                    <div class="transaction-voucher-actions">
                                        <button type="button" class="btn btn-outline-success btn-sm" id="btnCreateTransactionVoucher">전표 생성</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelectTransactionVoucher">전표 선택</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnLinkTransactionVoucher">전표 연결</button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnUnlinkTransactionVoucher">연결 해제</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="modal-footer transaction-modal-footer">
                    <button type="button" class="btn btn-danger btn-sm d-none" id="btnDeleteTransaction">삭제</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btnSaveTransaction">저장</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>

                <div id="transaction-today-picker" class="is-hidden"></div>
            </form>
        </div>
    </div>
</div>

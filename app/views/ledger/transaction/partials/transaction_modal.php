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

                    <section class="transaction-card transaction-overview-card" aria-label="거래개요">
                        <div class="transaction-card-header">
                            <h6>거래개요</h6>
                        </div>

                        <div class="transaction-toggle-row">
                            <div class="form-check form-switch transaction-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_import" name="is_import" value="1">
                                <label class="form-check-label" for="is_import">외화사용여부</label>
                            </div>

                            <div class="form-check form-switch transaction-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="use_file_reference" name="use_file_reference" value="1">
                                <label class="form-check-label" for="use_file_reference">파일참조</label>
                            </div>

                            <div class="transaction-modal-state" id="transactionStatusBadge">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                <span>입력</span>
                            </div>
                        </div>

                        <div class="transaction-modal-grid transaction-modal-grid-main">
                            <label class="transaction-field">
                                <span class="transaction-field-label">거래일자</span>
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

                            <label class="transaction-field">
                                <span class="transaction-field-label">사업구분</span>
                                <select class="form-select form-select-sm"
                                        name="business_unit"
                                        id="business_unit"
                                        data-code-group="BUSINESS_UNIT"
                                        data-empty-label="사업구분선택"
                                        required>
                                    <option value="">사업구분선택</option>
                                </select>
                            </label>

                            <label class="transaction-field">
                                <span class="transaction-field-label">거래유형</span>
                                <select class="form-select form-select-sm"
                                        name="transaction_type"
                                        id="transaction_type"
                                        data-code-group="TRANSACTION_TYPE"
                                        data-empty-label="거래유형선택"
                                        required>
                                    <option value="">거래유형선택</option>
                                </select>
                            </label>

                            <label class="transaction-field">
                                <span class="transaction-field-label">거래처</span>
                                <select class="form-select form-select-sm"
                                        name="client_id"
                                        id="client_id"
                                        data-placeholder="거래처검색"
                                        required>
                                    <option value=""></option>
                                </select>
                            </label>

                            <label class="transaction-field">
                                <span class="transaction-field-label">프로젝트</span>
                                <select class="form-select form-select-sm"
                                        name="project_id"
                                        id="project_id"
                                        data-placeholder="프로젝트 검색">
                                    <option value=""></option>
                                </select>
                            </label>
                        </div>

                        <div class="transaction-modal-grid transaction-modal-grid-summary">
                            <label class="transaction-field transaction-description-field">
                                <span class="transaction-field-label">적요</span>
                                <input type="text" class="form-control form-control-sm" name="description" id="transaction_description" placeholder="거래 내용을 입력하세요">
                            </label>

                            <label class="transaction-field transaction-currency-field">
                                <span class="transaction-field-label">통화</span>
                                <select class="form-select form-select-sm"
                                        name="currency"
                                        id="currency"
                                        data-code-group="CURRENCY"
                                        data-empty-label="선택(없음)">
                                    <option value=""></option>
                                </select>
                            </label>

                            <label class="transaction-field transaction-exchange-field">
                                <span class="transaction-field-label">환율</span>
                                <input type="text"
                                       class="form-control form-control-sm number-input"
                                       name="exchange_rate"
                                       id="exchange_rate"
                                       inputmode="decimal"
                                       autocomplete="off">
                            </label>
                        </div>

                        <div class="transaction-modal-grid transaction-modal-grid-note">
                            <label class="transaction-field">
                                <span class="transaction-field-label">비고</span>
                                <input type="text" class="form-control form-control-sm" name="note" id="transaction_note" maxlength="255" placeholder="비고를 입력하세요">
                            </label>

                            <label class="transaction-field">
                                <span class="transaction-field-label">메모</span>
                                <textarea class="form-control form-control-sm" name="memo" id="transaction_memo" rows="2" placeholder="메모를 입력하세요"></textarea>
                            </label>
                        </div>

                        <div class="transaction-file-panel d-none" id="transactionFilePanel">
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
                    </section>

                    <section class="transaction-card transaction-lines-section" aria-label="거래내역">
                        <div class="transaction-card-header">
                            <h6>거래내역</h6>
                        </div>

                        <div class="transaction-hot-wrap">
                            <div id="transactionLineHot" class="transaction-line-hot"></div>
                        </div>

                        <div class="transaction-lines-footer">
                            <div class="transaction-summary-grid">
                                <div class="transaction-summary-row">
                                    <span>공급가 합계</span>
                                    <input type="text" class="form-control form-control-sm" id="transaction_supply_total" value="0" readonly>
                                </div>

                                <div class="transaction-summary-row">
                                    <span>부가세 합계</span>
                                    <input type="text" class="form-control form-control-sm" id="transaction_vat_total" value="0" readonly>
                                </div>

                                <div class="transaction-summary-row total">
                                    <span>총금액</span>
                                    <input type="text" class="form-control form-control-sm" id="transaction_grand_total" value="0" readonly>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="transaction-card transaction-voucher-card" aria-label="전표 등록 및 연결관리">
                        <div class="transaction-card-header">
                            <h6>전표 등록 및 연결관리</h6>
                            <span class="transaction-status none" id="transactionVoucherStatus">미연결</span>
                        </div>

                        <div class="transaction-voucher-layout">
                            <div class="transaction-voucher-summary" id="transaction_voucher_summary">
                                저장 후 전표를 생성하거나 기존 전표와 연결할 수 있습니다.
                            </div>

                            <div class="transaction-voucher-actions">
                                <button type="button" class="btn btn-outline-success btn-sm" id="btnCreateTransactionVoucher">전표 생성</button>
                                <input type="text" class="form-control form-control-sm" id="transaction_voucher_id" placeholder="기존 전표 ID">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnLinkTransactionVoucher">전표 연결</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="btnUnlinkTransactionVoucher">연결 해제</button>
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

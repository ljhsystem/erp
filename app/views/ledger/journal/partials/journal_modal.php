<div class="modal fade"
     id="journalModal"
     tabindex="-1"
     aria-labelledby="journalModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable journal-voucher-dialog">
        <div class="modal-content journal-modal-card">
            <form id="journal-edit-form" class="journal-modal-form" autocomplete="off">
                <div class="modal-header journal-modal-header">
                    <div>
                        <h5 class="modal-title" id="journalModalLabel">전표 등록</h5>
                        <p class="journal-modal-subtitle mb-0">전표 처리상태, 분개라인, 거래·증빙 연결정보를 한 화면에서 관리합니다.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>

                <div class="modal-body journal-modal-body">
                    <input type="hidden" name="id" id="journal_id">
                    <input type="hidden" name="status" id="voucher_status" value="DRAFT">

                    <section class="form-section journal-section journal-status-panel" aria-label="전표 처리상태">
                        <div class="section-header journal-section-head journal-card-header">
                            <div class="journal-card-heading">
                                <span class="section-title journal-section-title journal-card-title">전표 처리상태</span>
                                <p class="journal-card-description">저장부터 승인과 마감까지 현재 전표의 진행 상태를 확인합니다.</p>
                            </div>
                        </div>

                        <div class="section-body">
                            <div id="voucher_status_badge"
                                 class="voucher-status-timeline"
                                 aria-live="polite"></div>
                        </div>
                    </section>

                    <section class="form-section journal-section journal-voucher-header" aria-label="전표 개요">
                        <div class="section-header journal-section-head journal-card-header">
                            <div class="journal-card-heading">
                                <span class="section-title journal-section-title journal-card-title">전표 개요</span>
                                <p class="journal-card-description">전표번호, 전표일자, 전표요약을 현재 테이블설정 기준으로 관리합니다.</p>
                            </div>
                        </div>

                        <div class="section-body">
                            <div class="journal-header-grid">
                                <div class="journal-form-field journal-voucher-no-field">
                                    <label class="form-label"
                                           for="voucher_no_display"
                                           data-voucher-label-for="voucher_no"></label>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="voucher_no_display"
                                           value="자동발번"
                                           readonly>
                                </div>

                                <div class="journal-form-field">
                                    <label class="form-label"
                                           for="voucher_date"
                                           data-voucher-label-for="voucher_date"></label>
                                    <div class="date-input">
                                        <input type="text"
                                               class="form-control form-control-sm admin-date"
                                               name="voucher_date"
                                               id="voucher_date"
                                               placeholder="날짜를 선택해 주세요"
                                               autocomplete="off"
                                               inputmode="numeric"
                                               maxlength="10"
                                               required>
                                        <i class="fa fa-calendar-days date-icon" aria-hidden="true"></i>
                                    </div>
                                </div>

                                <div class="journal-form-field journal-summary-text-field">
                                    <label class="form-label"
                                           for="voucher_summary_text"
                                           data-voucher-label-for="summary"></label>
                                    <div class="summary-autocomplete-wrap">
                                        <input type="text"
                                               class="form-control form-control-sm"
                                               name="summary_text"
                                               id="voucher_summary_text"
                                               placeholder="전표 적요를 입력해 주세요"
                                               autocomplete="off">
                                        <div id="voucher_summary_suggestions"
                                             class="summary-autocomplete-list d-none"
                                             role="listbox"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="form-section journal-section journal-lines-panel" aria-label="분개라인">
                        <div class="section-header journal-lines-toolbar journal-card-header">
                            <div class="journal-card-heading">
                                <span class="section-title journal-section-title journal-card-title">분개라인</span>
                                <p class="journal-card-description">HTML Grid에서 차변, 대변, 계정과목, 보조계정을 한 번에 입력하고 합계를 검증합니다.</p>
                            </div>
                            <div class="journal-card-header-actions">
                                <button type="button"
                                        class="btn btn-link btn-sm voucher-line-add-btn"
                                        id="btnAddVoucherLine">+추가</button>
                            </div>
                        </div>

                        <div class="section-body">
                            <div class="journal-lines-wrap">
                                <div id="voucher-line-grid-host" class="voucher-line-grid-host"></div>
                                <div class="table-responsive journal-lines-table-wrap">
                                    <table class="table table-bordered align-middle mb-0" id="voucher-line-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="64">순번</th>
                                                <th width="280">계정과목</th>
                                                <th width="260" class="line-ref-cell">보조계정</th>
                                                <th width="150">차변</th>
                                                <th width="150">대변</th>
                                                <th>라인 적요</th>
                                                <th width="64" class="journal-table-action-head"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="voucher-line-body">
                                            <tr class="voucher-line-empty">
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    분개라인을 추가해 주세요.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="journal-summary" aria-label="합계">
                                <div class="journal-form-field journal-summary-item">
                                    <label class="form-label" for="voucher_debit_total">차변 합계</label>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="voucher_debit_total"
                                           value="0"
                                           readonly>
                                </div>

                                <div class="journal-form-field journal-summary-item">
                                    <label class="form-label" for="voucher_credit_total">대변 합계</label>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="voucher_credit_total"
                                           value="0"
                                           readonly>
                                </div>

                                <div class="journal-form-field journal-summary-item journal-summary-item--status">
                                    <label class="form-label" for="voucher_balance_status">검증 상태</label>
                                    <div id="voucher_balance_status"
                                         class="voucher-validation-badge voucher-validation-error"
                                         aria-live="polite">차변/대변 합계를 확인해 주세요.</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="form-section journal-section journal-system-info-panel" aria-label="시스템 처리 정보">
                        <button type="button"
                                class="journal-card-toggle collapsed"
                                data-bs-toggle="collapse"
                                data-bs-target="#voucherSystemInfoCollapse"
                                aria-expanded="false"
                                aria-controls="voucherSystemInfoCollapse">
                            <span class="journal-card-heading">
                                <span class="section-title journal-section-title journal-card-title">시스템 처리 정보</span>
                                <span class="journal-card-description">상태, 생성자, 수정자, 생성일시, 수정일시를 현재 메타 순서대로 확인합니다.</span>
                            </span>
                            <i class="bi bi-chevron-down journal-card-icon" aria-hidden="true"></i>
                        </button>

                        <div id="voucherSystemInfoCollapse" class="collapse">
                            <div class="section-body journal-card-body">
                                <div id="voucher_system_info_fields" class="journal-header-grid journal-system-grid"></div>
                            </div>
                        </div>
                    </section>

                    <section class="form-section journal-section journal-link-info-panel" aria-label="증빙연결정보">
                        <button type="button"
                                class="journal-card-toggle collapsed"
                                data-bs-toggle="collapse"
                                data-bs-target="#voucherLinkInfoCollapse"
                                aria-expanded="false"
                                aria-controls="voucherLinkInfoCollapse">
                            <span class="journal-card-heading">
                                <span class="section-title journal-section-title journal-card-title">증빙연결정보</span>
                                <span class="journal-card-description">전표에 연결된 증빙을 확인하고 선택, 변경, 해제합니다.</span>
                            </span>
                            <i class="bi bi-chevron-down journal-card-icon" aria-hidden="true"></i>
                        </button>

                        <div id="voucherLinkInfoCollapse" class="collapse">
                            <div class="section-body journal-card-body journal-link-card-body">
                                <div class="journal-link-sections">
                                    <section class="journal-link-section journal-evidence-panel" aria-label="증빙 연결">
                                        <div class="journal-link-section-head">
                                            <span class="journal-link-section-title">증빙연결</span>
                                        </div>

                                        <div class="journal-evidence-link">
                                            <input type="hidden"
                                                   name="linked_evidence_id"
                                                   id="linked_evidence_id">
                                            <button type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    id="btnSelectEvidence">증빙 선택</button>
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    id="btnClearEvidenceLink">연결 해제</button>
                                            <div class="journal-transaction-summary"
                                                 id="linked_evidence_summary">연결된 증빙이 없습니다.</div>
                                            <div class="journal-source-origin d-none" id="linked_evidence_origin"></div>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-danger btn-sm d-none"
                            id="btnDeleteVoucherInModal">삭제</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btnSaveVoucher">저장</button>
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            id="btnRequestVoucherReview">검토요청</button>
                    <button type="button"
                            class="btn btn-outline-warning btn-sm d-none"
                            id="btnCancelVoucherReview">검토요청 취소</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>

                <div id="journal-today-picker" class="is-hidden"></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade"
     id="journalEvidenceSearchModal"
     tabindex="-1"
     aria-labelledby="journalEvidenceSearchModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="journalEvidenceSearchModalLabel">증빙 선택</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <div class="journal-transaction-search">
                    <input type="search"
                           class="form-control form-control-sm"
                           id="journal_evidence_search_keyword"
                           placeholder="자료유형, 일자, 거래처, 금액, 적요로 검색">
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            id="btnSearchEvidence">검색</button>
                </div>

                <div class="table-responsive journal-search-table-wrap">
                    <table class="table table-bordered align-middle mb-0 journal-transaction-search-table">
                        <thead class="table-light">
                            <tr>
                                <th width="120">증빙일자</th>
                                <th width="170">자료유형</th>
                                <th width="150">거래처</th>
                                <th width="120">금액</th>
                                <th>적요</th>
                                <th width="90">선택</th>
                            </tr>
                        </thead>
                        <tbody id="journal_evidence_search_body">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    증빙을 검색해 주세요.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

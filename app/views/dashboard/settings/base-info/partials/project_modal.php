<!-- 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/partials/project_modal.php' -->

<div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content project-modal-content">

            <form method="post" id="project-edit-form">

                <div class="modal-header">
                    <h5 class="modal-title" id="projectModalLabel">프로젝트 등록/수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>

                <div class="modal-body project-modal-body">

                    <input type="hidden" name="id" id="modal_project_id">
                    <input type="hidden" name="delete_project_image" id="delete_project_image" value="0">

                    <div class="card mb-3">
                        <div class="card-header py-1 px-2">기본 정보</div>
                        <div class="card-body py-2">
                            <div class="row g-2">

                                <div class="col-md-4">
                                    <label class="form-label">프로젝트명 *</label>
                                    <input type="text"
                                           name="project_name"
                                           id="modal_project_name"
                                           class="form-control form-control-sm"
                                           required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">공사명</label>
                                    <input type="text"
                                           name="construction_name"
                                           id="modal_construction_name"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">사용여부</label>
                                    <select name="is_active"
                                            id="modal_is_active"
                                            class="form-select form-select-sm">
                                        <option value="1">진행중</option>
                                        <option value="0">완료됨</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">담당직원</label>
                                    <select name="employee_id"
                                            id="modal_employee_id"
                                            class="form-select form-select-sm">
                                        <option value="">선택</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header py-1 px-2">거래처 및 계약 정보</div>
                        <div class="card-body py-2">
                            <div class="row g-2">

                                <div class="col-md-3">
                                    <label class="form-label">거래처</label>
                                    <select name="client_id"
                                            id="modal_project_client_id"
                                            class="form-select form-select-sm">
                                        <option value="">선택</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">거래처명</label>
                                    <input type="text"
                                           name="client_name"
                                           id="modal_client_name"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">거래처유형</label>
                                    <select name="client_type"
                                            id="modal_client_type"
                                            class="form-select form-select-sm"
                                            data-code-group="CLIENT_TYPE">
                                        <option value="">선택</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">계약구분</label>
                                    <select name="contract_type"
                                            id="modal_contract_type"
                                            class="form-select form-select-sm">
                                        <option value="">선택</option>
                                        <option value="공공">공공</option>
                                        <option value="계약">계약</option>
                                        <option value="민간">민간</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">계약공종</label>
                                    <select name="contract_work_type"
                                            id="modal_contract_work_type"
                                            class="form-select form-select-sm">
                                        <option value="">선택</option>
                                        <option value="민간공사">민간공사</option>
                                        <option value="관급공사">관급공사</option>
                                        <option value="기타">기타</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-2 mt-2">

                                <div class="col-md-3">
                                    <label class="form-label">현장대리인</label>
                                    <input type="text"
                                           name="site_agent"
                                           id="modal_site_agent"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">감독관</label>
                                    <input type="text"
                                           name="director"
                                           id="modal_director"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">실장</label>
                                    <input type="text"
                                           name="manager"
                                           id="modal_manager"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">입찰구분</label>
                                    <select name="bid_type"
                                            id="modal_bid_type"
                                            class="form-select form-select-sm">
                                        <option value="">선택</option>
                                        <option value="일반경쟁">일반경쟁</option>
                                        <option value="제한경쟁">제한경쟁</option>
                                        <option value="수의계약">수의계약</option>
                                        <option value="지명경쟁">지명경쟁</option>
                                        <option value="기타">기타</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header py-1 px-2">공사 정보</div>
                        <div class="card-body py-2">
                            <div class="row g-2">

                                <div class="col-md-3">
                                    <label class="form-label">업태</label>
                                    <input type="text"
                                           name="business_type"
                                           id="modal_business_type"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">주택유형</label>
                                    <input type="text"
                                           name="housing_type"
                                           id="modal_housing_type"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">공종</label>
                                    <input type="text"
                                           name="work_type"
                                           id="modal_work_type"
                                           class="form-control form-control-sm"
                                           placeholder="예: 건축공사">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">공종 세분류</label>
                                    <input type="text"
                                           name="work_subtype"
                                           id="modal_work_subtype"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">세부 공사종류</label>
                                    <input type="text"
                                           name="work_detail_type"
                                           id="modal_work_detail_type"
                                           class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header py-1 px-2">공사 위치</div>
                        <div class="card-body py-2">
                            <div class="row g-2">

                                <div class="col-md-2">
                                    <label class="form-label">시/도</label>
                                    <input type="text"
                                           name="site_region_city"
                                           id="modal_site_region_city"
                                           class="form-control form-control-sm"
                                           readonly>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">시/군/구</label>
                                    <input type="text"
                                           name="site_region_district"
                                           id="modal_site_region_district"
                                           class="form-control form-control-sm"
                                           readonly>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">주소</label>
                                    <div class="input-group">
                                        <input type="text"
                                               name="site_region_address"
                                               id="modal_site_region_address"
                                               class="form-control form-control-sm"
                                               placeholder="공사지 주소">
                                        <button type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                data-addr-picker
                                                data-address="#modal_site_region_address"
                                                data-sido="#modal_site_region_city"
                                                data-sigungu="#modal_site_region_district"
                                                data-detail="#modal_site_region_address_detail">
                                            주소검색
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">상세주소</label>
                                    <input type="text"
                                           name="site_region_address_detail"
                                           id="modal_site_region_address_detail"
                                           class="form-control form-control-sm"
                                           placeholder="공사지 상세주소">
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header py-1 px-2">일정 및 금액</div>
                        <div class="card-body py-2">
                            <div class="row g-2">

                                <div class="col-md-2">
                                    <label class="form-label">허가일자</label>
                                    <div class="date-input-wrap">
                                        <input type="text"
                                               id="modal_permit_date"
                                               name="permit_date"
                                               class="form-control form-control-sm admin-date"
                                               placeholder="YYYY-MM-DD"
                                               autocomplete="off">
                                        <span class="date-icon"><i class="bi bi-calendar3"></i></span>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">계약일자</label>
                                    <div class="date-input-wrap">
                                        <input type="text"
                                               id="modal_contract_date"
                                               name="contract_date"
                                               class="form-control form-control-sm admin-date"
                                               placeholder="YYYY-MM-DD"
                                               autocomplete="off">
                                        <span class="date-icon"><i class="bi bi-calendar3"></i></span>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">착공일자</label>
                                    <div class="date-input-wrap">
                                        <input type="text"
                                               id="modal_start_date"
                                               name="start_date"
                                               class="form-control form-control-sm admin-date"
                                               placeholder="YYYY-MM-DD"
                                               autocomplete="off">
                                        <span class="date-icon"><i class="bi bi-calendar3"></i></span>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">준공일자</label>
                                    <div class="date-input-wrap">
                                        <input type="text"
                                               id="modal_completion_date"
                                               name="completion_date"
                                               class="form-control form-control-sm admin-date"
                                               placeholder="YYYY-MM-DD"
                                               autocomplete="off">
                                        <span class="date-icon"><i class="bi bi-calendar3"></i></span>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">입찰공고일</label>
                                    <div class="date-input-wrap">
                                        <input type="text"
                                               id="modal_bid_notice_date"
                                               name="bid_notice_date"
                                               class="form-control form-control-sm admin-date"
                                               placeholder="YYYY-MM-DD"
                                               autocomplete="off">
                                        <span class="date-icon"><i class="bi bi-calendar3"></i></span>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">최초 계약금액</label>
                                    <input type="text"
                                           id="modal_initial_contract_amount"
                                           name="initial_contract_amount"
                                           data-format="amount"
                                           class="form-control form-control-sm text-end"
                                           inputmode="decimal">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header py-1 px-2">허가 및 인감</div>
                        <div class="card-body py-2">
                            <div class="row g-2">

                                <div class="col-md-6">
                                    <label class="form-label">허가기관</label>
                                    <input type="text"
                                           name="permit_agency"
                                           id="modal_permit_agency"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">사용인감명</label>
                                    <input type="text"
                                           name="authorized_company_seal"
                                           id="modal_authorized_company_seal"
                                           class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header py-1 px-2">비고 및 메모</div>
                        <div class="card-body py-2">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">비고</label>
                                    <textarea name="note"
                                              id="modal_note"
                                              class="form-control form-control-sm"
                                              rows="5"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">메모</label>
                                    <textarea name="memo"
                                              id="modal_memo"
                                              class="form-control form-control-sm"
                                              rows="5"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" id="btnDeleteProject" class="btn btn-danger btn-sm" style="display:none;">삭제</button>
                    <button type="submit" id="btnSaveProject" name="project_save" class="btn btn-success btn-sm">저장</button>
                    <button type="button" id="btnCloseProject" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>

            </form>
        </div>
    </div>
</div>

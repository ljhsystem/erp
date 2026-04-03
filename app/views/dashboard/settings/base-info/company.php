<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/company.php'
?>
<div id="company-settings-wrapper" class="company-settings">

    <h5 class="fw-bold mb-3">회사 기본설정</h5>

    <!-- =========================
         1. 회사 기본 정보
    ========================== -->
    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">
            회사 기본 정보
        </div>
        <div class="card-body">

            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">회사명(한글)</label>
                    <input type="text" class="form-control" name="company_name_ko">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">회사명(영문)</label>
                    <input type="text" class="form-control" name="company_name_en">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">대표자명</label>
                    <input type="text" class="form-control" name="ceo_name">
                </div>
            </div>

        </div>
    </div>

    <!-- =========================
         2. 사업자 / 법인 정보
    ========================== -->
    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">
            사업자 / 법인 정보
        </div>
        <div class="card-body">

            <div class="row g-2">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">사업자등록번호</label>
                    <input type="text" class="form-control" name="biz_number">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">법인등록번호</label>
                    <input type="text" class="form-control" name="corp_number">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">설립일</label>
                    <input type="date" class="form-control" name="found_date">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">업태</label>
                    <input type="text" class="form-control" name="biz_type">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">종목</label>
                    <input type="text" class="form-control" name="biz_item">
                </div>
            </div>

        </div>
    </div>

    <!-- =========================
         3. 주소 정보
    ========================== -->
    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">
            주소 정보
        </div>
        <div class="card-body">

            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">본점소재지</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="addr_main">
                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            data-addr-picker
                            data-target="[name='addr_main']">
                            검색
                        </button>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">상세주소</label>
                    <input type="text" class="form-control" name="addr_detail">
                </div>
            </div>

        </div>
    </div>

    <!-- =========================
         4. 연락처 정보
    ========================== -->
    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">
            연락처 정보
        </div>
        <div class="card-body">

        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label fw-semibold">전화번호</label>
                <input type="text" class="form-control" name="tel">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">팩스번호</label>
                <input type="text" class="form-control" name="fax">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">세금계산서 이메일</label>
                <input type="email" class="form-control" name="tax_email">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">서브 이메일</label>
                <input type="email" class="form-control" name="sub_email">
            </div>
        </div>

        <div class="row g-2 mt-2">
            <div class="col-md-6">
                <label class="form-label fw-semibold">홈페이지</label>
                <input type="text" class="form-control" name="company_website">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">인스타그램</label>
                <input type="text" class="form-control" name="sns_instagram">
            </div>
        </div>


        </div>
    </div>

    <!-- =========================
         5. 회사 공식 소개
    ========================== -->
    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">
            회사 공식 소개
        </div>
        <div class="card-body">

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">회사소개</label>
                    <textarea class="form-control" name="company_about" rows="5"></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">연혁</label>
                    <textarea class="form-control" name="company_history" rows="5"></textarea>
                </div>
            </div>

        </div>
    </div>

    <div class="text-end mb-4">
        <button id="btn-save-all" class="btn btn-primary w-100 py-2 fw-bold fs-5">
            전체 설정 저장
        </button>
    </div>

</div>

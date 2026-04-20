<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/system/site.php'
?>

<div id="site-settings-wrapper" class="site-settings col-12 mx-auto">
    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-globe me-2"></i>사이트 기본설정
    </h4>

    <form id="site-setting-form">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold text-primary">
                        기본 정보
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">브라우저 페이지 제목</label>
                            <input type="text" class="form-control" name="page_title">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">사이트 제목</label>
                            <input type="text" class="form-control" name="site_title">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">홈 소개 제목</label>
                            <input type="text" class="form-control" name="home_intro_title">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">홈 소개 문구</label>
                            <textarea class="form-control" name="home_intro_description" rows="5"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">홈 소개 이동 URL</label>
                            <input type="text" class="form-control" name="home_intro_url" placeholder="https://example.com">
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-semibold">푸터 문구</label>
                            <input type="text" class="form-control" name="footer_text">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold text-primary">
                        UI / 표시 설정
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">UI 스킨</label>
                                <select class="form-select" name="ui_skin">
                                    <option value="default">기본 (Blue)</option>
                                    <option value="green">그린</option>
                                    <option value="gray">그레이</option>
                                    <option value="dark">다크</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">테마 모드</label>
                                <select class="form-select" name="theme_mode">
                                    <option value="light">라이트</option>
                                    <option value="dark">다크</option>
                                    <option value="system">시스템 설정 따름</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">기본 글꼴</label>
                                <select class="form-select" name="font_family">
                                    <option value="">시스템 기본</option>
                                    <option value="Pretendard">Pretendard</option>
                                    <option value="Noto Sans KR">Noto Sans KR</option>
                                    <option value="Nanum Gothic">Nanum Gothic</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">기본 글자 크기</label>
                                <select class="form-select" name="font_scale">
                                    <option value="small">작게</option>
                                    <option value="normal">기본</option>
                                    <option value="large">크게</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">테이블 표시 밀도</label>
                                <select class="form-select" name="table_density">
                                    <option value="compact">촘촘</option>
                                    <option value="normal">기본</option>
                                    <option value="comfortable">여유</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">카드 / 섹션 밀도</label>
                                <select class="form-select" name="card_density">
                                    <option value="compact">촘촘</option>
                                    <option value="normal">기본</option>
                                    <option value="comfortable">여유</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">모서리 스타일</label>
                                <select class="form-select" name="radius_style">
                                    <option value="rounded">둥글게</option>
                                    <option value="sharp">각지게</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">버튼 스타일</label>
                                <select class="form-select" name="button_style">
                                    <option value="solid">채워진 버튼</option>
                                    <option value="outline">아웃라인 버튼</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">행 강조 스타일</label>
                                <select class="form-select" name="row_focus">
                                    <option value="soft">부드럽게</option>
                                    <option value="normal">기본</option>
                                    <option value="strong">강하게</option>
                                    <option value="none">없음</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">링크 밑줄 표시</label>
                                <select class="form-select" name="link_underline">
                                    <option value="off">표시 안 함</option>
                                    <option value="on">항상 표시</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">아이콘 크기</label>
                                <select class="form-select" name="icon_scale">
                                    <option value="small">작게</option>
                                    <option value="normal">기본</option>
                                    <option value="large">크게</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">알림 강조 스타일</label>
                                <select class="form-select" name="alert_style">
                                    <option value="soft">부드럽게</option>
                                    <option value="normal">기본</option>
                                    <option value="strong">강하게</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">사이드바 기본 상태</label>
                                <select class="form-select" name="sidebar_default">
                                    <option value="expanded">펼침</option>
                                    <option value="collapsed">접힘</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">모션 효과</label>
                                <select class="form-select" name="motion_mode">
                                    <option value="on">사용</option>
                                    <option value="off">사용 안 함</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                    <i class="bi bi-save me-1"></i> 저장
                </button>
            </div>
        </div>
    </form>
</div>

<?php
// 경로: PROJECT_ROOT . '/app/views/ledger/journal/journal.php'

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = "일반전표입력";

$layoutOptions = [
    'header'  => true,
    'navbar'  => true,
    'sidebar' => true,
    'footer'  => true,
    'wrapper' => 'single'
];

$breadcrumb = [
    '홈' => '/dashboard',
    '거래원장대시보드' => '/ledger',
    '전표입력' => '/ledger/input/general'
];
?>

<?php include_once __DIR__ . '/../../layout/breadcrumb.php'; ?>

<main class="journal-main">

<div class="container py-4">

    <!-- 🔵 페이지 타이틀 -->
    <div class="page-header mb-3">
        <h4 class="fw-bold">📝 일반 전표 입력</h4>
    </div>

    <!-- 🔵 상단 요약 카드 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="small text-muted">이번달 매출</div>
                    <div class="fs-5 fw-bold text-primary">₩45,200,000</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="small text-muted">이번달 원가</div>
                    <div class="fs-5 fw-bold text-danger">₩31,700,000</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="small text-muted">현재 손익</div>
                    <div class="fs-5 fw-bold text-success">₩13,500,000</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔵 전표 헤더 카드 -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header fw-bold">
            전표 기본 정보
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">전표일자</label>
                    <input type="date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">전표번호</label>
                    <input type="text" class="form-control" value="임시-0001" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">프로젝트</label>
                    <select class="form-select">
                        <option>청주현장</option>
                        <option>산성유원지</option>
                        <option>Serenity Golf</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">전표 전체 적요</label>
                    <input type="text" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <!-- 🔵 전표 입력 영역 -->
    <div class="row">

        <!-- 좌측: 입력 테이블 -->
        <div class="col-lg-9">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">
                    분개 입력
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0 text-center" id="journalTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50px;">#</th>
                                    <th>계정과목</th>
                                    <th style="width:150px;">차변</th>
                                    <th style="width:150px;">대변</th>
                                    <th style="width:200px;">라인적요</th>
                                    <th style="width:70px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>
                                        <select class="form-select">
                                            <option>1110 | 보통예금</option>
                                            <option>5200 | 공사원가</option>
                                            <option>8000 | 매출</option>
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control debit"></td>
                                    <td><input type="text" class="form-control credit"></td>
                                    <td><input type="text" class="form-control"></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>
                                        <select class="form-select">
                                            <option>1110 | 보통예금</option>
                                            <option>5200 | 공사원가</option>
                                            <option>8000 | 매출</option>
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control debit"></td>
                                    <td><input type="text" class="form-control credit"></td>
                                    <td><input type="text" class="form-control"></td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2">합계</th>
                                    <th id="totalDebit">0</th>
                                    <th id="totalCredit">0</th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="card-footer text-start">
                    <button class="btn btn-outline-secondary btn-sm" id="addRow">+ 행 추가</button>
                </div>
            </div>
        </div>

        <!-- 우측: 실시간 요약 -->
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">
                    전표 상태
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        차변 합계:
                        <span id="sumDebit" class="fw-bold text-primary">0</span>
                    </div>
                    <div class="mb-2">
                        대변 합계:
                        <span id="sumCredit" class="fw-bold text-danger">0</span>
                    </div>
                    <hr>
                    <div id="balanceStatus" class="fw-bold text-danger">
                        차대 불일치
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- 🔵 하단 버튼 -->
    <div class="mt-4 d-flex justify-content-end gap-2">
        <button class="btn btn-secondary">임시저장</button>
        <button class="btn btn-primary">저장</button>
    </div>

</div>
</main>
<?php
// 경로: PROJECT_ROOT . 'app/views/approval/index.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/approval/index.css') ?>

<main class="approval-main">
    <h5 class="page-title">📑 결재서류 목록</h5>

    <div class="mb-3 text-end">
        <a href="/approval/my_approval" class="btn btn-outline-primary btn-sm">
            🖊️ 나의 결재 목록 보기
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered table-hover table-sm text-center">
                <thead class="table-light">
                    <tr>
                        <th>번호</th>
                        <th>문서제목</th>
                        <th>기안자</th>
                        <th>상태</th>
                        <th>등록일</th>
                        <th>보기</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td class="text-start">출장보고서 - 부산</td>
                        <td>홍길동</td>
                        <td><span class="badge bg-warning text-dark">결재대기</span></td>
                        <td>2025-07-06</td>
                        <td><a href="#" class="btn btn-outline-primary btn-sm">열기</a></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td class="text-start">계약서 - A건설</td>
                        <td>김영희</td>
                        <td><span class="badge bg-success">결재완료</span></td>
                        <td>2025-07-05</td>
                        <td><a href="#" class="btn btn-outline-primary btn-sm">열기</a></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td class="text-start">회의록 - 팀회의</td>
                        <td>이철수</td>
                        <td><span class="badge bg-danger">반려</span></td>
                        <td>2025-07-04</td>
                        <td><a href="#" class="btn btn-outline-primary btn-sm">열기</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>


<?php include(__DIR__ . '/../layout/footer.php'); ?>
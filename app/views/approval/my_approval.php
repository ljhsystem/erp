<?php
// 경로: PROJECT_ROOT . 'app/views/approval/my_approval.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/approval/my_approval.css') ?>

<main class="my-approval-main">
    <h5 class="page-title">✅ 나의 결재 대기 목록</h5>

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered table-hover table-sm text-center my-approval-table">
                <thead class="table-light">
                    <tr>
                        <th>번호</th>
                        <th>문서제목</th>
                        <th>기안자</th>
                        <th>결재라인</th>
                        <th>상태</th>
                        <th>보기</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td class="text-start">업무보고서 - 프로젝트 A</td>
                        <td>최유정</td>
                        <td class="text-start">
                            <span class="badge bg-secondary">사원</span>
                            <span class="badge bg-primary">대리 (<?php echo htmlspecialchars($username); ?>)</span>
                            <span class="badge bg-light text-dark">과장</span>
                        </td>
                        <td><span class="badge bg-warning text-dark">내 승인 대기</span></td>
                        <td>
                            <a href="/dashboard/approval/view_approval.php?id=20250704" class="btn btn-outline-primary btn-sm">열기</a>
                        </td>
                    </tr>
                    <!-- 더보기: 발생한 문서가 있다면 추가 반복 -->
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
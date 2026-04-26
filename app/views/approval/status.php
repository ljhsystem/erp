<?php
// 경로: PROJECT_ROOT . 'app/views/approval/status.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/approval/status.css') ?>

<main class="approval-status-main">
    <h5 class="mb-4 fw-bold">📊 결재 현황</h5>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center small">
            <thead class="table-light">
                <tr>
                    <th>문서번호</th>
                    <th>제목</th>
                    <th>기안자</th>
                    <th>결재라인</th>
                    <th>현재상태</th>
                    <th>진행도</th>
                    <th>최종결재일</th>
                </tr>
            </thead>
            <tbody>
                <!-- 예시 행 -->
                <tr>
                    <td>#202507-001</td>
                    <td class="text-start">지출결의서 - 장비 임대료</td>
                    <td>홍길동 (대리)</td>
                    <td class="text-start">
                        <div class="approval-flow">
                            <span class="step approved">사원</span>
                            <span class="step approved">대리</span>
                            <span class="step current">과장</span>
                            <span class="step pending">부장</span>
                            <span class="step pending">이사</span>
                        </div>
                    </td>
                    <td><span class="badge bg-warning text-dark">진행 중</span></td>
                    <td>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: 40%;"></div>
                        </div>
                    </td>
                    <td>진행 중</td>
                </tr>

                <tr>
                    <td>#202507-002</td>
                    <td class="text-start">출장보고서 - 부산</td>
                    <td>이수정 (사원)</td>
                    <td class="text-start">
                        <div class="approval-flow">
                            <span class="step approved">사원</span>
                            <span class="step approved">대리</span>
                            <span class="step approved">과장</span>
                            <span class="step rejected">부장</span>
                            <span class="step pending">이사</span>
                        </div>
                    </td>
                    <td><span class="badge bg-danger">반려</span></td>
                    <td>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-danger" style="width: 60%;"></div>
                        </div>
                    </td>
                    <td>2025-07-06</td>
                </tr>

                <tr>
                    <td>#202507-003</td>
                    <td class="text-start">구매요청서 - 사무용품</td>
                    <td>김지훈 (과장)</td>
                    <td class="text-start">
                        <div class="approval-flow">
                            <span class="step approved">사원</span>
                            <span class="step approved">대리</span>
                            <span class="step approved">과장</span>
                            <span class="step approved">부장</span>
                            <span class="step approved">이사</span>
                        </div>
                    </td>
                    <td><span class="badge bg-success">승인 완료</span></td>
                    <td>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: 100%;"></div>
                        </div>
                    </td>
                    <td>2025-07-05</td>
                </tr>
            </tbody>
        </table>
    </div>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
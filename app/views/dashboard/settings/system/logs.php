<?php
// 경로: PROJECT_ROOT/app/views/dashboard/settings/system/logs.php
// 설명: 시스템설정 → 시스템 로그 (View 전용)
require_once PROJECT_ROOT . '/core/Storage.php';

$logDir = LOGS_DIR;

// 디렉터리 보장
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// 로그 파일 목록 수집
$files = array_values(array_filter(scandir($logDir), function ($f) use ($logDir) {
    return is_file($logDir . '/' . $f);
}));

// 최신 수정일 기준 정렬 (DESC)
usort($files, function ($a, $b) use ($logDir) {
    return filemtime($logDir . '/' . $b) <=> filemtime($logDir . '/' . $a);
});

// 요약 정보 계산
$totalCount = count($files);
$totalSize  = 0;

foreach ($files as $f) {
    $totalSize += filesize($logDir . '/' . $f);
}

// 용량 표시 함수
function formatSize($bytes)
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }
    return number_format($bytes / 1024, 1) . ' KB';
}
?>

<div class="logs-settings col-12 mx-auto">

    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-file-earmark-text me-2"></i>시스템 로그
    </h4>

    <!-- =========================
         상단 요약 카드
    ========================== -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-muted mb-1">로그 파일 수</div>
                    <div class="fs-3 fw-bold"><?= number_format($totalCount) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-muted mb-1">총 로그 용량</div>
                    <div class="fs-3 fw-bold"><?= formatSize($totalSize) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-muted mb-2">관리 작업</div>
                    <button class="btn btn-outline-danger btn-sm" id="delete-all-logs">
                        <i class="bi bi-trash"></i> 전체 로그 삭제
                    </button>
                    <div class="small text-muted mt-2">
                        ⚠️ 모든 로그 파일이 영구 삭제됩니다
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- =========================
         로그 목록 카드
    ========================== -->
    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">
            로그 파일 목록
        </div>

        <div class="card-body p-0">

        <?php if (!empty($files)): ?>

            <div class="table-responsive overflow-auto" style="max-height:350px;">

                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>파일명</th>
                            <th class="text-center">크기</th>
                            <th class="text-center">수정일</th>
                            <th class="text-center">작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $f):
                            $path  = $logDir . '/' . $f;
                            $size  = filesize($path);
                            $mtime = date('Y-m-d H:i:s', filemtime($path));
                        ?>
                        <tr>
                            <td><code><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td class="text-center"><?= formatSize($size) ?></td>
                            <td class="text-center"><?= $mtime ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary view-log"
                                        data-file="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-eye"></i> 보기
                                </button>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="/dashboard/settings/system/logs/download?file=<?= urlencode($f) ?>">
                                    <i class="bi bi-download"></i> 다운로드
                                </a>
                                <button class="btn btn-sm btn-outline-danger delete-log"
                                        data-file="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-trash"></i> 삭제
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>

            <div class="p-2 text-muted small border-top">
                ※ 대용량 로그 파일은 일부만 표시될 수 있습니다.
            </div>

        <?php else: ?>

            <div class="p-4 text-center text-muted">
                <i class="bi bi-info-circle me-1"></i>
                현재 생성된 로그 파일이 없습니다.
            </div>

        <?php endif; ?>

        </div>
    </div>

    <!-- =========================
         로그 뷰어
    ========================== -->
    <div class="card" id="log-viewer" style="display:none;">
        <div class="card-header fw-semibold text-primary d-flex justify-content-between">
            <span>
                <i class="bi bi-file-text me-1"></i> 로그 내용
            </span>
            <button class="btn btn-sm btn-outline-secondary" id="close-log-viewer">
                닫기
            </button>
        </div>
        <div class="card-body p-0">
            <pre id="log-content"
                 class="m-0 p-3 bg-dark text-light"
                 style="max-height:400px; overflow-y:auto; white-space:pre-wrap;"></pre>
        </div>
    </div>

</div>
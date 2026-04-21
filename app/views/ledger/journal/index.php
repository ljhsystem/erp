<?php
// 경로: PROJECT_ROOT . '/app/views/ledger/journal/index.php'

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '일반전표';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('/assets/css/pages/ledger/journal.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/journal.js');
?>

<main class="journal-page" id="journal-main">
    <div class="container-fluid py-4 journal-shell">
        <div class="journal-toolbar">
            <div class="journal-toolbar-copy">
                <h5 class="fw-bold">전표관리 &gt; 일반전표</h5>
                <div class="text-muted small">전표 헤더와 분개 라인을 한 화면에서 입력하고 저장합니다.</div>
            </div>

            <div class="journal-toolbar-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnJournalRefresh">새로고침</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnOpenJournalModal">전표 등록</button>
            </div>
        </div>

        <div class="content-area">
            <div class="card shadow-sm border-0 journal-list-card">
                <div class="card-header bg-white fw-bold">전표 목록</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0 journal-table" id="journal-table">
                            <thead class="table-light">
                                <tr>
                                    <th width="140">전표번호</th>
                                    <th width="120">전표일자</th>
                                    <th width="110">상태</th>
                                    <th width="120">참조유형</th>
                                    <th width="160">참조 ID</th>
                                    <th>적요</th>
                                    <th width="140">수정일시</th>
                                    <th width="140">관리</th>
                                </tr>
                            </thead>
                            <tbody id="journal-table-body">
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">등록된 전표가 없습니다.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/partials/journal_modal.php'; ?>

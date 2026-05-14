<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '원본자료';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => false,
    'wrapper' => 'single',
];

$pageScripts = AssetHelper::module('/assets/js/pages/ledger/dataRawStorage.js');
?>

<main class="ledger-data-raw-list-page" id="ledgerDataRawListPage">
    <div class="container-fluid py-3">
        <div class="page-header mb-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-archive me-2"></i>자료목록
                </h5>
                <div class="small text-muted mt-1">
                    업로드된 원본 Seed Data와 처리 이력을 조회합니다.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="/ledger/data/upload" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-upload me-1"></i>자료 업로드
                </a>
                <a href="/ledger/data/create" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-database-check me-1"></i>생성센터
                </a>
                <button type="button" class="btn btn-outline-dark btn-sm" id="btnReloadRawData">
                    <i class="bi bi-arrow-clockwise me-1"></i>새로고침
                </button>
            </div>
        </div>

        <section class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>업로드 배치</strong>
                    <span class="text-muted small ms-2" id="seedBatchSummary">-</span>
                </div>
                <span class="badge text-bg-light">조회 전용</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="seedBatchTable">
                    <thead class="table-light">
                    <tr>
                        <th>파일명</th>
                        <th>양식</th>
                        <th>업로드일시</th>
                        <th class="text-end">업로드건수</th>
                        <th class="text-end">VALID</th>
                        <th class="text-end">WARNING</th>
                        <th class="text-end">ERROR</th>
                        <th class="text-end">중복</th>
                        <th class="text-end">생성</th>
                        <th>처리상태</th>
                        <th>업로드 사용자</th>
                    </tr>
                    </thead>
                    <tbody id="seedBatchBody">
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">업로드 배치를 불러오는 중입니다.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Seed Row</strong>
                    <span class="text-muted small ms-2" id="seedRowSummary">배치를 선택하세요.</span>
                </div>
                <span class="small text-muted" id="selectedBatchLabel"></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="rawSeedRowsTable">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 72px;">행</th>
                        <th>상태</th>
                        <th>자료유형</th>
                        <th>거래구분</th>
                        <th>거래처원본</th>
                        <th class="text-end">공급가</th>
                        <th class="text-end">부가세</th>
                        <th class="text-end">합계</th>
                        <th>원본문자열</th>
                        <th>오류메시지</th>
                        <th>처리상태</th>
                    </tr>
                    </thead>
                    <tbody id="rawSeedRowsBody">
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">배치를 선택하면 원본 row가 표시됩니다.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Raw Payload</div>
                    <div class="card-body">
                        <pre class="mb-0 small text-break" id="rawPayloadPreview">Seed row를 선택하세요.</pre>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Parsed Payload / Error</div>
                    <div class="card-body">
                        <pre class="mb-3 small text-break" id="parsedPayloadPreview">Seed row를 선택하세요.</pre>
                        <div class="border-top pt-3">
                            <div class="fw-semibold small mb-2">오류 메시지</div>
                            <pre class="mb-0 small text-danger text-break" id="errorMessagePreview">-</pre>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

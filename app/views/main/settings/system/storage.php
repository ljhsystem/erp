<?php
require_once PROJECT_ROOT . '/core/Storage.php';

$dirs = [
    'PUBLIC_DIR' => PUBLIC_DIR,
    'PUBLIC_UPLOADS' => PUBLIC_UPLOADS,
    'STORAGE_ROOT' => STORAGE_ROOT,
    'STORAGE_UPLOADS' => STORAGE_UPLOADS,
    'LOGS_DIR' => LOGS_DIR,
    'DB_BACKUP' => STORAGE_DB_BACKUP,
];

$bucketMap = \Core\storage_bucket_map();

function check_dir_status(string $dir): array
{
    $exists = is_dir($dir);

    return [
        'exists' => $exists,
        'writable' => $exists ? is_writable($dir) : false,
        'realpath' => $exists ? realpath($dir) : null,
        'perms' => $exists ? substr(sprintf('%o', fileperms($dir)), -4) : null,
        'free_mb' => $exists ? floor(disk_free_space($dir) / 1024 / 1024) : null,
    ];
}
?>

<style>
    #storage-settings-wrapper .file-policy-table-wrap {
        overflow-x: auto;
    }

    #storage-settings-wrapper .file-policy-table {
        width: 100%;
        table-layout: fixed;
    }

    #storage-settings-wrapper .file-policy-table th,
    #storage-settings-wrapper .file-policy-table td {
        vertical-align: middle;
    }

    #storage-settings-wrapper .policy-col-name {
        width: 9%;
    }

    #storage-settings-wrapper .policy-col-bucket {
        width: 12%;
    }

    #storage-settings-wrapper .policy-col-ext {
        width: 11%;
    }

    #storage-settings-wrapper .policy-col-mime {
        width: 21%;
    }

    #storage-settings-wrapper .policy-col-description {
        width: 18%;
    }

    #storage-settings-wrapper .policy-col-size {
        width: 7%;
    }

    #storage-settings-wrapper .policy-col-status {
        width: 5%;
    }

    #storage-settings-wrapper .policy-col-actions {
        width: 17%;
    }

    #storage-settings-wrapper .policy-description-cell {
        line-height: 1.45;
        white-space: normal;
        word-break: keep-all;
    }

    #storage-settings-wrapper .policy-actions-cell {
        padding-left: 6px;
        padding-right: 6px;
        white-space: nowrap;
    }

    #storage-settings-wrapper .policy-action-buttons {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 4px;
        width: 100%;
    }

    #storage-settings-wrapper .policy-action-buttons .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 0;
        width: 100%;
        min-height: 34px;
        padding: 6px 3px;
        font-size: 12px;
        line-height: 1.15;
        text-align: center;
        white-space: nowrap;
    }
</style>

<div id="storage-settings-wrapper" class="storage-settings col-12 mx-auto">
    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-hdd-stack me-2"></i>파일저장소 관리
    </h4>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="fw-semibold text-dark mb-1">설정 영역</div>
                    <div class="text-muted small">
                        저장소 경로 상태와 업로드 정책을 확인하고 관리합니다.
                    </div>
                </div>
                <div>
                    <div class="fw-semibold text-dark mb-1">운영 도구 영역</div>
                    <div class="text-muted small">
                        관리자만 버킷 탐색을 사용할 수 있으며, 서버 실제 경로는 노출되지 않습니다.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <div class="fw-bold text-primary mb-2">설정</div>
        <div class="text-muted small">저장 위치와 정책 상태를 한 번에 점검할 수 있습니다.</div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold text-primary">
                    시스템 기본 경로 상태
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>구분</th>
                                <th>경로</th>
                                <th class="text-center">상태</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dirs as $name => $path):
                            $st = check_dir_status($path);
                        ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><code><?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td class="text-center">
                                    <?php if (!$st['exists']): ?>
                                        <span class="badge bg-danger">없음</span>
                                    <?php elseif (!$st['writable']): ?>
                                        <span class="badge bg-warning text-dark">쓰기 불가</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">정상</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold text-primary">
                    버킷 매핑 정보
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Bucket</th>
                                <th>매핑 경로</th>
                                <th class="text-center">상태</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bucketMap as $bucket => $path):
                            $st = check_dir_status($path);
                        ?>
                            <tr>
                                <td><code><?= htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><code><?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td class="text-center">
                                    <?php if (!$st['exists']): ?>
                                        <span class="badge bg-danger">없음</span>
                                    <?php elseif (!$st['writable']): ?>
                                        <span class="badge bg-warning text-dark">쓰기 불가</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">정상</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-5">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <span class="fw-semibold text-primary">파일 업로드 정책</span>
                <span class="text-muted small ms-2" id="policy-count-label">총 0건</span>
            </div>
            <button class="btn btn-sm btn-primary" id="btn-add-policy" type="button">
                <i class="bi bi-plus-circle me-1"></i>정책 추가
            </button>
        </div>
        <div class="card-body p-0 file-policy-table-wrap">
            <table class="table table-bordered table-sm align-middle mb-0 file-policy-table">
                <colgroup>
                    <col class="policy-col-name">
                    <col class="policy-col-bucket">
                    <col class="policy-col-ext">
                    <col class="policy-col-mime">
                    <col class="policy-col-description">
                    <col class="policy-col-size">
                    <col class="policy-col-status">
                    <col class="policy-col-actions">
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th>정책명</th>
                        <th>Bucket</th>
                        <th>허용 확장자</th>
                        <th>MIME</th>
                        <th>설명</th>
                        <th class="text-center">최대 용량(MB)</th>
                        <th class="text-center">상태</th>
                        <th class="text-center">관리</th>
                    </tr>
                </thead>
                <tbody id="file-policy-table"></tbody>
            </table>
        </div>
    </div>

    <div class="mb-3">
        <div class="fw-bold text-primary mb-2">운영 도구</div>
        <div class="text-muted small">
            실제 버킷과 업로드 흐름을 점검하는 영역입니다. 버킷 탐색은 관리자만 사용할 수 있습니다.
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold text-primary">
            파일 업로드 테스트
        </div>
        <div class="card-body">
            <form id="upload-test-form" class="row g-3" enctype="multipart/form-data">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">직접 버킷 선택</label>
                    <div class="input-group">
                        <select name="bucket" class="form-select">
                            <option value="">선택</option>
                            <?php foreach ($bucketMap as $bucket => $_): ?>
                                <option value="<?= htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="btn-open-bucket"
                                disabled>
                            <i class="bi bi-folder2-open me-1"></i>버킷 보기
                        </button>
                    </div>
                    <small class="text-muted">
                        정책을 선택하지 않으면 지정한 버킷으로 직접 테스트합니다.
                    </small>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">업로드 정책</label>
                    <select name="policy_key" class="form-select">
                        <option value="">정책 미사용</option>
                    </select>
                    <small class="text-muted">
                        정책을 선택하면 해당 정책 규칙이 우선 적용됩니다.
                    </small>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">테스트 파일</label>
                    <input type="file" name="file" class="form-control" required>
                </div>

                <div class="col-12 text-end">
                    <button class="btn btn-primary fw-bold" id="btn-run-upload-test" type="submit">
                        <i class="bi bi-upload me-1"></i>테스트 업로드
                    </button>
                </div>
            </form>

            <div id="upload-test-result" class="mt-3"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="policyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="policyModalTitle">파일 업로드 정책</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="policy-form">
                <div class="modal-body row g-3">
                    <input type="hidden" name="id" id="policy-id">

                    <div class="col-md-6">
                        <label class="form-label">정책명</label>
                        <input type="text" name="policy_name" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">정책 키</label>
                        <input type="text" name="policy_key" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Bucket</label>
                        <select name="bucket" class="form-select" required>
                            <?php foreach ($bucketMap as $bucket => $_): ?>
                                <option value="<?= htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">허용 확장자</label>
                        <input type="text" name="allowed_ext" class="form-control" placeholder="jpg,png,pdf" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">허용 MIME</label>
                        <input type="text" name="allowed_mime" class="form-control" placeholder="image/jpeg,image/png,application/pdf">
                        <small class="text-muted">
                            비워두면 확장자 기준으로 처리합니다.
                        </small>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">설명</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="정책의 사용 목적을 적어주세요."></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">최대 용량 (MB)</label>
                        <input type="number" name="max_size_mb" class="form-control" value="10" min="1" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label d-block">상태</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="policy-is-active">
                            <label class="form-check-label" for="policy-is-active">활성</label>
                        </div>
                        <input type="hidden" name="is_active" value="1">
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">닫기</button>
                    <button class="btn btn-primary" type="submit">저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bucketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">버킷 파일 목록</h5>
                    <div class="small text-muted">
                        관리자 전용 점검 기능입니다. 서버 실제 경로는 노출하지 않습니다.
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <div class="px-3 py-2 border-bottom small text-muted">
                    선택한 버킷: <strong id="bucketModalTitle"></strong>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>이름</th>
                            <th class="text-center">유형</th>
                            <th class="text-end">크기</th>
                            <th class="text-center">수정일</th>
                        </tr>
                    </thead>
                    <tbody id="bucket-file-list"></tbody>
                </table>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="policyDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">정책 삭제</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                선택한 업로드 정책을 삭제하시겠습니까?
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">닫기</button>
                <button class="btn btn-danger" type="button" id="btn-confirm-policy-delete">삭제</button>
            </div>
        </div>
    </div>
</div>

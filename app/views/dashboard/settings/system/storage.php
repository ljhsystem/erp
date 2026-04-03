<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/system/storage.php'
// 설명: 시스템설정 → 파일 저장소 관리 (View 전용, 최종본)

require_once PROJECT_ROOT . '/core/Storage.php';

$dirs = [
    'PUBLIC_DIR'      => PUBLIC_DIR,
    'PUBLIC_UPLOADS'  => PUBLIC_UPLOADS,
    'STORAGE_ROOT'    => STORAGE_ROOT,
    'STORAGE_UPLOADS' => STORAGE_UPLOADS,
    'LOGS_DIR'        => LOGS_DIR,
    'DB_BACKUP'       => STORAGE_DB_BACKUP
];

$bucketMap = \Core\storage_bucket_map();

function check_dir_status(string $dir): array
{
    $exists = is_dir($dir);

    return [
        'exists'   => $exists,
        'writable' => $exists ? is_writable($dir) : false,
        'realpath' => $exists ? realpath($dir) : null,
        'perms'    => $exists ? substr(sprintf('%o', fileperms($dir)), -4) : null,
        'free_mb'  => $exists ? floor(disk_free_space($dir) / 1024 / 1024) : null,
    ];
}

?>

<div id="storage-settings-wrapper" class="storage-settings col-12 mx-auto">

    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-hdd-stack me-2"></i>파일 저장소 관리
    </h4>

    <div class="row g-4">

        <!-- ==================================================
             1. 시스템 기본 경로 상태 (읽기 전용)
        ================================================== -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold text-primary">
                    📁 시스템 기본 경로 상태
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
                                <td class="fw-semibold"><?= $name ?></td>
                                <td><code><?= $path ?></code></td>
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

        <!-- ==================================================
             2. Bucket → 실제 경로 매핑 (읽기 전용)
        ================================================== -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold text-primary">
                    🗂 Bucket → 저장 경로 매핑
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Bucket</th>
                                <th>실제 경로</th>
                                <th class="text-center">상태</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bucketMap as $bucket => $path):
                            $st = check_dir_status($path);
                        ?>
                            <tr>
                                <td><code><?= $bucket ?></code></td>
                                <td><code><?= $path ?></code></td>
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

        <!-- ==================================================
             3. 파일 업로드 정책 (실제 설정)
        ================================================== -->
        <div class="col-12">
            <div class="card">
            <div class="card-header fw-semibold text-primary d-flex justify-content-between align-items-center">
                <span>📜 파일 업로드 정책</span>

                <button class="btn btn-sm btn-primary" id="btn-add-policy">
                    <i class="bi bi-plus-circle me-1"></i> 정책 추가
                </button>
            </div>


                <div class="card-body p-0">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>정책</th>
                                <th>Bucket</th>
                                <th>허용 확장자</th>
                                <th>MIME</th>
                                <th>설명</th>
                                <th class="text-center">최대 용량(MB)</th>
                                <th class="text-center">상태</th>
                                <th class="text-center">관리</th>
                            </tr>
                        </thead>
                        <tbody id="file-policy-table">
                            <!-- JS 로드 -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================================================
             4. 업로드 테스트 (Storage / Policy 통합)
        ================================================== -->
       <!-- ==================================================
            업로드 테스트 (버킷 + 정책 결합형)
        ================================================== -->
        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold text-primary">
                    🧪 파일 업로드 테스트
                </div>

                <div class="card-body">
                    <form id="upload-test-form" class="row g-3" enctype="multipart/form-data">

                        <!-- Bucket -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                저장 Bucket
                            </label>

                            <div class="input-group">
                                <select name="bucket" class="form-select">
                                    <option value="">선택</option>
                                    <?php foreach ($bucketMap as $bucket => $_): ?>
                                        <option value="<?= $bucket ?>"><?= $bucket ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        id="btn-open-bucket"
                                        disabled
                                        title="선택된 버킷 폴더 열기">
                                    <i class="bi bi-folder2-open"></i>폴더열기
                                </button>
                            </div>

                            <small class="text-muted">
                                업로드 정책 미선택 시, 이 버킷으로 직접 저장 테스트를 수행합니다.
                            </small>
                        </div>



                        <!-- 정책 -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">업로드 정책</label>
                            <select name="policy_key" class="form-select">
                                <option value="">정책 미사용 (버킷 직접 테스트)</option>
                                <!-- JS에서 정책 로드 -->
                            </select>
                            <small class="text-muted">
                                정책 선택 시 정책의 규칙이 우선 적용됩니다.
                            </small>
                        </div>

                        <!-- 파일 -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">파일</label>
                            <input type="file" name="file" class="form-control" required>
                        </div>

                        <!-- 버튼 -->
                        <div class="col-12 text-end">
                            <button class="btn btn-primary fw-bold">
                            <i class="bi bi-upload me-1"></i> 테스트 업로드
                            </button>
                        </div>
                    </form>

                    <div id="upload-test-result" class="mt-3"></div>
                </div>
            </div>
        </div>



    </div>
</div>

<!-- 파일 업로드 정책 추가 / 수정 모달 -->
<div class="modal fade" id="policyModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">파일 업로드 정책</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="policy-form">
                <div class="modal-body row g-3">

                    <input type="hidden" name="id" id="policy-id">

                    <div class="col-md-6">
                        <label class="form-label">정책 이름</label>
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
                                <option value="<?= $bucket ?>"><?= $bucket ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">허용 확장자</label>
                        <input type="text" name="allowed_ext" class="form-control"
                               placeholder="jpg,png,pdf">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">허용 MIME (선택)</label>
                        <input type="text" name="allowed_mime" class="form-control"
                            placeholder="image/jpeg,image/png,application/pdf">
                        <small class="text-muted">
                            비워두면 확장자 기준으로 자동 처리됩니다.
                        </small>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">설명</label>
                        <textarea name="description" class="form-control" rows="2"
                                placeholder="이 정책의 사용 목적을 적어주세요"></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">최대 용량 (MB)</label>
                        <input type="number" name="max_size_mb" class="form-control" value="10">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label d-block">상태</label>

                        <div class="form-check form-switch mt-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="policy-is-active"
                            >
                            <label class="form-check-label" for="policy-is-active">
                                활성
                            </label>
                        </div>

                        <!-- 서버 전송용 -->
                        <input type="hidden" name="is_active" value="1">
                    </div>



                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button class="btn btn-primary">저장</button>
                </div>
            </form>

        </div>
    </div>
</div>



<!-- 버킷 폴더 보기 모달 -->
<div class="modal fade" id="bucketModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    📂 <span id="bucketModalTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>이름</th>
                            <th class="text-center">유형</th>
                            <th class="text-end">크기</th>
                            <th class="text-center">수정일</th>
                        </tr>
                    </thead>
                    <tbody id="bucket-file-list">
                        <!-- JS -->
                    </tbody>
                </table>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

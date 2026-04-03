<?php
// 경로: PROJECT_ROOT . '/app/services/file/FileService.php'
// 설명: 파일 업로드, 삭제, 교체 등의 비즈니스 정책 서비스 계층
namespace App\Services\File;

use PDO;
use App\Models\System\SystemFileUploadPoliciesModel;
use function Core\storage_upload;
use function Core\storage_delete;
use function Core\storage_resolve_abs;
use function Core\storage_to_url;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;

class FileService
{
    private readonly PDO $pdo;
    private SystemFileUploadPoliciesModel $policyModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->logger = LoggerFactory::getLogger('service-file.FileService');
        $this->policyModel = new SystemFileUploadPoliciesModel($pdo);
        $this->logger->info("📦 FileService 초기화 완료"); 
    }

    /* --------------------------------------------------------
     * 공통: 로깅 + storage_upload 호출 래퍼
     * -------------------------------------------------------- */
    private function runUpload(
        array $file,
        string $bucket,
        array $extList,
        int $maxSize,
        array $mimeList
    ): array {
        $this->logger->info("📤 업로드 요청", [
            'bucket'     => $bucket,
            'orig_name'  => $file['name'] ?? null,
            'size'       => $file['size'] ?? 0,
            'ext_allow'  => $extList,
            'mime_allow' => $mimeList
        ]);

        $result = storage_upload($file, $bucket, $extList, $maxSize, $mimeList);

        if ($result['success'] ?? false) {
            $this->logger->info("✅ 업로드 성공", [
                'bucket'  => $bucket,
                'db_path' => $result['db_path'] ?? null,
                'file'    => $result['file'] ?? null,
                'size'    => $result['size'] ?? null,
                'mime'    => $result['mime'] ?? null
            ]);
        } else {
            $this->logger->warning("⚠ 업로드 실패", [
                'bucket'  => $bucket,
                'code'    => $result['code'] ?? '',
                'message' => $result['message'] ?? ''
            ]);
        }

        return $result;
    }


    /* --------------------------------------------------------
     * 1. 프로필 이미지 업로드
     * -------------------------------------------------------- */
    public function uploadProfile(array $file): array
    {
        return $this->runUpload(
            $file,
            'public://profile',
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            5 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
        );
    }


    /* --------------------------------------------------------
     * 2. 사업자등록증 업로드
     * -------------------------------------------------------- */
    public function uploadBusinessCert(array $file): array
    {
        return $this->runUpload(
            $file,
            'public://business_cert',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );
    }


    // 2-A. 자격증 파일 업로드 (개인정보이므로 private 저장)
    public function uploadCertificate(array $file): array
    {
        // 자격증은 공개되면 안되는 내부자료 → private bucket 사용
        $bucket   = 'private://certificate';
        $extList  = ['jpg', 'jpeg', 'png', 'pdf'];
        $mimeList = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize  = 10 * 1024 * 1024; // 10MB

        return $this->runUpload($file, $bucket, $extList, $maxSize, $mimeList);
    }



    /* --------------------------------------------------------
    * 2-B. 통장사본 업로드
    * -------------------------------------------------------- */
    public function uploadBankCopy(array $file): array
    {
        return $this->runUpload(
            $file,
            'private://bank_copy',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );
    }


    /* --------------------------------------------------------
     * 3. 일반 문서 업로드
     * -------------------------------------------------------- */
    public function uploadDocument(array $file): array
    {
        return $this->runUpload(
            $file,
            'public://documents',   // ← Storage bucket 명칭과 정확히 일치!
            ['jpg', 'jpeg', 'png', 'pdf', 'xls', 'xlsx'],
            10 * 1024 * 1024,
            [
                'image/jpeg',
                'image/png',
                'application/pdf',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]
        );
    }


    /* --------------------------------------------------------
    * 4. 신분증 / 사적 문서 업로드
    * -------------------------------------------------------- */
    public function uploadPrivateIdDoc(array $file): array
    {
        // 1) 실제 파일 업로드 수행
        $result = $this->runUpload(
            $file,
            'private://id_doc',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );

        // 2) 업로드 성공 후 DB 경로를 private:// 형식으로 변환
        if (!empty($result['success']) && isset($result['db_path'])) {

            // /storage/uploads/id_doc/ → private://id_doc/
            $result['db_path'] = str_replace(
                '/storage/uploads/id_doc/',
                'private://id_doc/',
                $result['db_path']
            );
        }

        return $result;
    }



    /* --------------------------------------------------------
     * 5. RAW / 내부자료 업로드
     * -------------------------------------------------------- */
    public function uploadRaw(array $file): array
    {
        return $this->runUpload(
            $file,
            'private://raw',
            ['jpg', 'jpeg', 'png', 'pdf', 'zip', 'csv', 'txt'],
            20 * 1024 * 1024,
            [
                'image/jpeg',
                'image/png',
                'application/pdf',
                'application/zip',
                'text/plain',
                'text/csv'
            ]
        );
    }


    /* --------------------------------------------------------
     * 6. 범용 업로드
     * -------------------------------------------------------- */
    public function upload(array $file, string $bucket, array $extList, int $size, array $mimeList = []): array
    {
        return $this->runUpload($file, $bucket, $extList, $size, $mimeList);
    }


    /* --------------------------------------------------------
     * 7. 파일 삭제
     * -------------------------------------------------------- */
    public function delete(?string $dbPath): bool
    {
        if (!$dbPath) {
            $this->logger->warning("⚠ 삭제 요청: dbPath 없음");
            return false;
        }

        $this->logger->info("🗑 삭제 요청", ['dbPath' => $dbPath]);

        $abs = storage_resolve_abs($dbPath);
        if (!$abs || !is_file($abs)) {
            $this->logger->warning("⚠ 삭제 실패: 파일 없음", ['dbPath' => $dbPath, 'abs' => $abs]);
            return false;
        }

        $success = @unlink($abs);

        $this->logger->info($success ? "🗑 삭제 성공" : "⚠ 삭제 실패", [
            'dbPath' => $dbPath,
            'abs'    => $abs
        ]);

        return $success;
    }


    /* --------------------------------------------------------
     * 8. DB 경로 → 절대경로
     * -------------------------------------------------------- */
    public function resolveAbsolute(string $dbPath): ?string
    {
        $abs = storage_resolve_abs($dbPath);

        $this->logger->info("📍 절대경로 변환", [
            'dbPath' => $dbPath,
            'abs'    => $abs
        ]);

        return $abs;
    }


    /* --------------------------------------------------------
     * 9. DB 경로 → URL
     * -------------------------------------------------------- */
    public function url(string $dbPath): ?string
    {
        $url = storage_to_url($dbPath);

        $this->logger->info("🌐 URL 변환", [
            'dbPath' => $dbPath,
            'url'    => $url
        ]);

        return $url;
    }


    /* --------------------------------------------------------
     * 10. 기존 파일 삭제 + 새 파일 업로드(교체)
     * -------------------------------------------------------- */
    public function replace(
        ?string $oldDbPath,
        array $newFile,
        string $bucket,
        array $extList,
        int $size,
        array $mimeList = []
    ): array {

        $this->logger->info("🔄 파일 교체 요청", [
            'old_file' => $oldDbPath,
            'bucket'   => $bucket
        ]);

        // 1) 업로드 시도
        $upload = $this->runUpload($newFile, $bucket, $extList, $size, $mimeList);

        if (empty($upload['success'])) {
            $this->logger->warning("⚠ 새 파일 업로드 실패 → 기존 파일 유지", [
                'old_file' => $oldDbPath,
                'error'    => $upload['message'] ?? ''
            ]);

            return [
                'success' => false,
                'code'    => $upload['code'] ?? 'upload_failed',
                'message' => $upload['message'] ?? '업로드 실패'
            ];
        }

        // 2) 기존 파일 삭제
        if ($oldDbPath) {
            $this->delete($oldDbPath);
        }

        // 3) 성공 리턴
        $result = [
            'success'  => true,
            'code'     => 'ok',
            'message'  => '파일 교체 완료',
            'db_path'  => $upload['db_path'],
            'abs_path' => $upload['abs'],
            'file'     => $upload['file'],
            'mime'     => $upload['mime'],
            'size'     => $upload['size'],
        ];

        $this->logger->info("🔄 교체 성공", $result);

        return $result;
    }

    /* --------------------------------------------------------
    * 브랜드 로고 업로드
    * -------------------------------------------------------- */
    public function uploadBrandLogo(array $file): array
    {
        return $this->runUpload(
            $file,
            'public://brand',
            ['jpg', 'jpeg', 'png', 'svg', 'ico', 'webp'],
            5 * 1024 * 1024,
            [
                'image/jpeg',
                'image/png',
                'image/svg+xml',
                'image/x-icon',
                'image/vnd.microsoft.icon',
                'image/webp'
            ]
        );
    }

    /* --------------------------------------------------------
    * 11. 정책 키로 업로드 수행 (Service가 정책 책임짐)
    * -------------------------------------------------------- */
    public function uploadByPolicyKey(array $file, string $policyKey): array
    {
        // 1️⃣ DB 정책 조회
        $policy = $this->policyModel->findByKey($policyKey);
    
        // 2️⃣ DB 정책 있으면 → 그걸 우선 사용
        if ($policy) {
            if ((int)$policy['is_active'] !== 1) {
                return [
                    'success' => false,
                    'message' => '비활성화된 업로드 정책입니다.'
                ];
            }
    
            return $this->runUpload(
                $file,
                $policy['bucket'],
                explode(',', $policy['allowed_ext']),
                (int)$policy['max_size_mb'] * 1024 * 1024,
                $policy['allowed_mime']
                    ? explode(',', $policy['allowed_mime'])
                    : []
            );
        }
    
        // 3️⃣ DB 정책 없으면 → 기존 하드코딩 fallback
        return match ($policyKey) {
            'profile_image'  => $this->uploadProfile($file),
            'business_cert'  => $this->uploadBusinessCert($file),
            'certificate'    => $this->uploadCertificate($file),
            'private_id_doc' => $this->uploadPrivateIdDoc($file),
            'document'       => $this->uploadDocument($file),
            default => [
                'success' => false,
                'message' => '업로드 정책이 정의되지 않았습니다.'
            ]
        };
    }
    
    
    
    /* ============================================================
     * 정책 목록 조회
     * ============================================================ */
    public function listPolicies(): array
    {
        return $this->policyModel->getAll();
    }
    

    /* ============================================================
     * 정책 저장 (신규 + 수정 통합)
     * ============================================================ */
    public function savePolicy(array $data): bool
    {
        // 수정
        if (!empty($data['id'])) {
            // UUID 그대로 사용 (string)
            return $this->policyModel->update($data['id'], $data);
        }
    
        // 신규 생성 → Service 책임으로 UUID 생성
        $data['id'] = UuidHelper::generate();
    
        return $this->policyModel->create($data);
    }
    

    /* ============================================================
    * 정책 수정 (전용)
    * ============================================================ */
    public function updatePolicy(array $data): bool
    {
        if (empty($data['id'])) {
            return false;
        }
    
        // UUID 그대로 전달
        return $this->policyModel->update($data['id'], $data);
    }
    

    /* ============================================================
     * 정책 삭제
     * ============================================================ */
    public function deletePolicy(string $id): bool
    {
        return $this->policyModel->delete($id);
    }
    
    
    /* ============================================================
    * 정책 활성/비활성 전용 (Model 메서드 그대로 사용)
    * ============================================================ */
    public function setPolicyActive(string $id, int $isActive, string $userId): bool
    {
        return $this->policyModel->setActive($id, (bool)$isActive, $userId);
    }
    
    


}

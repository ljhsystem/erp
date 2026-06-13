<?php
namespace App\Services\File;

use PDO;
use App\Models\System\FileUploadPoliciesModel;
use function Core\storage_upload;
use function Core\storage_delete;
use function Core\storage_resolve_abs;
use function Core\storage_to_url;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;

class FileService
{
    private readonly PDO $pdo;
    private FileUploadPoliciesModel $policyModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->logger = LoggerFactory::getLogger('service-file.FileService');
        $this->policyModel = new FileUploadPoliciesModel($pdo);
        $this->logger->info("📦 FileService 초기화 완료");
    }

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

    public function uploadCertificate(array $file): array
    {

        $bucket   = 'private://certificate';
        $extList  = ['jpg', 'jpeg', 'png', 'pdf'];
        $mimeList = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize  = 10 * 1024 * 1024; // 10MB

        return $this->runUpload($file, $bucket, $extList, $maxSize, $mimeList);
    }

    public function uploadBankCopy(array $file): array
    {
        return $this->runUpload(
            $file,
            'private://bank_file',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );
    }

    public function uploadCardCopy(array $file): array
    {
        return $this->runUpload(
            $file,
            'private://card_file',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );
    }

    public function uploadDocument(array $file): array
    {
        return $this->runUpload(
            $file,
            'public://documents',
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

    public function uploadPrivateIdDoc(array $file): array
    {

        $result = $this->runUpload(
            $file,
            'private://id_doc',
            ['jpg', 'jpeg', 'png', 'pdf'],
            10 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'application/pdf']
        );

        if (!empty($result['success']) && isset($result['db_path'])) {

            $result['db_path'] = str_replace(
                '/storage/uploads/id_doc/',
                'private://id_doc/',
                $result['db_path']
            );
        }

        return $result;
    }

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

    public function upload(array $file, string $bucket, array $extList, int $size, array $mimeList = []): array
    {
        return $this->runUpload($file, $bucket, $extList, $size, $mimeList);
    }

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

    public function resolveAbsolute(string $dbPath): ?string
    {
        $abs = storage_resolve_abs($dbPath);

        $this->logger->info("📍 절대경로 변환", [
            'dbPath' => $dbPath,
            'abs'    => $abs
        ]);

        return $abs;
    }

    public function url(string $dbPath): ?string
    {
        $url = storage_to_url($dbPath);

        $this->logger->info("🌐 URL 변환", [
            'dbPath' => $dbPath,
            'url'    => $url
        ]);

        return $url;
    }

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

        if ($oldDbPath) {
            $this->delete($oldDbPath);
        }

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

    public function uploadByPolicyKey(array $file, string $policyKey): array
    {

        $policy = $this->policyModel->findByKey($policyKey);

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

    public function listPolicies(): array
    {
        return $this->policyModel->getAll();
    }

    public function savePolicy(array $data): bool
    {

        if (!empty($data['id'])) {
            $data['updated_by'] = ActorHelper::user();
            return $this->policyModel->update($data['id'], $data);
        }

        $data['id'] = UuidHelper::generate();
        $data['created_by'] = ActorHelper::user();

        return $this->policyModel->create($data);
    }

    public function updatePolicy(array $data): bool
    {
        if (empty($data['id'])) {
            return false;
        }

        $data['updated_by'] = ActorHelper::user();
        return $this->policyModel->update($data['id'], $data);
    }

    public function deletePolicy(string $id): bool
    {
        return $this->policyModel->delete($id);
    }

    public function setPolicyActive(string $id, int $isActive): bool
    {
        return $this->policyModel->setActive($id, (bool)$isActive, ActorHelper::user());
    }
}

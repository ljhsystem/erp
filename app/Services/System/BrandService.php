<?php
// 경로: PROJECT_ROOT . '/app/Services/System/BrandService.php'
namespace App\Services\System;

use PDO;
use App\Models\System\BrandModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use function Core\storage_to_url;

class BrandService
{
    private readonly PDO $pdo;
    private $model;
    private $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model       = new BrandModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->logger      = LoggerFactory::getLogger('service-system.BrandService');
    }


    /* =========================================================
     * 5. 모든 자산 타입 조회
     * ========================================================= */
    public function getList(): array
    {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Fetching all brand assets');

        $rows = $this->model->getList();

        // URL 변환 및 데이터 가공
        return array_map(function ($row) {
            return [
                'id'         => $row['id'],
                'asset_type' => $row['asset_type'],
                'file_name'  => $row['file_name'],
                'db_path'    => $row['db_path'],
                'url'        => storage_to_url($row['db_path']),
                'is_active'  => (bool)$row['is_active'],
                'created_at' => $row['created_at'],
                'created_by' => $row['created_by'],
            ];
        }, $rows);
    }

    /* =========================================================
     * 8. 파일 ID로 활성화된 파일 정보 조회
     * ========================================================= */
    public function getById(string $fileId): ?array
    {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Fetching active file with ID: ' . $fileId);

        $file = $this->model->getById($fileId);

        if (!$file) {
            return null;
        }

        return [
            'id'         => $file['id'],
            'asset_type' => $file['asset_type'],
            'db_path'    => $file['db_path'],
            'url'        => storage_to_url($file['db_path']),
        ];
    }

    /* =========================================================
     * 4. 특정 타입 전체 목록 조회 (관리용)
     * ========================================================= */
    public function getByType(string $assetType): array
    {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Fetching all assets for type: ' . $assetType);

        $rows = $this->model->getList(['asset_type' => $assetType]);

        // URL 변환 및 데이터 가공
        return array_map(function ($row) {
            return [
                'id'         => $row['id'],
                'asset_type' => $row['asset_type'],
                'file_name'  => $row['file_name'],
                'db_path'    => $row['db_path'],
                'url'        => storage_to_url($row['db_path']),
                'is_active'  => (bool)$row['is_active'],
                'created_at' => $row['created_at'],
                'created_by' => $row['created_by'],
            ];
        }, $rows);
    }

    /* =========================================================
     * 2. 브랜드 자산 업로드 (신규 + 기존 비활성화)
     * ========================================================= */
    public function save(
        string $assetType,
        array $file,
        string $userId
    ): array {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Starting asset upload for type: ' . $assetType);

        // 자산 타입별 업로드 정책
        [$bucket, $extList, $maxSize, $mimeList] = $this->getUploadPolicy($assetType);

        $this->pdo->beginTransaction();

        try {
            // 1) 파일 업로드
            $upload = $this->fileService->upload(
                $file,
                $bucket,
                $extList,
                $maxSize,
                $mimeList
            );

            if (empty($upload['success'])) {
                $this->logger->error("[ERROR] File upload failed: " . $upload['message']);
                throw new \RuntimeException($upload['message'] ?? '파일 업로드 실패');
            }

            // 2) 기존 자산 비활성화 (삭제하지 않음)
            $this->model->deactivateByType($assetType, $userId);

            // 3) 신규 자산 등록
            $this->model->create([
                'id'         => UuidHelper::generate(),
                'asset_type' => $assetType,
                'db_path'    => $upload['db_path'],
                'file_name'  => $upload['file'],
                'mime_type'  => $upload['mime'],
                'is_active'  => 1,
                'created_by' => $userId,
            ]);

            $this->pdo->commit();

            $this->logger->info("[INFO] Brand asset uploaded successfully: {$assetType}");

            return [
                'success' => true,
                'message' => '브랜드 자산이 저장되었습니다.',
                'data'    => [
                    'asset_type' => $assetType,
                    'db_path'    => $upload['db_path'],
                ]
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error("[ERROR] Failed to upload brand asset: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    /* =========================================================
     * 7. 특정 파일 삭제
     * ========================================================= */
    public function delete(string $fileId): array
    {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Deleting file with ID: ' . $fileId);

        $this->pdo->beginTransaction();

        try {
            // 1) 삭제할 파일 조회
            $file = $this->model->getById($fileId);

            if (!$file) {
                throw new \RuntimeException('파일을 찾을 수 없습니다.');
            }

            // 2) 파일 삭제 (스토리지에서)
            $this->fileService->delete($file['db_path']);

            // 3) DB에서 파일 정보 삭제
            $this->model->deleteById($fileId);

            $this->pdo->commit();

            $this->logger->info("[INFO] File deleted successfully: {$fileId}");

            return [
                'success' => true,
                'message' => '파일이 삭제되었습니다.'
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error("[ERROR] Failed to delete file: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }




    /* =========================================================
     * 1. 활성 브랜드 자산 조회 (타입별)
     * ========================================================= */
    public function getActive(string $assetType): ?array
    {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Fetching active asset for type: ' . $assetType);

        $row = $this->model->getByType($assetType);
    
        if (!$row) {
            $this->logger->error("[ERROR] No active asset found for type: {$assetType}");
            return null;
        }

        $url = storage_to_url($row['db_path']);
        $this->logger->debug("[INFO] getActive URL for {$assetType}: " . $url);

        return [
            'id'         => $row['id'],
            'asset_type' => $row['asset_type'],
            'db_path'    => $row['db_path'],
            'url'        => $url,
        ];
    }


    /* =========================================================
     * 6. 특정 파일 활성화
     * ========================================================= */
    public function activate(string $fileId, string $userId): array
    {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Activating file with ID: ' . $fileId);

        $this->pdo->beginTransaction();

        try {
            // 1) 활성화할 파일 조회
            $file = $this->model->getById($fileId);
            if (!$file) {
                throw new \RuntimeException('파일을 찾을 수 없습니다.');
            }

            // 2) 기존 활성화된 파일 비활성화
            $this->model->deactivateByType($file['asset_type'], $userId);

            // 3) 해당 파일 활성화
            $stmt = $this->pdo->prepare("
                UPDATE system_brand_assets
                SET is_active = 1, updated_at = NOW(), updated_by = :updated_by
                WHERE id = :id
            ");
            $stmt->execute([
                ':id'         => $fileId,
                ':updated_by' => $userId,
            ]);

            $this->pdo->commit();

            $this->logger->info("[INFO] File activated successfully: {$fileId}");

            return [
                'success' => true,
                'message' => '파일이 활성화되었습니다.'
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error("[ERROR] Failed to activate file: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    
    public function getActiveAssets(): array
    {
        // main_logo와 favicon을 동시에 가져오기
        $mainLogo = $this->getActive('main_logo');
        $favicon = $this->getActive('favicon');

        return [
            'main_logo_url' => $mainLogo['url'] ?? null,
            'favicon_url'   => $favicon['url'] ?? null,
        ];
    }




    /* =========================================================
     * 3. 자산 타입별 업로드 정책
     * ========================================================= */
    private function getUploadPolicy(string $assetType): array
    {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Fetching upload policy for asset type: ' . $assetType);

        switch ($assetType) {
            case 'main_logo':
                return [
                    'public://brand',
                    ['png', 'jpg', 'jpeg', 'webp', 'svg'],
                    2 * 1024 * 1024,
                    ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']
                ];

            case 'print_logo':
                return [
                    'public://brand',
                    ['png', 'jpg', 'jpeg', 'svg', 'pdf'],
                    5 * 1024 * 1024,
                    ['image/png', 'image/jpeg', 'image/svg+xml', 'application/pdf']
                ];

            case 'favicon':
                return [
                    'public://brand',
                    ['png', 'ico'],
                    512 * 1024,
                    ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon']
                ];

            default:
                $this->logger->error("[ERROR] Unknown asset type: {$assetType}");
                throw new \InvalidArgumentException('알 수 없는 브랜드 자산 타입');
        }
    }








}



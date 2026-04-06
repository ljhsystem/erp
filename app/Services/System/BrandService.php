<?php
// 경로: PROJECT_ROOT . '/app/Services/System/BrandService.php'
namespace App\Services\System;

use PDO;
use App\Models\System\BrandModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\ActorHelper;
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
    public function getList(array $filters = []): array
    {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Fetching brand assets', [
            'filters' => $filters
        ]);
    
        $rows = $this->model->getList($filters);
    
        return array_map(function ($row) {

            return [
                'id'         => $row['id'],
                'asset_type' => $row['asset_type'],
                'file_name'  => $row['file_name'],
                'db_path'    => $row['db_path'],
                'url'        => storage_to_url($row['db_path']),
                'is_active'  => (bool)$row['is_active'],
                'created_at' => $row['created_at'],
        
                // 🔥 반드시 이걸로
                'created_by' => $row['created_by_name'] ?? $row['created_by'],
        
                // 🔥 추가
                'updated_by' => $row['updated_by_name'] ?? $row['updated_by'] ?? null,
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
     * 활성 브랜드 자산 조회 (타입별)
     * ========================================================= */
    public function getActive(string $assetType): ?array
    {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Fetching active asset for type: ' . $assetType);

        $row = $this->model->getActiveByType($assetType);
    
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
     * 브랜드 자산 업로드 (신규 + 기존 비활성화)
     * ========================================================= */
    public function save(
        string $assetType,
        array $file
    ): array {
        $this->logger->debug('[SYSTEM_BRAND_SERVICE] Starting asset upload for type: ' . $assetType);

        $this->pdo->beginTransaction();

        try {
            // 1) 파일 업로드
            $upload = $this->fileService->uploadByPolicyKey($file, 'brand_asset');

            if (empty($upload['success'])) {
                $this->logger->error("[ERROR] File upload failed: " . $upload['message']);
                throw new \RuntimeException($upload['message'] ?? '파일 업로드 실패');
            }
            $actor = ActorHelper::user();

            // 2) 기존 자산 비활성화 (삭제하지 않음)
            $this->model->deactivateByAssetType($assetType, $actor);

            // 3) 신규 자산 등록
            $this->model->create([
                'id'         => UuidHelper::generate(),
                'asset_type' => $assetType,
                'db_path'    => $upload['db_path'],
                'file_name'  => $upload['file'],
                'mime_type'  => $upload['mime'],
                'is_active'  => 1,
                'created_by' => $actor,
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
     * 특정 파일 삭제
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
     * 특정 파일 활성화
     * ========================================================= */
    public function activate(string $fileId): array
    {
        $this->pdo->beginTransaction();

        try {

            $file = $this->model->getById($fileId);

            if (!$file) {
                throw new \RuntimeException('파일을 찾을 수 없습니다.');
            }

            $actor = ActorHelper::user();

            $this->model->deactivateByAssetType($file['asset_type'], $actor);
            $this->model->updateStatusById($fileId, 1, $actor);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '파일이 활성화되었습니다.'
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    
    public function deactivate(string $fileId): array
    {
        $this->pdo->beginTransaction();
    
        try {
    
            $actor = ActorHelper::user();
    
            $this->model->updateStatusById($fileId, 0, $actor);
    
            $this->pdo->commit();
    
            return [
                'success' => true,
                'message' => '비활성화 완료'
            ];
    
        } catch (\Throwable $e) {
    
            $this->pdo->rollBack();
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }






}



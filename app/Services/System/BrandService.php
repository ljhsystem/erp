<?php

namespace App\Services\System;

use App\Models\System\BrandModel;
use App\Services\File\FileService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use function Core\storage_to_url;

class BrandService
{
    private const ALLOWED_ASSET_TYPES = ['main_logo', 'print_logo', 'favicon'];

    private readonly PDO $pdo;
    private BrandModel $model;
    private FileService $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new BrandModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.BrandService');
    }

    public function getList(array $filters = []): array
    {
        $rows = $this->model->getList($filters);

        return array_map(function (array $row): array {
            return [
                'id' => $row['id'],
                'asset_type' => $row['asset_type'],
                'asset_type_label' => $this->labelForAssetType((string) $row['asset_type']),
                'file_name' => $row['file_name'],
                'db_path' => $row['db_path'],
                'url' => storage_to_url($row['db_path']),
                'is_active' => (bool) $row['is_active'],
                'created_at' => $row['created_at'],
                'created_by' => $row['created_by_name'] ?? $row['created_by'],
                'updated_by' => $row['updated_by_name'] ?? ($row['updated_by'] ?? null),
            ];
        }, $rows);
    }

    public function getById(string $fileId): ?array
    {
        $file = $this->model->getById($fileId);
        if (!$file) {
            return null;
        }

        return [
            'id' => $file['id'],
            'asset_type' => $file['asset_type'],
            'asset_type_label' => $this->labelForAssetType((string) $file['asset_type']),
            'db_path' => $file['db_path'],
            'url' => storage_to_url($file['db_path']),
        ];
    }

    public function getActive(string $assetType): ?array
    {
        $this->assertValidAssetType($assetType);

        $row = $this->model->getActiveByType($assetType);
        if (!$row) {
            return null;
        }

        return [
            'id' => $row['id'],
            'asset_type' => $row['asset_type'],
            'asset_type_label' => $this->labelForAssetType((string) $row['asset_type']),
            'db_path' => $row['db_path'],
            'url' => storage_to_url($row['db_path']),
        ];
    }

    public function save(string $assetType, array $file): array
    {
        $this->pdo->beginTransaction();

        try {
            $this->assertValidAssetType($assetType);
            $this->assertValidUpload($assetType, $file);

            $upload = $this->fileService->uploadByPolicyKey($file, 'brand_asset');
            if (empty($upload['success'])) {
                throw new \RuntimeException($upload['message'] ?? '브랜드 파일 업로드에 실패했습니다.');
            }

            $actor = ActorHelper::user();
            $this->model->deactivateByAssetType($assetType, $actor);

            $this->model->create([
                'id' => UuidHelper::generate(),
                'asset_type' => $assetType,
                'db_path' => $upload['db_path'],
                'file_name' => $upload['file'],
                'mime_type' => $upload['mime'],
                'is_active' => 1,
                'created_by' => $actor,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '브랜드 자산을 저장했습니다.',
                'data' => [
                    'asset_type' => $assetType,
                    'asset_type_label' => $this->labelForAssetType($assetType),
                    'db_path' => $upload['db_path'],
                ],
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('[BRAND] save failed', ['error' => $e->getMessage(), 'asset_type' => $assetType]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function purge(string $fileId): array
    {
        $this->pdo->beginTransaction();

        try {
            $file = $this->model->getById($fileId);
            if (!$file) {
                throw new \RuntimeException('삭제할 브랜드 파일을 찾을 수 없습니다.');
            }

            $this->fileService->delete($file['db_path']);
            $this->model->hardDeleteById($fileId);

            if ((int) ($file['is_active'] ?? 0) === 1) {
                $fallback = $this->model->getLatestByType((string) $file['asset_type'], $fileId);
                if ($fallback) {
                    $this->model->updateStatusById($fallback['id'], 1, ActorHelper::user());
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '브랜드 파일을 삭제했습니다.',
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('[BRAND] purge failed', ['error' => $e->getMessage(), 'file_id' => $fileId]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function activate(string $fileId): array
    {
        $this->pdo->beginTransaction();

        try {
            $file = $this->model->getById($fileId);
            if (!$file) {
                throw new \RuntimeException('활성화할 브랜드 파일을 찾을 수 없습니다.');
            }

            $actor = ActorHelper::user();
            $this->model->deactivateByAssetType((string) $file['asset_type'], $actor);
            $this->model->updateStatusById($fileId, 1, $actor);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '기본 브랜드 파일로 적용했습니다.',
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function deactivate(string $fileId): array
    {
        $this->pdo->beginTransaction();

        try {
            $this->model->updateStatusById($fileId, 0, ActorHelper::user());
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '브랜드 파일을 비활성화했습니다.',
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function assertValidAssetType(string $assetType): void
    {
        if (!in_array($assetType, self::ALLOWED_ASSET_TYPES, true)) {
            throw new \RuntimeException('허용되지 않은 브랜드 자산 타입입니다.');
        }
    }

    private function assertValidUpload(string $assetType, array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('업로드 파일을 확인해주세요.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('유효한 업로드 파일이 아닙니다.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new \RuntimeException('이미지 크기는 5MB 이하만 업로드할 수 있습니다.');
        }

        $imageInfo = @getimagesize($tmpName);
        if (!$imageInfo) {
            throw new \RuntimeException('이미지 파일만 업로드할 수 있습니다.');
        }

        $mimeType = (string) ($imageInfo['mime'] ?? '');
        $allowedMimes = $assetType === 'favicon'
            ? ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml']
            : ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];

        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \RuntimeException('허용되지 않은 이미지 형식입니다.');
        }
    }

    private function labelForAssetType(string $assetType): string
    {
        return match ($assetType) {
            'main_logo' => '메인 로고',
            'print_logo' => '인쇄용 로고',
            'favicon' => '파비콘',
            default => $assetType,
        };
    }
}

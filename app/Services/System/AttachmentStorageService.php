<?php

namespace App\Services\System;

use App\Models\System\FileUploadPoliciesModel;

final class AttachmentStorageService
{
    public function __construct(private ?FileUploadPoliciesModel $policyModel = null)
    {
        $this->policyModel ??= new FileUploadPoliciesModel();
    }

    public function stage(array $file, string $policyKey): array
    {
        $policy = $this->policyModel->findByKey($policyKey);
        if (!$policy || (int) ($policy['is_active'] ?? 0) !== 1) {
            throw new \RuntimeException('활성화된 파일 업로드 정책을 확인할 수 없습니다.');
        }
        if ((string) ($policy['bucket'] ?? '') === 'private://attachment'
            && trim((string) getenv('ATTACHMENT_PRIVATE_STORAGE_ROOT')) === '') {
            throw new \RuntimeException('Attachment 비공개 저장경로가 설정되지 않아 업로드할 수 없습니다.');
        }
        $allowedExtensions = $this->csv((string) ($policy['allowed_ext'] ?? ''));
        $allowedMime = $this->csv((string) ($policy['allowed_mime'] ?? ''));
        $maxBytes = max(1, (int) ($policy['max_size_mb'] ?? 0)) * 1024 * 1024;
        $result = \Core\storage_upload(
            $file,
            (string) $policy['bucket'],
            $allowedExtensions,
            $maxBytes,
            $allowedMime
        );
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException('첨부파일 업로드 중 오류가 발생했습니다.');
        }
        $absolutePath = (string) ($result['abs'] ?? '');
        $objectKey = (string) ($result['db_path'] ?? '');
        $hash = $absolutePath !== '' && is_file($absolutePath) ? hash_file('sha256', $absolutePath) : false;
        if (!is_string($hash) || !preg_match('/^[0-9a-f]{64}$/', $hash)) {
            if ($objectKey !== '') \Core\storage_delete($objectKey);
            throw new \RuntimeException('첨부파일 무결성 확인 중 오류가 발생했습니다.');
        }
        return [
            'original_file_name' => $this->normalizeOriginalName((string) ($file['name'] ?? '')),
            'mime_type' => (string) ($result['mime'] ?? ''),
            'file_size' => (int) ($result['size'] ?? 0),
            'sha256_hash' => $hash,
            'storage_object_key' => $objectKey,
        ];
    }

    public function persistWithCompensation(array $file, string $policyKey, callable $persist): mixed
    {
        $staged = $this->stage($file, $policyKey);
        try {
            return $persist($staged);
        } catch (\Throwable $exception) {
            $this->discard((string) $staged['storage_object_key']);
            throw $exception;
        }
    }

    public function resolvePrivateDownload(string $objectKey): string
    {
        if (!str_starts_with($objectKey, 'private://')) {
            throw new \InvalidArgumentException('비공개 Attachment 객체키가 아닙니다.');
        }
        $absolutePath = \Core\storage_resolve_abs($objectKey);
        if ($absolutePath === null) throw new \RuntimeException('첨부파일 원본을 확인할 수 없습니다.');
        return $absolutePath;
    }

    public function discard(string $objectKey): void
    {
        if ($objectKey !== '' && str_starts_with($objectKey, 'private://')) {
            \Core\storage_delete($objectKey);
        }
    }

    private function csv(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn(string $item): string => strtolower(trim($item)),
            explode(',', $value)
        )));
    }

    private function normalizeOriginalName(string $name): string
    {
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', '', basename(str_replace('\\', '/', $name))) ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') throw new \InvalidArgumentException('첨부파일명을 확인해 주세요.');
        return mb_substr($normalized, 0, 255);
    }
}

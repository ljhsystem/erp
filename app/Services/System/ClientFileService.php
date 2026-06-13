<?php

namespace App\Services\System;

use App\Services\File\FileService;
use Core\LoggerFactory;
use Throwable;

class ClientFileService
{
    private FileService $fileService;
    private $logger;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
        $this->logger = LoggerFactory::getLogger('service-system.ClientFileService');
    }

    public function saveWithFiles(
        array $input,
        array $files,
        string $actorType,
        callable $normalizePayload,
        callable $validatePayload,
        callable $mapSaveErrorMessage,
        callable $save
    ): array {
        try {
            $payload = $normalizePayload($input);
            $validated = $validatePayload($payload);

            if (!($validated['success'] ?? false)) {
                return $validated;
            }

            $result = $save($payload, $actorType, $files);
            if (!($result['success'] ?? false)) {
                $result['message'] = $mapSaveErrorMessage((string) ($result['message'] ?? ''));
            }

            return $result;
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $mapSaveErrorMessage($e->getMessage()),
            ];
        }
    }

    public function processFileChanges(array $data, array $files, array $before): array
    {
        $newBusinessPath = null;
        $newRrnPath = null;
        $newBankPath = null;

        $deleteBusiness = !empty($data['delete_business_certificate']);
        $deleteRrn = !empty($data['delete_rrn_image']);
        $deleteBank = !empty($data['delete_bank_file']);

        if ($deleteBusiness && empty($files['business_certificate']['tmp_name'])) {
            if (!empty($before['business_certificate'])) {
                $this->fileService->delete($before['business_certificate']);
            }
            $data['business_certificate'] = null;
        }

        if ($deleteBank && empty($files['bank_file']['tmp_name'])) {
            if (!empty($before['bank_file'])) {
                $this->fileService->delete($before['bank_file']);
            }
            $data['bank_file'] = null;
        }

        if ($deleteRrn && empty($files['rrn_image']['tmp_name'])) {
            if (!empty($before['rrn_image'])) {
                $this->fileService->delete($before['rrn_image']);
            }
            $data['rrn_image'] = null;
        }

        if (
            isset($files['business_certificate']['error']) &&
            $files['business_certificate']['error'] !== UPLOAD_ERR_NO_FILE &&
            $files['business_certificate']['error'] !== UPLOAD_ERR_OK
        ) {
            throw new \Exception($this->resolveUploadErrorMessage(
                $files['business_certificate']['error'],
                '사업자등록증'
            ));
        }

        if (
            isset($files['rrn_image']['error']) &&
            $files['rrn_image']['error'] !== UPLOAD_ERR_NO_FILE &&
            $files['rrn_image']['error'] !== UPLOAD_ERR_OK
        ) {
            throw new \Exception($this->resolveUploadErrorMessage(
                $files['rrn_image']['error'],
                '주민등록증'
            ));
        }

        if (
            isset($files['bank_file']['error']) &&
            $files['bank_file']['error'] !== UPLOAD_ERR_NO_FILE &&
            $files['bank_file']['error'] !== UPLOAD_ERR_OK
        ) {
            throw new \Exception($this->resolveUploadErrorMessage(
                $files['bank_file']['error'],
                '통장사본'
            ));
        }

        if (!empty($files['business_certificate']['tmp_name'])) {
            $oldPath = $before['business_certificate'] ?? null;
            $upload = $this->fileService->uploadBusinessCert($files['business_certificate']);

            if (empty($upload['success'])) {
                throw new \Exception($upload['message']);
            }

            $data['business_certificate'] = $upload['db_path'];
            $newBusinessPath = $upload['db_path'];

            if (!empty($oldPath)) {
                $this->fileService->delete($oldPath);
            }
        }

        if (!empty($files['rrn_image']['tmp_name'])) {
            $oldPath = $before['rrn_image'] ?? null;
            $upload = $this->fileService->uploadPrivateIdDoc($files['rrn_image']);

            if (empty($upload['success'])) {
                throw new \Exception($upload['message']);
            }

            $data['rrn_image'] = $upload['db_path'];
            $newRrnPath = $upload['db_path'];

            if (!empty($oldPath)) {
                $this->fileService->delete($oldPath);
            }
        }

        if (!empty($files['bank_file']['tmp_name'])) {
            $oldPath = $before['bank_file'] ?? null;
            $upload = $this->fileService->uploadBankCopy($files['bank_file']);

            if (empty($upload['success'])) {
                throw new \Exception($upload['message']);
            }

            $data['bank_file'] = $upload['db_path'];
            $newBankPath = $upload['db_path'];

            if (!empty($oldPath)) {
                $this->fileService->delete($oldPath);
            }
        }

        if (!array_key_exists('business_certificate', $data) && !$deleteBusiness) {
            $data['business_certificate'] = $before['business_certificate'] ?? null;
        }

        if (!array_key_exists('rrn_image', $data) && !$deleteRrn) {
            $data['rrn_image'] = $before['rrn_image'] ?? null;
        }

        if (!array_key_exists('bank_file', $data) && !$deleteBank) {
            $data['bank_file'] = $before['bank_file'] ?? null;
        }

        unset($data['delete_business_certificate']);
        unset($data['delete_bank_file']);
        unset($data['delete_rrn_image']);

        return [
            'data' => $data,
            'new_business_path' => $newBusinessPath,
            'new_rrn_path' => $newRrnPath,
            'new_bank_path' => $newBankPath,
        ];
    }

    public function rollbackUploadedFiles(?string $newBusinessPath, ?string $newRrnPath, ?string $newBankPath): void
    {
        if (!empty($newBusinessPath)) {
            $this->fileService->delete($newBusinessPath);
        }

        if (!empty($newRrnPath)) {
            $this->fileService->delete($newRrnPath);
        }

        if (!empty($newBankPath)) {
            $this->fileService->delete($newBankPath);
        }
    }

    public function resolveUploadErrorMessage(int $errorCode, string $label): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "{$label} 파일 크기가 업로드 허용 용량을 초과했습니다.",
            UPLOAD_ERR_PARTIAL => "{$label} 파일이 일부만 업로드되었습니다. 다시 시도해주세요.",
            UPLOAD_ERR_NO_TMP_DIR => "{$label} 업로드용 임시 폴더를 찾을 수 없습니다.",
            UPLOAD_ERR_CANT_WRITE => "{$label} 파일을 서버에 저장할 수 없습니다.",
            UPLOAD_ERR_EXTENSION => "{$label} 파일 업로드가 서버 확장 모듈에 의해 중단되었습니다.",
            default => "{$label} 업로드 중 오류가 발생했습니다.",
        };
    }
}

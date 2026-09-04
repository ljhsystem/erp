<?php

namespace App\Services\System;

use App\Models\System\UserSettingModel;
use App\Services\Concerns\LogsServiceOperations;
use App\Services\Auth\UserContextService;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PDO;

class UserSettingService
{
    use LogsServiceOperations;
    private UserSettingModel $model;
    private UserContextService $userContextService;
    private $logger;

    public function __construct(PDO $pdo, ?UserContextService $userContextService = null)
    {
        $this->model = new UserSettingModel($pdo);
        $this->userContextService = $userContextService ?? new UserContextService();
        $this->logger = LoggerFactory::getLogger('service-system.UserSettingService');
    }

    public function detail(string $pageKey, string $settingType): array
    {
        $normalizedPageKey = $this->normalizePageKey($pageKey);
        $normalizedSettingType = $this->normalizeSettingType($settingType);
        $userId = $this->currentUserId();

        $row = $this->model->findOne($normalizedPageKey, $normalizedSettingType, $userId, false);
        $settingsJson = $this->decodeSettingsJson($row['settings_json'] ?? null);

        return [
            'exists' => $row !== null,
            'page_key' => $normalizedPageKey,
            'setting_type' => $normalizedSettingType,
            'settings_json' => $settingsJson,
            'updated_at' => $row['updated_at'] ?? ($row['created_at'] ?? null),
        ];
    }

    public function save(string $pageKey, string $settingType, string $description, array $settingsJson): array
    {
        return $this->runLoggedOperation($this->logger,'사용자 화면설정','USER_SETTING_SAVE','save',['page_key'=>$pageKey,'setting_type'=>$settingType],fn():array=>$this->saveInternal($pageKey,$settingType,$description,$settingsJson));
    }

    private function saveInternal(string $pageKey, string $settingType, string $description, array $settingsJson): array
    {
        $normalizedPageKey = $this->normalizePageKey($pageKey);
        $normalizedSettingType = $this->normalizeSettingType($settingType);
        $normalizedDescription = $this->normalizeDescription($description);
        $userId = $this->currentUserId();
        $actor = ActorHelper::user();
        $parsedActor = ActorHelper::parse($actor);
        $actorId = trim((string) ($parsedActor['id'] ?? ''));
        if ($actorId === '') {
            $actorId = $userId;
        }

        $saved = $this->model->save([
            'page_key' => $normalizedPageKey,
            'setting_type' => $normalizedSettingType,
            'description' => $normalizedDescription,
            'settings_json' => json_encode($settingsJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'user_id' => $userId,
            'actor' => $actorId,
        ]);

        return [
            'page_key' => $normalizedPageKey,
            'setting_type' => $normalizedSettingType,
            'settings_json' => $this->decodeSettingsJson($saved['settings_json'] ?? null),
            'updated_at' => $saved['updated_at'] ?? ($saved['created_at'] ?? null),
        ];
    }

    public function delete(string $pageKey, string $settingType): array
    {
        return $this->runLoggedOperation($this->logger,'사용자 화면설정','USER_SETTING_DELETE','delete',['page_key'=>$pageKey,'setting_type'=>$settingType],fn():array=>$this->deleteInternal($pageKey,$settingType));
    }

    private function deleteInternal(string $pageKey, string $settingType): array
    {
        $normalizedPageKey = $this->normalizePageKey($pageKey);
        $normalizedSettingType = $this->normalizeSettingType($settingType);
        $userId = $this->currentUserId();

        $deleted = $this->model->deleteOne($normalizedPageKey, $normalizedSettingType, $userId);

        return [
            'page_key' => $normalizedPageKey,
            'setting_type' => $normalizedSettingType,
            'deleted' => $deleted,
        ];
    }

    private function normalizePageKey(string $pageKey): string
    {
        $normalized = trim($pageKey);
        if ($normalized === '') {
            throw new \InvalidArgumentException('page_key는 필수입니다.');
        }

        return $normalized;
    }

    private function normalizeSettingType(string $settingType): string
    {
        $normalized = strtoupper(trim($settingType));
        if (!in_array($normalized, ['TABLE', 'VIEW', 'EXCEL_UPLOAD', 'EXCEL_DOWNLOAD'], true)) {
            throw new \InvalidArgumentException('지원하지 않는 setting_type입니다.');
        }

        return $normalized;
    }

    private function normalizeDescription(string $description): string
    {
        return trim($description);
    }

    private function currentUserId(): string
    {
        $userId = trim((string) ($this->userContextService->currentUserId() ?? ''));
        if ($userId === '') {
            throw new \RuntimeException('현재 사용자를 확인할 수 없습니다.');
        }

        return $userId;
    }

    private function decodeSettingsJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) ($value ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }
}

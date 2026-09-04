<?php
namespace App\Services\System;

use PDO;
use App\Models\System\SettingConfigModel;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use Core\Database;

class SettingService
{
    private readonly PDO $pdo;
    private SettingConfigModel $model;
    private $logger;

    public function __construct(?PDO $pdo = null)
    {
        $pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->pdo         = $pdo;
        $this->model  = new SettingConfigModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.SettingService');

    }

    public function get(string $key, $default = null)
    {
        $value = $this->model->get($key, $default);

        if ($value === null) {
            return $default;
        }

        return $value;
    }


    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->model->get($key, null);

        return is_numeric($value) ? (int)$value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->model->get($key, null);

        if ($value === null) return $default;

        return in_array((string)$value, ['1', 'true', 'yes', 'on'], true);
    }

    public function getJson(string $key, array $default = []): array
    {
        $value = $this->model->get($key, null);

        if (!$value) return $default;

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    public function getByCategory(string $category): array
    {
        $this->logger->debug('[GET_BY_CATEGORY] START', [
            'category' => $category
        ]);

        $rows = $this->model->getByCategory($category);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['config_key']] = $row;
        }

        $this->logger->debug('[GET_BY_CATEGORY] RESULT', [
            'count' => count($result),
            'keys'  => array_keys($result)
        ]);

        return $result;
    }

    public function save(
        string $key,
        string $value,
        string $category,
        ?string $userId = null,
        ?string $description = null
    ): bool {
        $userId = $userId ?? ActorHelper::user();

        if (!$this->model->isEditable($key)) {
            $this->logger->warning('[SAVE_SINGLE] NOT EDITABLE', [
                'key' => $key
            ]);
            return false;
        }

        $payload = [
            'config_key'   => $key,
            'config_value' => $value,
            'category'     => $category,
            'description'  => $description,
            'user_id'      => $userId,
        ];

        $result = $this->model->set($payload);

        $this->logger->info('시스템 설정을 저장했습니다.', [
            'event_code' => 'SYSTEM_SETTING_SAVED',
            'result' => $result ? 'SUCCESS' : 'FAILED',
            'service' => self::class,
            'action' => 'setting.save',
            'actor' => $userId,
            'target_type' => 'SYSTEM_SETTING',
            'target_id' => $key,
        ]);

        return $result;
    }

    public function saveBatch(
        array $input,
        string $category,
        string|array|null $userId = null,
        array $descriptions = []
    ): array {
        if (is_array($userId) && $descriptions === []) {
            $descriptions = $userId;
            $userId = null;
        }

        $userId = $userId ?? ActorHelper::user();

        $saved   = [];
        $skipped = [];

        foreach ($input as $key => $value) {

            $exists = $this->model->exists($key);

            if ($exists && !$this->model->isEditable($key)) {
                $this->logger->warning('[SAVE_BATCH] SKIPPED (NOT EDITABLE)', [
                    'key' => $key
                ]);
                $skipped[] = $key;
                continue;
            }

            $payload = [
                'config_key'   => $key,
                'config_value' => is_array($value) ? json_encode($value) : (string)$value,
                'category'     => $category,
                'description'  => $descriptions[$key] ?? null,
                'is_editable'  => 1,
                'user_id'      => $userId,
            ];

            $this->model->set($payload);

            $saved[] = $key;
        }

        $this->logger->info('시스템 설정을 일괄 저장했습니다.', [
            'event_code' => 'SYSTEM_SETTING_BATCH_SAVED',
            'result' => 'SUCCESS',
            'service' => self::class,
            'action' => 'setting.save_batch',
            'actor' => $userId,
            'category' => $category,
            'saved'    => $saved,
            'skipped'  => $skipped
        ]);

        return [
            'success' => true,
            'saved'   => $saved,
            'skipped' => $skipped
        ];
    }


}

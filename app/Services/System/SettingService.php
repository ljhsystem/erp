<?php
// 경로: PROJECT_ROOT/app/Services/System/SettingService.php
namespace App\Services\System;

use PDO;
use App\Models\System\SettingConfigModel;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;

class SettingService
{
    private readonly PDO $pdo;
    private SettingConfigModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new SettingConfigModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.SettingService');

        $this->logger->debug('[INIT] SystemSettingService initialized');
    }

    /* =============================================================
     * 1. 기본 Getter
     * ============================================================= */
    public function get(string $key, $default = null)
    {
        $value = $this->model->get($key, $default);
    
        // 🔥 이 줄이 핵심
        if ($value === null) {
            return $default;
        }
    
        return $value;
    }
    

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->model->get($key, null);

        $this->logger->debug('[GET_INT]', [
            'key'   => $key,
            'value' => $value
        ]);

        return is_numeric($value) ? (int)$value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->model->get($key, null);

        $this->logger->debug('[GET_BOOL]', [
            'key'   => $key,
            'value' => $value
        ]);

        if ($value === null) return $default;

        return in_array((string)$value, ['1', 'true', 'yes', 'on'], true);
    }

    public function getJson(string $key, array $default = []): array
    {
        $value = $this->model->get($key, null);

        $this->logger->debug('[GET_JSON]', [
            'key'   => $key,
            'value' => $value
        ]);

        if (!$value) return $default;

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    /* =============================================================
     * 2. Category 기준 조회
     * ============================================================= */
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

    /* =============================================================
     * 3. 단일 설정 저장
     * ============================================================= */
    public function save(
        string $key,
        string $value,
        string $category,
        ?string $userId = null,
        ?string $description = null
    ): bool {
        $userId = $userId ?? ActorHelper::user();

        $this->logger->debug('[SAVE_SINGLE] START', compact(
            'key', 'value', 'category', 'userId', 'description'
        ));

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

        $this->logger->debug('[SAVE_SINGLE] PAYLOAD', $payload);

        $result = $this->model->set($payload);

        $this->logger->info('[SAVE_SINGLE] DONE', [
            'key'    => $key,
            'result' => $result
        ]);

        return $result;
    }

    /* =============================================================
     * 4. 다중 설정 일괄 저장 (🔥 핵심 디버깅 구간)
     * ============================================================= */
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
    
        $this->logger->debug('[SAVE_BATCH] START', [
            'category' => $category,
            'input'    => $input
        ]);
    
        $saved   = [];
        $skipped = [];
    
        foreach ($input as $key => $value) {
    
            $this->logger->debug('[SAVE_BATCH] ITEM', [
                'key'   => $key,
                'value' => $value
            ]);
    
            // 🔹 1) 존재 여부 확인
            $exists = $this->model->exists($key);
    
            // 🔹 2) 존재하면 수정 가능 여부 체크
            if ($exists && !$this->model->isEditable($key)) {
                $this->logger->warning('[SAVE_BATCH] SKIPPED (NOT EDITABLE)', [
                    'key' => $key
                ]);
                $skipped[] = $key;
                continue;
            }
    
            // 🔹 3) 없으면 자동 생성 (is_editable = 1 기본)
            $payload = [
                'config_key'   => $key,
                'config_value' => is_array($value) ? json_encode($value) : (string)$value,
                'category'     => $category,
                'description'  => $descriptions[$key] ?? null,
                'is_editable'  => 1,
                'user_id'      => $userId, // nullable OK
            ];
    
            $this->logger->debug('[SAVE_BATCH] MODEL::SET PAYLOAD', $payload);
    
            $this->model->set($payload);
    
            $saved[] = $key;
        }
    
        $this->logger->info('[SAVE_BATCH] COMPLETE', [
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

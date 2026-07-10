<?php

namespace App\Models\System;

use Core\Database;
use Core\Helpers\UuidHelper;
use PDO;

class UserSettingModel
{
    private const TABLE = 'system_user_settings';

    private PDO $db;
    private array $columnExistsCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function findOne(string $pageKey, string $settingType, ?string $userId = null, bool $includeDeleted = false): ?array
    {
        $this->assertRequiredColumns();

        $conditions = [
            'page_key = :page_key',
            'setting_type = :setting_type',
        ];
        $params = [
            ':page_key' => $pageKey,
            ':setting_type' => $settingType,
        ];

        if ($this->columnExists('user_id') && $userId !== null && $userId !== '') {
            $conditions[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        if (!$includeDeleted && $this->columnExists('deleted_at')) {
            $conditions[] = 'deleted_at IS NULL';
        }

        $orderBy = $this->columnExists('updated_at')
            ? 'updated_at DESC'
            : ($this->columnExists('created_at') ? 'created_at DESC' : 'page_key ASC');

        $stmt = $this->db->prepare(
            'SELECT * FROM ' . self::TABLE
            . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY ' . $orderBy
            . ' LIMIT 1'
        );
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $payload): array
    {
        $this->assertRequiredColumns();

        $pageKey = trim((string) ($payload['page_key'] ?? ''));
        $settingType = strtoupper(trim((string) ($payload['setting_type'] ?? '')));
        $description = trim((string) ($payload['description'] ?? ''));
        $settingsJson = (string) ($payload['settings_json'] ?? '{}');
        $userId = trim((string) ($payload['user_id'] ?? ''));
        $actor = trim((string) ($payload['actor'] ?? ''));

        $existing = $this->findOne($pageKey, $settingType, $userId, true);
        if ($existing) {
            $this->updateExisting($existing, $description, $settingsJson, $actor);
            $saved = $this->findOne($pageKey, $settingType, $userId, false);
            return $saved ?? $existing;
        }

        $this->insertNew($pageKey, $settingType, $description, $settingsJson, $userId, $actor);
        return $this->findOne($pageKey, $settingType, $userId, false) ?? [
            'page_key' => $pageKey,
            'setting_type' => $settingType,
            'description' => $description,
            'settings_json' => $settingsJson,
        ];
    }

    public function deleteOne(string $pageKey, string $settingType, ?string $userId = null): bool
    {
        $this->assertRequiredColumns();

        $conditions = [
            'page_key = :page_key',
            'setting_type = :setting_type',
        ];
        $params = [
            ':page_key' => $pageKey,
            ':setting_type' => $settingType,
        ];

        if ($this->columnExists('user_id') && $userId !== null && $userId !== '') {
            $conditions[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM ' . self::TABLE
            . ' WHERE ' . implode(' AND ', $conditions)
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    private function insertNew(string $pageKey, string $settingType, string $description, string $settingsJson, string $userId, string $actor): void
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        if ($this->columnExists('id')) {
            $columns[] = 'id';
            $placeholders[] = ':id';
            $params[':id'] = UuidHelper::generate();
        }

        if ($this->columnExists('user_id')) {
            $columns[] = 'user_id';
            $placeholders[] = ':user_id';
            $params[':user_id'] = $userId;
        }

        $columns[] = 'page_key';
        $placeholders[] = ':page_key';
        $params[':page_key'] = $pageKey;

        $columns[] = 'setting_type';
        $placeholders[] = ':setting_type';
        $params[':setting_type'] = $settingType;

        $columns[] = 'settings_json';
        $placeholders[] = ':settings_json';
        $params[':settings_json'] = $settingsJson;

        if ($this->columnExists('description')) {
            $columns[] = 'description';
            $placeholders[] = ':description';
            $params[':description'] = $description;
        }

        if ($this->columnExists('created_at')) {
            $columns[] = 'created_at';
            $placeholders[] = ':created_at';
            $params[':created_at'] = date('Y-m-d H:i:s');
        }

        if ($this->columnExists('updated_at')) {
            $columns[] = 'updated_at';
            $placeholders[] = ':updated_at';
            $params[':updated_at'] = date('Y-m-d H:i:s');
        }

        if ($this->columnExists('created_by')) {
            $columns[] = 'created_by';
            $placeholders[] = ':created_by';
            $params[':created_by'] = $actor;
        }

        if ($this->columnExists('updated_by')) {
            $columns[] = 'updated_by';
            $placeholders[] = ':updated_by';
            $params[':updated_by'] = $actor;
        }

        $sql = 'INSERT INTO ' . self::TABLE
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function updateExisting(array $existing, string $description, string $settingsJson, string $actor): void
    {
        $assignments = [
            'settings_json = :settings_json',
        ];
        $params = [
            ':settings_json' => $settingsJson,
        ];

        if ($this->columnExists('description')) {
            $assignments[] = 'description = :description';
            $params[':description'] = $description;
        }

        if ($this->columnExists('updated_at')) {
            $assignments[] = 'updated_at = :updated_at';
            $params[':updated_at'] = date('Y-m-d H:i:s');
        }

        if ($this->columnExists('updated_by')) {
            $assignments[] = 'updated_by = :updated_by';
            $params[':updated_by'] = $actor;
        }

        if ($this->columnExists('deleted_at')) {
            $assignments[] = 'deleted_at = NULL';
        }

        if ($this->columnExists('deleted_by')) {
            $assignments[] = 'deleted_by = NULL';
        }

        $where = [];
        if ($this->columnExists('id') && !empty($existing['id'])) {
            $where[] = 'id = :id';
            $params[':id'] = (string) $existing['id'];
        } else {
            $where[] = 'page_key = :page_key';
            $where[] = 'setting_type = :setting_type';
            $params[':page_key'] = (string) ($existing['page_key'] ?? '');
            $params[':setting_type'] = (string) ($existing['setting_type'] ?? '');

            if ($this->columnExists('user_id') && array_key_exists('user_id', $existing)) {
                $where[] = 'user_id = :user_id';
                $params[':user_id'] = (string) ($existing['user_id'] ?? '');
            }
        }

        $stmt = $this->db->prepare(
            'UPDATE ' . self::TABLE
            . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
    }

    private function assertRequiredColumns(): void
    {
        foreach (['page_key', 'setting_type', 'settings_json'] as $column) {
            if (!$this->columnExists($column)) {
                throw new \RuntimeException('system_user_settings 필수 컬럼이 없습니다: ' . $column);
            }
        }
    }

    private function columnExists(string $column): bool
    {
        $cacheKey = self::TABLE . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column'
        );
        $stmt->execute([
            ':table' => self::TABLE,
            ':column' => $column,
        ]);

        $this->columnExistsCache[$cacheKey] = (int) $stmt->fetchColumn() > 0;
        return $this->columnExistsCache[$cacheKey];
    }
}

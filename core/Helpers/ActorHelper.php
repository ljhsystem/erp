<?php
// /core/Helpers/ActorHelper.php

namespace Core\Helpers;

use App\Models\User\ActorDirectoryModel;
use App\Services\Auth\AuthSessionService;

class ActorHelper
{
    /**
     * USER actor 반환 (🔥 반드시 고정 식별자만)
     */
    public static function user(): string
    {
        $userId = (new AuthSessionService())->getCurrentUserId();

        if (!$userId) {
            throw new \Exception('로그인 사용자 없음');
        }

        return "USER:{$userId}";
    }

    /**
     * SYSTEM actor 반환
     */
    public static function system(string $context = 'SYSTEM'): string
    {
        return "SYSTEM:{$context}";
    }

    /**
     * actor 타입에 따라 최종 actor 문자열 반환
     */
    public static function resolve(string $type): string
    {
        if ($type === 'USER') {
            return self::user();
        }

        if (str_starts_with($type, 'SYSTEM')) {

            $context = str_replace('SYSTEM_', '', $type);

            return self::system($context ?: 'DEFAULT');
        }

        // 🔥 fallback (중요)
        return self::system('UNKNOWN');
    }

    /**
     * actor 파싱 (조회용)
     */
    public static function parse(string $actor): array
    {
        if (str_starts_with($actor, 'USER:')) {
            return [
                'type' => 'USER',
                'id'   => str_replace('USER:', '', $actor)
            ];
        }

        if (str_starts_with($actor, 'SYSTEM:')) {
            return [
                'type'    => 'SYSTEM',
                'context' => str_replace('SYSTEM:', '', $actor)
            ];
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $actor) === 1) {
            return [
                'type' => 'USER',
                'id'   => $actor,
            ];
        }

        return [
            'type' => 'UNKNOWN',
            'raw'  => $actor
        ];
    }

    public static function displayName(?string $actor): string
    {
        $actor = is_string($actor) ? trim($actor) : '';
        if ($actor === '') {
            return '';
        }

        $parsed = self::parse($actor);
        if ($parsed['type'] === 'USER') {
            $id = trim((string) ($parsed['id'] ?? ''));
            if ($id === '') {
                return $actor;
            }

            return self::employeeNameByUserId($id) ?: '삭제된 사용자';
        }

        return $actor;
    }

    public static function enrichActorNames(array $rows, array $fieldMap): array
    {
        if ($rows === [] || $fieldMap === []) {
            return $rows;
        }

        $actorIds = [];
        foreach ($rows as $row) {
            foreach ($fieldMap as $actorField) {
                $actor = is_string($row[$actorField] ?? null) ? trim((string) $row[$actorField]) : '';
                $parsed = self::parse($actor);
                if ($parsed['type'] === 'USER') {
                    $id = trim((string) ($parsed['id'] ?? ''));
                    if ($id !== '') {
                        $actorIds[$id] = $id;
                    }
                }
            }
        }

        $nameMap = $actorIds === [] ? [] : self::employeeNamesByIds(array_values($actorIds));

        $result = [];
        foreach ($rows as $row) {
            $enriched = $row;
            foreach ($fieldMap as $nameField => $actorField) {
                $actor = is_string($row[$actorField] ?? null) ? trim((string) $row[$actorField]) : '';
                $parsed = self::parse($actor);
                if ($parsed['type'] === 'USER') {
                    $userId = trim((string) ($parsed['id'] ?? ''));
                    if (isset($nameMap[$userId]) && $nameMap[$userId] !== '') {
                        self::applyActorDisplayFields($enriched, (string) $nameField, (string) $actorField, $nameMap[$userId]);
                        continue;
                    }
                }

                self::applyActorDisplayFields($enriched, (string) $nameField, (string) $actorField, self::displayName($actor));
            }

            $result[] = $enriched;
        }

        return $result;
    }

    public static function enrichActorNamesRow(array $row, array $fieldMap): array
    {
        if ($fieldMap === []) {
            return $row;
        }

        foreach ($fieldMap as $nameField => $actorField) {
            self::applyActorDisplayFields(
                $row,
                (string) $nameField,
                (string) $actorField,
                self::displayName(is_string($row[$actorField] ?? null) ? trim((string) $row[$actorField]) : '')
            );
        }

        return $row;
    }

    private static function applyActorDisplayFields(array &$row, string $nameField, string $actorField, string $displayName): void
    {
        $displayField = self::displayNameField($actorField, $nameField);
        if ($displayField !== '') {
            $row[$displayField] = $displayName;
        }
    }

    private static function displayNameField(string $actorField, string $nameField = ''): string
    {
        if (str_ends_with($nameField, '_by_name')) {
            return $nameField;
        }

        if (str_ends_with($actorField, '_by')) {
            return "{$actorField}_name";
        }

        return '';
    }

    private static function employeeNamesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = (new ActorDirectoryModel())->findEmployeeNamesByUserIds($ids);

        $result = [];
        foreach ($rows as $userId => $name) {
            $userId = trim((string) $userId);
            if ($userId !== '') {
                $result[$userId] = is_string($name) ? trim($name) : '';
            }
        }

        return $result;
    }

    private static function employeeNameByUserId(string $userId): string
    {
        static $cache = [];
        if ($userId === '') {
            return '';
        }

        if (array_key_exists($userId, $cache)) {
            return (string) $cache[$userId];
        }

        $name = (new ActorDirectoryModel())->findEmployeeNameByUserId($userId);
        $cache[$userId] = $name;

        return $name;
    }
}

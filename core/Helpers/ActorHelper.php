<?php
// /core/Helpers/ActorHelper.php

namespace Core\Helpers;

class ActorHelper
{
    /**
     * USER actor 반환 (🔥 반드시 고정 식별자만)
     */
    public static function user(): string
    {
        $user = $_SESSION['user'] ?? [];

        $userId = $user['id'] ?? null;

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

        return [
            'type' => 'UNKNOWN',
            'raw'  => $actor
        ];
    }
}
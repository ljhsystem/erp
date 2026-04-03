<?php
// 경로: PROJECT_ROOT . '/core/Helpers/UuidHelper.php'

namespace Core\Helpers;

class UuidHelper
{
    // 1. RFC4122 v4 UUID 생성 함수
    public static function generate(): string
    {
        // 2. random_bytes 사용 가능 여부에 따라 두 가지 방식 선택
        $data = function_exists('random_bytes')
            ? random_bytes(16)
            : self::getRandomBytes(16);

        // 3. RFC4122 규격 비트 설정 (version 4)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version = 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant

        // 4. UUID 문자열 형태로 변환하여 반환
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // 5. CSPRNG가 없을 때 사용할 내부 랜덤 바이트 생성기
    private static function getRandomBytes(int $length): string
    {
        // 6. PHP CSPRNG 시도 (PHP 7+)
        try {
            return random_bytes($length);
        } catch (\Throwable $e) {
            // 계속 진행
        }

        // 7. OpenSSL 엔진 fallback
        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $strong);

            if ($bytes !== false && $strong === true) {
                return $bytes;
            }
        }

        // 8. 최후 fallback (비보안) - mt_rand 사용
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }

        return $bytes;
    }
}

<?php
declare(strict_types=1);
namespace App\Services\Mail;

use Core\LoggerFactory;

class MailToken
{
    /**
     * 1. 토큰 생성
     * @param array  $data   토큰 내부 데이터 (admin, user_code 등)
     * @param string $secret 서명 시크릿
     * @param int    $ttl    유효기간 (초 단위) 기본: 24시간
     * @return string Base64URL 인코딩된 토큰
     */
    private $logger;
    public function __construct()    {

        $this->logger = LoggerFactory::getLogger('service-mail.MailToken');
    }
    public static function create(array $data, string $secret, int $ttl = 86400): string
    {
        if ($secret === '') {
            self::log('create: secret is empty');
            throw new \InvalidArgumentException('MailToken: secret is empty');
        }

        $data['exp'] = time() + $ttl;

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            self::log('create: JSON encoding failed');
            throw new \RuntimeException('MailToken: JSON encoding failed');
        }

        $hmac = hash_hmac('sha256', $payload, $secret);

        $raw = $payload . '|' . $hmac;
        $b64 = base64_encode($raw);

        if ($b64 === false) {
            self::log('create: base64 encode failed');
            throw new \RuntimeException('MailToken: base64 encode failed');
        }

        $token = rtrim(strtr($b64, '+/', '-_'), '=');

        self::log('create: token created', [
            'exp'   => $data['exp'] ?? null,
            'short' => substr($token, 0, 16)
        ]);

        return $token;
    }

    /**
     * 2. 토큰 검증
     * @param string $token  Base64URL 형식의 토큰
     * @param string $secret 서명 시크릿
     * @return array|null    유효한 데이터 or null
     */
    public static function verify(string $token, string $secret): ?array
    {
        if ($secret === '' || $token === '') {
            self::log('verify: empty secret or token');
            return null;
        }

        $b64 = strtr($token, '-_', '+/');

        $pad = strlen($b64) % 4;
        if ($pad !== 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($b64, true);
        if ($decoded === false || strpos($decoded, '|') === false) {
            self::log('verify: base64 decode failed or invalid format', [
                'short' => substr($token, 0, 16)
            ]);
            return null;
        }

        [$payloadJson, $hmacProvided] = explode('|', $decoded, 2);

        $hmacExpected = hash_hmac('sha256', $payloadJson, $secret);

        if (!hash_equals($hmacExpected, $hmacProvided)) {
            self::log('verify: HMAC mismatch', [
                'short' => substr($token, 0, 16)
            ]);
            return null;
        }

        $data = json_decode($payloadJson, true);
        if (!is_array($data)) {
            self::log('verify: JSON decode failed', [
                'short' => substr($token, 0, 16)
            ]);
            return null;
        }

        if (!empty($data['exp']) && (int)$data['exp'] < time()) {
            self::log('verify: token expired', [
                'short' => substr($token, 0, 16),
                'exp'   => $data['exp']
            ]);
            return null;
        }

        self::log('verify: token valid', [
            'short' => substr($token, 0, 16)
        ]);

        return $data;
    }

    private static function log(string $msg, array $ctx = []): void
    {
        $line = date('c') . " | MailToken | " . $msg;
        if (!empty($ctx)) {
            $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);
        }
        $line .= PHP_EOL;

        @file_put_contents(
            PROJECT_ROOT . '/storage/logs/mail_debug.log',
            $line,
            FILE_APPEND
        );
    }
}

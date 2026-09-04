<?php
namespace App\Services\Auth;

use PDO;
use Core\Helpers\ConfigHelper;

class TokenService
{
    private readonly PDO $pdo;
    private string $secret;
    private int $defaultExpire = 3600;

    public function __construct(PDO $pdo, ?string $secret = null)
    {
        $this->pdo    = $pdo;
        $this->secret = $secret
            ?? ConfigHelper::get('app.secret');

        if (empty($this->secret)) {
            throw new \RuntimeException('TokenService: secret이 설정되지 않았습니다.');
        }

        $this->defaultExpire = ConfigHelper::get('auth.token_expire', 3600);

    }

    public function create(array $payload, ?int $expireSeconds = null): string
    {
        $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
        $expire  = time() + ($expireSeconds ?? $this->defaultExpire);

        $payload['exp'] = $expire;

        $base64Header  = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $base64Payload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $this->secret, true);
        $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $token = $base64Header . '.' . $base64Payload . '.' . $base64Signature;

        return $token;
    }

    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerJson  = base64_decode(strtr($headerB64, '-_', '+/'), true);
        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'), true);
        $signature   = base64_decode(strtr($signatureB64, '-_', '+/'), true);

        if ($headerJson === false || $payloadJson === false || $signature === false) {
            return null;
        }

        $header  = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!$header || !$payload) {
            return null;
        }

        if (($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        if (!isset($payload['exp'])) {
            return null;
        }

        if ($payload['exp'] < time()) {
            return null;
        }

        $expected = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $this->secret, true);

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return $payload;
    }

    public function createShortToken(array $payload): string
    {
        return $this->create($payload, 10 * 60); // 10분
    }

    public function randomString(int $length = 32): string
    {
        $str = bin2hex(random_bytes($length / 2));

        return $str;
    }
}

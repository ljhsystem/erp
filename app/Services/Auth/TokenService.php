<?php
// 경로: PROJECT_ROOT . '/app/Services/Auth/TokenService.php'
namespace App\Services\Auth;

use PDO;
//use Exception;
use Core\Helpers\ConfigHelper;
use Core\LoggerFactory;

class TokenService
{
    private readonly PDO $pdo;
    private string $secret;
    private int $defaultExpire = 3600; // 기본 1시간
    private $logger;

    public function __construct(PDO $pdo, ?string $secret = null)
    {
        $this->pdo    = $pdo;
        $this->logger = LoggerFactory::getLogger('service-auth.TokenService');
    
        $this->secret = $secret 
            ?? ConfigHelper::get('app.secret');
    
        if (empty($this->secret)) {
            $this->logger->error('TokenService 초기화 실패 - secret 없음');
            throw new \RuntimeException('TokenService: secret이 설정되지 않았습니다.');
        }
    
        $this->defaultExpire = ConfigHelper::get('auth.token_expire', 3600);
    
        $this->logger->info('TokenService 초기화 완료');
    }

    /* ============================================================
     * 1) JWT 생성
     * ============================================================ */
    public function create(array $payload, int $expireSeconds = null): string
    {
        $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
        $expire  = time() + ($expireSeconds ?? $this->defaultExpire);

        $payload['exp'] = $expire;

        $base64Header  = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $base64Payload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $this->secret, true);
        $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $token = $base64Header . '.' . $base64Payload . '.' . $base64Signature;

        $shortId = substr(hash('sha256', $token), 0, 16);

        $this->logger->info('토큰 생성', [
            'exp'      => $expire,
            'short_id' => $shortId
        ]);

        return $token;
    }

    /* ============================================================
     * 2) JWT 검증
     * ============================================================ */
    public function verify(string $token): ?array
    {
        $shortId = substr(hash('sha256', $token), 0, 16);

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            $this->logger->warning('토큰 검증 실패 - 형식 오류', compact('shortId'));
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // 1️⃣ base64 strict decode
        $headerJson  = base64_decode(strtr($headerB64, '-_', '+/'), true);
        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'), true);
        $signature   = base64_decode(strtr($signatureB64, '-_', '+/'), true);

        if ($headerJson === false || $payloadJson === false || $signature === false) {
            $this->logger->warning('토큰 검증 실패 - base64 decode 실패', compact('shortId'));
            return null;
        }

        // 2️⃣ JSON decode
        $header  = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!$header || !$payload) {
            $this->logger->warning('토큰 검증 실패 - JSON decode 실패', compact('shortId'));
            return null;
        }

        // 3️⃣ alg 체크
        if (($header['alg'] ?? null) !== 'HS256') {
            $this->logger->warning('토큰 검증 실패 - alg 불일치', compact('shortId'));
            return null;
        }

        // 4️⃣ payload 최소 검증
        if (!isset($payload['exp'])) {
            return null;
        }

        // 5️⃣ 만료 체크
        if ($payload['exp'] < time()) {
            $this->logger->info('토큰 만료', [
                'exp' => $payload['exp'],
                'short_id' => $shortId
            ]);
            return null;
        }

        // 6️⃣ 서명 검증
        $expected = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $this->secret, true);

        if (!hash_equals($expected, $signature)) {
            $this->logger->warning('토큰 검증 실패 - 서명 불일치', compact('shortId'));
            return null;
        }

        $this->logger->info('토큰 검증 성공', compact('shortId'));

        return $payload;
    }

    /* ============================================================
     * 3) 이메일 인증 / 비밀번호 재설정 등의 단기 토큰
     * ============================================================ */
    public function createShortToken(array $payload): string
    {
        $this->logger->info('단기 토큰 생성 요청', [
            'ttl' => 10 * 60
        ]);
        return $this->create($payload, 10 * 60); // 10분
    }

    /* ============================================================
     * ⚠ 자동로그인(Remember-Me) 기능은 완전히 제거됨
     * - createLongToken() 삭제
     * ============================================================ */

    /* ============================================================
     * 4) 임의 문자열 생성기 (비밀번호 찾기 등)
     * ============================================================ */
    public function randomString(int $length = 32): string
    {
        $str = bin2hex(random_bytes($length / 2));

        $this->logger->info('randomString 생성', [
            'length' => $length
        ]);

        return $str;
    }
}

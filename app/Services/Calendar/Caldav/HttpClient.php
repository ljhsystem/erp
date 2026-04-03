<?php
// 경로: PROJECT_ROOT . '/app/services/calendar/caldav/HttpClient.php'
namespace App\Services\Calendar\Caldav;

use Core\LoggerFactory;

/**
 * =========================================================
 * HttpClient
 * - CalDAV 저수준 HTTP 전용
 * - curl 래핑
 * - 인증 / same-origin / headers / status 통합 처리
 * =========================================================
 */
class HttpClient
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct(array $config)
    {
        $this->baseUrl  = rtrim((string)($config['base_url'] ?? ''), '/');
        $this->username = (string)($config['username'] ?? '');
        $this->password = (string)($config['password'] ?? '');

        if ($this->baseUrl === '' || $this->username === '' || $this->password === '') {
            throw new \RuntimeException('Invalid CalDAV config');
        }

        $this->logger = LoggerFactory::getLogger('service-calendar.HttpClient');
    }

    /* =========================================================
     * 메인 HTTP 요청
     * ========================================================= */
    public function request(
        string $method,
        string $hrefOrUrl,
        array $headers = [],
        ?string $body = null
    ): array {
        $url = $this->buildUrl($hrefOrUrl);

        if (!$this->isSameOrigin($url)) {
            $this->logger->warning('[HTTP] blocked by same-origin', ['url' => $url]);
            return [
                'status'  => 0,
                'body'    => '',
                'headers' => [],
            ];
        }

        $method = strtoupper($method);

        $this->logger->debug('[HTTP]', [
            'method' => $method,
            'url'    => $url,
        ]);

        $ch = curl_init($url);
        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => "{$this->username}:{$this->password}",
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,

            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,

            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,

            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$responseHeaders) {
                $len = strlen($line);
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) return $len;

                [$k, $v] = explode(':', $line, 2);
                $key = strtolower(trim($k));
                $responseHeaders[$key][] = trim($v);                
                return $len;
            },
        ]);

        /* -------------------------
         * 기본 헤더 (중요)
         * ------------------------- */
        $finalHeaders = array_merge(
            [
                'User-Agent: SUKHYANG-ERP-CalDAV/1.0',
                'Accept: application/xml, text/xml, text/calendar',
            ],
            $headers
        );

        // PROPFIND / REPORT는 XML
        if (in_array($method, ['PROPFIND', 'REPORT', 'PROPPATCH'], true)) {
            $finalHeaders[] = 'Content-Type: application/xml; charset=UTF-8';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } elseif ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $bodyRes = curl_exec($ch);
        $status  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($bodyRes === false) {
            $err = curl_error($ch);
            curl_close($ch);

            $this->logger->error('[HTTP] curl failed', ['error' => $err]);

            return [
                'status'  => 0,
                'body'    => '',
                'headers' => [],
            ];
        }

        curl_close($ch);

        if ($status >= 400) {
            $this->logger->warning('[HTTP] error response', [
                'status' => $status,
                'url'    => $url,
            ]);
        }

        return [
            'status'  => $status,
            'body'    => (string)$bodyRes,
            'headers' => $responseHeaders,
        ];
    }

    /* =========================================================
     * URL 조립 (href → absolute)
     * ========================================================= */
    private function buildUrl(string $href): string
    {
        $href = trim($href);
        if ($href === '') return $this->baseUrl . '/';

        // absolute URL
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $href = '/' . ltrim($href, '/');

        $basePath = (string)(parse_url($this->baseUrl, PHP_URL_PATH) ?? '');
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '' && str_starts_with($href, $basePath . '/')) {
            $href = substr($href, strlen($basePath));
        }

        return $this->baseUrl . $href;
    }

    /* =========================================================
     * same-origin 보호
     * ========================================================= */
    private function isSameOrigin(string $url): bool
    {
        $b = parse_url($this->baseUrl);
        $u = parse_url($url);
        if (!$b || !$u) return false;

        $bScheme = strtolower((string)($b['scheme'] ?? ''));
        $uScheme = strtolower((string)($u['scheme'] ?? ''));

        $bHost = strtolower((string)($b['host'] ?? ''));
        $uHost = strtolower((string)($u['host'] ?? ''));

        $bPort = (int)($b['port'] ?? ($bScheme === 'https' ? 443 : 80));
        $uPort = (int)($u['port'] ?? ($uScheme === 'https' ? 443 : 80));

        return $bScheme === $uScheme && $bHost === $uHost && $bPort === $uPort;
    }
}

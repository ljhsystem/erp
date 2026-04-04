<?php
// 경로: PROJECT_ROOT . '/app/Services/Integration/ExternalIntegrationService.php'
//국세청 / 공공데이터 사업자 상태 조회 API
namespace App\Services\Integration;

class ExternalIntegrationService
{

    private $baseUrl;
    private $serviceKey;

    public function __construct()
    {

        $configPath = PROJECT_ROOT.'/config/appsetting.json'; 

        if(!file_exists($configPath)){
            throw new \RuntimeException('config.json 없음 : '.$configPath);
        }

        $config = json_decode(
            file_get_contents($configPath),
            true
        );

        if(!$config){
            throw new \RuntimeException('config.json 파싱 실패');
        }

        if(!isset($config['BusinessApi'])){
            throw new \RuntimeException('BusinessApi 설정 없음');
        }

        $this->baseUrl = $config['BusinessApi']['BaseUrl'];
        $this->serviceKey = $config['BusinessApi']['ServiceKey'];
    }

    public function getBizStatus(string $bizNo): array
    {
        $payload = json_encode([
            "b_no" => [$bizNo]
        ]);
    
        $url = $this->baseUrl . "?serviceKey=" . $this->serviceKey;
    
        $ch = curl_init($url);
    
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 10
        ]);
    
        $response = curl_exec($ch);
    
        // 🔥 1. curl 실패 체크
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("API 호출 실패: {$error}");
        }
    
        // 🔥 2. 응답 로그 (디버깅용)
        // file_put_contents('/tmp/biz_api.log', $response.PHP_EOL, FILE_APPEND);
    
        $data = json_decode($response, true);
    
        // 🔥 3. JSON 파싱 실패 체크
        if ($data === null) {
            curl_close($ch);
            throw new \RuntimeException("JSON 파싱 실패: " . $response);
        }
    
        curl_close($ch);
    
        // 🔥 4. 정상 포맷으로 반환 (중요)
        return [
            'success' => true,
            'data' => $data
        ];
    }

}
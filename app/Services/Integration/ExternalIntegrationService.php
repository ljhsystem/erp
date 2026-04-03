<?php
// 경로: PROJECT_ROOT . '/app/services/ledger/ExternalIntegrationService.php'
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
            CURLOPT_POSTFIELDS => $payload
        ]);
    
        $response = curl_exec($ch);
    
        curl_close($ch);
    
        return json_decode($response, true);
    }

}
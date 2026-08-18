<?php
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

        if ($response === false) {
            $error = curl_error($ch);

            throw new \RuntimeException("API 호출 실패: {$error}");
        }

        $data = json_decode($response, true);

        if ($data === null) {

            throw new \RuntimeException("JSON 파싱 실패: " . $response);
        }

        return [
            'success' => true,
            'data' => $data
        ];
    }

}

<?php

namespace App\Controllers\User;

use Core\DbPdo;
use App\Services\User\ExternalAccountService;

class ExternalAccountController
{
    private ExternalAccountService $service;

    public function __construct()
    {
        $this->service = new ExternalAccountService(DbPdo::conn());
    }

    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->service->getMyAccounts();

        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        $serviceKey = $_GET['service_key']
        ?? $_GET['provider']
        ?? '';

        if ($serviceKey === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'service_key 값이 필요합니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->service->getMyAccount($serviceKey);

        try {
            $this->service->verifyConnection($serviceKey);
        } catch (\Throwable $e) {
        }


        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw   = file_get_contents('php://input');
            $input = json_decode($raw, true);

            $serviceKey = $input['service_key']
                ?? $input['provider']
                ?? null;

            if (!$serviceKey) {
                throw new \RuntimeException('service_key 값이 필요합니다.');
            }


            unset($input['service_key'], $input['provider']);

            $result = $this->service->saveMyAccount($serviceKey, $input);

            try {
                $this->service->verifyConnection($serviceKey);
            } catch (\Throwable $e) {

            }


            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $serviceKey = $input['service_key']
                ?? $input['provider']
                ?? null;

            if (!$serviceKey) {
                throw new \RuntimeException('service_key 값이 필요합니다.');
            }

            $result = $this->service->deleteMyAccount($serviceKey);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }




}

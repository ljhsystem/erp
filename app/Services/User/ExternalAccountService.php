<?php

namespace App\Services\User;

use App\Models\User\ExternalAccountModel;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PDO;

class ExternalAccountService
{
    private readonly PDO $pdo;
    private ExternalAccountModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new ExternalAccountModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-user.ExternalAccountService');
    }

    private function userId(): string
    {
        $actor = ActorHelper::parse(ActorHelper::user());
        $id = $actor['id'] ?? null;
        if (!$id) {
            throw new \RuntimeException('로그인이 필요합니다.');
        }

        return $id;
    }

    private function serviceName(string $serviceKey): string
    {
        return match ($serviceKey) {
            'synology' => 'Synology Calendar',
            'hometax' => '국세청 홈택스',
            'bank_kb' => 'KB국민은행',
            default => strtoupper($serviceKey),
        };
    }

    public function getMyAccount(string $serviceKey): ?array
    {
        return $this->model->getByUserAndService($this->userId(), $serviceKey);
    }

    public function saveMyAccount(string $serviceKey, array $input): array
    {
        $userId = $this->userId();

        $data = [];

        if (isset($input['external_login_id']) && trim($input['external_login_id']) !== '') {
            $data['external_login_id'] = trim($input['external_login_id']);
        }

        if (isset($input['external_password']) && trim($input['external_password']) !== '') {
            $data['external_password'] = $input['external_password'];
        }

        if (!empty($input['external_identifier'])) {
            $data['external_identifier'] = $input['external_identifier'];
        }

        foreach (['access_token', 'refresh_token', 'token_expires_at'] as $key) {
            if (!empty($input[$key])) {
                $data[$key] = $input[$key];
            }
        }

        if (isset($input['is_connected'])) {
            $data['is_connected'] = (int) $input['is_connected'];
            $data['last_connected_at'] =
                (int) $input['is_connected'] === 1 ? date('Y-m-d H:i:s') : null;
        }

        if (!empty($input['base_url'])) {
            $data['base_url'] = trim($input['base_url']);
        }

        $ok = $this->model->saveOrUpdate(
            $userId,
            $serviceKey,
            $this->serviceName($serviceKey),
            $data,
            $userId
        );

        if (!$ok) {
            return [
                'success' => false,
                'message' => '외부 서비스 계정 저장 실패',
            ];
        }

        try {
            $this->verifyConnection($serviceKey);
        } catch (\Throwable $e) {
        }

        return [
            'success' => true,
            'message' => '외부 서비스 계정이 저장되었습니다.',
        ];
    }

    public function deleteMyAccount(string $serviceKey): array
    {
        $userId = $this->userId();

        $sql = "DELETE FROM user_external_accounts
                WHERE user_id = ? AND service_key = ?";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([$userId, $serviceKey]);

        return [
            'success' => $ok,
            'message' => $ok
                ? '외부 서비스 계정을 삭제했습니다.'
                : '삭제 실패',
        ];
    }

    public function getMyAccounts(): array
    {
        return $this->model->getAllByUser($this->userId());
    }

    public function isServiceConnected(string $serviceKey): bool
    {
        return $this->model->isConnected($this->userId(), $serviceKey);
    }

    public function markSuccess(string $serviceKey): void
    {
        $this->model->updateConnectionStatus(
            $this->userId(),
            $serviceKey,
            1,
            null,
            $this->userId()
        );
    }

    public function markFailure(string $serviceKey, string $error): void
    {
        $this->model->updateConnectionStatus(
            $this->userId(),
            $serviceKey,
            0,
            mb_substr($error, 0, 255),
            $this->userId()
        );
    }

    public function verifyConnection(string $serviceKey): void
    {
        if ($serviceKey !== 'synology') {
            return;
        }

        $account = $this->model->getByUserAndService(
            $this->userId(),
            $serviceKey
        );

        if (!$account) {
            throw new \RuntimeException('계정 정보 없음');
        }

        $cfg = [
            'base_url' => $account['base_url'] ?? '',
            'username' => $account['external_login_id'] ?? '',
            'password' => $account['external_password'] ?? '',
        ];

        if (!$cfg['base_url'] || !$cfg['username']) {
            throw new \RuntimeException('Synology 설정 누락');
        }

        $this->logger->info('verifyConnection start', [
            'user_id' => $this->userId(),
            'service_key' => $serviceKey,
            'account' => $account,
        ]);

        try {
            $client = new \App\Services\Calendar\CalDavClient($cfg);
            $client->listPrincipals();
            $this->markSuccess($serviceKey);
        } catch (\Throwable $e) {
            $this->markFailure($serviceKey, $e->getMessage());
            throw $e;
        }
    }
}

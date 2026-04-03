<?php
// 경로: PROJECT_ROOT . '/app/services/user/ExternalAccountService.php'
namespace App\Services\User;

use PDO;
use App\Models\User\UserExternalAccountModel;
use Core\LoggerFactory;

class ExternalAccountService
{
    private readonly PDO $pdo;
    private UserExternalAccountModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new UserExternalAccountModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-user.ExternalAccountService');
    }

    /* =========================================================
     * 공통: 현재 로그인 사용자 ID
     * ========================================================= */
    private function userId(): string
    {
        $id = $_SESSION['user']['id'] ?? null;
        if (!$id) {
            throw new \RuntimeException('로그인이 필요합니다.');
        }
        return $id;
    }

    /* =========================================================
     * 공통: 서비스 키 → 표시명 매핑
     * ========================================================= */
    private function serviceName(string $serviceKey): string
    {
        return match ($serviceKey) {
            'synology' => 'Synology Calendar',
            'hometax'  => '국세청 홈택스',
            'bank_kb'  => 'KB국민은행',
            default    => strtoupper($serviceKey),
        };
    }

    /* =========================================================
     * 1. 내 외부 서비스 계정 단일 조회
     * ========================================================= */
    public function getMyAccount(string $serviceKey): ?array
    {
        return $this->model->getByUserAndService(
            $this->userId(),
            $serviceKey
        );
    }

    /* =========================================================
     * 2. 내 외부 서비스 계정 저장 / 수정
     * ========================================================= */
    public function saveMyAccount(string $serviceKey, array $input): array
    {
        $userId = $this->userId();
    
        $data = [];
    
        // 로그인 ID: 비어있지 않을 때만 갱신
        if (isset($input['external_login_id']) && trim($input['external_login_id']) !== '') {
            $data['external_login_id'] = trim($input['external_login_id']);
        }

        // 비밀번호: 입력했을 때만
        if (isset($input['external_password']) && trim($input['external_password']) !== '') {
            $data['external_password'] = $input['external_password'];
        }
    
        // 🆔 외부 UID
        if (!empty($input['external_identifier'])) {
            $data['external_identifier'] = $input['external_identifier'];
        }
    
        // 🔑 토큰 계열 (있을 때만)
        foreach (['access_token','refresh_token','token_expires_at'] as $k) {
            if (!empty($input[$k])) {
                $data[$k] = $input[$k];
            }
        }
    
        // 🔌 연결 여부 (명시된 경우만)
        if (isset($input['is_connected'])) {
            $data['is_connected'] = (int)$input['is_connected'];
            $data['last_connected_at'] =
                (int)$input['is_connected'] === 1 ? date('Y-m-d H:i:s') : null;
        }
    
        $ok = $this->model->saveOrUpdate(
            $userId,
            $serviceKey,
            $this->serviceName($serviceKey),
            $data,
            $userId
        );
    
        return [
            'success' => $ok,
            'message' => $ok
                ? '외부 서비스 계정이 저장되었습니다.'
                : '외부 서비스 계정 저장 실패'
        ];
    }
    

    /* =========================================================
     * 3. 외부 서비스 삭제제
     * ========================================================= */
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
                ? '외부 서비스 계정이 삭제되었습니다.'
                : '삭제 실패'
        ];
    }
    

    /* =========================================================
     * 4. 내 외부 서비스 전체 목록
     * ========================================================= */
    public function getMyAccounts(): array
    {
        return $this->model->getAllByUser($this->userId());
    }

    /* =========================================================
     * 5. 특정 서비스 연결 여부
     * ========================================================= */
    public function isServiceConnected(string $serviceKey): bool
    {
        return $this->model->isConnected(
            $this->userId(),
            $serviceKey
        );
    }


/* =========================================================
 * 외부 서비스 사용 성공 기록 (상태만 업데이트)
 * ========================================================= */
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

/* =========================================================
 * 외부 서비스 사용 실패 기록 (상태만 업데이트)
 * ========================================================= */
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



    
}

<?php
// 경로: PROJECT_ROOT . '/app/Services/Ledger/SubAccountService.php'
// 설명:
// 
namespace App\Services\Ledger;

use PDO;
use App\Models\Ledger\SubAccountModel;
use Core\Helpers\CodeHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;

class SubAccountService
{
    private readonly PDO $pdo;
    private SubAccountModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model = new SubAccountModel($this->pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.SubAccountService');
        $this->logger->info('SubAccountService initialized');
    }

    /* =========================================================
     * 계정별 보조계정 조회
     * ========================================================= */
    public function getByAccountId(string $accountId): array
    {
        try {

            return $this->model->getByAccountId($accountId);

        } catch (\Throwable $e) {

            $this->logger->error('getByAccountId failed', [
                'account_id' => $accountId,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    /* =========================================================
     * 보조계정 생성
     * ========================================================= */
    public function create(array $data): array
    {
        try {
    
            $data['id'] = UuidHelper::generate();
    
            /* 코드 생성 */
            $data['sub_code'] = CodeHelper::generateSubAccountCode(
                $this->pdo,
                $data['account_id']
            );
    
            if (!$this->model->create($data)) {

                return [
                    'success' => false,
                    'message' => '보조계정 생성 실패'
                ];
            }
            
            /* 🔥 추가 */
            $this->syncAllowSubAccount($data['account_id']);
            
            return [
                'success' => true,
                'id' => $data['id']
            ];
    
        } catch (\Throwable $e) {
    
            $this->logger->error('create failed', [
                'data' => $data,
                'exception' => $e->getMessage()
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* =========================================================
     * 보조계정 삭제
     * ========================================================= */
    public function delete(string $id): array
    {
        try {
    
            /* 🔥 먼저 account_id 조회 */
            $accountId = $this->getAccountIdBySubId($id);
    
            if (!$accountId) {
                return [
                    'success' => false,
                    'message' => '대상 없음'
                ];
            }
    
            /* 🔥 삭제 */
            $ok = $this->model->delete($id);
    
            if (!$ok) {
                return [
                    'success' => false,
                    'message' => '보조계정 삭제 실패'
                ];
            }
    
            /* 🔥 상태 동기화 */
            $this->syncAllowSubAccountAfterDelete($accountId);
    
            return [
                'success' => true
            ];
    
        } catch (\Throwable $e) {
    
            $this->logger->error('delete failed', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function update(string $id, array $data): array
    {
        $ok = $this->model->update($id, $data);

        return [
            'success' => $ok
        ];
    }

    private function syncAllowSubAccount(string $accountId): void
    {
        $sql = "
            UPDATE ledger_accounts
            SET allow_sub_account = 1
            WHERE id = :id
        ";
    
        $stmt = $this->pdo->prepare($sql);
    
        $stmt->execute([
            ':id' => $accountId
        ]);
    }
    private function syncAllowSubAccountAfterDelete(string $accountId): void
    {
        $sql = "
            UPDATE ledger_accounts a
            SET allow_sub_account = EXISTS (
                SELECT 1 FROM ledger_sub_accounts sa
                WHERE sa.account_id = a.id
            )
            WHERE id = :id
            AND NOT EXISTS (
                SELECT 1
                FROM ledger_sub_accounts sa
                WHERE sa.account_id = a.id
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $accountId
        ]);
    }

    private function getAccountIdBySubId(string $subId): string
    {
        $sql = "
            SELECT account_id
            FROM ledger_sub_accounts
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $subId
        ]);

        return (string)$stmt->fetchColumn();
    }


}
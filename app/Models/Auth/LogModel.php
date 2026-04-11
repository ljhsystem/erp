<?php
// 경로: PROJECT_ROOT . '/app/Models/Auth/AuthLogModel.php'
namespace App\Models\Auth;

use PDO;
use Core\Database;

class LogModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    // ---------------------------------------------------------------
    // 내부: 기본 로그 값(IP, UserAgent) 자동 채우기
    // ---------------------------------------------------------------
    private function enrichLogData(array $data): array
    {
        $data['ip_address'] = $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR']     ?? null);
        $data['user_agent'] = $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        return $data;
    }

    // ---------------------------------------------------------------
    // 공통 로그 기록 INSERT (Model = SQL 전담)
    // ---------------------------------------------------------------
    public function write(array $data): bool
    {
        try {
            // 기본값 자동 채움
            $data = $this->enrichLogData($data);

            $sql = "
                INSERT INTO auth_logs (
                    id,
                    user_id, username,
                    log_type, action_type, action_detail,
                    ip_address, user_agent,
                    success,
                    ref_table, ref_id,
                    created_by, created_at
                ) VALUES (
                    :id,
                    :user_id, :username,
                    :log_type, :action_type, :action_detail,
                    :ip_address, :user_agent,
                    :success,
                    :ref_table, :ref_id,
                    :created_by, NOW()
                )
            ";

            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                ':id'            => $data['id'],                      // ⭐ 서비스에서 전달된 UUID 사용
                ':user_id'       => $data['user_id']      ?? null,
                ':username'      => $data['username']     ?? null,
                ':log_type'      => $data['log_type']     ?? 'auth',
                ':action_type'   => $data['action_type']  ?? '',
                ':action_detail' => $data['action_detail'] ?? null,
                ':ip_address'    => $data['ip_address'],
                ':user_agent'    => $data['user_agent'],
                ':success'       => $data['success']      ?? 1,
                ':ref_table'     => $data['ref_table']    ?? null,
                ':ref_id'        => $data['ref_id']       ?? null,
                ':created_by'    => $data['created_by']   ?? null,
            ]);

        } catch (\Throwable $e) {
            error_log('[AuthLogModel] 로그 기록 실패: ' . $e->getMessage());
            return false;
        }
    }

    // ---------------------------------------------------------------
    // 특정 사용자 로그 조회
    // ---------------------------------------------------------------
    public function getUserLogs(string $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // 최근 인증로그 (로그인/로그아웃)
    // ---------------------------------------------------------------
    public function getRecentAuthLogs(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE log_type = 'auth'
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // 특정 액션로그 조회
    // ---------------------------------------------------------------
    public function getLogsByAction(string $actionType, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE action_type = :act
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':act', $actionType);
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // 승인 로그 조회 (approve)
    // ---------------------------------------------------------------
    public function getApprovalLogs(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE action_type = 'approve'
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // 특정 사용자 승인 로그 조회
    // ---------------------------------------------------------------
    public function getUserApprovalLogs(string $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE action_type = 'approve'
               AND user_id = :uid
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

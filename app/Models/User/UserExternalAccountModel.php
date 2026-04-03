<?php
// 경로: PROJECT_ROOT . '/app/models/user/UserExternalAccountModel.php'
namespace App\Models\User;

use PDO;

class UserExternalAccountModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /* ============================================================
     * 1. 사용자 + 서비스 계정 단일 조회
     * ============================================================ */
    public function getByUserAndService(string $userId, string $serviceKey): ?array
    {
        $sql = "
            SELECT *
            FROM user_external_accounts
            WHERE user_id = :user_id
              AND service_key = :service_key
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id'     => $userId,
            ':service_key' => $serviceKey
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ============================================================
     * 2. 사용자 외부 계정 저장 / 갱신 (UPSERT)
     * ============================================================ */
    public function saveOrUpdate(
        string $userId,
        string $serviceKey,
        string $serviceName,
        array $data,
        ?string $actorId = null
    ): bool {
        $sql = "
            INSERT INTO user_external_accounts (
                id,
                user_id,
                service_key,
                service_name,
                external_login_id,
                external_password,
                external_identifier,
                access_token,
                refresh_token,
                token_expires_at,
                is_connected,
                last_connected_at,
                created_at,
                created_by,
                updated_at,
                updated_by
            ) VALUES (
                UUID(),
                :user_id,
                :service_key,
                :service_name,
                :external_login_id,
                :external_password,
                :external_identifier,
                :access_token,
                :refresh_token,
                :token_expires_at,
                :is_connected,
                :last_connected_at,
                NOW(),
                :created_by,
                NOW(),
                :updated_by
            )
            ON DUPLICATE KEY UPDATE
                external_login_id   = VALUES(external_login_id),
                external_password   = VALUES(external_password),
                external_identifier = VALUES(external_identifier),
                access_token        = VALUES(access_token),
                refresh_token       = VALUES(refresh_token),
                token_expires_at    = VALUES(token_expires_at),
                is_connected        = VALUES(is_connected),
                last_connected_at   = VALUES(last_connected_at),
                updated_at          = NOW(),
                updated_by          = VALUES(updated_by)
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':user_id'               => $userId,
            ':service_key'           => $serviceKey,
            ':service_name'          => $serviceName,
            ':external_login_id'     => $data['external_login_id'] ?? null,
            ':external_password'     => $data['external_password'] ?? null,
            ':external_identifier'   => $data['external_identifier'] ?? null,
            ':access_token'          => $data['access_token'] ?? null,
            ':refresh_token'         => $data['refresh_token'] ?? null,
            ':token_expires_at'      => $data['token_expires_at'] ?? null,
            ':is_connected'          => isset($data['is_connected']) ? (int)$data['is_connected'] : 0,
            ':last_connected_at'     => $data['last_connected_at'] ?? null,
            ':created_by'            => $actorId,
            ':updated_by'            => $actorId,
        ]);
    }

    /* ============================================================
     * 3. 외부 서비스 연결 해제
     * ============================================================ */
    public function disconnect(string $userId, string $serviceKey, ?string $actorId = null): bool
    {
        $sql = "
            UPDATE user_external_accounts
               SET is_connected = 0,
                   updated_at = NOW(),
                   updated_by = :updated_by
             WHERE user_id = :user_id
               AND service_key = :service_key
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id'     => $userId,
            ':service_key' => $serviceKey,
            ':updated_by'  => $actorId
        ]);
    }

    /* ============================================================
     * 4. 사용자 기준 전체 외부 계정 조회
     * ============================================================ */
    public function getAllByUser(string $userId): array
    {
        $sql = "
            SELECT *
            FROM user_external_accounts
            WHERE user_id = ?
            ORDER BY service_key ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
     * 5. 특정 서비스 연결 여부
     * ============================================================ */
    public function isConnected(string $userId, string $serviceKey): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM user_external_accounts
            WHERE user_id = ?
              AND service_key = ?
              AND is_connected = 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $serviceKey]);

        return (int)$stmt->fetchColumn() > 0;
    }

//상태 전용 UPDATE 메서드
    public function updateConnectionStatus(
        string $userId,
        string $serviceKey,
        int $isConnected,
        ?string $errorMessage = null,
        ?string $actorId = null
    ): bool {
        $sql = "
            UPDATE user_external_accounts
            SET is_connected = :is_connected,
                last_connected_at = CASE
                    WHEN :connected_for_time = 1 THEN NOW()
                    ELSE last_connected_at
                END,
                last_error_message = :error,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE user_id = :user_id
            AND service_key = :service_key
        ";
    
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':is_connected'        => $isConnected,
            ':connected_for_time'  => $isConnected,
            ':error'               => $errorMessage,
            ':updated_by'          => $actorId,
            ':user_id'             => $userId,
            ':service_key'         => $serviceKey,
        ]);
        
    }
    
    public function getBySynologyOwnerId(int $ownerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM user_external_accounts
            WHERE service_key = 'synology'
              AND synology_owner_id = :oid
            LIMIT 1
        ");
        $stmt->execute([':oid' => $ownerId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    

    public function getByExternalLoginId(
        string $serviceKey,
        string $externalLoginId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM user_external_accounts
            WHERE service_key = :svc
              AND external_login_id = :login
            LIMIT 1
        ");
        $stmt->execute([
            ':svc'   => $serviceKey,
            ':login' => $externalLoginId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    






}

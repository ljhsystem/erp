<?php

declare(strict_types=1);

namespace App\Models\System;

use PDO;

final class ClientTaxProfileModel
{
    public function __construct(private readonly PDO $db) {}

    public function resolveVerified(string $clientId, string $date): ?array
    {
        $statement = $this->db->prepare(
            "SELECT profile.*,client.client_type,client.client_name FROM system_client_tax_profiles profile "
            . "JOIN system_clients client ON client.id=profile.client_id AND client.deleted_at IS NULL "
            . "WHERE profile.client_id=:client_id AND profile.deleted_at IS NULL "
            . "AND profile.effective_from<=:date_from AND (profile.effective_to IS NULL OR profile.effective_to>=:date_to) "
            . "ORDER BY profile.effective_from DESC,profile.id LIMIT 2"
        );
        $statement->execute([':client_id' => $clientId, ':date_from' => $date, ':date_to' => $date]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) > 1) {
            throw new \RuntimeException('소득자의 세무 프로필 유효기간이 중복됩니다.');
        }
        return $rows[0] ?? null;
    }

    public function lockClient(string $clientId): bool
    {
        $statement = $this->db->prepare('SELECT id FROM system_clients WHERE id=:id AND deleted_at IS NULL FOR UPDATE');
        $statement->execute([':id' => $clientId]);
        return (bool) $statement->fetchColumn();
    }

    public function codeExists(string $group, string $code): bool
    {
        $statement = $this->db->prepare('SELECT 1 FROM system_codes WHERE code_group=:group_name AND code=:code AND is_active=1');
        $statement->execute([':group_name' => $group, ':code' => $code]);
        return (bool) $statement->fetchColumn();
    }

    public function overlapping(string $clientId, string $from, ?string $to, string $excludeId = ''): bool
    {
        $statement = $this->db->prepare(
            "SELECT id FROM system_client_tax_profiles WHERE client_id=:client_id AND deleted_at IS NULL"
            . " AND id<>:exclude_id AND effective_from<=COALESCE(:effective_to,'9999-12-31')"
            . " AND COALESCE(effective_to,'9999-12-31')>=:effective_from LIMIT 1 FOR UPDATE"
        );
        $statement->execute([':client_id'=>$clientId,':exclude_id'=>$excludeId,':effective_from'=>$from,':effective_to'=>$to]);
        return (bool) $statement->fetchColumn();
    }

    public function find(string $id, bool $lock = false): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM system_client_tax_profiles WHERE id=:id' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':id'=>$id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(array $data): void
    {
        $fields=array_keys($data);$statement=$this->db->prepare('INSERT INTO system_client_tax_profiles('.implode(',',$fields).') VALUES(:'.implode(',:',$fields).')');
        $statement->execute(array_combine(array_map(static fn(string $field):string=>':'.$field,$fields),array_values($data)));
    }

    public function update(string $id, array $data): void
    {
        $sets=[];$params=[':id'=>$id];foreach($data as$field=>$value){$sets[]=$field.'=:'.$field;$params[':'.$field]=$value;}
        $statement=$this->db->prepare('UPDATE system_client_tax_profiles SET '.implode(',',$sets).' WHERE id=:id');$statement->execute($params);
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories\Ledger;

use PDO;

final class TransactionProjectionRepairRepository
{
    public function __construct(private PDO $db) {}

    public function findCompletedByRequestKey(string $requestKey): ?array
    {
        $stmt=$this->db->prepare("SELECT * FROM ledger_transaction_projection_repairs WHERE request_key=:request_key AND result_status='COMPLETED' LIMIT 1");
        $stmt->execute([':request_key'=>$requestKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function historyByTransactionId(string $transactionId): array
    {
        $stmt=$this->db->prepare('SELECT * FROM ledger_transaction_projection_repairs WHERE transaction_id=:transaction_id ORDER BY repaired_at,id');
        $stmt->execute([':transaction_id'=>$transactionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertCompleted(array $row): void
    {
        $columns=array_keys($row);
        $sql='INSERT INTO ledger_transaction_projection_repairs (`'.implode('`,`',$columns).'`) VALUES (:'.implode(',:',$columns).')';
        $stmt=$this->db->prepare($sql);
        $params=[];
        foreach ($row as $key=>$value) $params[':'.$key]=$value;
        $stmt->execute($params);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('REPAIR_AUDIT_INSERT_FAILED');
    }
}

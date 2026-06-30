<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceBankModel extends EvidenceWriteModel
{
    protected string $table = 'ledger_evidence_bank_transaction';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }
}

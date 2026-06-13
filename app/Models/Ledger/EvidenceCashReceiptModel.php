<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceCashReceiptModel extends EvidenceWriteModel
{
    protected string $table = 'ledger_evidence_cash_receipt';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }
}


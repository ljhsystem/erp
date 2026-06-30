<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceCardPurchaseModel extends EvidenceWriteModel
{
    protected string $table = 'ledger_evidence_card_statement';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }
}

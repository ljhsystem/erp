<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceCardHometaxModel extends EvidenceWriteModel
{
    protected string $table = 'ledger_evidence_card_hometax';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }
}

<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceTaxInvoiceModel extends EvidenceWriteModel
{
    protected string $table = 'ledger_evidence_tax_invoice';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }
}


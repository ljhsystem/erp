<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceTaxInvoiceManualModel extends EvidenceWriteModel
{
    protected string $table = 'ledger_evidence_tax_invoice_manual';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }
}


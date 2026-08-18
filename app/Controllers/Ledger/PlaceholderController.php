<?php

namespace App\Controllers\Ledger;

use PDO;

class PlaceholderController
{
    private LedgerController $ledgerController;

    public function __construct(?PDO $pdo = null)
    {
        $this->ledgerController = new LedgerController($pdo);
    }

    public function index(): void
    {
        $this->ledgerController->webPlaceholder();
    }
}

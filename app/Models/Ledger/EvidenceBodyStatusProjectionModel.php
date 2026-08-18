<?php

namespace App\Models\Ledger;

class EvidenceBodyStatusProjectionModel
{
    public function filteredStatusHasRows(string $status): bool { return true; }

    public function joinForBody(string $bodyAlias, string $canonicalImportTypeSql): string
    {
        return "LEFT JOIN (SELECT NULL AS processing_status) pr ON 1 = 0";
    }

    public function statusSelect(string $default = 'COMPLETED'): string { return 'body.evidence_status'; }

    public function reviewStatusSelect(string $default = ''): string { return 'NULL'; }

    public function errorMessageSelect(): string { return 'NULL'; }
}

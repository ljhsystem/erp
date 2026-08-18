<?php

namespace App\Services\Ledger;

interface JournalCandidateProviderInterface
{
    public function provide(array $context): array;
}

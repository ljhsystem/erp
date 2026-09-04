<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\JournalCandidateRepository;

class JournalRecentPatternCandidateProvider implements JournalCandidateProviderInterface
{
    public function __construct(private readonly JournalCandidateRepository $repository)
    {
    }

    public function provide(array $context): array
    {
        // 기존 Projection은 공식 POSTED 전표와 증빙유형 근거를 보장하지 못하므로
        // 역할 기반 최근분개 정책이 승인되기 전까지 추천에 사용하지 않는다.
        return [];
    }
}

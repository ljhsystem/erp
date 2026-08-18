<?php

namespace App\Services\Institution;

use App\Models\Institution\EmploymentContractModel;

final class EmploymentContractValidityService
{
    public function __construct(private readonly EmploymentContractModel $contracts)
    {
    }

    public function effectiveContracts(string $employeeId, string $date): array
    {
        return $this->canonicalize($this->contracts->validityCandidates($employeeId, $date, $date));
    }

    public function effectiveContractsForPeriod(string $start, string $end): array
    {
        return $this->canonicalize($this->contracts->validityCandidates(null, $start, $end));
    }

    public function assertNoOverlap(string $contractId): void
    {
        $candidate = $this->contracts->find($contractId, false, true);
        if (!$candidate) {
            throw new \RuntimeException('근로계약을 찾을 수 없습니다.');
        }

        $rows = $this->contracts->validityCandidates(
            (string) $candidate['employee_id'],
            (string) $candidate['contract_start_date'],
            $candidate['contract_end_date'] ?: '9999-12-31',
            true,
            ['APPROVAL_PENDING', 'APPROVED', 'TERMINATED']
        );
        $candidateRoot = $this->rootId($candidate, array_merge($rows, [$candidate]));
        foreach ($rows as $row) {
            if ((string) $row['id'] === $contractId) {
                continue;
            }
            if ($this->rootId($row, array_merge($rows, [$candidate])) === $candidateRoot) {
                continue;
            }
            throw new \RuntimeException('동일 직원의 유효기간이 겹치는 근로계약이 있습니다.');
        }
    }

    private function canonicalize(array $rows): array
    {
        $selected = [];
        foreach ($rows as $row) {
            $key = (string) $row['employee_id'] . ':' . $this->rootId($row, $rows);
            if (!isset($selected[$key]) || (int) $row['revision_no'] > (int) $selected[$key]['revision_no']) {
                $selected[$key] = $row;
            }
        }
        return array_values($selected);
    }

    private function rootId(array $row, array $rows): string
    {
        $byId = [];
        foreach ($rows as $item) {
            $byId[(string) $item['id']] = $item;
        }
        $current = $row;
        $seen = [];
        while (!empty($current['previous_contract_id'])) {
            $previousId = (string) $current['previous_contract_id'];
            if (isset($seen[$previousId]) || !isset($byId[$previousId])) {
                break;
            }
            $seen[$previousId] = true;
            $current = $byId[$previousId];
        }
        return (string) $current['id'];
    }
}

<?php

namespace App\Services\Approval;

use PDO;

final class ApprovalDocumentSummaryResolver
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function resolve(array $rows): array
    {
        $ids = [];
        $dailyIds = [];
        $businessIds = [];
        foreach ($rows as $row) {
            if (($row['document_type'] ?? '') === 'REGULAR_EMPLOYMENT_INCOME') {
                $ids[] = (string) $row['document_id'];
            } elseif (($row['document_type'] ?? '') === 'DAILY_EMPLOYMENT_INCOME') {
                $dailyIds[] = (string) $row['document_id'];
            } elseif (($row['document_type'] ?? '') === 'BUSINESS_INCOME') {
                $businessIds[] = (string) $row['document_id'];
            }
        }
        $summaries = $this->regularEmploymentIncomeSummaries(array_values(array_unique($ids)));
        $dailySummaries = $this->dailyEmploymentIncomeSummaries(array_values(array_unique($dailyIds)));
        $businessSummaries = $this->businessIncomeSummaries(array_values(array_unique($businessIds)));
        foreach ($rows as &$row) {
            $documentType = (string) ($row['document_type'] ?? '');
            if (!in_array($documentType, ['REGULAR_EMPLOYMENT_INCOME', 'DAILY_EMPLOYMENT_INCOME', 'BUSINESS_INCOME'], true)) continue;
            $summary = match ($documentType) {
                'DAILY_EMPLOYMENT_INCOME' => $dailySummaries[(string) ($row['document_id'] ?? '')] ?? null,
                'BUSINESS_INCOME' => $businessSummaries[(string) ($row['document_id'] ?? '')] ?? null,
                default => $summaries[(string) ($row['document_id'] ?? '')] ?? null,
            };
            if (!$summary) {
                $row['document_type_code'] = (string) $row['document_type'];
                $row['display_title'] = '요약정보 확인 필요';
                $row['applicant_summary'] = null;
                $row['primary_amount'] = null;
                $row['primary_amount_label'] = '총지급액';
                $row['reference_id'] = (string) ($row['document_id'] ?? '');
                $row['document_date'] = null;
                $row['title'] = $row['display_title'];
                $row['applicant_name'] = null;
                $row['total_amount'] = null;
                $row['application_date'] = null;
                $row['summary_status_code'] = 'NEEDS_VERIFICATION';
                continue;
            }
            $row['document_type_code'] = (string) $row['document_type'];
            $row['display_title'] = $summary['display_title'];
            $row['applicant_summary'] = $summary['applicant_summary'];
            $row['primary_amount'] = $summary['primary_amount'];
            $row['reference_id'] = (string) $row['document_id'];
            $row['document_date'] = $summary['document_date'];
            $row['title'] = $summary['display_title'];
            $row['applicant_name'] = $summary['applicant_summary'];
            $row['total_amount'] = $summary['primary_amount'];
            $row['primary_amount_label'] = '총지급액';
            $row['application_date'] = $summary['document_date'];
            $row['summary_status_code'] = 'RESOLVED';
        }
        unset($row);
        return $rows;
    }

    private function dailyEmploymentIncomeSummaries(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT h.id,h.income_year_month,LAST_DAY(CONCAT(h.income_year_month,'-01')) document_date,h.total_gross_amount,
                   COUNT(DISTINCT i.id) worker_count,
                   MAX(workday.work_date) last_work_date,
                   SUBSTRING_INDEX(GROUP_CONCAT(i.worker_name_snapshot ORDER BY g.sort_no,i.sort_no,i.id SEPARATOR ','),',',1) first_worker_name
              FROM institution_daily_employment_incomes h
              LEFT JOIN institution_daily_employment_income_groups g ON g.daily_employment_income_id=h.id
              LEFT JOIN institution_daily_employment_income_items i ON i.daily_employment_income_group_id=g.id
              LEFT JOIN institution_daily_employment_income_workdays workday ON workday.daily_employment_income_item_id=i.id
             WHERE h.id IN ({$placeholders}) AND h.deleted_at IS NULL
             GROUP BY h.id,h.income_year_month,h.total_gross_amount");
        $stmt->execute($ids);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            [$year, $month] = array_pad(explode('-', (string) $row['income_year_month'], 2), 2, '');
            $count = (int) $row['worker_count'];
            $first = trim((string) $row['first_worker_name']);
            $result[(string) $row['id']] = [
                'display_title' => sprintf('%d년 %d월귀속 일용근로소득', (int) $year, (int) $month),
                'applicant_summary' => $count > 1 ? sprintf('%s 외 %d명', $first, $count - 1) : ($first !== '' ? $first : null),
                'primary_amount' => $row['total_gross_amount'],
                'document_date' => $row['last_work_date'] ?: $row['document_date'],
            ];
        }
        return $result;
    }

    private function businessIncomeSummaries(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare("SELECT header.id,header.income_year_month,MIN(item.transaction_date) document_date,
                   COUNT(item.id) recipient_count,COALESCE(SUM(item.gross_payment_amount),0) gross_payment_amount,
                   SUBSTRING_INDEX(GROUP_CONCAT(client.client_name ORDER BY business_group.sort_no,item.sort_no,item.id SEPARATOR ','),',',1) first_recipient_name
              FROM institution_business_incomes header
              LEFT JOIN institution_business_income_groups business_group ON business_group.business_income_id=header.id AND business_group.deleted_at IS NULL
              LEFT JOIN institution_business_income_items item ON item.group_id=business_group.id AND item.deleted_at IS NULL
              LEFT JOIN system_clients client ON client.id=item.client_id
             WHERE header.id IN ({$placeholders}) AND header.deleted_at IS NULL
             GROUP BY header.id,header.income_year_month");
        $statement->execute($ids);$result=[];
        foreach($statement->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){[$year,$month]=array_pad(explode('-',(string)$row['income_year_month'],2),2,'');$count=(int)$row['recipient_count'];$first=trim((string)$row['first_recipient_name']);$result[(string)$row['id']]=['display_title'=>sprintf('%d년 %d월 사업소득',(int)$year,(int)$month),'applicant_summary'=>$count>1?sprintf('%s 외 %d건',$first,$count-1):($first!==''?$first:null),'primary_amount'=>$row['gross_payment_amount'],'document_date'=>$row['document_date']];}
        return $result;
    }

    private function regularEmploymentIncomeSummaries(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT h.id, h.income_year_month, LAST_DAY(CONCAT(h.income_year_month,'-01')) document_date, h.gross_amount,
                   COUNT(i.id) employee_count,
                   SUBSTRING_INDEX(GROUP_CONCAT(i.employee_name_snapshot ORDER BY i.sort_no, i.id SEPARATOR ','), ',', 1) first_employee_name
              FROM institution_regular_employment_incomes h
              LEFT JOIN institution_regular_employment_income_items i
                ON i.regular_employment_income_id=h.id AND i.deleted_at IS NULL
             WHERE h.id IN ({$placeholders}) AND h.deleted_at IS NULL
             GROUP BY h.id,h.income_year_month,h.gross_amount");
        $stmt->execute($ids);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            [$year, $month] = array_pad(explode('-', (string) $row['income_year_month'], 2), 2, '');
            $count = (int) $row['employee_count'];
            $first = trim((string) $row['first_employee_name']);
            $result[(string) $row['id']] = [
                'display_title' => sprintf('%d년 %d월 상용근로소득', (int) $year, (int) $month),
                'applicant_summary' => $count > 1 ? sprintf('%s 외 %d명', $first, $count - 1) : ($first !== '' ? $first : null),
                'primary_amount' => $row['gross_amount'],
                'document_date' => $row['document_date'],
            ];
        }
        return $result;
    }
}

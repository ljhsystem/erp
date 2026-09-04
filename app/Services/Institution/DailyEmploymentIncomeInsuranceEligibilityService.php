<?php

declare(strict_types=1);

namespace App\Services\Institution;

use Core\Security\Crypto;
use PDO;

final class DailyEmploymentIncomeInsuranceEligibilityService
{
    private InsuranceEligibilityResolver $resolver;

    public function __construct(private readonly PDO $db)
    {
        $this->resolver = new InsuranceEligibilityResolver($db);
    }

    public function resolveItem(array $input): array
    {
        $workdays = array_values((array)($input['workdays'] ?? []));
        $dates = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['work_date'] ?? '')),
            $workdays
        ))));
        sort($dates, SORT_STRING);
        $attributionDate = $dates[0] ?? trim((string)($input['attribution_date'] ?? ''));
        $companyId = trim((string)($input['company_id'] ?? ''));
        $workerId = trim((string)($input['worker_client_id'] ?? ''));
        $projectId = trim((string)($input['project_id'] ?? '')) ?: null;
        $workplaceId = trim((string)($input['social_insurance_workplace_id'] ?? '')) ?: null;
        $scope = strtoupper(trim((string)($input['work_scope_code'] ?? '')));
        if ($scope === 'PROJECT') $scope = 'CONSTRUCTION_SITE';
        $scopeDerivation = is_array($input['scope_derivation'] ?? null) ? $input['scope_derivation'] : [];
        $analysis = $this->employmentAnalysis($input, $dates, $workerId, $scope, $projectId);
        $projectAnalysis = $this->projectAnalysis($projectId);
        $workplaceAnalysis = $this->workplaceAnalysis($workplaceId);
        $monthlyDays = count($dates);
        $monthlyMinutes = array_sum(array_map(static fn(array $row): int => (int)($row['actual_work_minutes'] ?? 0), $workdays));
        $monthlyIncome = round(array_sum(array_map(static fn(array $row): float => (float)($row['gross_amount'] ?? 0), $workdays)), 2);

        $results = [];
        foreach (['NATIONAL_PENSION', 'HEALTH_INSURANCE'] as $insuranceType) {
            $transitionStatus = $this->transitionStatus($scope, $attributionDate, $projectAnalysis);
            $context = [
                'company_id' => $companyId,
                'worker_client_id' => $workerId,
                'attribution_date' => $attributionDate,
                'insurance_type_code' => $insuranceType,
                'employment_type_code' => 'DAILY',
                'business_unit_code' => $scopeDerivation['business_unit_code'] ?? ($input['business_unit_code'] ?? null),
                'work_scope_code' => $scope,
                'scope_derivation' => $scopeDerivation,
                'birth_date' => $this->birthDate($workerId),
                'employment_start_date' => $analysis['employment_start_date'],
                'employment_end_date' => $analysis['employment_end_date'],
                'employment_end_open' => $analysis['employment_end_open'],
                'continuous_employment_confirmed' => $analysis['continuous_employment_confirmed'],
                'monthly_work_days' => $monthlyDays,
                'monthly_work_minutes' => $monthlyMinutes,
                'monthly_income_amount' => $monthlyIncome,
                'transition_status_code' => $transitionStatus,
            ];
            $result = $this->resolver->resolve($context);
            if ($transitionStatus === 'CONFIRMATION_REQUIRED') {
                $result = $this->confirmation('project_contract_or_bid_date', 'PROJECT_TRANSITION_SOURCE_REQUIRED', $result['eligibility_revision_id'] ?? null);
            }
            if ($insuranceType === 'NATIONAL_PENSION' && $scope === 'CONSTRUCTION_SITE'
                && $attributionDate >= '2025-07-01' && ($result['status'] ?? null) === 'NOT_ELIGIBLE') {
                $result = $this->resolveConstructionAggregation($result, $context, $input, $workplaceAnalysis);
            }
            $results[$insuranceType] = $this->snapshot($result, $context, $analysis, $projectAnalysis, $workplaceAnalysis, $input);
        }
        $ltcContext = [
            'company_id' => $companyId,
            'worker_client_id' => $workerId,
            'attribution_date' => $attributionDate,
            'insurance_type_code' => 'LONG_TERM_CARE',
            'employment_type_code' => 'DAILY',
            'business_unit_code' => $scopeDerivation['business_unit_code'] ?? ($input['business_unit_code'] ?? null),
            'work_scope_code' => $scope,
            'scope_derivation' => $scopeDerivation,
            'dependent_result' => $results['HEALTH_INSURANCE'],
        ];
        $results['LONG_TERM_CARE'] = $this->snapshot(
            $this->resolver->resolve($ltcContext), $ltcContext, $analysis, $projectAnalysis, $workplaceAnalysis, $input
        );

        return $results;
    }

    private function employmentAnalysis(array $input, array $dates, string $workerId, string $scope, ?string $projectId): array
    {
        $first = $dates[0] ?? null;
        $last = $dates === [] ? null : $dates[count($dates) - 1];
        $month = $first === null ? '' : substr($first, 0, 7);
        $historicalClosed = $month !== '' && $month < date('Y-m');
        $hasAdjacent = $first !== null && $this->hasAdjacentConfirmedWorkday(
            trim((string)($input['company_id'] ?? '')), $workerId, $scope, $projectId, $first, (string)$last,
            trim((string)($input['document_id'] ?? '')) ?: null
        );
        $automaticShortTerm = $historicalClosed && !$hasAdjacent && $first !== null && $last !== null;
        return [
            'source_code' => 'DAILY_INCOME_WORKDAY_ANALYSIS',
            'first_work_date' => $first,
            'last_work_date' => $last,
            'employment_start_date' => $automaticShortTerm ? $first : null,
            'employment_end_date' => $automaticShortTerm ? $last : null,
            'employment_end_open' => $automaticShortTerm ? 0 : null,
            'continuous_employment_confirmed' => $automaticShortTerm ? 0 : null,
            'historical_closed_period' => $historicalClosed,
            'adjacent_confirmed_workday_exists' => $hasAdjacent,
            'automatic_short_term' => $automaticShortTerm,
        ];
    }

    private function hasAdjacentConfirmedWorkday(string $companyId, string $workerId, string $scope, ?string $projectId, string $first, string $last, ?string $excludeDocumentId): bool
    {
        if ($companyId === '' || $workerId === '') return true;
        $sql = "SELECT 1 FROM institution_daily_employment_income_workdays workday
            JOIN institution_daily_employment_income_items item ON item.id=workday.daily_employment_income_item_id
            JOIN institution_daily_employment_income_groups income_group ON income_group.id=item.daily_employment_income_group_id
            JOIN institution_daily_employment_incomes document ON document.id=income_group.daily_employment_income_id
            WHERE document.company_id=:company_id AND item.worker_client_id=:worker_id
              AND document.status_code='APPROVED'
              AND workday.work_date BETWEEN DATE_SUB(:first_date, INTERVAL 1 MONTH) AND DATE_ADD(:last_date, INTERVAL 1 MONTH)
              AND workday.work_date NOT BETWEEN :first_date_same AND :last_date_same
              AND ((:project_scope='CONSTRUCTION_SITE' AND income_group.project_id=:project_id)
                OR (:head_office_scope='HEAD_OFFICE' AND income_group.project_id IS NULL))";
        $params = ['company_id'=>$companyId,'worker_id'=>$workerId,'first_date'=>$first,'last_date'=>$last,
            'first_date_same'=>$first,'last_date_same'=>$last,'project_scope'=>$scope,'head_office_scope'=>$scope,'project_id'=>$projectId];
        if ($excludeDocumentId !== null) {
            $sql .= ' AND document.id<>:exclude_document_id';
            $params['exclude_document_id'] = $excludeDocumentId;
        }
        $statement = $this->db->prepare($sql . ' LIMIT 1');
        $statement->execute($params);
        return $statement->fetchColumn() !== false;
    }

    private function projectAnalysis(?string $projectId): array
    {
        if ($projectId === null) return ['project_id'=>null,'contract_date'=>null,'bid_notice_date'=>null,'source_code'=>null];
        $statement = $this->db->prepare('SELECT id,contract_date,bid_notice_date FROM system_projects WHERE id=:id AND deleted_at IS NULL');
        $statement->execute(['id'=>$projectId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['project_id'=>$projectId,'contract_date'=>$row['contract_date']??null,'bid_notice_date'=>$row['bid_notice_date']??null,
            'source_code'=>!empty($row['contract_date'])?'PROJECT_CONTRACT_DATE':(!empty($row['bid_notice_date'])?'PROJECT_BID_NOTICE_DATE':null)];
    }

    private function workplaceAnalysis(?string $workplaceId): array
    {
        if ($workplaceId === null) return ['workplace_id'=>null,'management_number'=>null];
        $statement=$this->db->prepare('SELECT id,management_number,project_id,effective_from,effective_to,confirmation_status_code FROM institution_social_insurance_workplaces WHERE id=:id');
        $statement->execute(['id'=>$workplaceId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: ['workplace_id'=>$workplaceId,'management_number'=>null];
    }

    private function transitionStatus(string $scope, string $date, array $project): ?string
    {
        if ($scope !== 'CONSTRUCTION_SITE' || $date < '2018-08-01' || $date > '2020-07-31') return null;
        $sourceDate = $project['contract_date'] ?: $project['bid_notice_date'];
        if (!$sourceDate) return 'CONFIRMATION_REQUIRED';
        return $sourceDate < '2018-08-01' ? 'TRANSITION_APPLICABLE' : 'TRANSITION_NOT_APPLICABLE';
    }

    private function resolveConstructionAggregation(array $siteResult, array $context, array $input, array $workplace): array
    {
        $managementNumber = trim((string)($workplace['management_number'] ?? ''));
        if ($managementNumber === '') return $this->confirmation('workplace_management_number', 'WORKPLACE_MANAGEMENT_NUMBER_REQUIRED', $siteResult['eligibility_revision_id'] ?? null);
        $dates=[];$minutes=0;$income=0.0;$workplaces=[];$projects=[];$itemIds=[];
        foreach ((array)($input['document_aggregate_candidates'] ?? []) as $candidate) {
            if ((string)($candidate['worker_client_id'] ?? '') !== (string)$context['worker_client_id']) continue;
            $candidateWorkplace = $this->workplaceAnalysis(trim((string)($candidate['social_insurance_workplace_id'] ?? '')) ?: null);
            if (trim((string)($candidateWorkplace['management_number'] ?? '')) !== $managementNumber) continue;
            $workplaces[(string)$candidateWorkplace['id']]=true;
            if (!empty($candidate['project_id'])) $projects[(string)$candidate['project_id']]=true;
            if (!empty($candidate['item_id'])) $itemIds[(string)$candidate['item_id']]=true;
            foreach ((array)($candidate['workdays'] ?? []) as $day) {
                $date=trim((string)($day['work_date']??''));
                if (substr($date,0,7)!==substr((string)$context['attribution_date'],0,7)) continue;
                $dates[$date]=true;$minutes+=(int)($day['actual_work_minutes']??0);$income+=(float)($day['gross_amount']??0);
            }
        }
        $aggregate=$context;
        $aggregate['monthly_work_days']=count($dates);$aggregate['monthly_work_minutes']=$minutes;$aggregate['monthly_income_amount']=round($income,2);
        $result=$this->resolver->resolve($aggregate);
        return $result + ['aggregation_scope_code'=>'CONSTRUCTION_MANAGEMENT_NUMBER','aggregation_workplace_ids'=>array_keys($workplaces),
            'aggregation_project_ids'=>array_keys($projects),'aggregation_item_ids'=>array_keys($itemIds)];
    }

    private function birthDate(string $workerId): ?string
    {
        if ($workerId === '') return null;
        $statement=$this->db->prepare('SELECT rrn FROM system_clients WHERE id=:id AND deleted_at IS NULL');$statement->execute(['id'=>$workerId]);
        $encrypted=$statement->fetchColumn();if(!is_string($encrypted)||trim($encrypted)==='')return null;
        try{$digits=preg_replace('/\D+/','',(new Crypto())->decryptResidentNumber($encrypted));}catch(\Throwable){return null;}
        if(!is_string($digits)||strlen($digits)<7)return null;$century=in_array($digits[6],['1','2','5','6'],true)?'19':'20';
        $date=$century.substr($digits,0,2).'-'.substr($digits,2,2).'-'.substr($digits,4,2);$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        return $parsed!==false&&$parsed->format('Y-m-d')===$date?$date:null;
    }

    private function confirmation(
        string $field,
        string $code,
        ?string $revisionId,
        string $reasonName = '가입자격 판정정보 확인 필요',
        string $reasonDetail = '가입자격 판정에 필요한 실제 정보를 확인해 주세요.'
    ): array
    {
        return ['status'=>'CONFIRMATION_REQUIRED','reason_code'=>'MISSING_ELIGIBILITY_INPUT','missing_inputs'=>[['field'=>$field,'code'=>$code]],
            'reason_name'=>$reasonName,'reason_detail'=>$reasonDetail,
            'eligibility_revision_id'=>$revisionId,'premium_revision_id'=>null];
    }

    private function snapshot(array $result,array $context,array $analysis,array $project,array $workplace,array $input): array
    {
        $workdayIds=[];$paymentLineIds=[];
        foreach((array)($input['workdays']??[]) as $day){if(!empty($day['id']))$workdayIds[]=(string)$day['id'];foreach((array)($day['payment_line_ids']??[]) as $id)$paymentLineIds[]=(string)$id;}
        return $result + ['snapshot_schema_version'=>'DAILY_INSURANCE_ELIGIBILITY_V2','employment_type_code'=>'DAILY',
            'business_unit_code'=>$context['business_unit_code']??null,'eligibility_work_scope_code'=>$context['work_scope_code']??null,
            'scope_derivation_snapshot'=>$context['scope_derivation']??null,'employment_analysis'=>$analysis,'analyzed_work_period'=>$analysis,'project_analysis'=>$project,
            'workplace_analysis'=>$workplace,'source_document_id'=>$input['document_id']??null,'source_group_id'=>$input['group_id']??null,
            'source_item_id'=>$input['item_id']??null,'source_workday_ids'=>array_values(array_unique($workdayIds)),
            'source_payment_line_ids'=>array_values(array_unique($paymentLineIds)),'aggregation_scope_code'=>$result['aggregation_scope_code']??'ITEM',
            'aggregation_item_ids'=>$result['aggregation_item_ids']??[],'aggregation_workplace_ids'=>$result['aggregation_workplace_ids']??[],
            'aggregation_project_ids'=>$result['aggregation_project_ids']??[],'evaluated_work_days'=>$result['used_work_days']??$context['monthly_work_days']??null,
            'evaluated_work_minutes'=>$result['used_work_minutes']??$context['monthly_work_minutes']??null,
            'evaluated_income_amount'=>$result['used_income_amount']??$context['monthly_income_amount']??null];
    }
}

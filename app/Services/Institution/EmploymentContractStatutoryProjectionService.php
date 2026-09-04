<?php

namespace App\Services\Institution;

use App\Models\Institution\EmploymentContractComponentModel;
use App\Models\Institution\EmploymentContractModel;
use App\Models\Institution\EmploymentContractWeeklyScheduleModel;
use App\Models\System\StatutoryStandardModel;
use App\Services\System\StatutoryStandardResolver;
use PDO;

class EmploymentContractStatutoryProjectionService
{
    private EmploymentContractModel $contracts;
    private EmploymentContractComponentModel $components;
    private EmploymentContractWeeklyScheduleModel $schedules;
    private StatutoryStandardResolver $resolver;
    private StatutoryStandardModel $standards;

    public function __construct(PDO $pdo)
    {
        $this->contracts = new EmploymentContractModel($pdo);
        $this->components = new EmploymentContractComponentModel($pdo);
        $this->schedules = new EmploymentContractWeeklyScheduleModel($pdo);
        $this->resolver = new StatutoryStandardResolver($pdo);
        $this->standards = new StatutoryStandardModel($pdo);
    }

    public function project(string $contractId, bool $forUpdate = false): array
    {
        $contract = $this->contracts->find($contractId, false, $forUpdate);
        if (!$contract) {
            throw new \RuntimeException('법정기준을 검증할 근로계약을 찾을 수 없습니다.');
        }

        return $this->evaluate(
            $contract,
            $this->components->activeForContract($contractId, $forUpdate),
            $this->schedules->forContract($contractId, $forUpdate)
        );
    }

    public function evaluate(array $contract, array $components, array $schedules): array
    {
        $basisDate = trim((string) ($contract['contract_start_date'] ?? ''));
        $standard = $this->resolver->resolveOptional('MINIMUM_WAGE', $basisDate);
        $minimumWage = $standard === null ? null : ($standard['value_data']['hourly_wage'] ?? null);
        $base = $this->baseComponent($components);
        $contractRate = is_numeric($base['rate'] ?? null) ? (float) $base['rate'] : null;
        $reviewRequired = array_values(array_filter(
            $components,
            static fn(array $row): bool => (string) ($row['minimum_wage_treatment'] ?? '') === 'REVIEW_REQUIRED'
        ));
        $difference = is_numeric($minimumWage) && $contractRate !== null
            ? round($contractRate - (float) $minimumWage, 4)
            : null;

        if (!is_numeric($minimumWage) || (float) $minimumWage <= 0 || $contractRate === null) {
            $status = 'NOT_VERIFIABLE';
            $message = '계약 기준일의 최저임금 또는 계약 계산단가가 없어 자동 검증할 수 없습니다.';
        } elseif ($difference < 0) {
            $status = 'WARNING';
            $message = $reviewRequired === []
                ? '계약 계산단가가 당시 최저임금보다 낮습니다.'
                : '계약 계산단가가 당시 최저임금보다 낮고 산입범위 확인이 필요한 지급항목이 있습니다.';
        } elseif ($reviewRequired !== []) {
            $status = 'NOT_VERIFIABLE';
            $message = '최저임금 산입범위 확인이 필요한 지급항목이 있어 최종 판정할 수 없습니다.';
        } else {
            $status = 'COMPLIANT';
            $message = '계약 계산단가가 당시 시간급 최저임금 이상입니다.';
        }

        $createdDate = substr((string) ($contract['created_at'] ?? ''), 0, 10);
        $historical = $createdDate !== '' && $basisDate < $createdDate;
        $explicitViolation = $status === 'WARNING' && $reviewRequired === [];
        $standardDetail = $standard === null ? null : $this->standards->detail((string) $standard['id']);

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'COMPLIANT' => '충족 확인',
                'WARNING' => '미달 가능성',
                default => '검증 불가',
            },
            'message' => $message,
            'basis_date' => $basisDate,
            'contract_classification' => $historical ? 'HISTORICAL_ENTRY' : 'CURRENT_NEW',
            'historical_snapshot' => $historical,
            'approval_blocked' => !$historical && $explicitViolation,
            'minimum_wage' => is_numeric($minimumWage) ? (float) $minimumWage : null,
            'contract_calculation_rate' => $contractRate,
            'contract_calculation_rate_label' => '기본급 산식의 계약 계산단가',
            'difference' => $difference,
            'minimum_wage_review_components' => array_map(
                static fn(array $row): array => [
                    'component_code' => (string) ($row['component_code'] ?? ''),
                    'component_name' => (string) ($row['component_name'] ?? ''),
                ],
                $reviewRequired
            ),
            'checks' => [
                'minimum_wage' => ['status' => $status, 'message' => $message],
                'overtime' => $this->notVerifiable('연장근로 법정기준 Revision이 없습니다.'),
                'break_time' => $this->notVerifiable('휴게시간 법정기준 Revision이 없습니다.'),
                'weekly_working_hours' => $this->notVerifiable('주 근로시간 법정기준 Revision이 없습니다.'),
                'probation' => $this->notVerifiable('수습기간 최저임금 감액 법정기준 Revision이 없습니다.'),
            ],
            'schedule_row_count' => count($schedules),
            'standard' => $standardDetail === null ? null : [
                'id' => (string) $standardDetail['id'],
                'type' => (string) $standardDetail['standard_type_code'],
                'revision' => (int) ($standardDetail['sort_no'] ?? 0),
                'effective_from' => (string) $standardDetail['effective_from'],
                'effective_to' => $standardDetail['effective_to'],
                'sources' => array_map(static fn(array $source): array => [
                    'source_name' => $source['source_name'],
                    'organization_name' => $source['organization_name'],
                    'law_name' => $source['law_name'],
                    'notice_no' => $source['notice_no'],
                    'published_at' => $source['published_at'],
                    'source_url' => $source['source_url'],
                    'note' => $source['note'],
                ], $standardDetail['sources'] ?? []),
            ],
            'snapshot_notice' => $historical
                ? '과거 실제계약 — 계약내용과 금액은 자동 변경하지 않습니다.'
                : '현재 신규계약은 명백한 법정기준 위반 시 결재요청이 차단됩니다.',
        ];
    }

    private function baseComponent(array $components): array
    {
        foreach ($components as $component) {
            if ((string) ($component['component_type'] ?? '') === 'BASE_PAY') {
                return $component;
            }
        }
        return [];
    }

    private function notVerifiable(string $message): array
    {
        return ['status' => 'NOT_VERIFIABLE', 'message' => $message];
    }
}

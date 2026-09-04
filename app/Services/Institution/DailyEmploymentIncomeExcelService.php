<?php

declare(strict_types=1);

namespace App\Services\Institution;

use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PDO;
use Psr\Log\LoggerInterface;

final class DailyEmploymentIncomeExcelService
{
    private LoggerInterface $logger;
    private const BASE_COLUMNS = [
        'income_year_month' => '귀속연월',
        'group_no' => '그룹번호',
        'business_unit' => '사업구분코드',
        'project_id' => '프로젝트ID',
        'work_team_id' => '작업팀ID',
        'group_work_description' => 'Group 작업내용',
        'employment_insurance_application_status_code' => '고용보험 회사부담',
        'industrial_accident_application_status_code' => '산재보험 회사부담',
        'group_sort_no' => '그룹순서',
        'worker_code' => '작업자코드',
        'worker_id' => '작업자ID',
        'worker_name' => '작업자명',
        'work_type_code' => '공종코드',
        'worker_work_description' => '작업자 작업내용',
        'daily_rate_amount' => '단가',
    ];

    public function __construct(private readonly PDO $db)
    {
        $this->logger = LoggerFactory::getLogger('service-institution-daily-employment-income-excel');
    }

    public function createTemplate(): Spreadsheet
    {
        return $this->logged('DAILY_INCOME_EXCEL_TEMPLATE', 'template', [], fn(): Spreadsheet => $this->createTemplateInternal());
    }

    private function createTemplateInternal(): Spreadsheet
    {
        return $this->spreadsheet('일용근로소득 입력양식', [[
            'group_no' => 1,
            'business_unit' => 'CONSTRUCTION',
            'group_work_description' => '외벽 석재 붙임공사',
            'worker_work_description' => '석재 재단 및 붙임',
            'group_sort_no' => 1,
            'work_type_code' => 'STONE',
            'daily_rate_amount' => 150000,
            'day_1' => 150000,
        ]]);
    }

    public function createDownload(array $groups, array $header = []): Spreadsheet
    {
        return $this->logged('DAILY_INCOME_EXCEL_DOWNLOAD', 'download', ['group_count' => count($groups)], fn(): Spreadsheet => $this->createDownloadInternal($groups, $header));
    }

    private function createDownloadInternal(array $groups, array $header = []): Spreadsheet
    {
        $rows = [];
        foreach (array_values($groups) as $groupIndex => $group) {
            foreach (array_values(is_array($group['items'] ?? null) ? $group['items'] : []) as $item) {
                $workdays = array_values(is_array($item['workdays'] ?? null) ? $item['workdays'] : []);
                $taxableAdjustment = array_sum(array_map(static fn(array $day): float => (float) ($day['taxable_additional_amount'] ?? $day['allowance_amount'] ?? 0), $workdays));
                $nonTaxableAdjustment = array_sum(array_map(static fn(array $day): float => (float) ($day['non_taxable_additional_amount'] ?? $day['non_taxable_amount'] ?? 0), $workdays));
                $nonTaxableReason = '';
                foreach ($workdays as $workday) {
                    if ($nonTaxableReason === '') $nonTaxableReason = trim((string) ($workday['non_taxable_reason'] ?? ''));
                }
                $row = [
                    'income_year_month' => $header['income_year_month'] ?? '',
                    'group_no' => $groupIndex + 1,
                    'business_unit' => $group['business_unit'] ?? '',
                    'project_id' => $group['project_id'] ?? '',
                    'work_team_id' => $group['work_team_id'] ?? '',
                    'group_work_description' => $group['work_description'] ?? '',
                    'employment_insurance_application_status_code' => $group['employment_insurance_application_status_code'] ?? '',
                    'industrial_accident_application_status_code' => $group['industrial_accident_application_status_code'] ?? '',
                    'group_sort_no' => $groupIndex + 1,
                    'worker_code' => $item['worker_code'] ?? '',
                    'worker_id' => $item['worker_client_id'] ?? '',
                    'worker_name' => $item['worker_name'] ?? '',
                    'work_type_code' => $item['work_type_code'] ?? '',
                    'worker_work_description' => $item['work_description'] ?? '',
                    'daily_rate_amount' => $item['daily_rate_amount'] ?? 0,
                    'taxable_adjustment_amount' => $taxableAdjustment,
                    'non_taxable_adjustment_amount' => $nonTaxableAdjustment,
                    'non_taxable_reason' => $nonTaxableReason,
                ];
                foreach ($workdays as $workday) {
                    $day = (int) substr((string) ($workday['work_date'] ?? ''), -2);
                    if ($day >= 1 && $day <= 31) {
                        $row['day_' . $day] = $workday['daily_rate_amount'] ?? true;
                        $row['day_' . $day . '_actual_work_minutes'] = $workday['actual_work_minutes'] ?? '';
                        $row['day_' . $day . '_calculation_note'] = $workday['calculation_note'] ?? '';
                    }
                }
                $rows[] = $row;
            }
        }
        return $this->spreadsheet('일용근로소득 문서', $rows);
    }

    public function preview(string $filePath, string $incomeYearMonth): array
    {
        return $this->logged('DAILY_INCOME_EXCEL_PREVIEW', 'preview', ['income_year_month' => $incomeYearMonth], fn(): array => $this->previewInternal($filePath, $incomeYearMonth));
    }

    private function previewInternal(string $filePath, string $incomeYearMonth): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $incomeYearMonth)) {
            throw new \InvalidArgumentException('귀속연월을 먼저 선택해 주세요.');
        }
        $sheet = IOFactory::load($filePath)->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_map(static fn($value): string => trim((string) $value), array_shift($sheet) ?: []);
        $keyByHeader = array_flip($this->columns());
        $errors = [];
        $warnings = [];
        $parsed = [];
        foreach ($sheet as $offset => $cells) {
            if (count(array_filter($cells, static fn($value): bool => trim((string) $value) !== '')) === 0) continue;
            $row = [];
            foreach ($headers as $columnIndex => $header) {
                if (isset($keyByHeader[$header])) $row[$keyByHeader[$header]] = $cells[$columnIndex] ?? null;
            }
            $rowNumber = $offset + 2;
            foreach (['group_no', 'business_unit', 'group_work_description', 'worker_work_description', 'work_type_code', 'daily_rate_amount'] as $required) {
                if (trim((string) ($row[$required] ?? '')) === '') $errors[] = ['row' => $rowNumber, 'field' => $required, 'message' => '필수값이 없습니다.'];
            }
            if (trim((string) ($row['worker_code'] ?? '')) === '' && trim((string) ($row['worker_id'] ?? '')) === '') {
                $errors[] = ['row' => $rowNumber, 'field' => 'worker_code', 'message' => '작업자코드 또는 작업자ID가 필요합니다.'];
            }
            $row['__row_number'] = $rowNumber;
            $parsed[] = $row;
        }
        $references = $this->references();
        $referenceModel = new \App\Models\Institution\DailyEmploymentIncomeModel($this->db);
        $companyId = $referenceModel->companyId();
        $groups = [];
        foreach ($parsed as $row) {
            $groupNo = trim((string) ($row['group_no'] ?? ''));
            $businessUnit = strtoupper(trim((string) ($row['business_unit'] ?? '')));
            $projectId = trim((string) ($row['project_id'] ?? ''));
            $workTeamId = trim((string) ($row['work_team_id'] ?? ''));
            $businessPolicyRow = $references['business_units'][$businessUnit] ?? null;
            if ($businessPolicyRow === null) {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'business_unit', 'message' => '유효한 사업구분코드가 아닙니다.'];
                continue;
            }
            $policy = (new DailyEmploymentIncomeBusinessUnitPolicyService())->fromCodeRow($businessPolicyRow);
            $insurance = [];
            $automaticBurden = in_array($businessUnit, ['HQ', 'ECOMMERCE'], true);
            foreach (['employment_insurance'=>'고용보험','industrial_accident'=>'산재보험'] as $prefix=>$label) {
                $status = $automaticBurden ? 'APPLICABLE' : strtoupper(trim((string) ($row[$prefix . '_application_status_code'] ?? '')));
                if (!in_array($status, ['APPLICABLE','EXCLUDED'], true)) $errors[] = ['row'=>$row['__row_number'],'field'=>$prefix . '_application_status_code','message'=>$label . ' 회사부담 여부는 우리 회사 부담 또는 우리 회사 미부담으로 입력해 주세요.'];
                $insurance[$prefix . '_application_status_code'] = $status;
                $insurance[$prefix . '_decision_reason'] = null;
                $insurance[$prefix . '_decision_source_code'] = $automaticBurden ? 'BUSINESS_DIVISION_POLICY' : 'DAILY_GROUP_MANUAL_SETTING';
            }
            if (!$policy['uses_project'] && $projectId !== '') {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'project_id', 'message' => '이 사업구분에는 프로젝트를 적용할 수 없습니다.'];
            }
            if ($policy['requires_project'] && $projectId === '') {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'project_id', 'message' => '프로젝트가 필요합니다.'];
            }
            if ($projectId !== '' && ($references['projects'][$projectId]['business_unit'] ?? null) !== $businessUnit) {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'project_id', 'message' => '프로젝트의 사업구분이 일치하지 않습니다.'];
            }
            if (!$policy['uses_work_team'] && $workTeamId !== '') {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'work_team_id', 'message' => '이 사업구분에는 작업팀을 적용할 수 없습니다.'];
            }
            if ($policy['requires_work_team'] && $workTeamId === '') {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'work_team_id', 'message' => '작업팀이 필요합니다.'];
            }
            if ($workTeamId !== '' && ($references['work_teams'][$workTeamId]['business_unit'] ?? null) !== $businessUnit) {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'work_team_id', 'message' => '작업팀의 사업구분이 일치하지 않습니다.'];
            }
            $groupDescription = trim((string) ($row['group_work_description'] ?? ''));
            $signature = implode('|', [$businessUnit, $projectId, $workTeamId, $groupDescription]);
            if (isset($groups[$groupNo]) && $groups[$groupNo]['signature'] !== $signature) {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'group_no', 'message' => '같은 그룹번호의 그룹 조건이 서로 다릅니다.'];
                continue;
            }
            $worker = $this->resolveWorker($row, $references['workers']);
            if ($worker === null) {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'worker_code', 'message' => '활성 거래처를 찾을 수 없습니다.'];
                continue;
            }
            $workType = strtoupper(trim((string) ($row['work_type_code'] ?? '')));
            if (!isset($references['work_types'][$workType])) {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'work_type_code', 'message' => '유효한 공종코드가 아닙니다.'];
                continue;
            }
            if ((float) ($row['non_taxable_adjustment_amount'] ?? 0) !== 0.0) {
                if (trim((string) ($row['non_taxable_reason'] ?? '')) === '') {
                    $errors[] = ['row' => $row['__row_number'], 'field' => 'non_taxable_reason', 'message' => '비과세 적용사유가 필요합니다.'];
                }
            }
            $groups[$groupNo] ??= [
                'signature' => $signature,
                'client_key' => 'excel-group-' . $groupNo,
                'business_unit' => $businessUnit,
                'project_id' => $projectId ?: null,
                'work_team_id' => $workTeamId ?: null,
                'work_description' => $groupDescription,
                ...$insurance,
                'items' => [],
            ];
            if (array_filter($groups[$groupNo]['items'], static fn(array $item): bool => (string) $item['worker_client_id'] === (string) $worker['id']) !== []) {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'worker_id', 'message' => '같은 그룹에 동일 작업자를 중복 등록할 수 없습니다.'];
                continue;
            }
            $workdays = [];
            for ($day = 1; $day <= 31; $day++) {
                $value = trim((string) ($row['day_' . $day] ?? ''));
                if ($value === '') continue;
                $minutes = trim((string) ($row['day_' . $day . '_actual_work_minutes'] ?? ''));
                if (!preg_match('/^[0-9]+$/', $minutes) || (int) $minutes < 1 || (int) $minutes > 1440) {
                    $errors[] = ['row' => $row['__row_number'], 'field' => 'day_' . $day . '_actual_work_minutes', 'message' => '실제근로시간(휴게시간 제외)은 1~1,440분 정수로 입력해 주세요.'];
                    continue;
                }
                $date = sprintf('%s-%02d', $incomeYearMonth, $day);
                if (substr($date, 0, 7) !== date('Y-m', strtotime($date))) {
                    $errors[] = ['row' => $row['__row_number'], 'field' => 'day_' . $day, 'message' => '귀속월에 존재하지 않는 날짜입니다.'];
                    continue;
                }
                $workdays[] = [
                    'work_date' => $date,
                    'actual_work_minutes' => (int) $minutes,
                    'work_quantity' => 1,
                    'daily_rate_amount' => is_numeric($value) ? (float) $value : (float) $row['daily_rate_amount'],
                    'calculation_note' => $this->calculationNote($row['day_' . $day . '_calculation_note'] ?? null, $row['__row_number'], $errors),
                ];
            }
            if ($workdays === []) {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'workdays', 'message' => '근무일을 한 건 이상 입력해 주세요.'];
                continue;
            }
            $workdays[0]['taxable_additional_amount'] = (float) ($row['taxable_adjustment_amount'] ?? 0);
            $workdays[0]['non_taxable_additional_amount'] = (float) ($row['non_taxable_adjustment_amount'] ?? 0);
            $workdays[0]['non_taxable_reason'] = trim((string) ($row['non_taxable_reason'] ?? ''));
            try {
                foreach ($workdays as $workday) {
                    $referenceModel->assertGroupReferences(
                        $companyId,
                        $businessUnit,
                        $projectId ?: null,
                        $workTeamId ?: null,
                        (string) $worker['id'],
                        (string) $workday['work_date']
                    );
                }
            } catch (\InvalidArgumentException $exception) {
                $errors[] = ['row' => $row['__row_number'], 'field' => 'work_scope', 'message' => $exception->getMessage()];
                continue;
            }
            $groups[$groupNo]['items'][] = [
                'client_key' => 'excel-row-' . $row['__row_number'],
                'worker_client_id' => $worker['id'],
                'worker_name' => $worker['name'],
                'work_type_code' => $workType,
                'work_description' => trim((string) $row['worker_work_description']),
                'daily_rate_amount' => (float) $row['daily_rate_amount'],
                'workdays' => $workdays,
            ];
        }
        $resultGroups = array_values(array_map(static function (array $group): array {
            unset($group['signature']);
            return $group;
        }, $groups));
        $minutesByWorkerDate = [];
        $occurrenceCountByWorkerDate = [];
        foreach ($resultGroups as $group) {
            foreach ($group['items'] as $item) {
                foreach ($item['workdays'] as $workday) {
                    $key = (string) $item['worker_client_id'] . '|' . (string) $workday['work_date'];
                    $minutesByWorkerDate[$key] = ($minutesByWorkerDate[$key] ?? 0) + (int) $workday['actual_work_minutes'];
                    $occurrenceCountByWorkerDate[$key] = ($occurrenceCountByWorkerDate[$key] ?? 0) + 1;
                }
            }
        }
        foreach ($minutesByWorkerDate as $key => $minutes) {
            [, $workDate] = explode('|', $key, 2);
            if ($minutes > 1440) {
                $errors[] = ['row' => null, 'field' => 'actual_work_minutes', 'message' => $workDate . ' 동일 근로자의 문서 전체 실제근로시간 합계는 1,440분을 초과할 수 없습니다.'];
            } elseif (($occurrenceCountByWorkerDate[$key] ?? 0) > 1) {
                $warnings[] = ['row' => null, 'field' => 'actual_work_minutes', 'message' => $workDate . ' 같은 근로자가 여러 근무그룹에 포함되어 있습니다. 시간대 중복 여부를 확인해 주세요.'];
            }
        }
        return [
            'success' => true,
            'data' => [
                'valid' => $errors === [],
                'groups' => $resultGroups,
                'rows' => $parsed,
                'errors' => $errors,
                'warnings' => $warnings,
                'summary' => ['group_count' => count($resultGroups), 'row_count' => count($parsed), 'error_count' => count($errors), 'warning_count' => count($warnings)],
            ],
            'message' => $errors === [] ? '엑셀 검증이 완료되었습니다.' : '엑셀 검증 오류를 확인해 주세요.',
        ];
    }

    private function logged(string $eventCode, string $action, array $context, callable $operation): mixed
    {
        try {
            $result = $operation();
            $this->logger->info('일용근로소득 엑셀 처리를 완료했습니다.', ['event_code' => $eventCode, 'result' => 'SUCCESS', 'action' => $action] + $context);
            return $result;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->logger->warning('일용근로소득 엑셀 처리가 차단되었습니다.', ['event_code' => $eventCode . '_BLOCKED', 'result' => 'BLOCKED', 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('일용근로소득 엑셀 처리에 실패했습니다.', ['event_code' => $eventCode . '_FAILED', 'result' => 'FAILED', 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context);
            throw $exception;
        }
    }

    private function columns(): array
    {
        $columns = self::BASE_COLUMNS;
        for ($day = 1; $day <= 31; $day++) {
            $columns['day_' . $day] = $day . '일 단가';
            $columns['day_' . $day . '_actual_work_minutes'] = $day . '일 실제근로시간(휴게시간 제외)';
            $columns['day_' . $day . '_calculation_note'] = $day . '일 산정내역';
        }
        return $columns + [
            'taxable_adjustment_amount' => '과세증감',
            'non_taxable_adjustment_amount' => '비과세증감',
            'non_taxable_reason' => '비과세 적용사유',
        ];
    }

    private function spreadsheet(string $title, array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $columns = $this->columns();
        $sheet->fromArray(array_values($columns), null, 'A1');
        $line = 2;
        foreach ($rows as $row) $sheet->fromArray(array_map(static fn($key) => $row[$key] ?? '', array_keys($columns)), null, 'A' . $line++);
        $sheet->freezePane('A2');
        return $spreadsheet;
    }

    private function references(): array
    {
        $workers = $this->db->query('SELECT id, id AS code, client_name AS name FROM system_clients WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no, client_name, id')->fetchAll(PDO::FETCH_ASSOC);
        $workTypes = $this->db->query("SELECT code FROM system_codes WHERE code_group='WORK_TYPE' AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
        $businessUnits = $this->db->query("SELECT code, code_name, sort_no, extra_data FROM system_codes WHERE code_group='BUSINESS_UNIT' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);
        $projects = $this->db->query('SELECT id, business_unit FROM system_projects WHERE is_active=1 AND deleted_at IS NULL')->fetchAll(PDO::FETCH_ASSOC);
        $workTeams = $this->db->query('SELECT id, business_unit FROM system_work_teams WHERE is_active=1 AND deleted_at IS NULL')->fetchAll(PDO::FETCH_ASSOC);
        return [
            'workers' => $workers,
            'work_types' => array_fill_keys($workTypes, true),
            'business_units' => array_column($businessUnits, null, 'code'),
            'projects' => array_column($projects, null, 'id'),
            'work_teams' => array_column($workTeams, null, 'id'),
        ];
    }

    private function calculationNote(mixed $value, int $rowNumber, array &$errors): ?string
    {
        $note = trim((string) $value);
        if ($note === '') return null;
        if (mb_strlen($note) > 500) {
            $errors[] = ['row' => $rowNumber, 'field' => 'calculation_note', 'message' => '산정내역은 500자 이하로 입력해 주세요.'];
            return null;
        }
        return $note;
    }

    private function resolveWorker(array $row, array $workers): ?array
    {
        $id = trim((string) ($row['worker_id'] ?? ''));
        $code = trim((string) ($row['worker_code'] ?? ''));
        foreach ($workers as $worker) {
            if ($id !== '' && $id === (string) $worker['id']) return $worker;
            if ($id === '' && $code !== '' && $code === (string) $worker['code']) return $worker;
        }
        return null;
    }
}

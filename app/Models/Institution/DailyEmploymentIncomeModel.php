<?php

namespace App\Models\Institution;

use Core\Helpers\ActorHelper;
use App\Services\Institution\DailyEmploymentIncomeLineContractService;
use App\Services\Institution\DailyEmploymentIncomeScopeKeyService;
use App\Services\Institution\IncomeCalculationCodeService;
use PDO;

final class DailyEmploymentIncomeModel
{
    private DailyEmploymentIncomeLineContractService $lineContract;
    private IncomeCalculationCodeService $incomeCodes;
    private DailyEmploymentIncomeScopeKeyService $scopeKeys;

    public function __construct(private readonly PDO $db)
    {
        $this->lineContract = new DailyEmploymentIncomeLineContractService();
        $this->incomeCodes = new IncomeCalculationCodeService($db);
        $this->scopeKeys = new DailyEmploymentIncomeScopeKeyService();
    }

    public function find(string $id, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM institution_daily_employment_incomes WHERE id = :id AND deleted_at IS NULL' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $this->db->prepare($sql);
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function companyId(): string
    {
        $ids = $this->db->query('SELECT id FROM system_company ORDER BY created_at, id')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($ids) !== 1) throw new \RuntimeException('회사 SSOT를 하나로 확정할 수 없습니다.');
        return (string) $ids[0];
    }

    public function nextSortNo(): int
    {
        return max(1, (int) $this->db->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM institution_daily_employment_incomes')->fetchColumn());
    }

    public function findCommand(string $requestKey, bool $forUpdate = false): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM institution_daily_employment_income_commands WHERE request_key=:request_key LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute([':request_key' => $requestKey]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function latestCompletedCommand(string $documentId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT * FROM institution_daily_employment_income_commands"
            . " WHERE daily_employment_income_id=:document_id AND command_status='COMPLETED'"
            . ' ORDER BY completed_at DESC,created_at DESC,id DESC LIMIT 1'
        );
        $statement->execute([':document_id' => $documentId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function approvalTemplateReadiness(string $documentType): array
    {
        $template = $this->db->prepare(
            'SELECT id FROM user_approval_templates WHERE document_type=:document_type AND is_active=1 ORDER BY sort_no,id'
        );
        $template->execute([':document_type' => $documentType]);
        $templateIds = $template->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($templateIds) !== 1) {
            return ['ready' => false, 'template_count' => count($templateIds), 'approval_step_count' => 0];
        }
        $steps = $this->db->prepare(
            "SELECT COUNT(*) FROM user_approval_template_steps WHERE template_id=:template_id AND is_active=1 AND step_type IN ('APPROVAL','FINAL_APPROVAL') AND (approver_id IS NOT NULL OR role_id IS NOT NULL)"
        );
        $steps->execute([':template_id' => $templateIds[0]]);
        $stepCount = (int) $steps->fetchColumn();
        return ['ready' => $stepCount > 0, 'template_count' => 1, 'approval_step_count' => $stepCount];
    }

    public function activeApprovalRequest(string $documentType, string $documentId, bool $forUpdate = false): ?array
    {
        $statement = $this->db->prepare(
            "SELECT * FROM user_approval_requests WHERE document_type=:document_type AND document_id=:document_id AND is_active=1 AND status IN ('pending','in_progress') ORDER BY requested_at DESC,id DESC LIMIT 1"
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute([':document_type' => $documentType, ':document_id' => $documentId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertCommand(array $row): void { $this->insert('institution_daily_employment_income_commands', $row); }
    public function insertHeader(array $row): void { $this->insert('institution_daily_employment_incomes', $row); }

    public function updateHeader(string $id, array $row): void
    {
        $sets = []; $params = [':id' => $id];
        foreach ($row as $key => $value) { $sets[] = "`{$key}`=:{$key}"; $params[":{$key}"] = $value; }
        $statement = $this->db->prepare('UPDATE institution_daily_employment_incomes SET ' . implode(',', $sets) . ' WHERE id=:id');
        $statement->execute($params);
    }

    public function updateWorkflow(string $id, string $status, string $approvalRequestId, string $actor, bool $approved = false): void
    {
        $values = [
            'status_code' => strtoupper($status),
            'approval_request_id' => $approvalRequestId,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actor,
        ];
        if ($approved) {
            $values['approved_by'] = $actor;
            $values['approved_at'] = date('Y-m-d H:i:s');
        }
        $this->updateHeader($id, $values);
    }

    public function completeCommand(string $requestKey, int $version, string $referenceId): void
    {
        $statement = $this->db->prepare("UPDATE institution_daily_employment_income_commands SET command_status='COMPLETED',result_version=:version,result_reference_id=:reference_id,completed_at=:completed_at WHERE request_key=:request_key AND command_status='PROCESSING'");
        $statement->execute([':version' => $version, ':reference_id' => $referenceId, ':completed_at' => date('Y-m-d H:i:s'), ':request_key' => $requestKey]);
        if ($statement->rowCount() !== 1) throw new \RuntimeException('저장 명령 완료상태를 기록하지 못했습니다.');
    }

    public function replaceAggregate(string $headerId, array $groups, string $actor): array
    {
        $persisted = ['groups' => [], 'items' => [], 'workdays' => [], 'lines' => []];
        $itemIds = $this->db->prepare('SELECT i.id FROM institution_daily_employment_income_items i JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id WHERE g.daily_employment_income_id=:id FOR UPDATE');
        $itemIds->execute([':id' => $headerId]);
        $ids = $itemIds->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if ($ids !== []) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $this->db->prepare("DELETE FROM institution_daily_employment_income_lines WHERE daily_employment_income_item_id IN ({$marks})")->execute($ids);
            $this->db->prepare("DELETE FROM institution_daily_employment_income_workdays WHERE daily_employment_income_item_id IN ({$marks})")->execute($ids);
        }
        if($ids!==[])$this->db->prepare('DELETE FROM institution_daily_employment_income_items WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')')->execute($ids);
        $this->db->prepare('DELETE FROM institution_daily_employment_income_groups WHERE daily_employment_income_id=:id')->execute([':id'=>$headerId]);
        foreach(array_values($groups) as $groupIndex=>$group){
          $groupId=\Core\Helpers\UuidHelper::generate();
          $persisted['groups'][$groupIndex] = $groupId;
          $this->insert('institution_daily_employment_income_groups',[
            'id'=>$groupId,'daily_employment_income_id'=>$headerId,'sort_no'=>$groupIndex+1,'business_unit'=>$group['business_unit'],
            'project_id'=>$group['project_id'],'work_team_id'=>$group['work_team_id'],
            'work_description'=>$group['work_description'],
            'employment_insurance_application_status_code'=>$group['employment_insurance_application_status_code'],
            'employment_insurance_decision_reason'=>$group['employment_insurance_decision_reason'],
            'employment_insurance_decision_source_code_id'=>$group['employment_insurance_decision_source_code_id'],
            'industrial_accident_application_status_code'=>$group['industrial_accident_application_status_code'],
            'industrial_accident_decision_reason'=>$group['industrial_accident_decision_reason'],
            'industrial_accident_decision_source_code_id'=>$group['industrial_accident_decision_source_code_id'],
            'created_by'=>$actor,'updated_by'=>$actor,
          ]);
          foreach(array_values($group['items']) as $itemIndex=>$item){
            $worker=$this->worker((string)$item['worker_client_id']);$itemId=\Core\Helpers\UuidHelper::generate();$summary=$item['summary'];
            $persisted['items'][$groupIndex][$itemIndex] = $itemId;
            $this->insert('institution_daily_employment_income_items', [
                'id'=>$itemId,'daily_employment_income_group_id'=>$groupId,'sort_no'=>$itemIndex+1,'worker_client_id'=>$item['worker_client_id'],
                'worker_name_snapshot' => $worker['client_name'], 'worker_registration_number_snapshot' => null,
                'work_type_code' => $item['work_type_code'], 'work_description' => $item['work_description'],
                'total_work_days' => $summary['total_work_days'], 'total_gross_amount' => $summary['total_gross_amount'],
                'total_deduction_amount' => $summary['total_deduction_amount'], 'total_net_payment_amount' => $summary['total_net_payment_amount'],
                'total_employer_burden_amount' => $summary['total_employer_burden_amount'], 'created_by' => $actor, 'updated_by' => $actor,
            ]);
            foreach($item['workdays'] as $day){
                $workdayId = \Core\Helpers\UuidHelper::generate();
                $persisted['workdays'][$groupIndex][$itemIndex][] = $workdayId;
                $this->insert('institution_daily_employment_income_workdays', [
                    'id' => $workdayId, 'daily_employment_income_item_id' => $itemId, 'work_date' => $day['work_date'],
                    'actual_work_minutes' => $day['actual_work_minutes'], 'work_quantity' => 1,
                    'daily_rate_amount' => $day['daily_rate_amount'], 'base_pay_amount' => $day['base_pay_amount'],
                    'allowance_amount' => $day['allowance_amount'],
                    'non_taxable_amount' => $day['non_taxable_amount'], 'non_taxable_reason' => $day['non_taxable_reason'] ?? null,
                    'calculation_note' => $day['calculation_note'] ?? null,
                    'taxable_amount' => $day['taxable_amount'],
                    'income_tax_amount' => $day['income_tax_amount'], 'local_income_tax_amount' => $day['local_income_tax_amount'],
                    'worker_social_insurance_amount' => $day['worker_social_insurance_amount'], 'employer_social_insurance_amount' => $day['employer_social_insurance_amount'],
                    'net_payment_amount' => $day['net_payment_amount'], 'work_team_assignment_id' => $day['work_team_assignment_id'],
                    'social_insurance_workplace_id' => $day['social_insurance_workplace_id'],
                    'calculation_status_code' => $day['calculation_status_code'] ?? 'CONFIRMATION_REQUIRED',
                    'created_by' => $actor, 'updated_by' => $actor,
                ]);
                foreach (array_values($day['lines']) as $lineIndex => $line) {
                    $this->lineContract->assertGrain(
                        (string) $line['line_type_code'],
                        (string) $line['line_code'],
                        $workdayId
                    );
                    $this->incomeCodes->assertIdInGroup(
                        $line['statutory_calculation_source_code_id'] ?? null,
                        IncomeCalculationCodeService::STATUTORY_SOURCE_GROUP
                    );
                    $this->incomeCodes->assertIdInGroup(
                        $line['actual_application_source_code_id'] ?? null,
                        IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP
                    );
                    $lineId = \Core\Helpers\UuidHelper::generate();
                    $persisted['lines'][$groupIndex][$itemIndex][] = ['id'=>$lineId,'line_type_code'=>$line['line_type_code'],'line_code'=>$line['line_code']];
                    $this->insert('institution_daily_employment_income_lines', [
                        'id' => $lineId, 'daily_employment_income_item_id' => $itemId,
                        'daily_employment_income_workday_id' => $workdayId,
                    ] + $this->scopeKeys->lineKeys($itemId, $workdayId, $line['non_taxable_revision_id'] ?? null, $line['effective_from'] ?? null, $line['effective_to'] ?? null) + [
                        'line_type_code' => $line['line_type_code'],
                        'line_code' => $line['line_code'], 'line_name_snapshot' => $line['line_name_snapshot'], 'sort_no' => $lineIndex + 1,
                        'application_status_code' => $line['application_status_code'] ?? null,
                        'calculation_basis_amount' => $line['calculation_basis_amount'] ?? null, 'calculation_rate' => $line['calculation_rate'] ?? null,
                        'calculation_before_rounding' => $line['calculation_before_rounding'] ?? null, 'rounding_method_code' => $line['rounding_method_code'] ?? null,
                        'rounding_unit' => $line['rounding_unit'] ?? null, 'statutory_standard_id' => $line['statutory_standard_id'] ?? null,
                        'coverage_id' => null, 'social_insurance_workplace_id' => null,
                        'calculated_amount' => $line['calculated_amount'] ?? null,
                        'final_amount' => $line['final_amount'],
                        'adjustment_reason' => $line['adjustment_reason'] ?? null,
                        'statutory_calculation_source_code_id' => $line['statutory_calculation_source_code_id'] ?? null,
                        'actual_application_source_code_id' => $line['actual_application_source_code_id'] ?? null,
                        'processed_at' => $line['processed_at'] ?? null,
                        'processed_by' => $line['processed_by'] ?? null,
                        'created_by' => $actor, 'updated_by' => $actor,
                    ]);
                }
            }
            foreach (array_values($item['lines'] ?? []) as $lineIndex => $line) {
                $this->lineContract->assertGrain(
                    (string) $line['line_type_code'],
                    (string) $line['line_code'],
                    null
                );
                $this->incomeCodes->assertIdInGroup(
                    $line['statutory_calculation_source_code_id'] ?? null,
                    IncomeCalculationCodeService::STATUTORY_SOURCE_GROUP
                );
                $this->incomeCodes->assertIdInGroup(
                    $line['actual_application_source_code_id'] ?? null,
                    IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP
                );
                $lineId = \Core\Helpers\UuidHelper::generate();
                $persisted['lines'][$groupIndex][$itemIndex][] = ['id'=>$lineId,'line_type_code'=>$line['line_type_code'],'line_code'=>$line['line_code']];
                $this->insert('institution_daily_employment_income_lines', [
                    'id' => $lineId,
                    'daily_employment_income_item_id' => $itemId,
                    'daily_employment_income_workday_id' => null,
                ] + $this->scopeKeys->lineKeys($itemId, null, $line['non_taxable_revision_id'] ?? null, $line['effective_from'] ?? null, $line['effective_to'] ?? null) + [
                    'line_type_code' => $line['line_type_code'],
                    'line_code' => $line['line_code'],
                    'line_name_snapshot' => $line['line_name_snapshot'],
                    'sort_no' => $lineIndex + 1,
                    'application_status_code' => $line['application_status_code'] ?? null,
                    'calculation_basis_amount' => $line['calculation_basis_amount'] ?? null,
                    'calculation_rate' => $line['calculation_rate'] ?? null,
                    'calculation_before_rounding' => $line['calculation_before_rounding'] ?? null,
                    'rounding_method_code' => $line['rounding_method_code'] ?? null,
                    'rounding_unit' => $line['rounding_unit'] ?? null,
                    'statutory_standard_id' => $line['statutory_standard_id'] ?? null,
                    'coverage_id' => $line['coverage_id'] ?? null,
                    'social_insurance_workplace_id' => $line['social_insurance_workplace_id'] ?? null,
                    'calculated_amount' => $line['calculated_amount'] ?? null,
                    'final_amount' => $line['final_amount'] ?? null,
                    'adjustment_reason' => $line['adjustment_reason'] ?? null,
                    'statutory_calculation_source_code_id' => $line['statutory_calculation_source_code_id'] ?? null,
                    'actual_application_source_code_id' => $line['actual_application_source_code_id'] ?? null,
                    'processed_at' => $line['processed_at'] ?? null,
                    'processed_by' => $line['processed_by'] ?? null,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]);
            }
          }
        }
        return $persisted;
    }

    private function worker(string $id): array
    {
        $statement = $this->db->prepare('SELECT * FROM system_clients WHERE id=:id AND is_active=1 AND deleted_at IS NULL');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \InvalidArgumentException('선택한 일용근로자를 찾을 수 없습니다.');
        return $row;
    }

    private function insert(string $table, array $row): void
    {
        $columns = array_keys($row);
        $statement = $this->db->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')');
        $statement->execute(array_combine(array_map(static fn(string $key): string => ':' . $key, $columns), array_values($row)));
    }

    public function page(array $query): array
    {
        $where = ['h.deleted_at IS NULL'];
        $params = [];
        foreach (json_decode((string) ($query['filters'] ?? '[]'), true) ?: [] as $index => $filter) {
            $field = trim((string) ($filter['field'] ?? ''));
            $value = trim((string) ($filter['value'] ?? ''));
            if ($value === '') continue;
            $parameter = ':filter_' . $index;
            if ($field === 'income_year_month') {
                $where[] = 'h.income_year_month = ' . $parameter;
                $params[$parameter] = $value;
            } elseif ($field === 'document_title') {
                $where[] = 'h.document_title LIKE ' . $parameter;
                $params[$parameter] = '%' . $value . '%';
            } elseif (in_array($field, ['status_code', 'approval_status'], true)) {
                $where[] = 'h.status_code = ' . $parameter;
                $params[$parameter] = strtoupper($value);
            } elseif (in_array($field, ['business_unit', 'project_id', 'work_team_id', 'worker_client_id'], true)) {
                if ($field === 'worker_client_id') {
                    $where[] = 'EXISTS (SELECT 1 FROM institution_daily_employment_income_groups fg JOIN institution_daily_employment_income_items fi ON fi.daily_employment_income_group_id=fg.id WHERE fg.daily_employment_income_id=h.id AND fi.worker_client_id=' . $parameter . ')';
                } else {
                    $where[] = 'EXISTS (SELECT 1 FROM institution_daily_employment_income_groups fg WHERE fg.daily_employment_income_id=h.id AND fg.' . $field . '=' . $parameter . ')';
                }
                $params[$parameter] = $field === 'business_unit' ? strtoupper($value) : $value;
            }
        }
        $dateStart = trim((string) ($query['dateStart'] ?? ''));
        $dateEnd = trim((string) ($query['dateEnd'] ?? ''));
        $search = trim((string) ($query['search']['value'] ?? ''));
        if ($search !== '') {
            $where[] = '(h.document_title LIKE :search OR h.income_year_month LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $from = ' FROM institution_daily_employment_incomes h WHERE ' . implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(*)' . $from);
        $count->execute($params);
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(500, (int) ($query['length'] ?? 100)));
        $orderColumns = ['h.sort_no', 'h.income_year_month', 'h.document_title', 'h.worker_count', 'h.work_team_count', 'h.total_work_days', 'h.total_gross_amount', 'h.total_deduction_amount', 'h.total_net_payment_amount', 'h.total_employer_burden_amount', 'h.status_code', 'h.created_by', 'h.created_at'];
        $orderIndex = max(0, (int) ($query['order'][0]['column'] ?? 0));
        $orderBy = $orderColumns[$orderIndex] ?? 'h.income_year_month';
        $orderDirection = strtolower((string) ($query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $sql = 'SELECT h.*' . $from . ' ORDER BY ' . $orderBy . ' ' . $orderDirection . ', h.id DESC LIMIT ' . $start . ',' . $length;
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $rows = ActorHelper::enrichActorNames($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'approved_by_name' => 'approved_by',
        ]);
        $total = (int) $count->fetchColumn();
        return ['rows' => $rows, 'total' => $total, 'filtered' => $total];
    }

    public function softDelete(string $id,string $actor):bool
    {
        $statement=$this->db->prepare("UPDATE institution_daily_employment_incomes SET deleted_at=NOW(),deleted_by=:deleted_by,updated_at=NOW(),updated_by=:updated_by WHERE id=:id AND deleted_at IS NULL AND status_code IN ('DRAFT','REJECTED','WITHDRAWN')");
        $statement->execute([':id'=>$id,':deleted_by'=>$actor,':updated_by'=>$actor]);return$statement->rowCount()===1;
    }

    public function trash():array
    {
        $statement=$this->db->query('SELECT id,document_title,income_year_month,status_code,deleted_at,deleted_by FROM institution_daily_employment_incomes WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC,id DESC');
        return ActorHelper::enrichActorNames($statement->fetchAll(PDO::FETCH_ASSOC)?:[],['deleted_by_name'=>'deleted_by']);
    }

    public function trashIds():array{return array_map('strval',$this->db->query('SELECT id FROM institution_daily_employment_incomes WHERE deleted_at IS NOT NULL ORDER BY deleted_at,id')->fetchAll(PDO::FETCH_COLUMN)?:[]);}

    public function restore(string $id,string $actor):bool
    {
        $statement=$this->db->prepare('UPDATE institution_daily_employment_incomes SET deleted_at=NULL,deleted_by=NULL,updated_at=NOW(),updated_by=:actor WHERE id=:id AND deleted_at IS NOT NULL');
        $statement->execute([':id'=>$id,':actor'=>$actor]);return$statement->rowCount()===1;
    }

    public function purge(string $id):bool
    {
        $guard=$this->db->prepare("SELECT id FROM institution_daily_employment_incomes h WHERE h.id=:id AND h.deleted_at IS NOT NULL AND h.status_code IN ('DRAFT','REJECTED','WITHDRAWN') AND NOT EXISTS(SELECT 1 FROM ledger_evidence_daily_employment_income e WHERE e.source_daily_employment_income_id=h.id) FOR UPDATE");
        $guard->execute([':id'=>$id]);if(!$guard->fetchColumn())return false;
        $itemIds=$this->db->prepare('SELECT i.id FROM institution_daily_employment_income_items i JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id WHERE g.daily_employment_income_id=:id');$itemIds->execute([':id'=>$id]);$ids=$itemIds->fetchAll(PDO::FETCH_COLUMN)?:[];
        if($ids!==[]){$marks=implode(',',array_fill(0,count($ids),'?'));$this->db->prepare("DELETE FROM institution_daily_employment_income_lines WHERE daily_employment_income_item_id IN ({$marks})")->execute($ids);$this->db->prepare("DELETE FROM institution_daily_employment_income_workdays WHERE daily_employment_income_item_id IN ({$marks})")->execute($ids);}
        if($ids!==[])$this->db->prepare('DELETE FROM institution_daily_employment_income_items WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')')->execute($ids);
        $this->db->prepare('DELETE FROM institution_daily_employment_income_groups WHERE daily_employment_income_id=:id')->execute([':id'=>$id]);
        $delete=$this->db->prepare('DELETE FROM institution_daily_employment_incomes WHERE id=:id AND deleted_at IS NOT NULL');$delete->execute([':id'=>$id]);return$delete->rowCount()===1;
    }

    public function groups(string $headerId):array
    {
        $statement=$this->db->prepare("SELECT g.*,p.project_name,t.team_name,c.code_name AS business_unit_name,c.sort_no AS business_unit_sort_no,c.extra_data AS business_unit_extra_data"
            . " FROM institution_daily_employment_income_groups g"
            . " LEFT JOIN system_codes c ON c.code_group='BUSINESS_UNIT' AND c.code=g.business_unit"
            . ' LEFT JOIN system_projects p ON p.id=g.project_id LEFT JOIN system_work_teams t ON t.id=g.work_team_id'
            . ' WHERE g.daily_employment_income_id=:id ORDER BY g.sort_no,g.id');
        $statement->execute([':id'=>$headerId]);return$statement->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    public function items(string $groupId): array
    {
        $statement = $this->db->prepare(
            'SELECT i.*, c.client_name AS worker_name, c.client_type AS worker_client_type, ct.code_name AS worker_client_type_name'
            . ' FROM institution_daily_employment_income_items i'
            . ' JOIN system_clients c ON c.id = i.worker_client_id'
            . " LEFT JOIN system_codes ct ON ct.code_group='CLIENT_TYPE' AND NULLIF(TRIM(c.client_type),'') IS NOT NULL AND ct.code=c.client_type AND ct.is_active=1"
            . ' WHERE i.daily_employment_income_group_id = :id ORDER BY i.sort_no, i.id'
        );
        $statement->execute([':id' => $groupId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resolveWorkerAssignment(string $companyId, string $workerId, ?string $teamId, ?string $projectId, string $date): ?array
    {
        if ($teamId === null) return null;
        $statement = $this->db->prepare(
            'SELECT a.* FROM system_work_team_assignments a'
            . ' JOIN system_clients c ON c.id = a.worker_client_id AND c.is_active = 1 AND c.deleted_at IS NULL'
            . ' WHERE a.company_id = :company_id AND a.worker_client_id = :worker_id AND a.work_team_id = :team_id'
            . ' AND a.scope_project_key = :scope_project_key'
            . ' AND a.status_code = :status'
            . ' AND a.effective_from <= :date_from AND (a.effective_to IS NULL OR a.effective_to >= :date_to)'
            . ' ORDER BY a.effective_from DESC FOR UPDATE'
        );
        $statement->execute([
            ':company_id' => $companyId, ':worker_id' => $workerId,
            ':team_id' => $teamId, ':scope_project_key' => $projectId ?: 'HEAD_OFFICE', ':status' => 'ACTIVE',
            ':date_from' => $date, ':date_to' => $date,
        ]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return count($rows) === 1 ? $rows[0] : null;
    }

    public function businessUnitPolicy(string $businessUnit, ?string $documentId = null): array
    {
        $statement = $this->db->prepare(
            "SELECT code,code_name,sort_no,extra_data FROM system_codes c WHERE c.code_group='BUSINESS_UNIT' AND c.code=:business_unit"
            . " AND (c.is_active=1 OR EXISTS(SELECT 1 FROM institution_daily_employment_income_groups g"
            . " WHERE g.daily_employment_income_id=:document_id AND g.business_unit=c.code))"
        );
        $statement->execute([':business_unit' => $businessUnit, ':document_id' => $documentId ?? '']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \InvalidArgumentException('유효하지 않거나 비활성화된 사업구분입니다.');
        return $row;
    }

    public function assertGroupReferences(string $companyId, string $businessUnit, ?string $projectId, ?string $teamId, string $workerId, string $workDate, ?string $documentId = null): void
    {
        if ($projectId !== null) {
            $statement=$this->db->prepare('SELECT COUNT(*) FROM system_projects p WHERE p.id=:project_id AND p.business_unit=:business_unit'
                . ' AND ((p.is_active=1 AND p.deleted_at IS NULL) OR EXISTS(SELECT 1 FROM institution_daily_employment_income_groups g WHERE g.daily_employment_income_id=:document_id AND g.project_id=p.id))'
                . ' AND (p.start_date IS NULL OR p.start_date<=:date_from) AND (p.completion_date IS NULL OR p.completion_date>=:date_to)');
            $statement->execute([':project_id'=>$projectId,':business_unit'=>$businessUnit,':document_id'=>$documentId??'',':date_from'=>$workDate,':date_to'=>$workDate]);
            if ((int)$statement->fetchColumn()!==1) throw new \InvalidArgumentException('다른 사업구분의 프로젝트이거나 근무일에 유효하지 않은 프로젝트입니다.');
        }
        if ($teamId !== null) {
            $statement=$this->db->prepare(
                'SELECT COUNT(*) FROM system_work_teams t '
                . 'JOIN system_work_team_assignments a ON a.work_team_id=t.id '
                . 'WHERE t.id=:team_id AND t.business_unit=:business_unit '
                . 'AND ((t.is_active=1 AND t.deleted_at IS NULL) OR EXISTS(SELECT 1 FROM institution_daily_employment_income_groups g WHERE g.daily_employment_income_id=:document_id AND g.work_team_id=t.id)) '
                . 'AND a.company_id=:company_id AND a.worker_client_id=:worker_id AND a.status_code=\'ACTIVE\' '
                . 'AND ((:project_scope IS NULL AND a.project_id IS NULL) OR a.project_id=:project_id) '
                . 'AND a.effective_from<=:work_date AND (a.effective_to IS NULL OR a.effective_to>=:work_date)'
            );
            $statement->execute([':team_id'=>$teamId,':business_unit'=>$businessUnit,':document_id'=>$documentId??'',':company_id'=>$companyId,':worker_id'=>$workerId,':project_scope'=>$projectId,':project_id'=>$projectId,':work_date'=>$workDate]);
            if ((int)$statement->fetchColumn()!==1) throw new \InvalidArgumentException('다른 프로젝트의 작업팀이거나 해당 작업자·근무일에 유효한 배치가 아닙니다.');
        }
        $this->worker($workerId);
    }

    public function resolveDailyInsuranceContext(
        string $companyId,
        string $businessUnit,
        string $workerId,
        ?string $projectId,
        string $date,
        ?string $explicitWorkplaceId = null
    ): array
    {
        $workplace = $this->db->prepare(
            "SELECT * FROM institution_social_insurance_workplaces
             WHERE company_id=:company_id AND business_unit=:business_unit AND scope_project_key=:scope_key
               AND confirmation_status_code IN ('CONFIRMED','NOT_REQUIRED')
               AND effective_from<=:date_from AND (effective_to IS NULL OR effective_to>=:date_to)
             ORDER BY effective_from DESC FOR UPDATE"
        );
        $workplace->execute([':company_id' => $companyId, ':business_unit' => $businessUnit, ':scope_key' => $projectId ?: 'HEAD_OFFICE', ':date_from' => $date, ':date_to' => $date]);
        $workplaces = $workplace->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($workplaces === [] && $explicitWorkplaceId !== null) {
            $explicit = $this->db->prepare(
                "SELECT * FROM institution_social_insurance_workplaces
                 WHERE id=:id AND company_id=:company_id AND business_unit=:business_unit
                   AND scope_project_key=:scope_key
                   AND confirmation_status_code IN ('CONFIRMED','NOT_REQUIRED')
                   AND effective_from<=:date_from AND (effective_to IS NULL OR effective_to>=:date_to)"
            );
            $explicit->execute([
                ':id' => $explicitWorkplaceId,
                ':company_id' => $companyId,
                ':business_unit' => $businessUnit,
                ':scope_key' => $projectId ?: 'HEAD_OFFICE',
                ':date_from' => $date,
                ':date_to' => $date,
            ]);
            $explicitRow = $explicit->fetch(PDO::FETCH_ASSOC);
            if (is_array($explicitRow)) $workplaces = [$explicitRow];
        }
        $insuranceTypes = [
            'NATIONAL_PENSION',
            'HEALTH_INSURANCE',
            'LONG_TERM_CARE_INSURANCE',
            'EMPLOYMENT_INSURANCE',
            'INDUSTRIAL_ACCIDENT_INSURANCE',
        ];
        if (count($workplaces) !== 1) {
            $code = $workplaces === [] ? 'INSURANCE_WORKPLACE_NOT_FOUND' : 'INSURANCE_WORKPLACE_AMBIGUOUS';
            $message = $workplaces === []
                ? '적용 가능한 보험사업장이 등록되어 있지 않습니다.'
                : '적용 가능한 보험사업장이 여러 건이므로 하나로 확정할 수 없습니다.';
            return ['workplace' => null, 'workplace_candidates' => $workplaces, 'coverages' => [], 'issues' => array_map(
                static fn(string $type): array => [
                    'insurance_type_code' => $type,
                    'status_code' => 'CONFIRMATION_REQUIRED',
                    'workplace_candidate_count' => count($workplaces),
                    'coverage_count' => 0,
                    'blocking_code' => $code,
                    'message' => $message,
                    'required_action' => '근무일·사업구분·프로젝트 범위에 맞는 보험사업장을 확인해 주세요.',
                ],
                $insuranceTypes
            )];
        }
        if ($workplaces[0]['confirmation_status_code'] === 'NOT_REQUIRED') {
            return [
                'workplace' => $workplaces[0],
                'workplace_candidates' => $workplaces,
                'coverages' => [],
                'report_statuses' => array_fill_keys($insuranceTypes, 'NOT_REQUIRED'),
                'issues' => [],
            ];
        }
        $coverage = $this->db->prepare(
            'SELECT * FROM institution_daily_worker_social_insurance_coverages
             WHERE company_id=:company_id AND worker_client_id=:worker_id AND social_insurance_workplace_id=:workplace_id
               AND effective_from<=:date_from AND (effective_to IS NULL OR effective_to>=:date_to)
             ORDER BY insurance_type_code,effective_from DESC FOR UPDATE'
        );
        $coverage->execute([':company_id' => $companyId, ':worker_id' => $workerId, ':workplace_id' => $workplaces[0]['id'], ':date_from' => $date, ':date_to' => $date]);
        $rows = $coverage->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $byType = [];
        foreach ($rows as $row) {
            $type = (string) $row['insurance_type_code'];
            if ($type === 'LONG_TERM_CARE') $type = 'LONG_TERM_CARE_INSURANCE';
            $byType[$type][] = $row;
        }
        $issues = [];
        $reportStatuses = [];
        foreach ($insuranceTypes as $type) {
            $typeRows = $byType[$type] ?? [];
            if (count($typeRows) === 0) {
                $reportStatuses[$type] = 'PENDING_REPORT';
                continue;
            }
            if (count($typeRows) === 1) {
                $reportStatuses[$type] = match ((string) ($typeRows[0]['application_status_code'] ?? '')) {
                    'APPLICABLE', 'EXCLUDED' => 'CONFIRMED',
                    'NOT_APPLICABLE' => 'NOT_REQUIRED',
                    default => 'CORRECTION_REQUIRED',
                };
                continue;
            }
            $reportStatuses[$type] = 'CORRECTION_REQUIRED';
            $issues[] = [
                'insurance_type_code' => $type,
                'status_code' => 'CONFIRMATION_REQUIRED',
                'workplace_candidate_count' => 1,
                'coverage_count' => count($typeRows),
                'blocking_code' => 'INSURANCE_COVERAGE_AMBIGUOUS',
                'message' => '근로자의 보험 적용정보가 중복되어 확정할 수 없습니다.',
                'required_action' => '근로자·보험종류·유효기간에 맞는 Coverage를 확인해 주세요.',
            ];
        }
        return [
            'workplace' => $workplaces[0],
            'workplace_candidates' => $workplaces,
            'coverages' => array_map(static fn(array $items): array => $items[0], array_filter($byType, static fn(array $items): bool => count($items) === 1)),
            'report_statuses' => $reportStatuses,
            'issues' => $issues,
        ];
    }

    public function workdays(string $itemId): array
    {
        $statement = $this->db->prepare('SELECT * FROM institution_daily_employment_income_workdays WHERE daily_employment_income_item_id = :id ORDER BY work_date, id');
        $statement->execute([':id' => $itemId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lines(string $itemId): array
    {
        $statement = $this->db->prepare(
            'SELECT line_row.*,standard_row.effective_from AS standard_effective_from,standard_row.effective_to AS standard_effective_to '
            . 'FROM institution_daily_employment_income_lines line_row '
            . 'LEFT JOIN system_statutory_standards standard_row ON standard_row.id=line_row.statutory_standard_id '
            . 'WHERE line_row.daily_employment_income_item_id = :id ORDER BY line_row.sort_no,line_row.id'
        );
        $statement->execute([':id' => $itemId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function duplicateWorkdayDocuments(
        string $documentId,
        string $companyId,
        string $workerId,
        string $workDate,
        string $businessUnit,
        ?string $projectId,
        ?string $workTeamId
    ): array {
        $statement = $this->db->prepare(
            'SELECT DISTINCT h.id,h.document_title,h.status_code'
            . ' FROM institution_daily_employment_incomes h'
            . ' JOIN institution_daily_employment_income_groups g ON g.daily_employment_income_id=h.id'
            . ' JOIN institution_daily_employment_income_items i ON i.daily_employment_income_group_id=g.id'
            . ' JOIN institution_daily_employment_income_workdays w ON w.daily_employment_income_item_id=i.id'
            . ' WHERE h.id<>:document_id AND h.company_id=:company_id AND h.deleted_at IS NULL'
            . " AND h.status_code IN ('DRAFT','PENDING','APPROVED','REJECTED','WITHDRAWN')"
            . ' AND i.worker_client_id=:worker_id AND w.work_date=:work_date AND g.business_unit=:business_unit'
            . ' AND ((:project_scope IS NULL AND g.project_id IS NULL) OR g.project_id=:project_id)'
            . ' AND ((:team_scope IS NULL AND g.work_team_id IS NULL) OR g.work_team_id=:team_id)'
            . ' ORDER BY h.id'
        );
        $statement->execute([
            ':document_id' => $documentId,
            ':company_id' => $companyId,
            ':worker_id' => $workerId,
            ':work_date' => $workDate,
            ':business_unit' => $businessUnit,
            ':project_scope' => $projectId,
            ':project_id' => $projectId,
            ':team_scope' => $workTeamId,
            ':team_id' => $workTeamId,
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function options(): array
    {
        return [
            'company' => $this->db->query('SELECT id,company_name_ko AS name,biz_number FROM system_company ORDER BY created_at,id LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null,
            'business_units' => $this->db->query("SELECT code,code_name,sort_no,extra_data FROM system_codes WHERE code_group='BUSINESS_UNIT' AND is_active=1 ORDER BY sort_no,code")->fetchAll(PDO::FETCH_ASSOC),
            'projects' => $this->db->query('SELECT id,project_name AS name,business_unit,start_date,completion_date,sort_no FROM system_projects WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no,project_name,id')->fetchAll(PDO::FETCH_ASSOC),
            'work_teams' => $this->db->query('SELECT id, team_name AS name, business_unit, sort_no FROM system_work_teams WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no, team_name, id')->fetchAll(PDO::FETCH_ASSOC),
            'workers' => $this->db->query("SELECT c.id,c.client_name AS name,c.client_type,ct.code_name AS client_type_name,c.sort_no FROM system_clients c LEFT JOIN system_codes ct ON ct.code_group='CLIENT_TYPE' AND NULLIF(TRIM(c.client_type),'') IS NOT NULL AND ct.code=c.client_type AND ct.is_active=1 WHERE c.is_active=1 AND c.deleted_at IS NULL ORDER BY c.sort_no,c.client_name,c.id")->fetchAll(PDO::FETCH_ASSOC),
            'work_types' => $this->db->query("SELECT code AS id, code_name AS name, sort_no, extra_data FROM system_codes WHERE code_group='WORK_TYPE' AND is_active=1 ORDER BY sort_no, code_name, code")->fetchAll(PDO::FETCH_ASSOC),
            'social_insurance_workplaces' => $this->db->query(
                "SELECT id,company_id,business_unit,workplace_name AS name,management_number,work_scope_code,project_id,effective_from,effective_to "
                . "FROM institution_social_insurance_workplaces WHERE confirmation_status_code IN ('CONFIRMED','NOT_REQUIRED') "
                . 'ORDER BY workplace_name,id'
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function groupOptionSearch(array $input): array
    {
        $type = strtolower(trim((string) ($input['option_type'] ?? '')));
        $keyword = trim((string) ($input['q'] ?? ''));
        $page = max(1, (int) ($input['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $like = '%' . $keyword . '%';
        $businessUnit = strtoupper(trim((string) ($input['business_unit'] ?? '')));

        if ($type === 'business_unit') {
            $statement = $this->db->prepare(
                "SELECT code AS id,code_name AS text,sort_no,extra_data FROM system_codes "
                . "WHERE code_group='BUSINESS_UNIT' AND is_active=1 AND (code LIKE :q1 OR code_name LIKE :q2) "
                . "ORDER BY sort_no,code LIMIT 21 OFFSET {$offset}"
            );
            $statement->execute([':q1'=>$like,':q2'=>$like]);
        } elseif ($type === 'project') {
            if ($businessUnit === '') return ['results'=>[], 'has_more'=>false, 'page'=>$page];
            $statement = $this->db->prepare(
                "SELECT id,project_name AS text,sort_no,business_unit,start_date,completion_date FROM system_projects "
                . "WHERE is_active=1 AND deleted_at IS NULL AND business_unit=:business_unit "
                . "AND (project_name LIKE :q1 OR construction_name LIKE :q2) "
                . "ORDER BY sort_no,project_name,id LIMIT 21 OFFSET {$offset}"
            );
            $statement->execute([':business_unit'=>$businessUnit,':q1'=>$like,':q2'=>$like]);
        } elseif ($type === 'work_team') {
            if ($businessUnit === '') return ['results'=>[], 'has_more'=>false, 'page'=>$page];
            $statement = $this->db->prepare(
                "SELECT t.id,t.team_name AS text,t.sort_no,t.business_unit FROM system_work_teams t "
                . "WHERE t.is_active=1 AND t.deleted_at IS NULL AND t.business_unit=:business_unit "
                . "AND t.team_name LIKE :q ORDER BY t.sort_no,t.team_name,t.id LIMIT 21 OFFSET {$offset}"
            );
            $statement->execute([':business_unit'=>$businessUnit,':q'=>$like]);
        } elseif ($type === 'worker') {
            $statement = $this->db->prepare(
                "SELECT c.id,c.client_name AS text,c.client_name,c.client_type,"
                . "COALESCE(ct.code_name,c.client_type,'') AS client_type_name,c.sort_no FROM system_clients c "
                . "LEFT JOIN system_codes ct ON ct.code_group='CLIENT_TYPE' AND ct.code=c.client_type AND ct.is_active=1 "
                . "WHERE c.is_active=1 AND c.deleted_at IS NULL "
                . "AND (c.client_name LIKE :q1 OR c.id LIKE :q2) "
                . "ORDER BY c.sort_no,c.client_name,c.id LIMIT 21 OFFSET {$offset}"
            );
            $statement->execute([':q1'=>$like,':q2'=>$like]);
        } elseif ($type === 'work_type') {
            $statement = $this->db->prepare(
                "SELECT code AS id,code_name AS text,sort_no FROM system_codes "
                . "WHERE code_group='WORK_TYPE' AND is_active=1 AND (code LIKE :q1 OR code_name LIKE :q2) "
                . "ORDER BY sort_no,code_name,code LIMIT 21 OFFSET {$offset}"
            );
            $statement->execute([':q1'=>$like,':q2'=>$like]);
        } else {
            throw new \InvalidArgumentException('지원하지 않는 선택목록입니다.');
        }
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return ['results'=>array_slice($rows,0,$limit),'has_more'=>count($rows)>$limit,'page'=>$page];
    }
}

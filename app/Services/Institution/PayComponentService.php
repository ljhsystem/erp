<?php

namespace App\Services\Institution;

use App\Models\Institution\PayComponentModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

final class PayComponentService
{
    private PayComponentModel $components;
    private PDO $db;
    private LoggerInterface $logger;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->components = new PayComponentModel($db);
        $this->logger = LoggerFactory::getLogger('service-institution-pay-component');
    }

    public function optionsForDate(string $date): array
    {
        $this->assertDate($date);
        return array_map(fn(array $component): array => $this->option($component), $this->components->activeForDate($date));
    }

    public function requireActiveForDate(string $id, string $date): array
    {
        $this->assertDate($date);
        $component = $this->components->findActive(trim($id), $date);
        if (!$component) {
            throw new \InvalidArgumentException('선택한 지급항목이 적용기준일에 유효하지 않습니다.');
        }
        return $component;
    }

    public function list(bool $includeDeleted = false): array
    {
        $rows = ActorHelper::enrichActorNames($this->components->all($includeDeleted), ['created_by_name'=>'created_by','updated_by_name'=>'updated_by','deleted_by_name'=>'deleted_by']);
        return ['success' => true, 'data' => $rows, 'message' => ''];
    }

    public function detail(string $id): array
    {
        $component = $this->components->find(trim($id), true);
        if (!$component) throw new \InvalidArgumentException('급여항목을 찾을 수 없습니다.');
        return ['success' => true, 'data' => $component, 'message' => ''];
    }

    public function save(array $input): array
    {
        return $this->logged('PAY_COMPONENT_SAVED','save',['target_id'=>trim((string)($input['id']??''))?:null],fn():array=>$this->saveInternal($input));
    }

    private function saveInternal(array $input): array
    {
        $id = trim((string) ($input['id'] ?? ''));
        $code = strtoupper(trim((string) ($input['component_code'] ?? '')));
        $name = trim((string) ($input['component_name'] ?? ''));
        if ($code === '' || !preg_match('/^[A-Z][A-Z0-9_]{1,49}$/', $code)) throw new \InvalidArgumentException('항목코드는 영문 대문자, 숫자, 밑줄로 입력해 주세요.');
        if ($name === '') throw new \InvalidArgumentException('항목명을 입력해 주세요.');
        if ($this->components->codeExists($code, $id)) throw new \InvalidArgumentException('이미 등록된 항목코드입니다.');
        $type = $this->allowed($input, 'component_type', ['BASE_PAY','ALLOWANCE','STATUTORY_PREMIUM','BONUS','OTHER_WAGE'], '항목유형');
        $calculation = $this->allowed($input, 'default_calculation_type', ['FIXED_AMOUNT','FORMULA','HOURLY_RATE'], '계산방식');
        $tax = $this->allowed($input, 'default_tax_type', ['TAXABLE','NON_TAXABLE','POLICY_CALCULATED'], '과세정책');
        $ordinary = $this->allowed($input, 'ordinary_wage_treatment', ['INCLUDED','EXCLUDED','REVIEW_REQUIRED'], '통상임금 정책');
        $average = $this->allowed($input, 'average_wage_treatment', ['INCLUDED','EXCLUDED','REVIEW_REQUIRED'], '평균임금 정책');
        $minimum = $this->allowed($input, 'minimum_wage_treatment', ['INCLUDED','EXCLUDED','REVIEW_REQUIRED'], '최저임금 정책');
        $from = $this->nullableDate($input['effective_from'] ?? null, '적용 시작일');
        $to = $this->nullableDate($input['effective_to'] ?? null, '적용 종료일');
        if ($from && $to && $to < $from) throw new \InvalidArgumentException('적용 종료일은 시작일보다 빠를 수 없습니다.');
        $existing = $id === '' ? null : $this->components->find($id, true);
        if ($id !== '' && !$existing) throw new \InvalidArgumentException('급여항목을 찾을 수 없습니다.');
        $sortNo = $existing ? (int) $existing['sort_no'] : $this->components->nextSortNo();
        $now = date('Y-m-d H:i:s'); $actor = ActorHelper::user();
        $data = ['sort_no'=>$sortNo,'component_code'=>$code,'component_name'=>$name,'component_type'=>$type,'default_calculation_type'=>$calculation,'default_tax_type'=>$tax,'tax_policy_code'=>$this->nullable($input['tax_policy_code']??null),'ordinary_wage_treatment'=>$ordinary,'average_wage_treatment'=>$average,'minimum_wage_treatment'=>$minimum,'is_active'=>!empty($input['is_active'])?1:0,'effective_from'=>$from,'effective_to'=>$to,'note'=>$this->nullable($input['note']??null),'memo'=>$this->nullable($input['memo']??null),'updated_at'=>$now,'updated_by'=>$actor,'deleted_at'=>null,'deleted_by'=>null];
        if ($id === '') { $id = UuidHelper::generate(); $data = ['id'=>$id,'created_at'=>$now,'created_by'=>$actor] + $data; $this->components->insert($data); }
        else { $this->components->update($id, $data); }
        return ['success'=>true,'data'=>$this->components->find($id, true),'message'=>'저장되었습니다.'];
    }

    public function delete(string $id): array
    {
        return $this->logged('PAY_COMPONENT_DELETED','delete',['target_id'=>$id],fn():array=>$this->deleteInternal($id),true);
    }

    private function deleteInternal(string $id): array
    {
        $id = trim($id); if (!$this->components->find($id)) throw new \InvalidArgumentException('급여항목을 찾을 수 없습니다.');
        $this->components->update($id, ['is_active'=>0,'deleted_at'=>date('Y-m-d H:i:s'),'deleted_by'=>ActorHelper::user(),'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>ActorHelper::user()]);
        return ['success'=>true,'data'=>['id'=>$id],'message'=>'삭제되었습니다.'];
    }

    public function reorder(array $changes): array
    {
        return $this->logged('PAY_COMPONENT_REORDERED','reorder',['requested_count'=>count($changes)],fn():array=>$this->reorderInternal($changes));
    }

    private function reorderInternal(array $changes): array
    {
        if ($changes === []) {
            return ['success' => true, 'data' => [], 'message' => '변경할 순서가 없습니다.'];
        }
        $rows = [];
        foreach ($changes as $change) {
            $id = trim((string) ($change['id'] ?? ''));
            $sortNo = filter_var($change['newSortNo'] ?? $change['sort_no'] ?? null, FILTER_VALIDATE_INT);
            if ($id === '' || $sortNo === false || $sortNo < 1) {
                throw new \InvalidArgumentException('순서 변경 데이터가 올바르지 않습니다.');
            }
            $rows[] = ['id' => $id, 'sort_no' => (int) $sortNo];
        }
        if (count(array_unique(array_column($rows, 'id'))) !== count($rows)
            || count(array_unique(array_column($rows, 'sort_no'))) !== count($rows)) {
            throw new \InvalidArgumentException('순서 변경 대상 또는 순번이 중복되었습니다.');
        }
        $actor = ActorHelper::user();
        $updatedAt = date('Y-m-d H:i:s');
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $this->components->updateSortNo($row['id'], 1000000 + $index + 1, $updatedAt, $actor);
            }
            foreach ($rows as $row) {
                $this->components->updateSortNo($row['id'], $row['sort_no'], $updatedAt, $actor);
            }
            if ($ownsTransaction) $this->db->commit();
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
        return ['success' => true, 'data' => $rows, 'message' => '순서를 변경했습니다.'];
    }

    public function taxableFlag(array $component): int
    {
        return strtoupper(trim((string) ($component['default_tax_type'] ?? ''))) === 'TAXABLE' ? 1 : 0;
    }

    public function taxLabel(array $component): string
    {
        return match (strtoupper(trim((string) ($component['default_tax_type'] ?? '')))) {
            'TAXABLE' => '과세',
            'NON_TAXABLE' => '비과세',
            'POLICY_CALCULATED' => '정책 적용',
            default => '정책 확인 필요',
        };
    }

    private function option(array $component): array
    {
        return [
            'value' => (string) $component['id'],
            'label' => (string) $component['component_name'],
            'sort_no' => (int) $component['sort_no'],
            'meta' => [
                'sort_no' => (int) $component['sort_no'],
                'component_code' => (string) $component['component_code'],
                'component_name' => (string) $component['component_name'],
                'component_type' => (string) $component['component_type'],
                'default_calculation_type' => (string) $component['default_calculation_type'],
                'default_tax_type' => (string) $component['default_tax_type'],
                'tax_policy_code' => $component['tax_policy_code'],
                'taxable_flag' => $this->taxableFlag($component),
                'tax_label' => $this->taxLabel($component),
                'minimum_wage_treatment' => (string) $component['minimum_wage_treatment'],
                'ordinary_wage_treatment' => (string) $component['ordinary_wage_treatment'],
                'average_wage_treatment' => (string) $component['average_wage_treatment'],
                'effective_from' => $component['effective_from'],
                'effective_to' => $component['effective_to'],
            ],
        ];
    }

    private function assertDate(string $date): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('지급항목 적용기준일을 확인해 주세요.');
        }
    }

    private function allowed(array $input, string $key, array $allowed, string $label): string { $value=strtoupper(trim((string)($input[$key]??'')));if(!in_array($value,$allowed,true))throw new \InvalidArgumentException("{$label}을 확인해 주세요.");return$value; }
    private function nullable(mixed $value): ?string { $value=trim((string)$value);return$value===''?null:$value; }
    private function nullableDate(mixed $value, string $label): ?string { $value=$this->nullable($value);if($value===null)return null;$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new \InvalidArgumentException("{$label}을 확인해 주세요.");return$value; }
    private function logged(string$event,string$action,array$context,callable$operation,bool$warning=false):array{try{$result=$operation();$payload=['event_code'=>$event,'result'=>'SUCCESS','service'=>self::class,'action'=>$action,'actor'=>ActorHelper::user()]+$context;if($warning)$this->logger->warning('급여항목 업무 처리가 완료되었습니다.',$payload);else$this->logger->info('급여항목 업무 처리가 완료되었습니다.',$payload);return$result;}catch(\PDOException$e){$this->logger->error('급여항목 업무 처리에 실패했습니다.',['event_code'=>$event.'_FAILED','result'=>'FAILED','service'=>self::class,'action'=>$action,'actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]+$context);throw$e;}catch(\InvalidArgumentException|\DomainException|\RuntimeException$e){$this->logger->warning('급여항목 업무 처리가 차단되었습니다.',['event_code'=>$event.'_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>$action,'actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]+$context);throw$e;}catch(\Throwable$e){$this->logger->error('급여항목 업무 처리에 실패했습니다.',['event_code'=>$event.'_FAILED','result'=>'FAILED','service'=>self::class,'action'=>$action,'actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]+$context);throw$e;}}
}

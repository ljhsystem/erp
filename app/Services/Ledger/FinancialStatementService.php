<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\FinancialStatementRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class FinancialStatementService
{
    private const TYPES=['trial-balance','income-statement','statement-position','product-cost','construction-cost','retained-earnings'];
    public function __construct(private readonly FinancialStatementRepository $repository) {}
    public static function fromPdo(PDO $pdo): self{return new self(new FinancialStatementRepository($pdo));}

    public function report(string $type,array $filters): array
    {
        if(!in_array($type,self::TYPES,true))throw new InvalidArgumentException('지원하지 않는 재무제표입니다.');
        $dateTo=$this->date((string)($filters['date_to']??date('Y-12-31')));
        $dateFrom=$this->date((string)($filters['date_from']??substr($dateTo,0,4).'-01-01'));
        if($dateFrom>$dateTo)throw new InvalidArgumentException('조회 시작일은 종료일보다 늦을 수 없습니다.');
        $accounts=$this->repository->accountBalances($dateFrom,$dateTo);$rows=$this->rollup($accounts);
        $rows=$this->selectRows($type,$rows);$summary=$this->summary($type,$rows);
        return ['type'=>$type,'date_from'=>$dateFrom,'date_to'=>$dateTo,'prior_date_from'=>(new DateTimeImmutable($dateFrom))->modify('-1 year')->format('Y-m-d'),'prior_date_to'=>(new DateTimeImmutable($dateTo))->modify('-1 year')->format('Y-m-d'),'rows'=>$rows,'summary'=>$summary];
    }

    private function rollup(array $accounts): array
    {
        $map=[];$order=[];
        foreach($accounts as $row){foreach(['opening_debit','opening_credit','period_debit','period_credit','ending_debit','ending_credit','prior_period_debit','prior_period_credit','prior_ending_debit','prior_ending_credit'] as $field)$row[$field]=(float)$row[$field];$map[(string)$row['id']]=$row;$order[]=(string)$row['id'];}
        for($i=count($order)-1;$i>=0;$i--){$id=$order[$i];$parent=(string)($map[$id]['parent_id']??'');if($parent===''||!isset($map[$parent]))continue;foreach(['opening_debit','opening_credit','period_debit','period_credit','ending_debit','ending_credit','prior_period_debit','prior_period_credit','prior_ending_debit','prior_ending_credit'] as $field)$map[$parent][$field]+=$map[$id][$field];}
        $rows=[];foreach($order as $id){$row=$map[$id];$credit=$row['normal_balance']==='CREDIT';$row['opening_balance']=$credit?$row['opening_credit']-$row['opening_debit']:$row['opening_debit']-$row['opening_credit'];$row['current_amount']=$credit?$row['period_credit']-$row['period_debit']:$row['period_debit']-$row['period_credit'];$row['prior_amount']=$credit?$row['prior_period_credit']-$row['prior_period_debit']:$row['prior_period_debit']-$row['prior_period_credit'];$row['ending_balance']=$credit?$row['ending_credit']-$row['ending_debit']:$row['ending_debit']-$row['ending_credit'];$row['prior_ending_balance']=$credit?$row['prior_ending_credit']-$row['prior_ending_debit']:$row['prior_ending_debit']-$row['prior_ending_credit'];$row['change_amount']=$row['current_amount']-$row['prior_amount'];$row['change_rate']=$row['prior_amount']==0.0?null:($row['change_amount']/$row['prior_amount']*100);$rows[]=$row;}return $rows;
    }

    private function selectRows(string $type,array $rows): array
    {
        $selected=array_values(array_filter($rows,static function(array $row)use($type):bool{$code=(string)$row['account_code'];$group=(string)$row['account_group'];return match($type){'trial-balance'=>true,'statement-position'=>in_array($group,['자산','부채','자본'],true),'income-statement'=>in_array($group,['수익','비용'],true),'product-cost'=>str_starts_with($code,'511'),'construction-cost'=>str_starts_with($code,'512'),'retained-earnings'=>str_starts_with($code,'35'),default=>false};}));
        if($type==='statement-position')foreach($selected as &$row){$row['current_amount']=$row['ending_balance'];$row['prior_amount']=$row['prior_ending_balance'];$row['change_amount']=$row['current_amount']-$row['prior_amount'];$row['change_rate']=$row['prior_amount']==0.0?null:$row['change_amount']/$row['prior_amount']*100;}unset($row);
        if($type==='statement-position'){
            $incomeCurrent=$this->netIncome($rows,'current_amount');$incomePrior=$this->netIncome($rows,'prior_amount');
            $selected[]=$this->calculatedRow('calculated-current-income','당기순손익',$incomeCurrent,$incomePrior,'자본',2,9998);
        }
        if($type==='income-statement'){
            $amount=static function(array $items,string $code,string $field):float{foreach($items as $item)if((string)$item['account_code']===$code)return(float)$item[$field];return 0.0;};
            foreach([['calculated-gross-profit','매출총이익',fn(string $f):float=>$amount($rows,'400000',$f)-$amount($rows,'500000',$f)],['calculated-operating-income','영업이익',fn(string $f):float=>$amount($rows,'400000',$f)-$amount($rows,'500000',$f)-$amount($rows,'550000',$f)],['calculated-pretax-income','법인세차감전순이익',fn(string $f):float=>$amount($rows,'400000',$f)-$amount($rows,'500000',$f)-$amount($rows,'550000',$f)+$amount($rows,'600000',$f)-$amount($rows,'700000',$f)],['calculated-net-income','당기순이익',fn(string $f):float=>$this->netIncome($rows,$f)]] as $index=>$formula){$selected[]=$this->calculatedRow($formula[0],$formula[1],$formula[2]('current_amount'),$formula[2]('prior_amount'),'계산항목',1,9900+$index);}
        }
        if($type==='product-cost'){$current=0.0;$prior=0.0;foreach($selected as $row)if((string)$row['account_code']==='511000'){$current=(float)$row['current_amount'];$prior=(float)$row['prior_amount'];}$selected[]=$this->calculatedRow('calculated-product-cost','상품매출원가',$current,$prior,'계산항목',1,9999);}
        if($type==='construction-cost'){$current=0.0;$prior=0.0;foreach($selected as $row)if((string)$row['account_code']==='512000'){$current=(float)$row['current_amount'];$prior=(float)$row['prior_amount'];}$selected[]=$this->calculatedRow('calculated-construction-cost','완성공사원가',$current,$prior,'계산항목',1,9999);}
        if($type==='retained-earnings'){$incomeCurrent=$this->netIncome($rows,'current_amount');$incomePrior=$this->netIncome($rows,'prior_amount');$selected[]=['id'=>'calculated-net-income','parent_id'=>null,'account_code'=>'CALC-NET','account_name'=>'당기순이익','account_group'=>'자본','normal_balance'=>'CREDIT','level'=>2,'is_posting'=>0,'sort_no'=>9997,'opening_balance'=>0.0,'period_debit'=>0.0,'period_credit'=>0.0,'current_amount'=>$incomeCurrent,'prior_amount'=>$incomePrior,'change_amount'=>$incomeCurrent-$incomePrior,'change_rate'=>$incomePrior==0.0?null:(($incomeCurrent-$incomePrior)/$incomePrior*100),'ending_balance'=>$incomeCurrent,'prior_ending_balance'=>$incomePrior];$totalCurrent=array_sum(array_map(static fn(array $r):float=>((int)$r['level']===4?(float)$r['ending_balance']:0.0),$selected))+$incomeCurrent;$totalPrior=array_sum(array_map(static fn(array $r):float=>((int)$r['level']===4?(float)$r['prior_ending_balance']:0.0),$selected))+$incomePrior;$selected[]=['id'=>'calculated-carry-forward','parent_id'=>null,'account_code'=>'CALC-CARRY','account_name'=>'차기이월미처분이익잉여금','account_group'=>'자본','normal_balance'=>'CREDIT','level'=>1,'is_posting'=>0,'sort_no'=>9999,'opening_balance'=>0.0,'period_debit'=>0.0,'period_credit'=>0.0,'current_amount'=>$totalCurrent,'prior_amount'=>$totalPrior,'change_amount'=>$totalCurrent-$totalPrior,'change_rate'=>$totalPrior==0.0?null:(($totalCurrent-$totalPrior)/$totalPrior*100),'ending_balance'=>$totalCurrent,'prior_ending_balance'=>$totalPrior];}
        return $selected;
    }

    private function summary(string $type,array $rows): array
    {
        $root=static function(array $rows,string $group,string $field):float{$total=0.0;foreach($rows as $r)if((string)$r['account_group']===$group&&(int)$r['level']===1)$total+=(float)$r[$field];return $total;};
        $assets=$root($rows,'자산','current_amount');$liabilities=$root($rows,'부채','current_amount');$equity=$root($rows,'자본','current_amount');$revenue=$root($rows,'수익','current_amount');$expense=$root($rows,'비용','current_amount');
        if($type==='statement-position'){$assets=$root($rows,'자산','ending_balance');$liabilities=$root($rows,'부채','ending_balance');$equity=$root($rows,'자본','ending_balance');foreach($rows as $r)if(($r['id']??'')==='calculated-current-income')$equity+=(float)$r['current_amount'];}
        $netIncome=$revenue-$expense;if($type==='retained-earnings')foreach($rows as $r)if(($r['id']??'')==='calculated-net-income')$netIncome=(float)$r['current_amount'];
        return ['row_count'=>count($rows),'assets'=>$assets,'liabilities'=>$liabilities,'equity'=>$equity,'balance_difference'=>$assets-$liabilities-$equity,'revenue'=>$revenue,'expense'=>$expense,'net_income'=>$netIncome,'opening_debit'=>array_sum(array_map(static fn(array $r):float=>(int)$r['level']===1?(float)($r['opening_debit']??0):0.0,$rows)),'opening_credit'=>array_sum(array_map(static fn(array $r):float=>(int)$r['level']===1?(float)($r['opening_credit']??0):0.0,$rows)),'period_debit'=>array_sum(array_map(static fn(array $r):float=>(int)$r['level']===1?(float)($r['period_debit']??0):0.0,$rows)),'period_credit'=>array_sum(array_map(static fn(array $r):float=>(int)$r['level']===1?(float)($r['period_credit']??0):0.0,$rows))];
    }
    private function calculatedRow(string $id,string $name,float $current,float $prior,string $group,int $level,int $sort):array{return ['id'=>$id,'parent_id'=>null,'account_code'=>'CALC','account_name'=>$name,'account_group'=>$group,'normal_balance'=>'CREDIT','level'=>$level,'is_posting'=>0,'sort_no'=>$sort,'opening_balance'=>0.0,'opening_debit'=>0.0,'opening_credit'=>0.0,'period_debit'=>0.0,'period_credit'=>0.0,'current_amount'=>$current,'prior_amount'=>$prior,'change_amount'=>$current-$prior,'change_rate'=>$prior==0.0?null:(($current-$prior)/$prior*100),'ending_balance'=>$current,'prior_ending_balance'=>$prior];}
    private function netIncome(array $rows,string $field):float{$revenue=0.0;$expense=0.0;foreach($rows as $r){if((int)$r['level']!==1)continue;if($r['account_group']==='수익')$revenue+=(float)$r[$field];if($r['account_group']==='비용')$expense+=(float)$r[$field];}return $revenue-$expense;}
    private function date(string $value):string{$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('조회일자를 확인해 주세요.');return $value;}
}

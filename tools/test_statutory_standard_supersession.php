<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Models\System\StatutoryStandardSupersessionModel;
use App\Services\System\StatutoryStandardResolver;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;

$db=DbPdo::conn();$db->beginTransaction();$results=[];
$insert=$db->prepare('INSERT INTO system_statutory_standards(id,sort_no,standard_type_code,effective_from,effective_to,value_data,note,created_at,created_by,updated_at,updated_by) VALUES(:id,:sort,:type,:from,:to,:value,:note,NOW(),:actor,NOW(),:actor2)');
$create=function(string $type,string $from,?string $to,string $marker)use($insert):string{$id=UuidHelper::generate();$actor=ActorHelper::system('STATUTORY_SUPERSESSION_TEST');$insert->execute([':id'=>$id,':sort'=>900000+random_int(1,99999),':type'=>$type,':from'=>$from,':to'=>$to,':value'=>json_encode(['marker'=>$marker]),':note'=>'Supersession rollback fixture',':actor'=>$actor,':actor2'=>$actor]);return$id;};
$expectFailure=function(string $name,callable $callback)use(&$results):void{try{$callback();$results[$name]=false;}catch(Throwable){$results[$name]=true;}};
try {
    $resolver=new StatutoryStandardResolver($db);$relations=new StatutoryStandardSupersessionModel($db);$actor=ActorHelper::system('STATUTORY_SUPERSESSION_TEST');
    $a=$create('SUPERSESSION_FIXTURE','2090-01-01',null,'A');
    $results['A_no_supersession']=$resolver->resolve('SUPERSESSION_FIXTURE','2090-01-01')['id']===$a;
    $b=$create('SUPERSESSION_FIXTURE','2090-01-01','2090-12-31','B');$relations->create($a,$b,'A를 B로 정정',$actor);
    $results['B_a_to_b']=$resolver->resolve('SUPERSESSION_FIXTURE','2090-06-01')['id']===$b;
    $c=$create('SUPERSESSION_FIXTURE','2091-01-01',null,'C');$relations->create($b,$c,'B를 C로 정정',$actor);
    $results['C_a_to_b_to_c']=$resolver->resolve('SUPERSESSION_FIXTURE','2091-01-01')['id']===$c;
    $results['D_historical_reference']=$db->query("SELECT id FROM system_statutory_standards WHERE id=".$db->quote($a))->fetchColumn()===$a;
    $other=$create('SUPERSESSION_OTHER','2090-01-01',null,'OTHER');
    $expectFailure('E_duplicate_successor',fn()=> $relations->create($a,$other,'분기 금지',$actor));
    $expectFailure('F_cycle',fn()=> $relations->create($c,$a,'cycle 금지',$actor));
    $expectFailure('G_cross_type',fn()=> $relations->create($other,$a,'Type 불일치',$actor));
    $results['H_not_found']=$resolver->resolveOptional('SUPERSESSION_FIXTURE','2089-01-01')===null;
    $independent=$create('SUPERSESSION_FIXTURE','2091-01-01',null,'INDEPENDENT');
    $expectFailure('I_ambiguous',fn()=> $resolver->resolve('SUPERSESSION_FIXTURE','2091-01-01'));
    if (in_array(false,$results,true)) throw new RuntimeException('Supersession Fixture 검증에 실패했습니다.');
    echo json_encode(['success'=>true,'results'=>$results,'fixture_persisted'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
} finally {
    if ($db->inTransaction()) $db->rollBack();
}

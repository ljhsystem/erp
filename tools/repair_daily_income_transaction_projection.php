<?php

declare(strict_types=1);

define('PROJECT_ROOT',dirname(__DIR__));
require PROJECT_ROOT.'/vendor/autoload.php';

use App\Services\Institution\DailyEmploymentIncomeTransactionRepairService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;

$options=getopt('', ['transaction-id:','evidence-id:','reason:','execute']);
foreach(['transaction-id','evidence-id','reason'] as $required){if(!isset($options[$required])||trim((string)$options[$required])==='')throw new RuntimeException('REQUIRED_OPTION_MISSING');}
$db=DbPdo::conn();
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
if((string)$db->query('SELECT DATABASE()')->fetchColumn()!=='sukhyang')throw new RuntimeException('OPERATING_SCHEMA_MISMATCH');
$service=new DailyEmploymentIncomeTransactionRepairService($db);
try {
    $result=isset($options['execute'])
        ?$service->execute((string)$options['transaction-id'],(string)$options['evidence-id'],(string)$options['reason'],ActorHelper::system('DAILY_INCOME_PROJECTION_REPAIR'))
        :$service->dryRun((string)$options['transaction-id'],(string)$options['evidence-id'],(string)$options['reason']);
    echo json_encode(['success'=>true,'mode'=>isset($options['execute'])?'EXECUTE':'DRY_RUN','result'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
} catch(Throwable $e) {
    fwrite(STDERR,json_encode(['success'=>false,'error_code'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL);
    exit(1);
}

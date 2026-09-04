<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\DailyEmploymentIncomeAccountingLinkPolicyService;

$policy = new DailyEmploymentIncomeAccountingLinkPolicyService();
$base = ['closure_id'=>'c','daily_employment_income_id'=>'d','daily_employment_income_group_id'=>'g',
    'daily_employment_income_item_id'=>'i','worker_client_id'=>'w','business_key_hash'=>str_repeat('a',64),
    'payload_hash'=>str_repeat('b',64),'evidence_id'=>'e'];
$policy->validate($base + ['artifact_role'=>'EVIDENCE','evidence_id'=>'e']);
$policy->validate($base + ['artifact_role'=>'WORKER_PAYMENT','worker_client_id'=>'w','transaction_id'=>'t']);
foreach (['INSTITUTION_EVIDENCE','ACCOUNTING_VOUCHER','UNKNOWN'] as $role) {
    try {
        $policy->validate($base + ['artifact_role'=>$role]);
        throw new RuntimeException("Legacy 역할 {$role}이 차단되지 않았습니다.");
    } catch (InvalidArgumentException) {
    }
}
echo "OK: 일용근로소득 연결 역할 EVIDENCE·WORKER_PAYMENT SSOT\n";

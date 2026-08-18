<?php

declare(strict_types=1);

use App\Services\Institution\PersonnelActionChangePolicy;
use App\Services\Institution\PersonnelActionApplyService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$results = [];
$expectPass = static function (string $name, callable $callback) use (&$results): void {
    $callback();
    $results[$name] = true;
};
$expectFail = static function (string $name, callable $callback) use (&$results): void {
    try {
        $callback();
        $results[$name] = false;
    } catch (InvalidArgumentException) {
        $results[$name] = true;
    }
};
$change = static fn(string $type, array $values = []): array => ['change_type_code' => $type] + $values;

$results['command_count_11'] = count(PersonnelActionChangePolicy::commandCodes()) === 11;
$results['action_count_13'] = count(PersonnelActionChangePolicy::actionTypes()) === 13;
$results['label_count_11'] = count(PersonnelActionChangePolicy::metadata()['commands']) === 11;

$pdo = DbPdo::conn();
$dbActionTypes = $pdo->query("SELECT code FROM system_codes WHERE code_group='PERSONNEL_ACTION_TYPE' AND is_active=1 ORDER BY code")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$policyActionTypes = PersonnelActionChangePolicy::actionTypes();
sort($dbActionTypes);
sort($policyActionTypes);
$results['active_system_codes_match_policy'] = $dbActionTypes === $policyActionTypes;
$applyService = new PersonnelActionApplyService($pdo);
$applyChange = new ReflectionMethod($applyService, 'applyChange');
try {
    $applyChange->invoke($applyService, ['id' => 'fixture-employee'], 'fixture-target', ['change_type_code' => 'INVALID', 'effective_date' => date('Y-m-d')], 'SYSTEM:PERSONNEL_ACTION_POLICY_TEST');
    $results['apply_default_rejects_unknown'] = false;
} catch (ReflectionException $exception) {
    throw $exception;
} catch (Throwable $exception) {
    $results['apply_default_rejects_unknown'] = $exception instanceof RuntimeException && str_contains($exception->getMessage(), '지원하지 않는');
}

$expectPass('department_transfer_pass', fn() => PersonnelActionChangePolicy::assertCommandSet('DEPARTMENT_TRANSFER', [$change('DEPARTMENT')]));
$expectFail('department_transfer_job_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('DEPARTMENT_TRANSFER', [$change('JOB')]));
$expectFail('leave_only_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('LEAVE_OF_ABSENCE', [$change('LEAVE')]));
$expectFail('leave_status_only_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('LEAVE_OF_ABSENCE', [$change('EMPLOYMENT_STATUS', ['after_employment_status' => 'ON_LEAVE'])]));
$expectPass('leave_complete_pass', fn() => PersonnelActionChangePolicy::assertCommandSet('LEAVE_OF_ABSENCE', [$change('LEAVE'), $change('EMPLOYMENT_STATUS', ['after_employment_status' => 'ON_LEAVE'])]));
$expectFail('leave_wrong_status_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('LEAVE_OF_ABSENCE', [$change('LEAVE'), $change('EMPLOYMENT_STATUS', ['after_employment_status' => 'ACTIVE'])]));
$expectPass('reinstatement_complete_pass', fn() => PersonnelActionChangePolicy::assertCommandSet('REINSTATEMENT', [$change('RETURN_FROM_LEAVE'), $change('EMPLOYMENT_STATUS', ['after_employment_status' => 'ACTIVE'])]));
$expectPass('retirement_complete_pass', fn() => PersonnelActionChangePolicy::assertCommandSet('RETIREMENT', [$change('RETIRE_DATE'), $change('EMPLOYMENT_STATUS', ['after_employment_status' => 'RETIRED'])]));
$expectPass('hire_complete_pass', fn() => PersonnelActionChangePolicy::assertCommandSet('HIRE', [$change('HIRE_DATE'), $change('EMPLOYMENT_STATUS', ['after_employment_status' => 'ACTIVE'])]));
$expectFail('hire_incomplete_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('HIRE', [$change('EMPLOYMENT_STATUS', ['after_employment_status' => 'ACTIVE'])]));
$expectPass('transfer_required_any_pass', fn() => PersonnelActionChangePolicy::assertCommandSet('TRANSFER', [$change('JOB')]));
$expectFail('transfer_empty_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('TRANSFER', []));
$expectPass('other_required_any_pass', fn() => PersonnelActionChangePolicy::assertCommandSet('OTHER', [$change('WORKPLACE')]));
$expectFail('other_empty_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('OTHER', []));
$expectFail('duplicate_change_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('OTHER', [$change('DEPARTMENT'), $change('DEPARTMENT')]));
$expectFail('unknown_change_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('OTHER', [$change('INVALID')]));
$expectFail('unknown_action_fail', fn() => PersonnelActionChangePolicy::assertCommandSet('CUSTOM_ACTION', [$change('DEPARTMENT')]));

$view = file_get_contents(PROJECT_ROOT . '/app/views/institution/personnel-action/index.php');
$results['view_hardcoded_change_options_zero'] = is_string($view) && preg_match('/<option value="(?:EMPLOYMENT_STATUS|DEPARTMENT|POSITION|JOB|PROJECT_ASSIGNMENT|PROJECT_RELEASE|WORKPLACE|LEAVE|RETURN_FROM_LEAVE|HIRE_DATE|RETIRE_DATE)">/', $view) === 0;

$failed = array_keys(array_filter($results, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'results' => $results, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);

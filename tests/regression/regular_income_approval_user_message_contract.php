<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeService.php');
$controller = (string) file_get_contents($root . '/app/Controllers/Approval/ApprovalInboxController.php');
$client = (string) file_get_contents($root . '/public/assets/js/pages/approval/inbox/index.js');
$successMessage = '최종 승인이 완료되었습니다. 직원별 급여 증빙과 거래가 생성되었습니다. 증빙관리에서 업무분류를 확인해 주세요.';
$forbidden = ['Closure', 'Accounting Generation', 'Evidence Link', 'Registry', 'Institution Liability', 'Transaction Projection', 'Preflight'];
$userResponseSection = strstr($service, '$userMessage=') ?: '';
$checks = [
    'approved_message' => str_contains($service, $successMessage),
    'compatible_message_fields' => str_contains($service, "'message'=>\$userMessage") && str_contains($service, "'user_message'=>\$userMessage"),
    'machine_result_code' => str_contains($service, 'REGULAR_INCOME_FINAL_APPROVAL_COMPLETED'),
    'no_forbidden_success_term' => array_filter($forbidden, static fn(string $term): bool => str_contains(substr($userResponseSection, 0, 500), $term)) === [],
    'common_notification_import' => str_contains($client, "import { notify as showNotification } from '/public/assets/js/common/notification.js';"),
    'no_alert_path' => !str_contains($client, 'window.alert(') && !preg_match('/(^|[^.])alert\(/', $client),
    'user_message_preferred' => str_contains($client, 'result.user_message || result.message'),
    'correlation_visible' => str_contains($client, '오류 추적번호:'),
    'warning_and_error_toast' => str_contains($client, "error.notificationType = systemError ? 'error' : 'warning'"),
    'duplicate_click_guard' => str_contains($client, 'if (acting || !detail?.actions?.can_act) return;') && str_contains($client, 'setActing(true)'),
    'api_compatibility' => str_contains($controller, "'message'=>\$userMessage") && str_contains($controller, "'user_message'=>\$userMessage"),
    'system_error_correlation' => substr_count($controller, "'correlation_id' => \$correlationId") >= 2,
];
$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);

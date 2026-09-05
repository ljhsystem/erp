<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$serviceRoot = $root . '/app/Services';
$classification = require $root . '/config/service_logging_contract.php';
$serviceFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($serviceRoot));

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_contains($path, '/Services/Calendar/')) continue;
    $serviceFiles[] = $path;
}

sort($serviceFiles);
$summary = [
    'service_files' => count($serviceFiles),
    'logger_factory_files' => 0,
    'log_call_files' => 0,
    'log_calls' => 0,
    'violations' => [],
    'review_required' => [],
    'classified_exceptions' => [],
];

$sensitiveContextPattern = '/[\'\"](?:rrn|resident_registration|password|passwd|secret|token|authorization|cookie|session|api_key|service_key|abs|abs_path|db_path|file_path|full_path)[\'\"]\s*=>/i';
$rawContextPattern = '/[\'\"](?:data|payload|rows|filters|changes|path|file|files|trace)[\'\"]\s*=>/i';
$mutationPattern = '/\b(?:public\s+(?:static\s+)?function\s+(?:save|create|update|delete|restore|purge|submit|withdraw|approve|reject|act|upload|send|sync|reorder|run|execute|process|apply|activate|deactivate|mark|replace|correct|transition|import|export)|beginTransaction\s*\()/i';

foreach ($serviceFiles as $path) {
    $code = file_get_contents($path);
    $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
    if ($code === false) {
        $summary['violations'][] = ['file' => $relative, 'reason' => '파일을 읽을 수 없습니다.'];
        continue;
    }
    $logCalls = preg_match_all('/->(?:debug|info|notice|warning|error|critical|alert|emergency|log)\s*\(/', $code)
        + preg_match_all('/->\{\$[A-Za-z_][A-Za-z0-9_]*\}\s*\(/', $code);
    $usesOperationTrait = str_contains($code, 'use LogsServiceOperations;');
    if ($usesOperationTrait && !str_contains($code, 'runLoggedOperation(')) {
        $summary['violations'][] = ['file' => $relative, 'reason' => '공용 로그 Trait를 선언했지만 실제 업무 경계에서 호출하지 않습니다.'];
    }
    $hasBoundaryLogger = $logCalls > 0 || $usesOperationTrait;
    if (str_contains($code, 'LoggerFactory')) $summary['logger_factory_files']++;
    if ($logCalls > 0) $summary['log_call_files']++;
    $summary['log_calls'] += $logCalls;

    if (preg_match('/\berror_log\s*\(/', $code)) {
        $summary['violations'][] = ['file' => $relative, 'reason' => 'Service에서 error_log()를 직접 호출합니다.'];
    }
    if (preg_match('~file_put_contents\s*\([^;]{0,400}(?:storage(?:/|\\\\)logs|\.log[\'\"]|_log\.txt)~is', $code)) {
        $summary['violations'][] = ['file' => $relative, 'reason' => 'Service가 LoggerFactory를 우회하여 개별 로그파일을 기록합니다.'];
    }
    $lines = preg_split('/\R/u', $code) ?: [];
    foreach ($lines as $index => $line) {
        if (!preg_match('/->(?:(?:debug|info|notice|warning|error|critical|alert|emergency|log)|\{\$[A-Za-z_][A-Za-z0-9_]*\})\s*\(/', $line)) continue;
        $blockLines = [];
        foreach (array_slice($lines, $index, 20) as $candidateLine) {
            $endArray = strpos($candidateLine, ']);');
            $endCall = strpos($candidateLine, ');');
            $end = $endArray !== false ? $endArray + 3 : ($endCall !== false ? $endCall + 2 : false);
            $blockLines[] = $end === false ? $candidateLine : substr($candidateLine, 0, $end);
            if ($end !== false) break;
        }
        $block = implode("\n", $blockLines);
        if (preg_match('/->(?:debug|info|notice|warning|error|critical|alert|emergency|log)\s*\(\s*[\'\"]\s*[A-Za-z]/', $line)) {
            $summary['violations'][] = ['file'=>$relative,'reason'=>'사용자 로그관리에서 이해하기 어려운 영문 로그 메시지가 남아 있습니다.'];
            break;
        }
        if (preg_match('/(?:initialized|\(\)\s*called|\breturned\b|호출)/i', $line)) {
            $summary['violations'][] = ['file'=>$relative,'reason'=>'업무 결과가 아닌 초기화·호출·조회성 잡음 로그가 남아 있습니다.'];
            break;
        }
        if (preg_match('/getMessage\s*\(|getTraceAsString\s*\(/', $block)) {
            $summary['violations'][] = ['file'=>$relative,'reason'=>'예외 원문 또는 Stack Trace를 Logger Context에 직접 기록합니다.'];
            break;
        }
        if (preg_match($sensitiveContextPattern, $block)) {
            $summary['violations'][] = ['file'=>$relative,'reason'=>'Logger Context에 금지된 민감정보 키가 전달됩니다.'];
            break;
        }
        if (preg_match($rawContextPattern, $block)) {
            $summary['violations'][] = ['file'=>$relative,'reason'=>'Logger Context에 원본 입력 배열 또는 파일경로를 직접 전달합니다.'];
            break;
        }
    }
    if (preg_match($mutationPattern, $code) && !$hasBoundaryLogger) {
        $type = $classification[$relative] ?? null;
        if (in_array($type, ['PURE','DELEGATED','INFRASTRUCTURE'], true)) {
            $summary['classified_exceptions'][] = ['file'=>$relative,'type'=>$type];
        } else {
            $summary['violations'][] = [
                'file' => $relative,
                'reason' => '상태 변경 업무 경계로 보이지만 로그가 없고 비기록 책임도 분류되지 않았습니다.',
            ];
        }
    }
    if (preg_match($mutationPattern, $code) && $hasBoundaryLogger && !isset($classification[$relative])) {
        $hasSuccessOutcome = $usesOperationTrait || preg_match('/[\'\"]result[\'\"]\s*=>\s*(?:[\'\"]SUCCESS[\'\"]|[^;\n]*[\'\"]SUCCESS[\'\"]|\$outcome)/', $code) === 1;
        $dynamicOutcomeContract = preg_match('/(?=.*\$outcome\s*=)(?=.*[\'\"]SUCCESS[\'\"])(?=.*[\'\"]BLOCKED[\'\"])(?=.*[\'\"]FAILED[\'\"])(?=.*[\'\"]result[\'\"]\s*=>\s*\$outcome)/s', $code) === 1;
        $hasBlockedOutcome = $usesOperationTrait || $dynamicOutcomeContract || preg_match('/[\'\"]result[\'\"]\s*=>\s*(?:[\'\"]BLOCKED[\'\"]|[^;\n]*[\'\"]BLOCKED[\'\"])/', $code) === 1;
        $hasFailedOutcome = $usesOperationTrait || $dynamicOutcomeContract || preg_match('/[\'\"]result[\'\"]\s*=>\s*(?:[\'\"]FAILED[\'\"]|[^;\n]*[\'\"]FAILED[\'\"])/', $code) === 1;
        $hasBusinessBlockPath = preg_match('/(?:InvalidArgumentException|DomainException)/', $code) === 1;
        $hasSystemFailurePath = preg_match('/(?:PDOException|catch\s*\(\s*\\?Throwable|beginTransaction\s*\(|IOFactory|Mailer|curl_)/i', $code) === 1;
        if (!$hasSuccessOutcome) {
            $summary['review_required'][] = ['file' => $relative, 'reason' => '상태 변경 경계의 성공 로그가 확인되지 않습니다.'];
        }
        if ($hasBusinessBlockPath && !$hasBlockedOutcome) {
            $summary['review_required'][] = ['file' => $relative, 'reason' => '업무 차단 로그가 확인되지 않습니다.'];
        }
        if ($hasSystemFailurePath && !$hasFailedOutcome) {
            $summary['review_required'][] = ['file' => $relative, 'reason' => '시스템 실패 로그가 확인되지 않습니다.'];
        }
    }
}

foreach (['app/Controllers', 'app/Models', 'app/Repositories', 'app/views', 'public/assets/js'] as $relativeRoot) {
    $directory = $root . '/' . $relativeRoot;
    if (!is_dir($directory)) continue;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($files as $file) {
        if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js'], true)) continue;
        $code = file_get_contents($file->getPathname());
        if ($code === false) continue;
        if (preg_match('/\berror_log\s*\(|LoggerFactory::getLogger\s*\(/', $code)) {
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            $summary['violations'][] = ['file' => $relative, 'reason' => '업무 로그는 Service에서만 기록해야 합니다.'];
        }
    }
}

$summary['status'] = $summary['violations'] === [] && $summary['review_required'] === [] ? 'PASS' : 'FAIL';
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($summary['status'] === 'PASS' ? 0 : 1);

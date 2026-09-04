<?php

declare(strict_types=1);

$servicePath = dirname(__DIR__, 2) . '/app/Services/Ledger/EvidenceGenerationService.php';
$source = file_get_contents($servicePath);
$editorPath = dirname(__DIR__, 2) . '/public/assets/js/pages/ledger/evidence-page-app.js';
$editorSource = file_get_contents($editorPath);

if ($source === false || $editorSource === false) {
    fwrite(STDERR, "증빙원본 편집 조회 구현을 읽을 수 없습니다.\n");
    exit(1);
}

$directLookup = "if (\$requestedId !== '' && \$importType !== '')";
$staticListLookup = "elseif (\$importType !== '')";
$directPosition = strpos($source, $directLookup);
$staticPosition = strpos($source, $staticListLookup);

if ($directPosition === false || $staticPosition === false || $directPosition >= $staticPosition) {
    fwrite(STDERR, "증빙원본 ID 상세조회가 목록 지원 유형 제한보다 우선하지 않습니다.\n");
    exit(1);
}

if (!str_contains($source, '$bodyQueryTypes = [$importType];')) {
    fwrite(STDERR, "증빙원본 ID 상세조회에 요청 자료유형이 적용되지 않습니다.\n");
    exit(1);
}

if (!str_contains($editorSource, "fetch(API.seedRows, {")
    || !str_contains($editorSource, "method: 'POST'")) {
    fwrite(STDERR, "증빙원본 편집 조회가 공식 POST API 계약을 사용하지 않습니다.\n");
    exit(1);
}

fwrite(STDOUT, "evidence editor direct lookup contract: OK\n");

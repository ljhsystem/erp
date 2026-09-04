<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    $root . '/app/Services/Institution/EmploymentContractService.php',
    $root . '/app/Controllers/Institution/EmploymentContractController.php',
    $root . '/routes/api/institution.php',
    $root . '/app/views/institution/employment-contract/index.php',
    $root . '/public/assets/js/pages/institution/employment-contract/modal-runtime.js',
    $root . '/public/assets/js/pages/institution/employment-contract/statutory-validation.js',
];
$source = implode("\n", array_map(static fn(string $file): string => (string) file_get_contents($file), $files));
$checks = [
    'EmploymentContractStatutoryProjectionService' => 'Projection Service 연결이 없습니다.',
    'statutory-projection' => 'Projection API Route가 없습니다.',
    'employmentStatutoryProjection' => 'Projection UI가 없습니다.',
    'void statutoryValidation.load(id)' => '상세 Modal lazy-load가 없습니다.',
    "if (!empty(\$projection['approval_blocked']))" => '결재요청 Guard가 없습니다.',
];
foreach ($checks as $needle => $message) {
    if (!str_contains($source, $needle)) throw new RuntimeException($message);
}

echo "employment contract statutory projection contract: OK\n";

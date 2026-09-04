<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;
use Core\Helpers\ActorHelper;

$mode = $argv[1] ?? 'audit';
if (!in_array($mode, ['audit', 'apply'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_2013_statutory_period_corrections.php [audit|apply]');
}

$targets = [
    '021889f4-3c43-466d-8e33-80f9f39455bc' => [
        'type' => 'BUSINESS_INCOME_WITHHOLDING',
        'before_from' => '2013-01-01',
        'after_from' => '1998-01-01',
        'to' => null,
        'note' => '원천징수대상 사업소득 3% 세율(1998.1.1. 이후)',
    ],
    'fcdcf736-719f-4ffe-aac0-c3817667b2d0' => [
        'type' => 'CORPORATE_TAX',
        'before_from' => '2013-01-01',
        'after_from' => '2012-01-01',
        'to' => '2017-12-31',
        'note' => '2012년 1월 1일 이후 개시 사업연도 법인세 표준세율',
    ],
    'b8e71053-e476-4d6c-b11f-c9e9f33ee3ba' => [
        'type' => 'DAILY_WORKER_INCOME_TAX',
        'before_from' => '2013-01-01',
        'after_from' => '2009-01-01',
        'to' => '2018-12-31',
        'note' => '2009년 귀속분부터 적용된 일용근로소득 원천징수 계산계약',
    ],
    'e87a3872-b45d-4be8-8b02-6f102bf6ec99' => [
        'type' => 'EMPLOYMENT_INSURANCE',
        'before_from' => '2013-01-01',
        'after_from' => '2011-04-01',
        'to' => '2013-06-30',
        'note' => '2011.4.1.~2013.6.30. 고용보험료율',
    ],
    '7255f865-08d0-4fd9-b9d6-85361f93fe0a' => [
        'type' => 'LOCAL_INCOME_TAX_WITHHOLDING',
        'before_from' => '2013-01-01',
        'after_from' => '2010-01-01',
        'to' => '2013-12-31',
        'note' => '2010.1.1.~2013.12.31. 지방소득세 소득분 특별징수',
    ],
];

$db = DbPdo::conn();
$placeholders = implode(',', array_fill(0, count($targets), '?'));
$select = $db->prepare(
    "SELECT id, standard_type_code, effective_from, effective_to, value_data, note, updated_at, updated_by
       FROM system_statutory_standards
      WHERE id IN ($placeholders)
      ORDER BY standard_type_code, effective_from, id"
);
$select->execute(array_keys($targets));
$rows = $select->fetchAll(PDO::FETCH_ASSOC) ?: [];
if (count($rows) !== count($targets)) {
    throw new RuntimeException('법정기준 기간 보정 대상이 정확히 5건이 아닙니다.');
}

$beforeById = array_column($rows, null, 'id');
$changes = [];
foreach ($targets as $id => $target) {
    $row = $beforeById[$id] ?? null;
    if (!$row
        || $row['standard_type_code'] !== $target['type']
        || $row['effective_from'] !== $target['before_from']
        || $row['effective_to'] !== $target['to']
        || !json_validate((string) $row['value_data'])) {
        throw new RuntimeException($target['type'] . '의 운영 기준이 승인 전제와 다릅니다.');
    }
    $changes[] = [
        'id' => $id,
        'type' => $target['type'],
        'before' => ['from' => $row['effective_from'], 'to' => $row['effective_to'], 'note' => $row['note']],
        'after' => ['from' => $target['after_from'], 'to' => $target['to'], 'note' => $target['note']],
    ];
}

$periods = $db->query(
    'SELECT id, standard_type_code, effective_from, effective_to FROM system_statutory_standards'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($periods as &$period) {
    if (isset($targets[$period['id']])) {
        $period['effective_from'] = $targets[$period['id']]['after_from'];
    }
}
unset($period);

for ($left = 0, $count = count($periods); $left < $count; $left++) {
    for ($right = $left + 1; $right < $count; $right++) {
        if ($periods[$left]['standard_type_code'] !== $periods[$right]['standard_type_code']) {
            continue;
        }
        $leftTo = $periods[$left]['effective_to'] ?? '9999-12-31';
        $rightTo = $periods[$right]['effective_to'] ?? '9999-12-31';
        if ($periods[$left]['effective_from'] <= $rightTo && $leftTo >= $periods[$right]['effective_from']) {
            throw new RuntimeException($periods[$left]['standard_type_code'] . ' 적용기간 중복이 발생합니다.');
        }
    }
}

if ($mode === 'audit') {
    echo json_encode(['success' => true, 'mode' => $mode, 'changes' => $changes], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

$actor = ActorHelper::system('STATUTORY_2013_PERIOD_CORRECTION');
$update = $db->prepare(
    'UPDATE system_statutory_standards
        SET effective_from = :after_from, note = :note, updated_at = NOW(), updated_by = :actor
      WHERE id = :id
        AND standard_type_code = :type
        AND effective_from = :before_from
        AND effective_to <=> :effective_to
        AND value_data = :value_data'
);

$db->beginTransaction();
try {
    $affected = 0;
    foreach ($targets as $id => $target) {
        $row = $beforeById[$id];
        $update->execute([
            'after_from' => $target['after_from'],
            'note' => $target['note'],
            'actor' => $actor,
            'id' => $id,
            'type' => $target['type'],
            'before_from' => $target['before_from'],
            'effective_to' => $target['to'],
            'value_data' => $row['value_data'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException($target['type'] . ' 기간 수정 행 수가 1건이 아닙니다.');
        }
        $affected++;
    }

    $verify = $db->prepare('SELECT effective_from, effective_to, note FROM system_statutory_standards WHERE id = ?');
    foreach ($targets as $id => $target) {
        $verify->execute([$id]);
        $after = $verify->fetch(PDO::FETCH_ASSOC) ?: [];
        if (($after['effective_from'] ?? null) !== $target['after_from']
            || ($after['effective_to'] ?? null) !== $target['to']
            || ($after['note'] ?? null) !== $target['note']) {
            throw new RuntimeException($target['type'] . ' 기간 적용 후 검증에 실패했습니다.');
        }
    }
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

echo json_encode([
    'success' => true,
    'mode' => $mode,
    'affected' => $affected,
    'actor' => $actor,
    'changes' => $changes,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;

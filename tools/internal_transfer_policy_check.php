<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Repositories\Funds\InternalTransferRepository;

function baseCandidate(): array
{
    return [
        'voucher' => [
            'id' => 'voucher-1',
            'status' => 'REVIEWED',
            'deleted_at' => null,
            'is_reversal' => 0,
            'reversal_of' => null,
        ],
        'evidences' => [
            [
                'id' => 'evidence-out',
                'bank_account_id' => 'account-out',
                'withdraw_amount' => 100,
                'deposit_amount' => 0,
                'account_exists' => true,
            ],
            [
                'id' => 'evidence-in',
                'bank_account_id' => 'account-in',
                'withdraw_amount' => 0,
                'deposit_amount' => 100,
                'account_exists' => true,
            ],
        ],
        'bank_account_line_refs' => [
            [
                'voucher_line_id' => 'line-credit',
                'bank_account_id' => 'account-out',
                'debit' => 0,
                'credit' => 100,
                'account_exists' => true,
            ],
            [
                'voucher_line_id' => 'line-debit',
                'bank_account_id' => 'account-in',
                'debit' => 100,
                'credit' => 0,
                'account_exists' => true,
            ],
        ],
    ];
}

$cases = [];
foreach (['REVIEWED', 'POSTED', 'CLOSED'] as $status) {
    $candidate = baseCandidate();
    $candidate['voucher']['status'] = $status;
    $cases[$status] = [$candidate, true];
}
foreach (['DRAFT', 'REVIEW_REQUESTED'] as $status) {
    $candidate = baseCandidate();
    $candidate['voucher']['status'] = $status;
    $cases[$status] = [$candidate, false];
}

$candidate = baseCandidate();
$candidate['voucher']['deleted_at'] = '2026-07-30 00:00:00';
$cases['deleted voucher'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['voucher']['is_reversal'] = 1;
$cases['reversal voucher'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['voucher']['reversal_of'] = 'voucher-original';
$cases['reversal-of voucher'] = [$candidate, false];

$candidate = baseCandidate();
array_pop($candidate['evidences']);
$cases['one evidence'] = [$candidate, false];

$candidate = baseCandidate();
$thirdEvidence = $candidate['evidences'][0];
$thirdEvidence['id'] = 'evidence-extra';
$candidate['evidences'][] = $thirdEvidence;
$cases['three evidences'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['evidences'][0]['withdraw_amount'] = 0;
$candidate['evidences'][0]['deposit_amount'] = 100;
$cases['two deposits'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['evidences'][1]['withdraw_amount'] = 100;
$candidate['evidences'][1]['deposit_amount'] = 0;
$cases['two withdrawals'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['evidences'][1]['deposit_amount'] = 99.99;
$cases['evidence amount mismatch'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['evidences'][1]['bank_account_id'] = 'account-out';
$cases['same bank account'] = [$candidate, false];

$candidate = baseCandidate();
array_pop($candidate['bank_account_line_refs']);
$cases['missing account ref'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['bank_account_line_refs'][] = [
    'voucher_line_id' => 'line-extra',
    'bank_account_id' => 'account-extra',
    'debit' => 0,
    'credit' => 1,
    'account_exists' => true,
];
$cases['extra account ref'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['bank_account_line_refs'][0]['debit'] = 100;
$candidate['bank_account_line_refs'][0]['credit'] = 0;
$cases['outgoing account on debit'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['bank_account_line_refs'][1]['debit'] = 0;
$candidate['bank_account_line_refs'][1]['credit'] = 100;
$cases['incoming account on credit'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['bank_account_line_refs'][0]['credit'] = 99;
$cases['voucher line amount mismatch'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['evidences'][0]['account_exists'] = false;
$cases['missing evidence bank account'] = [$candidate, false];

$candidate = baseCandidate();
$candidate['bank_account_line_refs'][0]['account_exists'] = false;
$cases['missing referenced bank account'] = [$candidate, false];

$failed = 0;
foreach ($cases as $name => [$candidate, $expected]) {
    $actual = InternalTransferRepository::qualifies($candidate);
    $passed = $actual === $expected;
    echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}

echo sprintf('TOTAL=%d FAILED=%d', count($cases), $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);

<?php

use Core\Helpers\AssetHelper;

$overview = isset($overview) && is_array($overview) ? $overview : [];
$groups = isset($overview['groups']) && is_array($overview['groups']) ? $overview['groups'] : [];
$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => false,
    'wrapper' => 'single',
];
$pageStyles = AssetHelper::css('/assets/css/pages/funds/funds-overview/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/funds/funds-overview/index.js');
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn(mixed $value): string => number_format((float) $value);
$date = static function (mixed $value): string {
    $text = trim((string) $value);
    if ($text === '') {
        return '-';
    }

    $timestamp = strtotime($text);
    return $timestamp === false ? $text : date('Y-m-d', $timestamp);
};
?>

<main class="funds-overview-page" id="fundsOverviewPage">
    <div class="container-fluid py-3">
        <header class="funds-overview-header">
            <div class="funds-overview-title">
                <span class="funds-overview-title-icon"><i class="bi bi-wallet2"></i></span>
                <div>
                    <h5>자금현황</h5>
                    <p>회사가 보유한 자금을 한눈에 확인하세요.</p>
                </div>
            </div>
            <label class="funds-overview-search">
                <span class="visually-hidden">계좌 검색</span>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" id="fundsOverviewSearch" class="form-control" placeholder="계좌명, 은행, 계좌번호 검색">
            </label>
        </header>

        <section class="funds-overview-hero" aria-label="전체 자금 요약">
            <div class="funds-overview-total">
                <span class="funds-overview-eyebrow">TOTAL AVAILABLE FUNDS</span>
                <div class="funds-overview-total-value">
                    <strong><?= $money($overview['total_balance'] ?? 0) ?></strong>
                    <span>원</span>
                </div>
                <p>실제잔액 <?= $money($overview['actual_balance'] ?? 0) ?>원 · 회계잔액 <?= $money($overview['accounting_balance'] ?? 0) ?>원 · 차이 <?= $money($overview['balance_difference'] ?? 0) ?>원</p>
            </div>
            <div class="funds-overview-kpis">
                <a class="funds-overview-kpi funds-overview-kpi-cash" href="<?= $escape($overview['cash_transactions_url'] ?? '/ledger/funds') ?>">
                    <span class="funds-overview-kpi-icon"><i class="bi bi-cash-stack"></i></span>
                    <div>
                        <span>보유 현금 · <?= (int) ($overview['cash_account_count'] ?? 0) ?>개</span>
                        <strong><?= $money($overview['cash_balance'] ?? 0) ?><small>원</small></strong>
                    </div>
                    <i class="bi bi-chevron-right funds-overview-kpi-arrow"></i>
                </a>
                <a class="funds-overview-kpi" href="<?= $escape($overview['operating_transactions_url'] ?? '/ledger/funds') ?>">
                    <span class="funds-overview-kpi-icon"><i class="bi bi-credit-card-2-front"></i></span>
                    <div>
                        <span>운영 계좌</span>
                        <strong><?= (int) ($overview['account_count'] ?? 0) ?><small>개</small></strong>
                    </div>
                    <i class="bi bi-chevron-right funds-overview-kpi-arrow"></i>
                </a>
                <a class="funds-overview-kpi" href="<?= $escape($overview['last_transaction_url'] ?? '/ledger/funds') ?>">
                    <span class="funds-overview-kpi-icon"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <span>최종 거래일</span>
                        <strong><?= $escape($date($overview['last_transaction_at'] ?? null)) ?></strong>
                    </div>
                    <i class="bi bi-chevron-right funds-overview-kpi-arrow"></i>
                </a>
            </div>
        </section>

        <div class="funds-overview-section-heading">
            <div>
                <h6>자금 유형별 현황</h6>
                <span>계좌명을 누르면 상세 거래내역으로 이동합니다.</span>
            </div>
            <span class="funds-overview-group-total"><?= count($groups) ?>개 유형</span>
        </div>

        <div class="funds-overview-groups">
            <?php foreach ($groups as $group): ?>
                <?php $accountCount = count($group['accounts'] ?? []); ?>
                <section class="funds-overview-group" data-funds-group data-expanded="false">
                    <header class="funds-overview-group-header">
                        <div class="funds-overview-group-identity">
                            <span class="funds-overview-group-icon"><i class="bi <?= $escape($group['icon'] ?? 'bi-wallet2') ?>"></i></span>
                            <div>
                                <h6><?= $escape($group['label'] ?? '') ?></h6>
                                <span><?= $accountCount ?>개 계좌</span>
                            </div>
                        </div>
                        <div class="funds-overview-group-balance">
                            <span>합계 잔액</span>
                            <strong><?= $money($group['total_balance'] ?? 0) ?><small>원</small></strong>
                        </div>
                    </header>

                    <div class="funds-overview-account-list">
                        <?php foreach (($group['accounts'] ?? []) as $accountIndex => $account): ?>
                            <?php
                            $searchText = implode(' ', [
                                $account['account_name'] ?? '',
                                $account['bank_name'] ?? '',
                                $account['account_number'] ?? '',
                            ]);
                            ?>
                            <a
                                class="funds-overview-account<?= $accountIndex >= 5 ? ' funds-overview-account-extra' : '' ?>"
                                href="<?= $escape($account['transactions_url'] ?? '#') ?>"
                                data-funds-account
                                data-search-text="<?= $escape(mb_strtolower($searchText, 'UTF-8')) ?>"
                            >
                                <div class="funds-overview-account-main">
                                    <div>
                                        <strong><?= $escape($account['account_name'] ?? '-') ?></strong>
                                        <span><?= $escape($account['bank_name'] ?? '-') ?> · <?= $escape($account['account_number'] ?? '-') ?></span>
                                    </div>
                                </div>
                                <div class="funds-overview-account-value">
                                    <strong><?= $money($account['actual_balance'] ?? 0) ?><small>원</small></strong>
                                    <span>회계 <?= $money($account['accounting_balance'] ?? 0) ?>원 · 차이 <?= $money($account['balance_difference'] ?? 0) ?>원</span>
                                    <span>최종 <?= $escape($date($account['last_transaction_at'] ?? null)) ?></span>
                                </div>
                                <i class="bi bi-chevron-right funds-overview-account-arrow" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($accountCount > 5): ?>
                        <button type="button" class="funds-overview-more" data-funds-toggle aria-expanded="false">
                            <span data-funds-toggle-label>계좌 <?= $accountCount - 5 ?>개 더보기</span>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </button>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        <div class="funds-overview-empty<?= $groups === [] ? '' : ' d-none' ?>" id="fundsOverviewEmpty">
            <i class="bi bi-wallet2"></i>
            <strong>표시할 계좌가 없습니다.</strong>
            <span>사용 중인 계좌를 등록하거나 검색어를 확인해 주세요.</span>
        </div>
    </div>
</main>

<?php

use Core\Helpers\AssetHelper;

$pageTitle = $pageTitle ?? '거래내역';
$pageSubtitle = $pageSubtitle ?? '저장된 거래 내역을 조회합니다.';
$workUnit = $workUnit ?? 'SITE';
$createUrl = $createUrl ?? '/site/transaction/create';
$listApiUrl = $listApiUrl ?? '/api/site/transaction/list';
$pageStyles = <<<'HTML'
<style>
.site-transaction-page { width: min(1100px, 100%); margin: 0 auto; }
.site-transaction-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 20px; padding: calc(var(--padding) * 2); box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); }
.site-transaction-header { display: flex; justify-content: space-between; align-items: center; gap: calc(var(--gap) + 2px); margin-bottom: calc(var(--gap) * 1.6); }
.site-transaction-title { margin: 0; font-size: calc(var(--font-size) * 2); font-weight: 800; color: #0f172a; }
.site-transaction-subtitle { margin: calc(var(--gap) * 0.8) 0 0; color: #64748b; font-size: var(--font-size); }
.site-transaction-btn { display: inline-flex; align-items: center; justify-content: center; min-height: var(--row-height); padding: 0 calc(var(--padding) + 6px); border-radius: 12px; border: 1px solid #d1d5db; background: #fff; color: #111827; text-decoration: none; cursor: pointer; font-weight: 600; font-size: var(--font-size); }
.site-transaction-btn.primary { background: var(--color-primary, #0f766e); border-color: var(--color-primary, #0f766e); color: #fff; }
.site-transaction-flash { margin-bottom: calc(var(--gap) + 4px); padding: calc(var(--padding) + 2px) calc(var(--padding) + 4px); border-radius: 14px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; font-weight: 600; font-size: var(--font-size); }
.site-transaction-table-wrap { overflow-x: auto; }
.site-transaction-table { width: 100%; border-collapse: collapse; }
.site-transaction-table th, .site-transaction-table td { padding: var(--padding); border-bottom: 1px solid #e5e7eb; text-align: left; font-size: var(--font-size); white-space: nowrap; min-height: var(--row-height); }
.site-transaction-table th { color: #475569; font-size: calc(var(--font-size) - 1px); font-weight: 800; background: #f8fafc; }
.site-transaction-table .is-number { text-align: right; font-variant-numeric: tabular-nums; }
.site-transaction-status { display: inline-flex; align-items: center; min-height: calc(var(--row-height) - 6px); padding: 0 var(--padding); border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: calc(var(--font-size) - 2px); font-weight: 700; }
.site-transaction-empty { padding: calc(var(--padding) * 2) var(--padding); text-align: center; color: #6b7280; font-size: var(--font-size); }
</style>
HTML;
$pageScripts = AssetHelper::module('/assets/js/site/transaction.js');
?>

<main
    class="site-transaction-page"
    data-transaction-page="list"
    data-work-unit="<?= htmlspecialchars((string) $workUnit, ENT_QUOTES, 'UTF-8') ?>"
    data-list-api-url="<?= htmlspecialchars((string) $listApiUrl, ENT_QUOTES, 'UTF-8') ?>"
>
    <section class="site-transaction-card">
        <div class="site-transaction-header">
            <div>
                <h1 class="site-transaction-title"><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="site-transaction-subtitle"><?= htmlspecialchars((string) $pageSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a class="site-transaction-btn primary" href="<?= htmlspecialchars((string) $createUrl, ENT_QUOTES, 'UTF-8') ?>">거래입력</a>
        </div>

        <div class="site-transaction-flash" data-role="flash" hidden></div>

        <div class="site-transaction-table-wrap">
            <table class="site-transaction-table">
                <thead>
                    <tr>
                        <th>거래일자</th>
                        <th>거래처</th>
                        <th class="is-number">총금액</th>
                        <th>상태</th>
                    </tr>
                </thead>
                <tbody data-role="transaction-list"></tbody>
            </table>
        </div>

        <div class="site-transaction-empty" data-role="transaction-empty" hidden>등록된 거래가 없습니다.</div>
    </section>
</main>

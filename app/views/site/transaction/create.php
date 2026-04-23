<?php

use Core\Helpers\AssetHelper;
use Core\Helpers\RefTypeHelper;

$pageTitle = $pageTitle ?? '거래입력';
$pageSubtitle = $pageSubtitle ?? '기본 거래를 입력하고 저장합니다.';
$workUnit = $workUnit ?? 'SITE';
$listUrl = $listUrl ?? '/site/transaction';
$saveUrl = $saveUrl ?? '/api/transaction/save';
$listButtonLabel = $listButtonLabel ?? '목록으로';
$refTypeOptions = RefTypeHelper::labels();
$pageStyles = <<<'HTML'
<style>
.site-transaction-page { width: min(1360px, 100%); margin: 0 auto; }
.site-transaction-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 24px; padding: calc(var(--padding) * 2); box-shadow: 0 22px 48px rgba(15, 23, 42, 0.08); }
.site-transaction-header { display: flex; justify-content: space-between; align-items: flex-start; gap: calc(var(--gap) * 1.6); margin-bottom: calc(var(--gap) * 2.4); }
.site-transaction-title { margin: 0; font-size: calc(var(--font-size) * 2); font-weight: 800; color: #0f172a; }
.site-transaction-subtitle { margin: calc(var(--gap) * 0.8) 0 0; color: #64748b; font-size: var(--font-size); line-height: 1.6; }
.site-transaction-btn { display: inline-flex; align-items: center; justify-content: center; min-height: var(--row-height); padding: 0 calc(var(--padding) + 6px); border-radius: 12px; border: 1px solid #d1d5db; background: #fff; color: #111827; cursor: pointer; text-decoration: none; font-weight: 700; font-size: var(--font-size); }
.site-transaction-btn.primary { background: var(--color-primary, #0f766e); border-color: var(--color-primary, #0f766e); color: #fff; }
.site-transaction-btn.secondary { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.site-transaction-btn.danger { color: #b91c1c; border-color: #fecaca; background: #fff5f5; }
.site-transaction-topbar { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: calc(var(--gap) * 1.6); margin-bottom: calc(var(--gap) * 1.8); }
.site-transaction-subbar { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: calc(var(--gap) * 1.6); margin-bottom: calc(var(--gap) * 2.4); }
.site-transaction-field { display: flex; flex-direction: column; gap: var(--gap); min-width: 0; }
.site-transaction-field-label { font-size: calc(var(--font-size) - 1px); font-weight: 800; color: #334155; }
.site-transaction-field input[type="text"], .site-transaction-field select { width: 100%; min-height: calc(var(--row-height) + 10px); border: 1px solid #d1d5db; border-radius: 12px; padding: var(--padding) calc(var(--padding) + 2px); font-size: var(--font-size); background: #fff; }
.site-transaction-field textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 12px; padding: calc(var(--padding) + 2px) calc(var(--padding) + 4px); font-size: var(--font-size); background: #fff; resize: vertical; }
.site-transaction-field .select2-container { width: 100% !important; }
.site-transaction-field .select2-container--default .select2-selection--single { min-height: calc(var(--row-height) + 10px); border-radius: 12px; border: 1px solid #d1d5db; padding: calc(var(--padding) - 2px) calc(var(--padding) + 2px); display: flex; align-items: center; font-size: var(--font-size); }
.site-transaction-field .select2-container--default .select2-selection--single .select2-selection__rendered { padding-left: 0; padding-right: 24px; line-height: 1.4; color: #111827; }
.site-transaction-field .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(var(--row-height) + 8px); right: var(--padding); }
.site-transaction-picker-row { display: grid; grid-template-columns: minmax(0, 1fr) calc(var(--row-height) + 10px); gap: var(--gap); align-items: end; }
.site-transaction-picker-row .site-transaction-btn { min-height: calc(var(--row-height) + 10px); padding: 0; font-size: calc(var(--font-size) + 8px); }
.site-transaction-date-wrap { position: relative; }
.site-transaction-date-input { cursor: pointer; background: linear-gradient(180deg, #ffffff, #f8fafc); }
.site-transaction-date-icon { position: absolute; right: calc(var(--padding) + 2px); top: 50%; transform: translateY(-50%); color: #64748b; font-size: calc(var(--font-size) + 2px); pointer-events: none; }
.site-transaction-toggle-card { display: flex; align-items: center; gap: calc(var(--gap) + 2px); min-height: calc(var(--row-height) + 10px); padding: var(--padding) calc(var(--padding) + 2px); border: 1px solid #d1d5db; border-radius: 12px; background: #f8fafc; font-size: var(--font-size); }
.site-transaction-toggle-card input { margin: 0; }
.site-transaction-import-fields[hidden] { display: none !important; }
.site-transaction-table-toolbar { display: flex; align-items: center; justify-content: space-between; gap: calc(var(--gap) + 2px); margin: calc(var(--gap) * 2.4) 0 calc(var(--gap) * 1.4); }
.site-transaction-table-toolbar h2 { margin: 0; font-size: calc(var(--font-size) + 4px); font-weight: 800; color: #0f172a; }
.site-transaction-table-wrap { overflow-x: auto; border: 1px solid #dbe3ee; border-radius: 20px; background: #fff; }
.site-transaction-table { width: 100%; border-collapse: collapse; min-width: 1040px; }
.site-transaction-table th, .site-transaction-table td { border-bottom: 1px solid #e2e8f0; padding: var(--padding); vertical-align: middle; font-size: var(--font-size); }
.site-transaction-table th { background: #f8fafc; color: #475569; font-size: calc(var(--font-size) - 1px); font-weight: 800; white-space: nowrap; }
.site-transaction-table td { background: #fff; }
.site-transaction-table tr:last-child td { border-bottom: 0; }
.site-transaction-table input[type="text"] { width: 100%; min-height: var(--row-height); border: 1px solid #d1d5db; border-radius: 10px; padding: calc(var(--padding) - 2px) var(--padding); font-size: var(--font-size); background: #fff; }
.site-transaction-table input[readonly] { background: #f8fafc; color: #334155; font-weight: 700; }
.site-transaction-col-name { min-width: 240px; }
.site-transaction-col-spec { min-width: 150px; }
.site-transaction-col-unit { width: 96px; }
.site-transaction-col-tax { width: 120px; }
.site-transaction-col-qty, .site-transaction-col-price, .site-transaction-col-total { width: 128px; }
.site-transaction-col-delete { width: 92px; text-align: center; }
.site-transaction-col-delete button { width: 100%; }
.site-transaction-tax-group { display: flex; align-items: center; justify-content: center; gap: calc(var(--gap) + 2px); flex-wrap: nowrap; }
.site-transaction-tax-option { display: inline-flex; align-items: center; gap: 6px; font-size: calc(var(--font-size) - 2px); font-weight: 700; color: #475569; }
.site-transaction-tax-option input { margin: 0; }
.site-transaction-number { text-align: right; font-variant-numeric: tabular-nums; }
.site-transaction-summary { display: grid; grid-template-columns: minmax(0, 1fr) minmax(360px, 420px); gap: calc(var(--gap) * 1.8); align-items: start; margin-top: calc(var(--gap) * 2); }
.site-transaction-note-panel { padding: calc(var(--padding) + 8px); border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; display: grid; gap: calc(var(--gap) * 1.6); }
.site-transaction-summary-grid { display: grid; gap: var(--gap); }
.site-transaction-summary-row { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: calc(var(--gap) * 1.6); padding: calc(var(--padding) + 5px) calc(var(--padding) + 8px); border-radius: 16px; background: #f8fafc; border: 1px solid #e2e8f0; }
.site-transaction-summary-row.total { background: linear-gradient(135deg, rgba(15, 118, 110, 0.08), rgba(15, 23, 42, 0.02)); border-color: rgba(15, 118, 110, 0.18); }
.site-transaction-summary-row span { color: #475569; font-size: calc(var(--font-size) - 1px); font-weight: 800; }
.site-transaction-summary-row strong { color: #0f172a; font-size: calc(var(--font-size) + 8px); font-variant-numeric: tabular-nums; }
.site-transaction-summary-control { display: flex; align-items: center; gap: var(--gap); justify-content: flex-end; }
.site-transaction-summary-control input[type="text"] { width: 160px; min-height: var(--row-height); border: 1px solid #d1d5db; border-radius: 10px; padding: calc(var(--padding) - 2px) var(--padding); font-size: var(--font-size); background: #fff; text-align: right; font-variant-numeric: tabular-nums; }
.site-transaction-footer { display: flex; align-items: center; justify-content: space-between; gap: calc(var(--gap) * 1.6); flex-wrap: wrap; margin-top: calc(var(--gap) * 2.4); }
.site-transaction-message { font-size: var(--font-size); font-weight: 700; }
.site-transaction-message[data-state="success"] { color: #047857; }
.site-transaction-message[data-state="error"] { color: #b91c1c; }
.site-transaction-picker-layer { position: fixed; z-index: 10020; }
.is-hidden { display: none !important; }
@media (max-width: 1080px) {
    .site-transaction-topbar, .site-transaction-subbar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .site-transaction-summary { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
    .site-transaction-card { padding: 20px; border-radius: 20px; }
    .site-transaction-topbar, .site-transaction-subbar { grid-template-columns: 1fr; }
    .site-transaction-header, .site-transaction-footer { flex-direction: column; align-items: stretch; }
    .site-transaction-footer .site-transaction-btn { width: 100%; }
    .site-transaction-summary-control { flex-direction: column; align-items: stretch; }
    .site-transaction-summary-control input[type="text"] { width: 100%; }
}
</style>
HTML;
$pageScripts = AssetHelper::module('/assets/js/site/transaction.js');
?>

<main
    class="site-transaction-page"
    data-transaction-page="create"
    data-work-unit="<?= htmlspecialchars((string) $workUnit, ENT_QUOTES, 'UTF-8') ?>"
    data-list-url="<?= htmlspecialchars((string) $listUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-save-url="<?= htmlspecialchars((string) $saveUrl, ENT_QUOTES, 'UTF-8') ?>"
>
    <form class="site-transaction-card" data-role="transaction-form">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="source_type" value="MANUAL">
        <input type="hidden" name="item_summary" value="">
        <input type="hidden" name="work_unit" value="<?= htmlspecialchars((string) $workUnit, ENT_QUOTES, 'UTF-8') ?>">

        <div class="site-transaction-header">
            <div>
                <h1 class="site-transaction-title"><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="site-transaction-subtitle"><?= htmlspecialchars((string) $pageSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a class="site-transaction-btn" href="<?= htmlspecialchars((string) $listUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $listButtonLabel, ENT_QUOTES, 'UTF-8') ?></a>
        </div>

        <section class="site-transaction-topbar">
            <label class="site-transaction-field">
                <span class="site-transaction-field-label">거래일자</span>
                <div class="site-transaction-date-wrap">
                    <input type="text" name="transaction_date" class="site-transaction-date-input" data-role="transaction-date" value="<?= date('Y-m-d') ?>" autocomplete="off" readonly>
                    <span class="site-transaction-date-icon">📅</span>
                </div>
            </label>

            <label class="site-transaction-field">
                <span class="site-transaction-field-label">거래유형</span>
                <select name="transaction_type">
                    <option value="PURCHASE">매입</option>
                    <option value="SALE">매출</option>
                    <option value="EXPENSE">비용</option>
                    <option value="ETC">기타</option>
                </select>
            </label>

            <label class="site-transaction-field">
                <span class="site-transaction-field-label">거래처</span>
                <div class="site-transaction-picker-row">
                    <select name="client_id" data-role="client-picker">
                        <option value=""></option>
                    </select>
                    <button type="button" class="site-transaction-btn secondary" data-role="quick-create-client">+</button>
                </div>
            </label>

            <label class="site-transaction-field">
                <span class="site-transaction-field-label">프로젝트</span>
                <div class="site-transaction-picker-row">
                    <select name="project_id" data-role="project-picker">
                        <option value=""></option>
                    </select>
                    <button type="button" class="site-transaction-btn secondary" data-role="quick-create-project">+</button>
                </div>
            </label>
        </section>

        <section class="site-transaction-subbar">
            <label class="site-transaction-field">
                <span class="site-transaction-field-label">참조유형</span>
                <select name="ref_type">
                    <option value="">선택</option>
                    <?php foreach ($refTypeOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="site-transaction-field">
                <span class="site-transaction-field-label">기본 과세구분</span>
                <select name="tax_type" data-role="header-tax-type">
                    <option value="TAXABLE">과세</option>
                    <option value="EXEMPT">면세</option>
                </select>
            </label>

            <label class="site-transaction-field">
                <span class="site-transaction-field-label">혼합 과세</span>
                <div class="site-transaction-toggle-card">
                    <input type="checkbox" name="use_item_tax" value="1" data-role="use-item-tax">
                    <span>품목별 과세 사용</span>
                </div>
            </label>

            <label class="site-transaction-field">
                <span class="site-transaction-field-label">수입 거래</span>
                <div class="site-transaction-toggle-card">
                    <input type="checkbox" name="is_import" value="1" data-role="is-import">
                    <span>통화/환율 입력 사용</span>
                </div>
            </label>

            <div class="site-transaction-import-fields" data-role="import-fields" hidden>
                <div class="site-transaction-subbar" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0;">
                    <label class="site-transaction-field">
                        <span class="site-transaction-field-label">통화</span>
                        <input type="text" name="currency" value="KRW" maxlength="3" placeholder="KRW">
                    </label>
                    <label class="site-transaction-field">
                        <span class="site-transaction-field-label">환율</span>
                        <input type="text" class="number-input" name="exchange_rate" value="" placeholder="예: 1325.50">
                    </label>
                </div>
            </div>
        </section>

        <div class="site-transaction-table-toolbar">
            <h2>품목 입력</h2>
            <button type="button" class="site-transaction-btn" data-role="add-item">+ 행 추가</button>
        </div>

        <div class="site-transaction-table-wrap">
            <table class="site-transaction-table">
                <thead>
                    <tr>
                        <th class="site-transaction-col-name">품명</th>
                        <th class="site-transaction-col-spec">규격</th>
                        <th class="site-transaction-col-unit">단위</th>
                        <th class="site-transaction-col-tax" data-role="item-tax-header" hidden>과세</th>
                        <th class="site-transaction-col-qty">수량</th>
                        <th class="site-transaction-col-price">단가</th>
                        <th class="site-transaction-col-total">금액</th>
                        <th class="site-transaction-col-delete">삭제</th>
                    </tr>
                </thead>
                <tbody data-role="items"></tbody>
            </table>
        </div>

        <section class="site-transaction-summary">
            <div class="site-transaction-note-panel">
                <label class="site-transaction-field">
                    <span class="site-transaction-field-label">비고</span>
                    <input type="text" name="note" maxlength="255" placeholder="비고를 입력하세요">
                </label>
                <label class="site-transaction-field">
                    <span class="site-transaction-field-label">메모</span>
                    <textarea name="memo" rows="4" placeholder="메모를 입력하세요"></textarea>
                </label>
            </div>

            <div class="site-transaction-summary-grid">
                <div class="site-transaction-summary-row">
                    <span>품목 합계</span>
                    <strong data-role="summary-supply">0</strong>
                </div>
                <div class="site-transaction-summary-row">
                    <span>부가세</span>
                    <div class="site-transaction-summary-control">
                        <input type="text" class="number-input" name="vat_amount" data-role="header-vat" value="0">
                        <button type="button" class="site-transaction-btn secondary" data-role="auto-vat">부가세 자동계산</button>
                    </div>
                </div>
                <div class="site-transaction-summary-row total">
                    <span>총금액</span>
                    <strong data-role="summary-total">0</strong>
                </div>
                <input type="hidden" name="supply_amount" value="0">
                <input type="hidden" name="total_amount" value="0">
            </div>
        </section>

        <div class="site-transaction-footer">
            <div class="site-transaction-message" data-role="message"></div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="site-transaction-btn primary">저장</button>
                <a class="site-transaction-btn" href="<?= htmlspecialchars((string) $listUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $listButtonLabel, ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
    </form>
</main>

<div id="transaction-date-picker" class="site-transaction-picker-layer is-hidden"></div>

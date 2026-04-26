<?php
// 경로: PROJECT_ROOT . '/app/views/components/ui-search.php'
// 공통 검색폼

$searchId = $searchId ?? 'common';

$dateOptions = $dateOptions ?? '';
$searchFieldOptions = $searchFieldOptions ?? '';

$periodGuideTitle = $periodGuideTitle ?? '기간 조건 안내';
$periodGuideItems = $periodGuideItems ?? [
    '기간 기준을 선택한 뒤 시작일과 종료일을 지정해 조회할 수 있습니다.',
    '오늘, 1개월, 3개월 같은 빠른 선택 버튼으로 기간을 바로 채울 수 있습니다.',
    '시작일과 종료일은 직접 입력할 수 있습니다.',
    '기간 설정 후 검색 버튼을 누르면 조건이 적용됩니다.',
];

$searchGuideTitle = $searchGuideTitle ?? '검색어 안내';
$searchGuideItems = $searchGuideItems ?? [
    '검색어 여러 개는 쉼표(,)로 구분해서 입력할 수 있습니다.',
    '예시: 1, 3, 5',
    '조건 항목은 한 줄에 하나씩 검색할 수 있습니다.',
    '검색 조건은 최대 5개까지 추가할 수 있습니다.',
];

$periodButtons = $periodButtons ?? [
    ['key' => 'today',     'label' => '오늘'],
    ['key' => 'yesterday', 'label' => '어제'],
    ['key' => '3days',     'label' => '3일'],
    ['key' => '7days',     'label' => '7일'],
    ['key' => '15days',    'label' => '15일'],
    ['key' => '1month',    'label' => '1개월'],
    ['key' => '3months',   'label' => '3개월'],
    ['key' => '6months',   'label' => '6개월'],
];

$dateInputClass = $dateInputClass ?? 'form-control form-control-sm admin-date';
$dateStartPlaceholder = $dateStartPlaceholder ?? '시작일';
$dateEndPlaceholder   = $dateEndPlaceholder ?? '종료일';
$dateInputAttrs = $dateInputAttrs ?? '';

$showPeriodTooltip = $showPeriodTooltip ?? true;
$showSearchTooltip = $showSearchTooltip ?? true;
?>
<div id="<?= $searchId ?>SearchFormContainer" class="search-form-container">
    <span id="<?= $searchId ?>ToggleSearchForm" class="search-toggle-text">접기</span>

    <div id="<?= $searchId ?>SearchFormBody" class="search-form-body">

        <label class="search-form-title">검색</label>

        <form id="<?= $searchId ?>SearchConditionsForm">

            <div class="period-row">

                <div class="period-label-area">
                    <button type="button"
                            id="<?= $searchId ?>PeriodLabel"
                            class="label-btn">
                        기간
                        <?php if ($showPeriodTooltip): ?>
                            <i class="fa fa-question-circle tooltip-trigger"
                               id="<?= $searchId ?>PeriodTooltipTrigger"></i>
                        <?php endif; ?>
                    </button>

                    <?php if ($showPeriodTooltip): ?>
                        <div id="<?= $searchId ?>PeriodTooltipContainer" class="tooltip-container">
                            <strong><?= htmlspecialchars($periodGuideTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                            <ul>
                                <?php foreach ($periodGuideItems as $item): ?>
                                    <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <select id="<?= $searchId ?>DateType"
                        name="dateType"
                        class="form-select form-select-sm date-type-select">
                    <?= $dateOptions ?>
                </select>

                <div class="period-quick-btns">
                    <?php foreach ($periodButtons as $btn): ?>
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm"
                                onclick="setPeriod('<?= htmlspecialchars($btn['key'], ENT_QUOTES, 'UTF-8') ?>', this)">
                            <?= htmlspecialchars($btn['label'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="date-range">
                    <div class="date-input">
                        <input type="text"
                               name="dateStart"
                               class="<?= htmlspecialchars($dateInputClass, ENT_QUOTES, 'UTF-8') ?>"
                               <?= $dateInputAttrs ?>
                               placeholder="<?= htmlspecialchars($dateStartPlaceholder, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fa fa-calendar-days date-icon" aria-hidden="true"></i>
                    </div>

                    <span class="date-tilde">~</span>

                    <div class="date-input">
                        <input type="text"
                               name="dateEnd"
                               class="<?= htmlspecialchars($dateInputClass, ENT_QUOTES, 'UTF-8') ?>"
                               <?= $dateInputAttrs ?>
                               placeholder="<?= htmlspecialchars($dateEndPlaceholder, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fa fa-calendar-days date-icon" aria-hidden="true"></i>
                    </div>
                </div>
            </div>

            <div id="<?= $searchId ?>SearchWrapper" class="search-wrapper">

                <div class="search-label-area">
                    <div class="label-btn" id="<?= $searchId ?>SearchLabel">
                        검색어
                        <?php if ($showSearchTooltip): ?>
                            <i class="fa fa-question-circle tooltip-trigger"
                               id="<?= $searchId ?>TooltipTrigger"></i>
                        <?php endif; ?>
                    </div>

                    <?php if ($showSearchTooltip): ?>
                        <div id="<?= $searchId ?>TooltipContainer" class="tooltip-container">
                            <strong><?= htmlspecialchars($searchGuideTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                            <ul>
                                <?php foreach ($searchGuideItems as $item): ?>
                                    <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="<?= $searchId ?>SearchConditions" class="search-conditions">
                    <div class="search-condition">
                        <select name="searchField[]" class="form-select form-select-sm search-field">
                            <?= $searchFieldOptions ?>
                        </select>

                        <input type="text"
                               name="searchValue[]"
                               class="form-control search-input"
                               placeholder="검색어 입력">

                        <button type="button"
                                id="<?= $searchId ?>AddSearchCondition"
                                class="btn btn-dark">
                            +
                        </button>

                        <button type="reset"
                                id="<?= $searchId ?>ResetButton"
                                class="btn btn-secondary">
                            초기화
                        </button>

                        <button type="submit"
                                id="<?= $searchId ?>SearchButton"
                                class="btn btn-success">
                            검색
                        </button>
                    </div>
                </div>

            </div>

        </form>

    </div>
</div>

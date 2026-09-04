<?php
$bootstrapData = htmlspecialchars(json_encode(['options' => $options, 'capabilities' => $cap], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<div class="statutory-standards-page is-search-initializing" data-bootstrap="<?= $bootstrapData ?>">
    <div class="page-header">
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-journal-check me-2"></i>법정기준관리
        </h4>
        <span id="statutoryStandardCount" class="text-primary statutory-standard-count page-count"></span>
    </div>

    <div class="content-area">
        <?php
        $searchId = 'statutoryStandard';
        $dateOptions = '<option value="as_of_date">기준일</option>';
        $searchFieldOptions = '<option value="standard_type_code">법정기준 종류</option>'
            . '<option value="effective_year">적용연도</option>'
            . '<option value="period_status">적용상태</option>'
            . '<option value="note">비고</option>';
        $showPeriodTooltip = false;
        $showSearchTooltip = false;
        $searchInitialCollapsed = true;
        include PROJECT_ROOT . '/app/views/components/ui-search.php';

        $tableId = 'statutory-standard-table';
        $ajaxUrl = '/api/settings/statutory-standards/list';
        $columnsType = 'statutoryStandard';
        $enableButtons = true;
        $enableSearch = true;
        $enablePaging = true;
        $enableReorder = false;
        include PROJECT_ROOT . '/app/views/components/ui-table.php';
        ?>
    </div>
</div>

<div class="modal fade ui-form-modal" id="standardModal" tabindex="-1" aria-labelledby="standardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable statutory-standard-dialog">
        <form class="modal-content statutory-standard-modal-card" id="standardForm">
            <div class="modal-header statutory-standard-modal-header">
                <div>
                    <h5 class="modal-title" id="standardModalLabel">법정기준 상세</h5>
                    <p class="statutory-standard-modal-subtitle mb-0">적용기간, 기준값과 공식 근거자료를 한 화면에서 관리합니다.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body statutory-standard-modal-body">
                <input type="hidden" name="id">
                <input type="hidden" name="supersedes_revision_id">
                <input type="hidden" name="correction_reason">
                <section class="ui-form-card statutory-primary-card">
                    <div class="ui-form-card__header"><div class="ui-form-card__heading"><h6 class="ui-form-card__title">기준정보</h6><p class="statutory-table-reference mb-0" data-table-reference="standard"></p><p class="ui-form-card__description">법정기준 종류와 적용기간을 입력합니다.</p></div></div>
                    <div class="ui-form-card__body row g-3">
                        <div class="col-md-6"><label class="form-label" data-meta-label="standard_type_code"></label><select class="form-select admin-select2" name="standard_type_code" data-hide-common-add="true"></select></div>
                        <div class="col-md-3"><label class="form-label" data-meta-label="effective_from"></label><div class="date-input"><input class="form-control admin-date" type="text" name="effective_from" inputmode="numeric" maxlength="10" autocomplete="off"><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></div></div>
                        <div class="col-md-3"><label class="form-label" data-meta-label="effective_to"></label><div class="date-input"><input class="form-control admin-date" type="text" name="effective_to" inputmode="numeric" maxlength="10" autocomplete="off"><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></div></div>
                        <div class="col-12"><label class="form-label" data-meta-label="note"></label><input class="form-control" name="note" maxlength="1000"></div>
                    </div>
                </section>
                <section class="ui-form-card" id="standardValueCard">
                    <div class="ui-form-card__header"><h6 class="ui-form-card__title">기준값</h6><p class="statutory-table-reference mb-0" data-table-reference="standard"></p></div>
                    <div class="ui-form-card__body row g-3" id="standardValueFields"></div>
                </section>
                <section class="ui-form-card d-none" id="standardCalculationPolicySection">
                    <div class="ui-form-card__header"><h6 class="ui-form-card__title">계산처리 기준</h6></div>
                    <div class="ui-form-card__body row g-3" id="standardCalculationPolicyFields"></div>
                </section>
                <section class="ui-form-card" id="standardRevisionAuditCard">
                    <div class="ui-form-card__header"><h6 class="ui-form-card__title">Revision 정정 이력</h6><p class="ui-form-card__description">원 Revision과 후속 Revision의 불변 대체 체인을 표시합니다.</p></div>
                    <div class="ui-form-card__body" id="standardRevisionChain"><span class="text-muted">정정 이력이 없습니다.</span></div>
                </section>
                <section class="ui-form-card">
                    <div class="ui-form-card__header d-flex justify-content-between align-items-center">
                        <div><h6 class="ui-form-card__title">관련근거</h6><p class="statutory-table-reference mb-0" data-table-reference="source"></p></div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="sourceAddButton">관련근거 추가</button>
                    </div>
                    <div class="ui-form-card__body" id="standardSources"></div>
                </section>
                <section class="ui-form-card statutory-system-card" aria-label="시스템 처리 정보">
                    <button type="button" class="statutory-card-toggle collapsed" id="statutorySystemInfoToggle"
                            data-ui-modal-card-collapse data-bs-target="#statutorySystemInfoCollapse"
                            aria-expanded="false" aria-controls="statutorySystemInfoCollapse">
                        <span class="statutory-card-heading">
                            <span class="ui-form-card__title">시스템 처리 정보</span>
                            <span class="statutory-table-reference" data-table-reference="standard"></span>
                        </span>
                        <i class="bi bi-chevron-down statutory-card-icon" aria-hidden="true"></i>
                    </button>
                    <div id="statutorySystemInfoCollapse" class="collapse">
                        <div class="ui-form-card__body statutory-system-grid" id="statutorySystemInfoFields"></div>
                    </div>
                </section>
            </div>
            <div class="modal-footer justify-content-end">
                <div class="d-flex gap-2">
                    <?php if (!empty($cap['save'])): ?><button type="button" class="btn btn-outline-primary btn-sm d-none" id="standardRenewalButton">개정 등록</button><?php endif; ?>
                    <?php if (!empty($cap['delete'])): ?><button type="button" class="btn btn-danger btn-sm d-none" id="standardDeleteButton">영구삭제</button><?php endif; ?>
                    <?php if (!empty($cap['save'])): ?><button type="submit" class="btn btn-success btn-sm" id="standardSaveButton">저장</button><?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div id="statutory-standard-date-picker" class="is-hidden"></div>

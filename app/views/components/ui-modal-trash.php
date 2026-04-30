<?php
// Path: PROJECT_ROOT . '/app/views/components/ui-modal-trash.php'
// 공통 휴지통 모달

$modalId      = $modalId      ?? 'trashModal';
$type         = $type         ?? 'default';
$modalTitle   = $modalTitle   ?? '휴지통';
$tableId      = $tableId      ?? 'trash-table';

$checkAllId   = $checkAllId   ?? "{$type}TrashCheckAll";
$btnRestoreId = $btnRestoreId ?? "btnRestoreSelected_{$type}";
$btnDeleteId  = $btnDeleteId  ?? "btnDeleteSelected_{$type}";
$btnDeleteAll = $btnDeleteAll ?? "btnDeleteAll_{$type}";

$tableHead    = $tableHead    ?? '';
$emptyMessage = $emptyMessage ?? '삭제된 데이터를 선택하세요.';
?>

<div class="modal fade"
     id="<?= $modalId ?>"
     tabindex="-1"
     aria-hidden="true"
     data-type="<?= htmlspecialchars($type, ENT_QUOTES) ?>"
     data-list-url="<?= $listUrl ?? '' ?>"
     data-restore-url="<?= $restoreUrl ?? '' ?>"
     data-delete-url="<?= $deleteUrl ?? '' ?>"
     data-delete-all-url="<?= $deleteAllUrl ?? '' ?>">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars($modalTitle, ENT_QUOTES, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>

            <div class="modal-body trash-body">
                <div class="trash trash-layout <?= $type === 'cover' ? 'cover-trash-layout' : '' ?>">

                    <div class="trash-left <?= $type === 'cover' ? 'cover-trash-left' : '' ?>">

                        <div class="trash-toolbar">
                            <button type="button" class="btn btn-success btn-sm btn-restore-selected" id="<?= $btnRestoreId ?>">선택복원</button>
                            <button type="button" class="btn btn-danger btn-sm btn-delete-selected" id="<?= $btnDeleteId ?>">선택영구삭제</button>
                            <button type="button" class="btn btn-outline-success btn-sm btn-restore-all" id="btnRestoreAll_<?= $type ?>">전체복원</button>
                            <button type="button" class="btn btn-outline-danger btn-sm btn-delete-all" id="<?= $btnDeleteAll ?>">전체영구삭제</button>
                        </div>

                        <div class="trash-table">
                            <table id="<?= $tableId ?>" class="table table-hover w-100 trash-table">
                                <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="<?= $checkAllId ?>" class="trash-check-all">
                                    </th>
                                    <?= $tableHead ?>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="trash-right <?= $type === 'cover' ? 'cover-trash-right' : '' ?>"
                         id="<?= $type ?>-trash-detail-wrap">

                        <div class="trash-detail <?= $type === 'cover' ? 'cover-trash-detail' : '' ?>"
                             id="<?= $type ?>-trash-detail"
                             style="display:none;">

                            <div class="empty">
                                <?= htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') ?>
                            </div>

                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>

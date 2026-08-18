<?php $escape = $escape ?? static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<div class="modal fade" id="paymentScheduleFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="paymentScheduleForm">
        <div class="modal-header"><h5 class="modal-title">지급예정 등록</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button></div>
        <div class="modal-body">
            <input type="hidden" name="id">
            <div class="row g-3">
                <label class="col-md-8"><span>지급 원천</span><input class="form-control" id="paymentSourcePicker" readonly><small class="text-muted">전표 승인 시 확정된 전표 행에서 자동 생성됩니다.</small></label>
                <input type="hidden" name="source_type">
                <input type="hidden" name="source_id">
                <input type="hidden" name="source_line_key">
                <label class="col-md-4"><span>지급예정일</span><input class="form-control" type="date" name="payment_due_date"><small class="text-muted">미확정 상태로 저장할 수 있습니다.</small></label>
                <label class="col-md-4"><span>지급예정액</span><input class="form-control text-end" type="number" name="scheduled_amount" readonly></label>
                <?php foreach (['clients'=>'거래처','projects'=>'프로젝트','assignees'=>'담당자','bank_accounts'=>'지급계좌'] as $key=>$label): ?>
                    <?php $field = ['clients'=>'client_id','projects'=>'project_id','assignees'=>'assignee_id','bank_accounts'=>'payment_bank_account_id'][$key]; ?>
                    <label class="col-md-4"><span><?= $label ?></span><select class="form-select" name="<?= $field ?>"><option value="">선택(없음)</option><?php foreach (($options[$key] ?? []) as $item): ?><option value="<?= $escape($item['id'] ?? '') ?>"><?= $escape($item['name'] ?? '') ?></option><?php endforeach; ?></select></label>
                <?php endforeach; ?>
                <label class="col-12"><span>메모</span><textarea class="form-control" name="memo" rows="3"></textarea></label>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary">저장</button></div>
    </form></div>
</div>

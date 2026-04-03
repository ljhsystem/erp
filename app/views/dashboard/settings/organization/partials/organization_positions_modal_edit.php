<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/organization_position_modal_edit.php';
?>
<div class="modal fade" id="positionEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="pos-edit-form">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">직책 수정</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <input type="hidden" name="id" id="pos_edit_id">

          <!-- 직책 선택 -->
          <div class="mb-3">
            <label class="form-label">직책</label>
            <select id="pos_edit_select" class="form-select" required>
                <option value="">선택하세요</option>
                <option value="대표" data-rank="1">대표</option>
                <option value="전무" data-rank="2">전무</option>
                <option value="상무" data-rank="3">상무</option>
                <option value="이사" data-rank="4">이사</option>
                <option value="부장" data-rank="5">부장</option>
                <option value="과장" data-rank="6">과장</option>
                <option value="대리" data-rank="7">대리</option>
                <option value="사원" data-rank="8">사원</option>
                <option value="인턴" data-rank="9">인턴</option>
            </select>
          </div>

          <!-- 등급 -->
          <div class="mb-3">
            <label class="form-label">등급</label>
            <input type="number" id="pos_edit_rank" class="form-control" required>
          </div>

          <!-- 장문 설명 -->
          <div class="mb-3">
            <label class="form-label">설명</label>
            <textarea id="pos_edit_desc" class="form-control" rows="3" placeholder="직책 설명을 입력하세요"></textarea>
          </div>

          <!-- 활성 여부 -->
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="pos_edit_is_active" name="is_active">
            <label class="form-check-label" for="pos_edit_is_active">활성</label>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">저장</button>
          <button type="button" id="pos_edit_delete_btn" class="btn btn-danger me-auto">삭제</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
        </div>

      </div>
    </form>
  </div>
</div>

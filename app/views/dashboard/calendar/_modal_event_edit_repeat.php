<!-- 📄 /app/views/dashboard/calendar/_modal_event_edit_repeat.php -->
<div id="modal-event-edit-repeat"
     class="shint-modal is-hidden"
     role="dialog"
     aria-modal="true">

  <div class="shint-modal__card shint-modal__card--repeat">

    <!-- Header -->
    <header class="shint-modal__head">
      <h3 class="shint-modal__title">사용자 지정 반복</h3>
      <button type="button"
              class="shint-iconbtn"
              data-close="repeat-modal">×</button>
    </header>

    <!-- Body -->
    <div class="shint-modal__body">

      <!-- 매번 반복 -->
      <div class="repeat-row">
        <div class="repeat-label">매번 반복</div>
        <div class="repeat-inline">
          <select id="repeat-interval" class="shint-select w-sm">
            <option>1</option><option>2</option><option>3</option>
          </select>
          <select id="repeat-freq" class="shint-select w-md">
            <option value="DAILY">일간</option>
            <option value="WEEKLY">주간</option>
            <option value="MONTHLY">월간</option>
            <option value="YEARLY">매년</option>
          </select>
        </div>
      </div>

      <!-- 월간 기준 -->
      <div class="repeat-row" id="repeat-monthly-mode">
        <div class="repeat-label">반복 기준</div>
        <div class="repeat-stack">
          <label class="shint-radio">
            <input type="radio" name="monthly-type" value="date" checked>
            매월의 날짜
          </label>
          <label class="shint-radio">
            <input type="radio" name="monthly-type" value="weekday">
            매주의 요일
          </label>
        </div>
      </div>

      <!-- 주간 요일 -->
      <div class="repeat-row" id="repeat-weekly-days">
        <div class="repeat-label">반복 시점</div>
        <div class="repeat-weekdays">
          <label><input type="checkbox" value="SU">일</label>
          <label><input type="checkbox" value="MO">월</label>
          <label><input type="checkbox" value="TU">화</label>
          <label><input type="checkbox" value="WE">수</label>
          <label><input type="checkbox" value="TH">목</label>
          <label><input type="checkbox" value="FR">금</label>
          <label><input type="checkbox" value="SA">토</label>
        </div>
      </div>

      <hr>

      <!-- 종료 -->
      <div class="repeat-row" id="repeat-end-row">
        <div class="repeat-label">종료</div>
        <div class="repeat-stack">

          <label class="shint-radio">
            <input type="radio" name="repeat-end" value="none" checked>
            없음
          </label>

          <label class="shint-radio repeat-inline">
            <input type="radio" name="repeat-end" value="count">
            이후
            <input id="repeat-count" type="number" value="1"
                   class="shint-input w-sm">
            발생수
          </label>

          <label class="shint-radio repeat-inline">
            <input type="radio" name="repeat-end" value="until">
            까지
            <input id="repeat-until"
                   type="text"
                   class="shint-input w-date"
                   data-picker="date">
          </label>

        </div>
      </div>

      <hr>

      <!-- 요약 -->
      <div class="repeat-row" id="repeat-summary-row">
        <div class="repeat-label">요약</div>
        <div class="repeat-summary" id="repeat-summary">
          매월, 첫번째 목요일에
        </div>
      </div>

    </div>

    <!-- Footer -->
    <footer class="shint-modal__foot">
      <button type="button" class="shint-btn" data-close="repeat-modal">
        취소
      </button>
      <button type="button"
              class="shint-btn shint-btn--primary"
              id="repeat-save">
        저장
      </button>
    </footer>

  </div>
</div>

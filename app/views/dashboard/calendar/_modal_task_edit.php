<!-- 📄 /app/views/dashboard/calendar/_modal_task_edit.php -->
<div id="modal-task-edit"
     class="shint-modal is-hidden"
     role="dialog"
     aria-modal="true"
     aria-labelledby="task-editor-title">

  <div class="shint-modal__card shint-modal__card--edit">

    <header class="shint-modal__head">
      <h3 id="task-editor-title" class="shint-modal__title">작업 편집</h3>
      <button type="button"
              class="shint-iconbtn"
              data-close="modal"
              aria-label="닫기">×</button>
    </header>

    <div class="shint-modal__body">
      <form id="task-form" class="shint-form" autocomplete="off">

        <!-- 제목 -->
        <div class="shint-row">
          <div class="shint-ico">T</div>
          <div class="shint-field">
            <input id="task-title"
                   class="shint-input shint-input--xl"
                   type="text"
                   placeholder="작업 제목"
                   required>
          </div>
        </div>

        <!-- 날짜 / 시간 -->
        <div class="shint-row">
          <div class="shint-ico">🕒</div>

          <div class="shint-field">
            <!-- ✅ 예전에 쓰던 wrap 구조 -->
            <div class="qt-dt-wrap">
              <input
                id="task-due"
                type="text"
                readonly
                class="shint-input admin-picker"
                data-picker="datetime"
              />
            </div>
          </div>
        </div>


        <!-- Task Edit – 작업 목록 -->
        <div class="shint-row">
          <div class="shint-ico">📥</div>
          <div class="shint-field">

            <div class="tasklist-wrap">
              <button type="button"
                      id="tasklist-btn-edit-modal" 
                      class="evt-cal-btn">
                <span class="evt-cal-color"></span>
                <span class="evt-cal-name">Inbox</span>
              </button>

                <!-- 드롭다운 -->
              <div id="tasklist-dropdown-edit-modal"
                  class="evt-cal-list"
                  hidden></div>
            </div>

            <input type="hidden"
                  id="task-list"
                  name="task_list" />

          </div>
        </div>


        <!-- 내용 -->
        <div class="shint-row">
          <div class="shint-ico">📝</div>
          <div class="shint-field">
            <textarea id="task-desc"
                      class="shint-textarea"
                      rows="6"
                      placeholder="내용"></textarea>
          </div>
        </div>

        <!-- 알람 -->
        <div class="shint-row">
          <div class="shint-ico">🔔</div>

          <div class="shint-field">

            <!-- 알람 목록 -->
            <div id="task-edit-alarm-list"
                class="task-alarm-list"></div>

            <!-- 알람 추가 -->
            <button type="button"
                    id="task-edit-alarm-add"
                    class="shint-btn shint-btn--ghost">
              + 알림 추가
            </button>
          </div>
        </div>



        <!-- hidden -->
        <input type="hidden" id="task-uid">

        <div class="shint-actions">
          <button type="button"
                  class="shint-btn"
                  data-close="modal">취소</button>
          <button type="submit"
                  class="shint-btn shint-btn--primary">저장</button>
        </div>

      </form>
    </div>
  </div>






  <!-- 🔥 알람 프리셋 드롭다운 (공용, body로 이동됨) -->
  <div id="task-edit-alarm-dropdown"
    class="task-alarm-dropdown"
    hidden></div>

</div>

<!-- 📄 /app/views/dashboard/calendar/_modal_quick.php -->
<div id="modal-quick"
     class="shint-modal is-hidden"
     role="dialog"
     aria-modal="true"
     aria-labelledby="quick-title">

  <div class="shint-modal__card shint-modal__card--quick" tabindex="-1">

    <!-- ======================
         Header
    ======================= -->
    <header class="shint-modal__head">
      <h3 id="quick-title" class="shint-modal__title">일정 생성</h3>
      <button type="button"
        id="btn-quick-close"
        class="shint-iconbtn"
        aria-label="닫기">×</button>
    </header>

    <!-- ======================
         Body
    ======================= -->
    <div class="shint-modal__body">

      <!-- Tabs -->
      <div class="shint-tabs" role="tablist" aria-label="생성 유형">
        <button type="button"
                class="shint-tab qt is-active"
                data-qtype="event"
                role="tab"
                aria-selected="true">이벤트</button>

        <button type="button"
                class="shint-tab qt"
                data-qtype="task"
                role="tab"
                aria-selected="false">작업</button>
      </div>

      <!-- ======================
           Form
      ======================= -->
      <div class="shint-form">

        <!-- 제목 -->
        <div class="shint-row">
          <div class="shint-ico">T</div>
          <div class="shint-field">
            <input id="quick-input-title"
                   class="shint-input shint-input--xl"
                   type="text"
                   placeholder="제목을 추가하십시오."
                   autocomplete="off">
          </div>
        </div>
        <!-- ======================
            날짜 / 시간 (이벤트)
        ======================= -->
        <div class="shint-row shint-only-event">
          <div class="shint-ico">🕒</div>

          <div class="shint-field">

            <!-- 시작 -->
            <div class="qt-dt-row" data-role="start">

                  <!-- 날짜 + 아이콘 -->
                  <div class="qt-date-wrap">
                    <input id="quick-input-start-date"
                          class="shint-input"
                          type="text"
                          readonly
                          data-picker="date"
                          aria-label="시작 날짜">
                    <i class="qt-date-ico">📅</i>
                  </div>

                    <!-- 시작 시간 -->
                  <div class="qt-time-wrap">
                    <input
                      id="quick-input-start-time"
                      class="shint-input qt-time"
                      type="text"
                      readonly
                      data-picker="time-list"
                      data-role="start"
                      value="09:00"
                    />
                    <i class="qt-time-caret"></i>
                  </div>  
            </div>



            <!-- 종료 -->
            <div class="qt-dt-row" data-role="end">

                  <!-- 날짜 + 아이콘 -->
                  <div class="qt-date-wrap">
                    <input id="quick-input-end-date"
                          class="shint-input"
                          type="text"
                          readonly
                          data-picker="date"
                          aria-label="종료 날짜">
                    <i class="qt-date-ico">📅</i>
                  </div>

                    <!-- 종료 시간 -->
                  <div class="qt-time-wrap">
                    <input
                      id="quick-input-end-time"
                      class="shint-input qt-time"
                      type="text"
                      readonly
                      data-picker="time-list"
                      data-role="end"
                      value="10:00"
                    />
                    <i class="qt-time-caret"></i>
                  </div>
            </div>

            <label class="shint-check qt-allday">
              <input id="quick-input-allday" type="checkbox">
              <span>온종일 이벤트</span>
            </label>

          </div>
        </div>


        <!-- =================================================
             🕒 작업 마감일 (DATETIME)
        ================================================== -->
        <div class="shint-row shint-only-task">
          <div class="shint-ico">⏰</div>

          <div class="shint-field">
            <div class="qt-date-wrap">
              <input id="quick-input-task-due"
                    class="shint-input admin-picker"
                    type="text"
                    readonly
                    data-picker="datetime"
                    aria-label="작업 마감일">
              <i class="qt-date-ico">📅</i>
            </div>
          </div>
        </div>


        <!-- 작업 리스트 (퀵 모달) -->
        <div class="shint-row shint-only-task">
          <div class="shint-ico">📥</div>
          <div class="shint-field">

            <button type="button"
                    id="tasklist-btn-quick"
                    class="tasklist-btn evt-cal-btn"
                    data-scope="quick">
              <span class="evt-cal-color"></span>
              <span class="evt-cal-name">Inbox</span>
            </button>

            <input type="hidden"
                  id="quick-input-tasklist"
                  class="tasklist-input" />

            <div id="tasklist-dropdown-quick"
                class="tasklist-dropdown evt-cal-list"
                hidden></div>

          </div>
        </div>


        <!-- 게스트 -->
        <div class="shint-row shint-only-event">
          <div class="shint-ico">👥</div>
          <div class="shint-field">
            <input id="quick-input-guests"
                   class="shint-input"
                   type="text"
                   placeholder="게스트 추가 (예: a@b.com, c@d.com)">
          </div>
        </div>

      <!-- 위치 -->
      <div class="shint-row shint-only-event">
        <div class="shint-ico">📍</div>

        <!-- 🔥 relative는 inline style 말고 CSS로 관리 권장 -->
        <div class="shint-field shint-field--loc">

          <input id="quick-input-location"
                class="shint-input"
                type="text"
                autocomplete="off"
                placeholder="위치 추가">

          <!-- 자동완성 박스 -->
          <div id="quick-location-suggest"
              class="loc-suggest-box"
              hidden></div>

        </div>
      </div>

        <!-- 설명 -->
        <div class="shint-row">
          <div class="shint-ico">📝</div>
          <div class="shint-field">
            <textarea id="quick-input-desc"
                      class="shint-textarea"
                      rows="3"
                      placeholder="설명을 추가하십시오"></textarea>
          </div>
        </div>

        <!-- 캘린더 선택 -->
        <div class="shint-row shint-only-event">
          <div class="shint-ico">🗓️</div>

          <div class="shint-field">

            <!-- 🔘 선택 버튼 -->
            <button type="button"
                    id="quick-calendar-btn"
                    class="evt-cal-btn">

              <span id="quick-calendar-color"
                    class="evt-cal-color"></span>

              <span id="quick-calendar-name"
                    class="evt-cal-name">개인</span>
            </button>

            <!-- 실제 저장용 -->
            <input type="hidden" id="quick-input-calendar" />
            <input type="hidden" id="quick-input-calendar-href" />

            <!-- 📋 캘린더 리스트 -->
            <div id="quick-calendar-list"
                class="evt-cal-list"
                hidden></div>

            <div class="shint-hint">
              캘린더 색상은 선택한 캘린더 기준으로 자동 적용됩니다.
            </div>

          </div>
        </div>


      </div>
    </div>







    <!-- ======================
         Footer
    ======================= -->
    <footer class="shint-modal__foot">
      <button type="button"
              id="btn-quick-detail"
              class="shint-btn shint-btn--ghost">
        세부 정보
      </button>

      <button type="button"
              id="btn-quick-save"
              class="shint-btn shint-btn--primary">
        저장
      </button>
    </footer>

  </div>
</div>

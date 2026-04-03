<!-- 📄 /app/views/dashboard/calendar/_modal_event_edit.php -->
<div id="modal-event-edit"
     class="shint-modal is-hidden"
     role="dialog"
     aria-modal="true">

  <div class="shint-modal__card shint-modal__card--edit">

    <!-- Header -->
    <header class="shint-modal__head">
      <h3 class="shint-modal__title">이벤트 편집</h3>
      <button type="button" class="shint-iconbtn" data-close="modal">×</button>
    </header>

    <!-- Body -->
    <div class="shint-modal__body">
      <form id="event-form" autocomplete="off">

        <!-- 제목 -->
        <div class="shint-row">
          <div class="shint-ico">T</div>
          <div class="shint-field">
            <input id="event-title"
                   class="shint-input shint-input--xl"
                   placeholder="제목을 추가하세요">
          </div>
        </div>
        
        <!-- 날짜 / 시간 (Event Edit) -->
        <div class="shint-row">
          <div class="shint-ico">🕒</div>

          <div class="shint-field">

            <div class="evt-dt-inline">

              <!-- 시작 날짜 -->
              <div class="evt-date-wrap">
                <input
                  id="event-start-date"
                  class="shint-input"
                  type="text"
                  readonly
                  data-picker="date"
                  aria-label="시작 날짜"
                >
                <i class="evt-ico">📅</i>
              </div>

              <!-- 시작 시간 (🔥 time-list picker 대상) -->
              <div class="evt-time-wrap">
                <input
                  id="event-start-time"
                  class="shint-input qt-time"
                  type="text"
                  readonly
                  data-picker="time-list"
                  data-role="start"
                  value="09:00"
                  aria-label="시작 시간"
                >
                <i class="evt-caret"></i>
              </div>

              <span class="evt-tilde">~</span>

              <!-- 종료 날짜 -->
              <div class="evt-date-wrap">
                <input
                  id="event-end-date"
                  class="shint-input"
                  type="text"
                  readonly
                  data-picker="date"
                  aria-label="종료 날짜"
                >
                <i class="evt-ico">📅</i>
              </div>

              <!-- 종료 시간 (🔥 time-list picker 대상) -->
              <div class="evt-time-wrap">
                <input
                  id="event-end-time"
                  class="shint-input qt-time"
                  type="text"
                  readonly
                  data-picker="time-list"
                  data-role="end"
                  value="10:00"
                  aria-label="종료 시간"
                >
                <i class="evt-caret"></i>
              </div>

            </div>

            <!-- 온종일 -->
            <label class="evt-allday">
              <input id="event-allday" type="checkbox">
              <span>온종일 이벤트</span>
            </label>

          </div>
        </div>




        <hr class="shint-divider">

        <h4 class="shint-section-title">이벤트 세부 사항</h4>

        <!-- 소유자 -->
        <div class="shint-row">
          <div class="shint-ico">👤</div>
          <div class="shint-field">
           <span id="event-owner"></span>
          </div>
        </div>

        <!-- 반복 -->
        <div class="shint-row">
          <div class="shint-ico">🔁</div>

          <div class="shint-field shint-inline">
            <!-- 표시용 버튼 -->
            <button type="button" id="event-repeat-btn" class="shint-select shint-select--button">
              <span class="evt-repeat-label">반복되지 않음</span>
              <i class="bi bi-caret-down-fill"></i>
            </button>

            <!-- 드롭다운 -->
            <div id="event-repeat-menu" class="evt-repeat-menu" hidden>
              <button type="button" data-preset="none">반복되지 않음</button>
              <button type="button" data-preset="daily">날마다</button>
              <button type="button" data-preset="weekly">매주</button>
              <button type="button" data-preset="monthly">매월</button>
              <button type="button" data-preset="yearly">매년</button>
              <hr>
              <button type="button" data-preset="custom">사용자 지정</button>
            </div>
          </div>
        </div>



        <!-- 게스트 -->
        <div class="shint-row">
          <div class="shint-ico">👥</div>
          <div class="shint-field">
            <input id="event-guests"
                   class="shint-input"
                   placeholder="게스트 추가">
          </div>
        </div>

        <!-- 위치 -->
        <div class="shint-row">
          <div class="shint-ico">📍</div>
          <div class="shint-field">
            <input id="event-location"
                   class="shint-input"
                   placeholder="위치 추가">
          </div>
        </div>

        <!-- =========================
            캘린더 + 이벤트 색상
        ========================== -->
        <div class="shint-row">
          <div class="shint-ico">🗓️</div>

          <div class="shint-field shint-inline evt-calendar-wrap">

            <!-- =========================
                캘린더 선택 버튼
                (■ 캘린더색 + 이름 + ▼)
            ========================== -->
            <button type="button"
                    id="event-calendar-btn"
                    class="shint-calbtn"
                    aria-haspopup="listbox"
                    aria-expanded="false">

              <!-- ■ 캘린더 고유 색상 -->
              <span id="event-calendar-color"
                    class="evt-cal-color"></span>

              <!-- 캘린더 이름 -->
              <span id="event-calendar-name"
                    class="evt-cal-name">
                캘린더
              </span>

              <!-- ▼ (버튼에만 존재) -->
              <i class="bi bi-caret-down-fill"></i>
            </button>

            <!-- =========================
                캘린더 목록
                (■ 캘린더색 + 이름)만 표시
                ❌ 이벤트 색 / ▼ 없음
            ========================== -->
            <div id="event-calendar-list"
                class="shint-cal-list"
                role="listbox"
                hidden>
              <!-- JS 렌더:
                  <button class="evt-calendar-item">
                    <span class="evt-cal-color"></span>
                    <span class="evt-cal-name"></span>
                  </button>
              -->
            </div>

            <!-- =========================
                이벤트 색상 버튼
                (● 이벤트색 + ▼)
            ========================== -->
            <button type="button"
                    id="event-color-btn"
                    class="shint-colorbtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    title="이벤트 색상">

              <!-- ● 이벤트 고유 색상 -->
              <span id="event-color-dot"
                    class="evt-event-color"></span>

              <i class="bi bi-caret-down-fill"></i>
            </button>

            <!-- =========================
                이벤트 색상 팔레트
            ========================== -->
            <div id="evt-color-palette"
                class="evt-color-palette"
                role="listbox"
                hidden></div>


            <!-- =========================
                저장용 hidden 값
            ========================== -->
            <input type="hidden" id="event-calendar-id">
            <input type="hidden" id="event-color">

          </div>
        </div>




        <!-- 알림 (Synology 구조) -->
        <div class="shint-row row-reminders">
          <div class="shint-ico">🔔</div>
          <div class="shint-field shint-stack" id="event-reminders">

            <div class="evt-reminder-row shint-inline">
              <select class="shint-select">
                <option>이벤트 시</option>
                <option>시작 5분 전</option>
                <option>시작 10분 전</option>
                <option>시작 30분 전</option>
                <option>시작 1시간 전</option>
              </select>

              <button type="button" class="shint-iconbtn">−</button>
            </div>

            <button type="button"
                    class="shint-btn shint-btn--ghost">
              + 추가
            </button>
          </div>
        </div>

        <!-- 설명 -->
        <div class="shint-row">
          <div class="shint-ico">📝</div>
          <div class="shint-field">
            <textarea id="event-desc"
                      class="shint-textarea"
                      rows="6"
                      placeholder="설명을 추가하세요"></textarea>
          </div>
        </div>

        <!-- 업로드 -->
        <div class="shint-row">
          <div class="shint-ico">📎</div>
          <div class="shint-field">
            <button type="button"
                    class="shint-btn shint-btn--light">
              업로드
            </button>
          </div>
        </div>

        <!-- Actions -->
        <div class="shint-actions">
          <button type="button" class="shint-btn" data-close="modal">취소</button>
          <button type="submit" class="shint-btn shint-btn--primary">저장</button>
        </div>

      </form>
    </div>
  </div>
</div>

// 📄 /public/assets/js/pages/dashboard/calendar/sidebar.right.edit.js
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
(() => {
    'use strict';
    console.log('[sidebar.right.edit] loaded');
  
    const BUILD = '2026-01-RIGHT-EDIT-V2';
    if (window.__RIGHT_EDIT_BUILD__ === BUILD) return;
    window.__RIGHT_EDIT_BUILD__ = BUILD;

    /* =====================================================
    * Edit Picker State
    * ===================================================== */
    const EditState = {
      date: null // Date | null
    };
    

    let editPicker = null;
    let isRestoringPicker = false;

  
    /* =====================================================
     * DOM
     * ===================================================== */
    let editPanel, listPanel;
    let btnBack, btnSave;
    let inputTitle, inputDue, selectList, taDesc;
  
    /* =====================================================
     * State
     * ===================================================== */
    let currentTask = null;
  
    document.addEventListener('DOMContentLoaded', () => {
      editPanel = document.getElementById('task-panel');
      listPanel = document.getElementById('right-list-panel');
  
      btnBack   = document.getElementById('btn-task-edit-back');
      btnSave   = document.getElementById('task-edit-save');
  
      inputTitle = document.getElementById('task-edit-title');
      inputDue   = document.getElementById('task-edit-due');
      taDesc     = document.getElementById('task-edit-desc');



      const taskBtn = document.getElementById('tasklist-btn-edit');
      const dropdown = document.getElementById('tasklist-dropdown-edit');
      
      taskBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
      
        dropdown.classList.toggle('is-hidden');
      });
      
      document.addEventListener('click', (e) => {
        if (!dropdown) return;
      
        if (dropdown.contains(e.target)) return;
        if (taskBtn.contains(e.target)) return;
      
        dropdown.classList.add('is-hidden');
      });

      //날짜시간피커
      const el = document.getElementById('datetime-picker');
      if (!el) return;    
      editPicker = AdminPicker.create({
        type: 'datetime',
        container: el
      });

  
      if (!editPanel || !listPanel) {
        console.warn('[right.edit] panel DOM missing');
        return;
      }
  
      bindUI();
      bindEditPicker();
    });

    window.addEventListener('task:save', async (e) => {
      try {
        const { task, prev } = e.detail || {};
        if (!task?.uid) {
          console.warn('[right.list] task:save - uid missing', task);
          alert('저장 실패: UID가 없습니다.');
          return;
        }
    
        await window.CalendarAPI.updateTask(task.uid, {
          title: task.title,
          description: task.description,
          due: task.due_iso,
          calendar_id: task.calendar_id
        });
    
      } catch (err) {
        console.error('[right.list] updateTask failed', err);
        alert(err?.message || '저장 중 오류가 발생했습니다.');
      }
    });



  
    function bindEditPicker() {
      if (!inputDue || !editPicker) return;
    
      // picker → state
      editPicker.subscribe((state, finalDate) => {
        if (!finalDate) {
          inputDue.value = '';
          return;
        }
      
        if (state.timeEnabled) {
          inputDue.value = toDatetimeLocal(finalDate);
        } else {
          inputDue.value = toDateOnly(finalDate);
        }
      });
      
      
      
    
      // input 클릭 → picker open
      inputDue.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
      
        isRestoringPicker = true;
      
        editPicker.open({
          anchor: inputDue,
          placement: 'left',
          offset: 8
        });
      
        requestAnimationFrame(() => {
          if (EditState.date) {
            editPicker.setDate?.(EditState.date);
          
            if (EditState.hasTime) {
              editPicker.setTime?.({
                hour: EditState.date.getHours(),
                minute: EditState.date.getMinutes()
              });
            } else {
              editPicker.setTime?.(null);
            }
          }          
      
          setTimeout(() => {
            isRestoringPicker = false;
          }, 0);
        });
      });
        
        
    }
  
    
    
    function applyTaskDueToEditState(dueIso) {
      if (!editPicker) return;
    
      if (!dueIso) {
        editPicker.clearDate();
        return;
      }
    
      const d = parseToDate(dueIso);
      const hasTime =
        dueIso.includes('T') &&
        !(d.getHours() === 0 && d.getMinutes() === 0);
    
      editPicker.setDate(d);
    
      if (hasTime) {
        editPicker.toggleTime(true);
        editPicker.setTime({
          hour: (() => {
            let h = d.getHours();
            if (h === 0) return 12;
            if (h > 12) return h - 12;
            return h;
          })(),
          minute: d.getMinutes(),
          meridiem: d.getHours() >= 12 ? 'PM' : 'AM'
        });
      } else {
        editPicker.toggleTime(false);
        editPicker.setTime({ hour: null, minute: null, meridiem: null });
      }
    }
    
    
    


    /* =====================================================
     * Event Emit
     * ===================================================== */
    function emit(name, detail) {
      window.dispatchEvent(new CustomEvent(name, { detail }));
    }
  
    /* =====================================================
     * UI Bindings
     * ===================================================== */
    function bindUI() {
      // 🔙 뒤로가기
      btnBack?.addEventListener('click', () => {
        close();
        emit('rightedit:back');
      });


      // 🔒 수정 모드에서 작업목록 변경 차단
      selectList?.addEventListener('change', (e) => {

        // 🔥 수정모드 판단은 currentTask 존재 여부
        if (currentTask && currentTask.uid) {
      
          e.preventDefault();
          e.stopPropagation();
      
          selectList.value = currentTask.calendar_id || '';
      
          window.AppCore?.notify?.(
            'warn',
            '기존 작업은 작업목록을 변경할 수 없습니다.'
          );
      
          return;
        }
      
        // 생성모드면 색상 적용
        const lists = window.CalendarContext?.calendars || [];
        applyListColor(selectList.value, lists);
      });

      selectList?.addEventListener('change', () => {
        const lists = window.CalendarContext?.calendars || [];
        applyListColor(selectList.value, lists);
      });
  
      // 💾 저장
      btnSave?.addEventListener('click', () => {
        if (!currentTask) return;

        const pickerState = editPicker.getState();
        const finalDate   = editPicker.getFinalDate();

        let due_iso = '';

        if (finalDate instanceof Date) {
          due_iso = pickerState.timeEnabled
            ? toDatetimeLocal(finalDate)   // YYYY-MM-DDTHH:mm
            : toDateOnly(finalDate);       // YYYY-MM-DD
        }

        const next = {
          ...currentTask,
          title: inputTitle.value.trim(),
          description: taDesc.value.trim(),
          due_iso
        };

        if (!next.title) {
          alert('작업 이름을 입력해 주세요.');
          inputTitle?.focus();
          return;
        }

        const listId = selectList?.value || '';
        if (listId) next.calendar_id = listId;

        emit('task:save', { task: next, prev: currentTask });

        close();
        emit('rightedit:back');
      });

  
      // 📥 리스트에서 편집 요청
      window.addEventListener('rightpanel:openEdit', (e) => {
        const task = e?.detail?.task;
        if (!task) return;
        open(task);
      });
  
      // 📋 작업 목록 옵션 세팅
      window.addEventListener('tasklists:set', (e) => {
        const lists = e?.detail?.lists;
        if (Array.isArray(lists)) fillLists(lists);
      });
    }
  
    /* =====================================================
     * Open / Close
     * ===================================================== */
    function open(task) {
        // 🔥 task 구조 정규화
        const t = normalizeTask(task);
        currentTask = t;
        
        // 패널 전환
        listPanel.classList.remove('is-open');
        editPanel.classList.add('is-open');
        
        // 값 채우기
        inputTitle.value = t.title || '';
        taDesc.value     = t.description || '';

        applyTaskDueToEditState(t.due_iso);

        // 🔥 작업목록 강제 재세팅
        if (window.CalendarContext?.calendars) {
          renderTaskListDropdown(
            window.CalendarContext.calendars,
            t.calendar_id
          );
        }
    }
      
    function close() {
      editPanel.classList.remove('is-open');
      listPanel.classList.add('is-open');
      currentTask = null;
    }
  
    /* =====================================================
     * List Select Fill
     * ===================================================== */
    function fillLists(lists) {
      if (!selectList) return;

      selectList.innerHTML = '';

      const taskLists = lists.filter(l =>
        l.type === 'task' || l.supports_task === true
      );

      if (!taskLists.length) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '작업목록 없음';
        selectList.appendChild(opt);
        return;
      }

      taskLists.forEach(l => {
        const id = String(l.calendar_id ?? l.id ?? '');
        if (!id) return;

        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = l.name ?? l.title ?? id;
        selectList.appendChild(opt);
      });

      if (currentTask) {
        const value = String(currentTask.calendar_id ?? '');

        selectList.value = value;

        applyListColor(value, taskLists);
      }
    }
        
    
  
    /* =====================================================
     * Utils
     * ===================================================== */    
    function parseToDate(iso) {
      if (!iso) return null;
    
      if (/^\d{8}T\d{6}$/.test(iso)) {
        return new Date(
          iso.slice(0,4)+'-'+iso.slice(4,6)+'-'+iso.slice(6,8)+'T'+
          iso.slice(9,11)+':'+iso.slice(11,13)
        );
      }
    
      // 🔥 공백 → T로 치환 후 Date 생성
      if (iso.includes(' ')) {
        return new Date(iso.replace(' ', 'T'));
      }
    
      return new Date(iso);
    }
    
    function toDatetimeLocal(d) {
      return (
        d.getFullYear() + '-' +
        String(d.getMonth()+1).padStart(2,'0') + '-' +
        String(d.getDate()).padStart(2,'0') + ' ' +   // 🔥 T → 공백
        String(d.getHours()).padStart(2,'0') + ':' +
        String(d.getMinutes()).padStart(2,'0')
      );
    }
    
    function toDateOnly(d) {
      return (
        d.getFullYear() + '-' +
        String(d.getMonth()+1).padStart(2,'0') + '-' +
        String(d.getDate()).padStart(2,'0')
      );
    }    

    function normalizeTask(task) {
        const ep = task.extendedProps || {};
      
        return {
          ...task,
      
          // title
          title: task.title ?? ep.title ?? '',
      
          // description
          description: ep.description ?? task.description ?? '',
      
          // due date
          due_iso:
            ep.due_iso ??
            task.due_iso ??
            task.startStr ??
            '',
      
          // calendar / list
          calendar_id:
            ep.calendar_id ??
            task.calendar_id ??
            ''
        };
      }
      

      function applyListColor(value, lists) {
        const selected = lists.find(l =>
          String(l.calendar_id ?? l.id ?? '') === String(value)
        );
      
        if (!selected) {
          selectList.style.background = '';
          selectList.style.borderColor = '';
          return;
        }
      
        const color =
          selected.admin_calendar_color ||
          selected.calendar_color ||
          '#94a3b8';
      
        selectList.style.background = color + '22';
        selectList.style.borderColor = color;
      }

      function renderTaskListDropdown(lists, selectedId) {
        const dropdown = document.getElementById('tasklist-dropdown-edit');
        const btn = document.getElementById('tasklist-btn-edit');
        const hiddenInput = document.getElementById('task-edit-list');
      
        if (!dropdown || !btn) return;
      
        dropdown.innerHTML = '';
      
        const taskLists = lists.filter(l =>
          l.type === 'task' || l.supports_task === true
        );
      
        taskLists.forEach(l => {
          const id = String(l.calendar_id ?? l.id ?? '');
          const name = l.name ?? l.title ?? id;
      
          const color =
            l.admin_calendar_color ||
            l.calendar_color ||
            '#94a3b8';
      
          const item = document.createElement('div');
          item.className = 'tasklist-item';
      
          item.innerHTML = `
            <span class="tasklist-color" style="background:${color}"></span>
            <span>${name}</span>
          `;
      
          item.addEventListener('click', () => {

            // 🔒 수정 모드에서 목록 변경 차단
            if (currentTask && currentTask.uid && id !== currentTask.calendar_id) {
          
              window.AppCore?.notify?.(
                'warn',
                '기존 작업은 작업목록을 변경할 수 없습니다.'
              );
          
              dropdown.classList.add('is-hidden');
              return;
            }
          
            hiddenInput.value = id;
          
            btn.querySelector('.tasklist-color').style.background = color;
            btn.querySelector('.tasklist-name').textContent = name;
          
            dropdown.classList.add('is-hidden');
          });
      
          dropdown.appendChild(item);
      
          if (id === selectedId) {
            hiddenInput.value = id;
            btn.querySelector('.tasklist-color').style.background = color;
            btn.querySelector('.tasklist-name').textContent = name;
          }
        });
      }

  
  })();
  
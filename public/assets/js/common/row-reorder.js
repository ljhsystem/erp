// 경로: /assets/js/common/row-reorder.js

export function bindRowReorder(table, options){

    const {
        api,
        idField = 'id',
        sortNoField = 'sort_no',
        extraData = null,
        onSuccess = null,
        onError = null
    } = options;
  
    if(!table){
        console.error('[RowReorder] table 없음');
        return;
    }
  
    if(!api){
        console.error('[RowReorder] api 없음');
        return;
    }
  
    /* =========================
       🔥 중복 바인딩 방지
    ========================= */
    table.off('row-reorder');
  
    table.on('row-reorder', function(e, diff){
  
        if(!diff || !diff.length) return;
  
        const changes = [];
  
        diff.forEach(d => {
  
            const rowData = table.row(d.node).data();
            if(!rowData) return;
  
            const item = {
                id: rowData[idField],
                [sortNoField]: d.newPosition + 1,
                newSortNo: d.newPosition + 1
            };
  
            if(extraData){
                Object.assign(item, extraData(rowData));
            }
  
            changes.push(item);
        });
  
        if(!changes.length) return;
  
        /* =========================
           🔥 UI 깨짐 방지 (툴팁 제거)
        ========================= */
        cleanupUI();
  
        fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ changes })
        })
        .then(res => res.json())
        .then(json => {
  
            if(json.success){
  
                /* 🔥 redraw 이후도 대비 */
                cleanupUI();
  
                if(onSuccess){
                    onSuccess(json);
                }
  
            }else{
  
                if(onError){
                    onError(json);
                }else{
                    alert(json.message || '순서 저장 실패');
                }
            }
  
        })
        .catch(err => {
            console.error(err);
  
            if(onError){
                onError(err);
            }else{
                alert('순서 저장 실패');
            }
        });
  
    });
  
    /* =========================
       🔥 redraw 대응 (핵심)
    ========================= */
    table.on('draw', function(){
        cleanupUI();
    });
  
  }
  
  
  /* =========================
     🔥 공통 UI 정리 함수
  ========================= */
  function cleanupUI(){
  
      /* tooltip 제거 */
      document.querySelectorAll('.tooltip, .tooltip-container').forEach(el => {
          el.remove();
      });
  
      /* body에 남은 클래스 제거 */
      document.body.classList.remove('tooltip-open');
  
  }

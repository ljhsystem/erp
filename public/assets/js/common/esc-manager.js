// 경로: /public/assets/js/common/esc-manager.js
(function(){

    "use strict";

    window.ESCStack = {

        handlers: [],

        push(fn){
            this.handlers.push(fn);
        },

        remove(fn){
            this.handlers = this.handlers.filter(h => h !== fn);
        },

        trigger(){

            if(this.handlers.length > 0){

                const top = this.handlers[this.handlers.length - 1];

                if(top){
                    top();
                    return true;
                }
            }

            return false;
        }
    };

    /* 전역 ESC (단 하나만 존재해야 함) */
    window.addEventListener('keydown', function(e){

        if(e.key !== 'Escape') return;
    
        /* 1️⃣ picker 먼저 */
        const handled = window.ESCStack.trigger();
    
        if(handled){
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return;
        }
    
        /* 2️⃣ modal */
        const openModal = document.querySelector('.modal.show');
    
        if(openModal){
            bootstrap.Modal.getInstance(openModal)?.hide();
        }
    
    }, true);   // 🔥🔥🔥 캡처 단계

})();
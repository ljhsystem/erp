document.addEventListener('DOMContentLoaded', () => {
    const spanEl = document.querySelector('#vision h2 span');
    if (!spanEl) return;

    const txtArr = [
        '일류입니다.',
        '실행 전문가입니다.',
        '큰 계획을 가지고 있습니다.',
    ];
    let index = 0;
    let currentTxt = '';

    const typingSpeed = 80;
    const deletingSpeed = 60;
    const holdTime = 2000;  
    function writeTxt() {
        currentTxt = txtArr[index];
        let i = 0;
        spanEl.textContent = '';

        const addLetter = () => {
            if (i < currentTxt.length) {
                spanEl.textContent += currentTxt[i++];
                setTimeout(addLetter, typingSpeed);
            } else {
                setTimeout(deleteTxt, holdTime);
            }
        };
        addLetter();
    }

    function deleteTxt() {
        let i = currentTxt.length;
        const removeLetter = () => {
            if (i >= 0) {
                spanEl.textContent = currentTxt.substring(0, i--);
                setTimeout(removeLetter, deletingSpeed);
            } else {
                index = (index + 1) % txtArr.length;
                writeTxt();
            }
        };
        removeLetter();
    }

    writeTxt();
});



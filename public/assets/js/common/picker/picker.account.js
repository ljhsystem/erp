// /public/assets/js/common/picker/picker.account.js

export function createAccountPicker({ container }) {

  container.classList.add('picker', 'picker-account'); // 🔥 핵심

  container.innerHTML = `
      <div class="admin-picker">
          <div class="admin-picker__panel">
  
              <input type="text" class="form-control mb-2" placeholder="검색">
  
              <div class="picker-list"></div>
  
          </div>
      </div>
  `;

  const listEl = container.querySelector('.picker-list');

  let subscribers = [];

  function load(){

      fetch('/api/ledger/account/list')
      .then(res => res.json())
      .then(json => {

          listEl.innerHTML = '';

          json.data.forEach(row => {

              const div = document.createElement('div');

              div.className = 'picker-item';
              div.innerHTML = `
                  ${row.account_code} - ${row.account_name}
              `;

              div.onclick = () => {
                  subscribers.forEach(fn => fn('select', row));
              };

              listEl.appendChild(div);
          });
      });
  }

  load();

  return {
      open(){},
      close(){ container.classList.add('is-hidden'); },
      subscribe(fn){ subscribers.push(fn); }
  };
}
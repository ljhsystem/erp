(function () {
  const drawer = document.getElementById('mobile-nav-drawer');
  const overlay = document.getElementById('mobile-nav-overlay');
  const openButton = document.getElementById('mobile-nav-toggle');
  const closeButton = document.getElementById('mobile-nav-close');
  const currentTime = document.getElementById('current-time');
  const currentTimeMobile = document.getElementById('mobile-current-time');
  const sessionTimer = document.getElementById('session-timer');
  const sessionTimerMobile = document.getElementById('mobile-session-timer');

  if (!drawer || !overlay || !openButton) {
    return;
  }

  const syncText = () => {
    if (currentTime && currentTimeMobile) {
      currentTimeMobile.textContent = currentTime.textContent || '--:--';
    }

    if (sessionTimer && sessionTimerMobile) {
      sessionTimerMobile.textContent = sessionTimer.textContent || '00:00';
    }
  };

  const openDrawer = () => {
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    overlay.hidden = false;
    overlay.classList.add('is-open');
    openButton.setAttribute('aria-expanded', 'true');
    document.body.classList.add('mobile-nav-open');
    syncText();
  };

  const closeDrawer = () => {
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    overlay.classList.remove('is-open');
    overlay.hidden = true;
    openButton.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('mobile-nav-open');
  };

  openButton.addEventListener('click', () => {
    if (drawer.classList.contains('is-open')) {
      closeDrawer();
      return;
    }

    openDrawer();
  });

  closeButton?.addEventListener('click', closeDrawer);
  overlay.addEventListener('click', closeDrawer);

  document.querySelectorAll('[data-mobile-nav-link="true"]').forEach((link) => {
    link.addEventListener('click', closeDrawer);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
      closeDrawer();
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768 && drawer.classList.contains('is-open')) {
      closeDrawer();
    }
  });

  syncText();

  if (currentTime || sessionTimer) {
    const observer = new MutationObserver(syncText);

    if (currentTime) {
      observer.observe(currentTime, { childList: true, subtree: true, characterData: true });
    }

    if (sessionTimer) {
      observer.observe(sessionTimer, { childList: true, subtree: true, characterData: true });
    }
  }
})();

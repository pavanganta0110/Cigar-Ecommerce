(() => {
  const gate = document.querySelector('[data-age-gate]');
  if (!gate || !window.compadresAgeGate) return;

  const confirm = gate.querySelector('[data-age-confirm]');
  const exit = gate.querySelector('[data-age-exit]');
  const status = gate.querySelector('[data-age-status]');
  const previousFocus = document.activeElement;
  const focusable = [confirm, exit];

  document.body.classList.add('age-gate-open');
  confirm.focus();

  gate.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      status.textContent = gate.dataset.escapeMessage;
      confirm.focus();
      return;
    }
    if (event.key !== 'Tab') return;
    const current = focusable.indexOf(document.activeElement);
    if (event.shiftKey && current <= 0) {
      event.preventDefault();
      exit.focus();
    } else if (!event.shiftKey && current === focusable.length - 1) {
      event.preventDefault();
      confirm.focus();
    }
  });

  confirm.addEventListener('click', async () => {
    confirm.disabled = true;
    status.textContent = 'Saving confirmation…';
    const body = new URLSearchParams({ action: 'compadres_age_confirm', nonce: window.compadresAgeGate.nonce });
    try {
      const response = await fetch(window.compadresAgeGate.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error('Confirmation could not be saved.');
      gate.remove();
      document.body.classList.remove('age-gate-open');
      if (previousFocus instanceof HTMLElement && previousFocus !== document.body) {
        previousFocus.focus();
      } else {
        const main = document.querySelector('main');
        if (main) {
          main.setAttribute('tabindex', '-1');
          main.focus();
          main.addEventListener('blur', () => main.removeAttribute('tabindex'), { once: true });
        }
      }
    } catch (error) {
      status.textContent = error instanceof Error ? error.message : 'Confirmation could not be saved.';
      confirm.disabled = false;
      confirm.focus();
    }
  });
})();

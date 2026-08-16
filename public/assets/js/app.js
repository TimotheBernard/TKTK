(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  window.api = async function api(path, options = {}) {
    const opts = { method: 'GET', headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' }, ...options };
    if (opts.body && !(opts.body instanceof FormData) && typeof opts.body !== 'string') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    const res = await fetch(path, opts);
    const json = await res.json().catch(() => ({ success: false, error: { message: 'Invalid JSON' } }));
    if (!res.ok || json.success === false) {
      const msg = json.error?.message || json.error?.code || res.statusText;
      throw new Error(msg);
    }
    return json.data;
  };

  window.toast = function toast(message, kind = '') {
    const stack = document.getElementById('toasts');
    if (!stack) return;
    const el = document.createElement('div');
    el.className = 'toast ' + kind;
    el.textContent = message;
    stack.appendChild(el);
    setTimeout(() => el.remove(), 4000);
  };

  document.querySelector('[data-toggle-nav]')?.addEventListener('click', () => {
    document.querySelector('.sidebar')?.classList.toggle('open');
  });
})();

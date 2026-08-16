const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

window.TM = {
    csrf,
    api: async (path, options = {}) => {
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-Token': csrf,
            ...(options.headers || {}),
        };
        if (options.body && typeof options.body !== 'string') {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        const res = await fetch(path, { credentials: 'same-origin', ...options, headers });
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.error?.message || json.error?.code || 'Erreur API');
        }
        return json.data;
    },
    toast(message) {
        const root = document.getElementById('toast-root');
        if (!root) return;
        const el = document.createElement('div');
        el.className = 'toast';
        el.textContent = message;
        root.appendChild(el);
        setTimeout(() => el.remove(), 3200);
    },
    badge(status) {
        const s = String(status || '').toLowerCase();
        return `<span class="badge ${s}">${s.replaceAll('_', ' ')}</span>`;
    },
    time(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return iso;
        return d.toISOString().slice(11, 23);
    },
    qs(sel) { return document.querySelector(sel); },
};

document.querySelector('[data-toggle-nav]')?.addEventListener('click', () => {
    document.body.classList.toggle('nav-open');
});

async function refreshWorkerPill() {
    const pill = document.getElementById('worker-pill');
    if (!pill) return;
    try {
        const data = await window.TM.api('/api/settings.php');
        const alive = data.scheduler?.alive;
        pill.textContent = alive ? 'Worker actif' : 'Worker arrêté';
        pill.style.color = alive ? 'var(--ok)' : 'var(--muted)';
    } catch {
        pill.textContent = 'Worker ?';
    }
}

refreshWorkerPill();
setInterval(refreshWorkerPill, 5000);

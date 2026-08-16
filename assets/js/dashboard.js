async function renderDashboard() {
    const data = await TM.api('/api/dashboard.php');
    const c = data.account_counts || {};
    const t = data.task_counts || {};
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('m-accounts', c.total ?? 0);
    set('m-active', c.active ?? 0);
    set('m-busy', c.busy ?? 0);
    set('m-valid', c.sessions_valid ?? 0);
    set('m-expired', c.sessions_expired ?? 0);
    set('m-disabled', c.disabled ?? 0);
    set('m-error', c.error ?? 0);
    set('m-artists', (data.artists || []).length);
    set('m-posts', (data.posts || []).length);
    set('m-scheduled', t.scheduled ?? 0);
    set('m-imminent', t.imminent ?? 0);
    set('m-running', t.running ?? 0);
    set('m-done', t.completed ?? 0);
    set('m-failed', t.failed ?? 0);

    const accBody = document.getElementById('account-status-body');
    if (accBody) {
        accBody.innerHTML = (data.accounts || []).map((a) => `
            <tr>
                <td><a href="/account.php?id=${encodeURIComponent(a.id)}">${a.label}</a></td>
                <td>${TM.badge(a.status)}</td>
                <td>${TM.badge(a.session_status)}</td>
            </tr>`).join('') || '<tr><td colspan="3">Aucun compte</td></tr>';
    }

    const feed = document.getElementById('activity-feed');
    if (feed) {
        feed.innerHTML = (data.history || []).map((h) => `
            <li><time>${TM.time(h.created_at)}</time><span>${h.event} — ${h.account_id || h.post_id || ''}</span></li>
        `).join('') || '<li>Aucune activité</li>';
    }

    const posts = document.getElementById('latest-posts');
    if (posts) {
        posts.innerHTML = (data.posts || []).slice(0, 8).map((p) => `
            <tr><td>${TM.time(p.detected_at)}</td><td>${p.username}</td><td>${TM.badge(p.status)}</td></tr>
        `).join('') || '<tr><td colspan="3">Aucune publication</td></tr>';
    }
}

renderDashboard().catch((e) => TM.toast(e.message));
setInterval(() => renderDashboard().catch(() => {}), 1000);

document.getElementById('sim-fanout')?.addEventListener('click', async () => {
    try {
        const data = await TM.api('/api/simulate.php?action=new_post', { method: 'POST', body: { delays: [2, 4, 5, 8, 12] } });
        TM.toast(`${data.result?.tasks?.length || 0} tâches créées`);
        renderDashboard();
    } catch (e) {
        TM.toast(e.message);
    }
});

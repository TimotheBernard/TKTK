async function loadTasks() {
    const params = new URLSearchParams(location.search);
    const qs = params.get('account_id') ? '&account_id=' + encodeURIComponent(params.get('account_id')) : '';
    const data = await TM.api('/api/tasks.php?view=supervision' + qs);
    const body = document.getElementById('tasks-body');
    body.innerHTML = (data.items || []).map((row) => {
        const t = row.task;
        const cd = row.countdown_s == null ? '—' : (row.countdown_s <= 0 ? 'due' : row.countdown_s.toFixed(1) + ' s');
        return `<tr>
            <td>${TM.time(t.scheduled_at)}</td>
            <td>${row.account_label}</td>
            <td>${row.target_label}</td>
            <td>${TM.badge(t.status)}</td>
            <td class="countdown">${cd}</td>
            <td class="row">
                <button class="btn ghost" data-act="cancel" data-id="${t.id}">Annuler</button>
                <button class="btn ghost" data-act="retry" data-id="${t.id}">Relancer</button>
            </td>
        </tr>`;
    }).join('') || '<tr><td colspan="6">Aucune tâche</td></tr>';

    const cds = document.getElementById('countdowns');
    cds.innerHTML = (data.countdowns || []).map((c) => `
        <div class="card"><strong>${c.label}</strong><div class="countdown">Prochaine tâche dans : ${c.countdown_s.toFixed(1)} s</div></div>
    `).join('') || '<p class="muted">Aucun compte à rebours</p>';
}

document.getElementById('tasks-body')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    await TM.api(`/api/tasks.php?id=${encodeURIComponent(btn.dataset.id)}&action=${btn.dataset.act}`, { method: 'POST', body: {} });
    await loadTasks();
});

loadTasks().catch((e) => TM.toast(e.message));
setInterval(() => loadTasks().catch(() => {}), 400);

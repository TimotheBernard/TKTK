async function load() {
    const [targets, accounts, artists] = await Promise.all([
        TM.api('/api/targets.php'),
        TM.api('/api/accounts.php'),
        TM.api('/api/artists.php'),
    ]);
    const accMap = Object.fromEntries((accounts.items || []).map((a) => [a.id, a.label]));
    const artMap = Object.fromEntries((artists.items || []).map((a) => [a.id, a.name]));
    document.getElementById('account_id').innerHTML = (accounts.items || []).map((a) => `<option value="${a.id}">${a.label}</option>`).join('');
    document.getElementById('artist_id').innerHTML = (artists.items || []).map((a) => `<option value="${a.id}">${a.name}</option>`).join('');
    document.getElementById('targets-body').innerHTML = (targets.items || []).map((t) => `
        <tr>
            <td>${accMap[t.account_id] || t.account_id}</td>
            <td>${artMap[t.artist_id] || t.artist_id}</td>
            <td>${t.enabled ? 'Oui' : 'Non'}</td>
            <td>${t.check_new_posts ? 'Oui' : 'Non'}</td>
            <td><button class="btn danger" data-id="${t.id}">Supprimer</button></td>
        </tr>
    `).join('') || '<tr><td colspan="5">Aucune association</td></tr>';
    const warn = document.getElementById('orphan-warning');
    if (warn) {
        warn.textContent = (targets.orphans || []).length
            ? `${targets.orphans.length} association(s) sans règle — aucune tâche ne sera créée.`
            : '';
    }
}

document.getElementById('target-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    try {
        await TM.api('/api/targets.php', {
            method: 'POST',
            body: {
                account_id: form.account_id.value,
                artist_id: form.artist_id.value,
                enabled: true,
                check_new_posts: form.check_new_posts.checked,
            },
        });
        await load();
        TM.toast('Association créée');
    } catch (err) {
        TM.toast(err.message);
    }
});
document.getElementById('targets-body')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-id]');
    if (!btn) return;
    await TM.api('/api/targets.php?id=' + encodeURIComponent(btn.dataset.id), { method: 'DELETE' });
    await load();
});
load().catch((e) => TM.toast(e.message));

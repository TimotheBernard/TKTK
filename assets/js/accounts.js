const modal = document.getElementById('account-modal');
const form = document.getElementById('account-form');

async function loadAccounts() {
    const data = await TM.api('/api/accounts.php');
    const body = document.getElementById('accounts-body');
    body.innerHTML = (data.items || []).map((a) => `
        <tr>
            <td><a href="/account.php?id=${encodeURIComponent(a.id)}">${a.label}</a></td>
            <td>${a.username || '—'}</td>
            <td>${TM.badge(a.status)}</td>
            <td>${TM.badge(a.session_status)}</td>
            <td>${a.enabled ? 'Oui' : 'Non'}</td>
            <td class="row">
                <button class="btn ghost" data-act="edit" data-id="${a.id}">Modifier</button>
                <button class="btn ghost" data-act="${a.enabled ? 'disable' : 'enable'}" data-id="${a.id}">${a.enabled ? 'Désactiver' : 'Activer'}</button>
                <button class="btn ghost" data-act="test_session" data-id="${a.id}">Tester</button>
                <button class="btn ghost" data-act="open_tiktok" data-id="${a.id}">Ouvrir TikTok</button>
                <a class="btn ghost" href="/targets.php?account_id=${encodeURIComponent(a.id)}">Cibles</a>
                <a class="btn ghost" href="/rules.php?account_id=${encodeURIComponent(a.id)}">Règles</a>
                <a class="btn ghost" href="/tasks.php?account_id=${encodeURIComponent(a.id)}">Tâches</a>
                <a class="btn ghost" href="/history.php?account_id=${encodeURIComponent(a.id)}">Historique</a>
                <button class="btn danger" data-act="delete" data-id="${a.id}">Supprimer</button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="6">Aucun compte</td></tr>';
}

document.getElementById('add-account')?.addEventListener('click', () => {
    form.reset();
    form.id.value = '';
    modal.classList.add('open');
});
modal?.addEventListener('click', (e) => {
    if (e.target === modal || e.target.dataset.close) modal.classList.remove('open');
});
form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        label: form.label.value,
        username: form.username.value,
        notes: form.notes.value,
        enabled: form.enabled.checked,
    };
    try {
        if (form.id.value) {
            await TM.api('/api/accounts.php?id=' + encodeURIComponent(form.id.value), { method: 'PUT', body: payload });
        } else {
            await TM.api('/api/accounts.php', { method: 'POST', body: payload });
        }
        modal.classList.remove('open');
        await loadAccounts();
        TM.toast('Compte enregistré');
    } catch (err) {
        TM.toast(err.message);
    }
});

document.getElementById('accounts-body')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const id = btn.dataset.id;
    const act = btn.dataset.act;
    try {
        if (act === 'edit') {
            const data = await TM.api('/api/accounts.php?id=' + encodeURIComponent(id));
            const a = data.account;
            form.id.value = a.id;
            form.label.value = a.label;
            form.username.value = a.username;
            form.notes.value = a.notes || '';
            form.enabled.checked = !!a.enabled;
            modal.classList.add('open');
            return;
        }
        if (act === 'delete') {
            if (!confirm('Supprimer ce compte ?')) return;
            await TM.api('/api/accounts.php?id=' + encodeURIComponent(id), { method: 'DELETE' });
        } else {
            await TM.api(`/api/accounts.php?id=${encodeURIComponent(id)}&action=${act}`, { method: 'POST', body: {} });
        }
        await loadAccounts();
    } catch (err) {
        TM.toast(err.message);
    }
});

loadAccounts().catch((e) => TM.toast(e.message));

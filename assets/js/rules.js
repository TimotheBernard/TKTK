async function loadRules() {
    const [rules, accounts, artists] = await Promise.all([
        TM.api('/api/rules.php'),
        TM.api('/api/accounts.php'),
        TM.api('/api/artists.php'),
    ]);
    const accMap = Object.fromEntries((accounts.items || []).map((a) => [a.id, a.label]));
    const artMap = Object.fromEntries((artists.items || []).map((a) => [a.id, a.name]));
    document.getElementById('rule-account').innerHTML = (accounts.items || []).map((a) => `<option value="${a.id}">${a.label}</option>`).join('');
    document.getElementById('rule-artist').innerHTML = (artists.items || []).map((a) => `<option value="${a.id}">${a.name}</option>`).join('');
    document.getElementById('rules-body').innerHTML = (rules.items || []).map((r) => `
        <tr>
            <td>${accMap[r.account_id] || r.account_id}</td>
            <td>${artMap[r.artist_id] || r.artist_id}</td>
            <td><input type="checkbox" data-id="${r.id}" data-field="enabled" ${r.enabled ? 'checked' : ''}></td>
            <td class="num"><input type="number" min="0" step="1" value="${r.delay_seconds}" data-id="${r.id}" data-field="delay_seconds" style="width:80px"></td>
        </tr>
    `).join('') || '<tr><td colspan="4">Aucune règle</td></tr>';
}

document.getElementById('save-delays')?.addEventListener('click', async () => {
    const items = [];
    document.querySelectorAll('#rules-body tr').forEach((tr) => {
        const delay = tr.querySelector('[data-field="delay_seconds"]');
        const enabled = tr.querySelector('[data-field="enabled"]');
        if (!delay) return;
        items.push({
            id: delay.dataset.id,
            delay_seconds: Number(delay.value),
            enabled: enabled.checked,
        });
    });
    try {
        await TM.api('/api/rules.php?action=bulk_delays', { method: 'PUT', body: { items } });
        TM.toast('Délais enregistrés');
        await loadRules();
    } catch (e) {
        TM.toast(e.message);
    }
});

document.getElementById('rule-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await TM.api('/api/rules.php', {
            method: 'POST',
            body: {
                account_id: e.target.account_id.value,
                artist_id: e.target.artist_id.value,
                delay_seconds: Number(e.target.delay_seconds.value),
                enabled: true,
            },
        });
        await loadRules();
    } catch (err) {
        TM.toast(err.message);
    }
});

loadRules().catch((e) => TM.toast(e.message));

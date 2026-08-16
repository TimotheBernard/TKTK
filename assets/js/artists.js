const modal = document.getElementById('artist-modal');
const form = document.getElementById('artist-form');

async function loadArtists() {
    const data = await TM.api('/api/artists.php');
    document.getElementById('artists-body').innerHTML = (data.items || []).map((a) => `
        <tr>
            <td>${a.name}</td>
            <td>${a.username}</td>
            <td>${a.category || '—'}</td>
            <td>${a.enabled ? 'Oui' : 'Non'}</td>
            <td class="row">
                <button class="btn ghost" data-act="edit" data-json='${JSON.stringify(a)}'>Modifier</button>
                <button class="btn danger" data-act="delete" data-id="${a.id}">Supprimer</button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="5">Aucun artiste</td></tr>';
}

document.getElementById('add-artist')?.addEventListener('click', () => {
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
        name: form.name.value,
        username: form.username.value,
        category: form.category.value,
        country: form.country.value,
        enabled: form.enabled.checked,
    };
    try {
        if (form.id.value) {
            await TM.api('/api/artists.php?id=' + encodeURIComponent(form.id.value), { method: 'PUT', body: payload });
        } else {
            await TM.api('/api/artists.php', { method: 'POST', body: payload });
        }
        modal.classList.remove('open');
        await loadArtists();
    } catch (err) {
        TM.toast(err.message);
    }
});
document.getElementById('artists-body')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    if (btn.dataset.act === 'edit') {
        const a = JSON.parse(btn.dataset.json);
        form.id.value = a.id;
        form.name.value = a.name;
        form.username.value = a.username;
        form.category.value = a.category || '';
        form.country.value = a.country || '';
        form.enabled.checked = !!a.enabled;
        modal.classList.add('open');
        return;
    }
    if (btn.dataset.act === 'delete' && confirm('Supprimer cet artiste ?')) {
        await TM.api('/api/artists.php?id=' + encodeURIComponent(btn.dataset.id), { method: 'DELETE' });
        await loadArtists();
    }
});
loadArtists().catch((e) => TM.toast(e.message));

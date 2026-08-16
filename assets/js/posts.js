async function loadPosts() {
    const data = await TM.api('/api/posts.php');
    document.getElementById('posts-body').innerHTML = (data.items || []).map((p) => `
        <tr>
            <td>${TM.time(p.detected_at)}</td>
            <td>${p.username}</td>
            <td><a href="${p.url}" target="_blank" rel="noreferrer">${p.video_id || p.url}</a></td>
            <td>${TM.badge(p.status)}</td>
            <td>${p.source}</td>
        </tr>
    `).join('') || '<tr><td colspan="5">Aucune publication</td></tr>';
}

document.getElementById('post-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const url = e.target.url.value;
    try {
        const data = await TM.api('/api/posts.php', { method: 'POST', body: { url, artist_id: e.target.artist_id.value || undefined } });
        TM.toast(data.duplicate ? 'Publication déjà connue' : `${(data.tasks || []).length} tâche(s) créée(s)`);
        e.target.reset();
        await loadPosts();
    } catch (err) {
        TM.toast(err.message);
    }
});

async function fillArtists() {
    const artists = await TM.api('/api/artists.php');
    const sel = document.getElementById('artist_id');
    sel.innerHTML = '<option value="">Déduire du username</option>' + (artists.items || []).map((a) => `<option value="${a.id}">${a.name}</option>`).join('');
}

fillArtists().then(loadPosts).catch((e) => TM.toast(e.message));
setInterval(() => loadPosts().catch(() => {}), 3000);

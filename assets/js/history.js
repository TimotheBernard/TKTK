async function loadHistory() {
    const params = new URLSearchParams(location.search);
    const qs = params.get('account_id') ? '?account_id=' + encodeURIComponent(params.get('account_id')) : '';
    const data = await TM.api('/api/history.php' + qs);
    document.getElementById('history-body').innerHTML = (data.items || []).map((h) => `
        <tr>
            <td>${TM.time(h.created_at)}</td>
            <td>${h.event}</td>
            <td>${h.account_id || '—'}</td>
            <td>${h.post_id || '—'}</td>
            <td>${TM.badge(h.status)}</td>
            <td>${h.duration_ms || 0} ms</td>
        </tr>
    `).join('') || '<tr><td colspan="6">Vide</td></tr>';
}
loadHistory().catch((e) => TM.toast(e.message));
setInterval(() => loadHistory().catch(() => {}), 2000);

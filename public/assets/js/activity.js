(() => {
  const list = document.getElementById('activity-list');

  const label = (item) => {
    const who = item.account_id || item.artist_id || '';
    const map = {
      NEW_POST: 'Nouvelle publication détectée',
      SCENARIO_STARTED: 'Scénario démarré',
      SCENARIO_COMPLETED: 'Scénario terminé',
      SCENARIO_FAILED: 'Scénario en échec',
      PUBLICATION_SCHEDULED: 'Publication programmée',
      PUBLICATION_SENT: 'Publication envoyée',
      PUBLICATION_FAILED: 'Publication échouée',
      SESSION_CONNECTED: 'Session connectée',
      SESSION_EXPIRED: 'Session expirée',
      ACCOUNT_ADDED: 'Compte ajouté',
      task_created: 'Tâche créée',
      task_started: 'Tâche démarrée',
      post_opened: 'Publication ouverte',
    };
    return (map[item.event] || item.event) + (who ? ' · ' + who : '');
  };

  async function refresh() {
    const data = await api('/api/activity');
    const items = data.items || [];
    list.innerHTML = items.map((item) => `<li><time>${item.created_at || ''}</time><div>${label(item)}</div></li>`).join('');
  }

  refresh().catch((err) => toast(err.message, 'error'));
  setInterval(() => refresh().catch(() => {}), 1500);
})();

(() => {
  const grid = document.getElementById('resource-grid');

  async function refresh() {
    const data = await api('/api/resources');
    const cards = [
      ['Comptes enregistrés', data.accounts_registered],
      ['Comptes connectés', data.accounts_connected],
      ['Sessions expirées', data.accounts_expired],
      ['Navigateurs actifs', data.browsers_active],
      ['Tâches en attente', data.tasks_pending],
      ['Tâches en cours', data.tasks_running],
      ['CPU', (data.cpu_percent ?? 0) + ' %'],
      ['RAM', (data.ram_percent ?? 0) + ' %'],
    ];
    grid.innerHTML = cards.map(([label, value]) => `<article class="resource-card"><small>${label}</small><strong>${value}</strong></article>`).join('');
  }

  refresh().catch((err) => toast(err.message, 'error'));
  setInterval(() => refresh().catch(() => {}), 2000);
})();

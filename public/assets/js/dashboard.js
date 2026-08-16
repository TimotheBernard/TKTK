(() => {
  const grid = document.getElementById('account-grid');
  const empty = document.getElementById('empty-accounts');
  const search = document.getElementById('account-search');
  const filterGroup = document.getElementById('filter-group');
  const filterStatus = document.getElementById('filter-status');
  const filterFav = document.getElementById('filter-fav');
  const sortBy = document.getElementById('sort-by');
  const simBtn = document.getElementById('sim-fanout');
  const pageSize = 24;
  let state = { accounts: [], artists: [], actions: [], expanded: new Set(), page: 1, media: [] };
  const isDev = document.body.dataset.dev === 'true';
  if (isDev) simBtn.hidden = false;

  const statusLabel = (account) => {
    const runtime = account.runtime_status || account.status || 'idle';
    const connection = account.connection_status || account.session_status || 'unknown';
    if (runtime === 'session_expired' || connection === 'expired') return 'SESSION EXPIRÉE';
    if (connection === 'connected') return 'CONNECTÉ';
    if (runtime === 'disabled') return 'DISABLED';
    return String(runtime).toUpperCase();
  };

  const matches = (account) => {
    const q = (search.value || '').toLowerCase();
    const hay = [account.label, account.username, account.display_name, account.group].join(' ').toLowerCase();
    if (q && !hay.includes(q)) return false;
    if (filterGroup.value && account.group !== filterGroup.value) return false;
    if (filterFav.checked && !account.favorite) return false;
    const st = filterStatus.value;
    if (st === 'connected' && (account.connection_status || account.session_status) !== 'connected') return false;
    if (st && st !== 'connected' && (account.runtime_status || account.status) !== st) return false;
    return true;
  };

  const sorted = (items) => {
    const key = sortBy.value;
    return [...items].sort((a, b) => {
      if (key === 'favorite') return Number(b.favorite) - Number(a.favorite);
      if (key === 'status') return String(a.runtime_status).localeCompare(String(b.runtime_status));
      return String(a[key] || '').localeCompare(String(b[key] || ''), 'fr');
    });
  };

  const initials = (account) => (account.username || account.label || '?').replace('@', '').slice(0, 2).toUpperCase();

  function render() {
    const items = sorted(state.accounts.filter(matches));
    const slice = items.slice(0, state.page * pageSize);
    empty.hidden = items.length > 0;
    grid.innerHTML = slice.map((account) => cardHtml(account)).join('');
    if (items.length > slice.length) {
      grid.insertAdjacentHTML('beforeend', '<button class="btn secondary" id="load-more" type="button">Charger plus</button>');
    }
  }

  function cardHtml(account) {
    const expanded = state.expanded.has(account.id) ? ' expanded' : '';
    const runtime = account.runtime_status || 'idle';
    const connection = account.connection_status || 'unknown';
    const artistsById = Object.fromEntries(state.artists.map((a) => [a.id, a]));
    const targets = (account.targets || []).map((t) => {
      const artist = artistsById[t.artist_id] || {};
      return `<div class="row-item"><span>${escapeHtml(artist.username || t.artist_id)}</span><span>${t.enabled ? 'ACTIF' : 'OFF'}</span></div>`;
    }).join('');
    const scenarios = (account.scenarios || []).map((s) => {
      const artist = artistsById[s.artist_id] || {};
      return `<div class="row-item"><span>${escapeHtml(artist.username || '')} → ${escapeHtml(s.label || s.id)}</span><span>${s.enabled ? 'ON' : 'OFF'}</span></div>`;
    }).join('');
    const current = account.current_task ? `Tâche ${account.current_task.id}` : '—';
    const next = account.next_task ? account.next_task.scheduled_at : '—';
    const photo = account.profile_picture
      ? `<img src="${escapeHtml(account.profile_picture)}" alt="">`
      : initials(account);
    return `<article class="account-card${expanded}" data-id="${account.id}">
      <div class="card-head">
        <div class="avatar">${photo}</div>
        <div>
          <strong>${escapeHtml(account.username || account.label)}</strong>
          <div class="muted">${escapeHtml(account.display_name || account.label)} · ${escapeHtml(account.group || 'AUTRES')}</div>
          <div><span class="status-dot ${connection} ${runtime}"></span>${escapeHtml(statusLabel(account))} · ${escapeHtml(runtime.toUpperCase())}</div>
        </div>
      </div>
      <div class="card-meta">
        <span>${account.followed_count || 0} suivis</span>
        <span>${account.scenario_count || 0} scénarios</span>
      </div>
      <div class="muted">Tâche actuelle : ${escapeHtml(current)} · Prochaine : ${escapeHtml(next)}</div>
      <div class="card-actions">
        <button class="btn" data-open="${account.id}">OUVRIR</button>
        <button class="btn secondary" data-config="${account.id}">CONFIGURER</button>
      </div>
      <div class="expand-body">
        <h3>COMPTES SUIVIS</h3>
        ${targets || '<p class="muted">Aucune cible</p>'}
        <button class="linkish" data-add-target="${account.id}">+ AJOUTER UNE CIBLE</button>
        <h3>SCÉNARIOS</h3>
        ${scenarios || '<p class="muted">Aucun scénario</p>'}
        <button class="linkish" data-add-scenario="${account.id}">+ CRÉER UN SCÉNARIO</button>
        <h3>PUBLICATIONS</h3>
        <button class="linkish" data-publish="${account.id}">Ouvrir l’éditeur</button>
        <h3>HISTORIQUE</h3>
        <p class="muted">Voir aussi /activity</p>
        <h3>PARAMÈTRES</h3>
        <button class="linkish" data-fav="${account.id}">${account.favorite ? 'Retirer des favoris' : 'Mettre en favori'}</button>
      </div>
    </article>`;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  }

  async function refresh() {
    const data = await api('/api/dashboard');
    state.accounts = data.accounts || [];
    state.artists = data.artists || [];
    state.actions = data.actions || [];
    const pill = document.getElementById('worker-pill');
    if (pill) {
      pill.textContent = data.scheduler?.alive ? 'Worker · en ligne' : 'Worker · hors ligne';
    }
    render();
  }

  grid.addEventListener('click', async (event) => {
    const loadMore = event.target.closest('#load-more');
    if (loadMore) {
      state.page += 1;
      render();
      return;
    }
    const open = event.target.closest('[data-open]');
    if (open) {
      event.stopPropagation();
      await api('/api/accounts/' + open.dataset.open + '/open', { method: 'POST', body: {} });
      toast('Session navigateur lancée');
      return;
    }
    const config = event.target.closest('[data-config]');
    if (config) {
      event.stopPropagation();
      openSettings(config.dataset.config);
      return;
    }
    const addTarget = event.target.closest('[data-add-target]');
    if (addTarget) {
      event.stopPropagation();
      document.querySelector('#form-target [name=account_id]').value = addTarget.dataset.addTarget;
      document.getElementById('modal-target').showModal();
      return;
    }
    const addScenario = event.target.closest('[data-add-scenario]');
    if (addScenario) {
      event.stopPropagation();
      openScenario(addScenario.dataset.addScenario);
      return;
    }
    const publish = event.target.closest('[data-publish]');
    if (publish) {
      event.stopPropagation();
      openPublish(publish.dataset.publish);
      return;
    }
    const fav = event.target.closest('[data-fav]');
    if (fav) {
      event.stopPropagation();
      const account = state.accounts.find((a) => a.id === fav.dataset.fav);
      await api('/api/accounts/' + fav.dataset.fav, { method: 'PUT', body: { favorite: !account?.favorite } });
      await refresh();
      return;
    }
    const card = event.target.closest('.account-card');
    if (card) {
      if (state.expanded.has(card.dataset.id)) state.expanded.delete(card.dataset.id);
      else state.expanded.add(card.dataset.id);
      render();
    }
  });

  document.getElementById('add-account').addEventListener('click', () => document.getElementById('modal-account').showModal());
  document.getElementById('save-account').addEventListener('click', async (event) => {
    event.preventDefault();
    const form = document.getElementById('form-account');
    const body = Object.fromEntries(new FormData(form).entries());
    await api('/api/accounts', { method: 'POST', body });
    document.getElementById('modal-account').close();
    form.reset();
    await refresh();
  });

  document.getElementById('save-target').addEventListener('click', async (event) => {
    event.preventDefault();
    const form = document.getElementById('form-target');
    const data = Object.fromEntries(new FormData(form).entries());
    const artist = await api('/api/artists', { method: 'POST', body: { name: data.name, username: data.username } });
    await api('/api/targets', { method: 'POST', body: { account_id: data.account_id, artist_id: artist.id } });
    document.getElementById('modal-target').close();
    form.reset();
    await refresh();
  });

  function openScenario(accountId) {
    const account = state.accounts.find((a) => a.id === accountId);
    document.querySelector('#form-scenario [name=account_id]').value = accountId;
    const select = document.getElementById('scenario-artist');
    const targets = (account?.targets || []).map((t) => state.artists.find((a) => a.id === t.artist_id)).filter(Boolean);
    select.innerHTML = targets.map((a) => `<option value="${a.id}">${escapeHtml(a.username)}</option>`).join('');
    const editor = document.getElementById('scenario-steps');
    editor.innerHTML = '';
    addStepRow('OPEN_POST');
    addStepRow('WATCH');
    addStepRow('WAIT');
    document.getElementById('modal-scenario').showModal();
  }

  function addStepRow(type = 'WAIT') {
    const editor = document.getElementById('scenario-steps');
    const row = document.createElement('div');
    row.className = 'step-row';
    const options = (state.actions.length ? state.actions : [{ type: 'OPEN_POST' }, { type: 'WAIT' }, { type: 'WATCH' }, { type: 'USER_CONFIRMATION' }])
      .map((a) => `<option ${a.type === type ? 'selected' : ''}>${a.type}</option>`).join('');
    row.innerHTML = `<select>${options}</select><input placeholder="valeur / secondes"><button type="button" class="btn secondary">✕</button>`;
    row.querySelector('button').addEventListener('click', () => row.remove());
    editor.appendChild(row);
  }

  document.getElementById('add-step').addEventListener('click', () => addStepRow());
  document.getElementById('save-scenario').addEventListener('click', async (event) => {
    event.preventDefault();
    const form = document.getElementById('form-scenario');
    const data = Object.fromEntries(new FormData(form).entries());
    const steps = [...document.querySelectorAll('#scenario-steps .step-row')].map((row) => {
      const type = row.querySelector('select').value;
      const value = row.querySelector('input').value;
      const step = { type };
      if (type === 'WAIT' || type === 'WAIT_RANDOM') step.seconds = Number(value || 0);
      else if (value) step.value = value;
      if (type === 'WATCH' && !step.value) step.value = '100%';
      return step;
    });
    const timingType = data.timing_type;
    const n = Number(data.timing_value || 0);
    const timing = timingType === 'minutes' ? { type: 'minutes', minutes: n }
      : timingType === 'hours' ? { type: 'hours', hours: n }
        : timingType === 'window' ? { type: 'window', window_seconds: n || 86400 }
          : timingType === 'random_window' ? { type: 'random_window', min_seconds: 0, max_seconds: n || 3600 }
            : { type: 'fixed', delay_seconds: n };
    await api('/api/scenarios', { method: 'POST', body: { account_id: data.account_id, artist_id: data.artist_id, label: data.label, trigger: data.trigger, timing, steps } });
    document.getElementById('modal-scenario').close();
    await refresh();
  });

  document.getElementById('record-scenario').addEventListener('click', async () => {
    const events = [
      { type: 'OPEN_PROFILE' },
      { type: 'OPEN_POST' },
      { type: 'WATCH', value: '100%' },
      { type: 'WAIT', seconds: 5 },
    ];
    const data = await api('/api/recorder', { method: 'POST', body: { events } });
    const editor = document.getElementById('scenario-steps');
    editor.innerHTML = '';
    (data.steps || []).forEach((step) => {
      addStepRow(step.type);
      const row = editor.lastElementChild;
      row.querySelector('input').value = step.seconds ?? step.value ?? '';
    });
    toast('Parcours abstrait importé (OPEN_PROFILE → OPEN_POST → WATCH → WAIT)');
  });

  function openPublish(accountId) {
    const account = state.accounts.find((a) => a.id === accountId);
    document.querySelector('#form-publish [name=account_id]').value = accountId;
    document.querySelector('#form-publish [name=account_label]').value = account?.username || account?.label || '';
    state.media = [];
    document.getElementById('media-previews').innerHTML = '';
    document.getElementById('modal-publish').showModal();
  }

  const dropzone = document.getElementById('dropzone');
  const mediaInput = document.getElementById('media-input');
  dropzone.addEventListener('click', () => mediaInput.click());
  dropzone.addEventListener('dragover', (e) => e.preventDefault());
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadFiles(e.dataTransfer.files);
  });
  mediaInput.addEventListener('change', () => uploadFiles(mediaInput.files));

  async function uploadFiles(files) {
    for (const file of files) {
      const body = new FormData();
      body.append('file', file);
      const uploaded = await api('/api/uploads', { method: 'POST', body });
      state.media.push(uploaded);
      const figure = document.createElement('figure');
      figure.innerHTML = (uploaded.kind === 'video' ? `<video src="/uploads-preview/${uploaded.filename}"></video>` : `<img alt="" src="">`) + '<button type="button">×</button>';
      if (uploaded.kind !== 'video') {
        figure.querySelector('img').src = URL.createObjectURL(file);
      }
      figure.querySelector('button').addEventListener('click', () => {
        state.media = state.media.filter((m) => m.filename !== uploaded.filename);
        figure.remove();
      });
      document.getElementById('media-previews').appendChild(figure);
    }
  }

  const caption = document.querySelector('#form-publish [name=caption]');
  const mentionBox = document.getElementById('mention-box');
  caption.addEventListener('input', async () => {
    const at = caption.value.lastIndexOf('@');
    if (at < 0) {
      mentionBox.hidden = true;
      return;
    }
    const q = caption.value.slice(at + 1).toLowerCase();
    const names = await api('/api/mentions');
    const items = (names.items || names).filter((n) => String(n).toLowerCase().includes(q)).slice(0, 8);
    mentionBox.hidden = items.length === 0;
    mentionBox.innerHTML = items.map((n) => `<button type="button" class="linkish" data-mention="${escapeHtml(n)}">${escapeHtml(n)}</button>`).join(' ');
  });
  mentionBox.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-mention]');
    if (!btn) return;
    const at = caption.value.lastIndexOf('@');
    caption.value = caption.value.slice(0, at) + btn.dataset.mention + ' ';
    mentionBox.hidden = true;
  });

  document.getElementById('save-publish').addEventListener('click', async (event) => {
    event.preventDefault();
    const form = document.getElementById('form-publish');
    const data = Object.fromEntries(new FormData(form).entries());
    const mentions = [...data.caption.matchAll(/@[a-z0-9._]+/gi)].map((m) => m[0]);
    await api('/api/publications', {
      method: 'POST',
      body: {
        account_id: data.account_id,
        caption: data.caption,
        mentions,
        media: state.media,
        mode: data.mode,
        scheduled_at: data.scheduled_at ? new Date(data.scheduled_at).toISOString() : null,
      },
    });
    document.getElementById('modal-publish').close();
    toast('Publication enregistrée');
    await refresh();
  });

  function openSettings(accountId) {
    const account = state.accounts.find((a) => a.id === accountId);
    const form = document.getElementById('form-settings');
    form.account_id.value = accountId;
    form.label.value = account?.label || '';
    form.username.value = account?.username || '';
    form.group.value = account?.group || 'AUTRES';
    document.getElementById('modal-settings').showModal();
  }

  document.getElementById('save-settings').addEventListener('click', async (event) => {
    event.preventDefault();
    const form = document.getElementById('form-settings');
    const data = Object.fromEntries(new FormData(form).entries());
    await api('/api/accounts/' + data.account_id, { method: 'PUT', body: data });
    document.getElementById('modal-settings').close();
    await refresh();
  });

  simBtn.addEventListener('click', async () => {
    await api('/api/simulate', { method: 'POST', body: { action: 'NEW_POST' } });
    toast('NEW_POST simulé');
    await refresh();
  });

  [search, filterGroup, filterStatus, sortBy, filterFav].forEach((el) => el.addEventListener('input', render));
  filterFav.addEventListener('change', render);

  refresh().catch((err) => toast(err.message, 'error'));
  setInterval(() => refresh().catch(() => {}), 2000);
})();

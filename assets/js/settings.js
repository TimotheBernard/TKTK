async function loadSettings() {
    const data = await TM.api('/api/settings.php');
    const s = data.settings;
    const form = document.getElementById('settings-form');
    form.scheduler_enabled.checked = !!s.scheduler_enabled;
    form.selenium_enabled.checked = !!s.selenium_enabled;
    form.watch_enabled.checked = !!s.watch_enabled;
    form.dev_mode.checked = !!s.dev_mode;
    form.verbose_logs.checked = !!s.verbose_logs;
    form.python_path.value = s.python_path || '';
    form.chrome_path.value = s.chrome_path || '';
    form.watch_interval_seconds.value = s.watch_interval_seconds;
    form.browser_timeout_seconds.value = s.browser_timeout_seconds;
    form.browser_warmup_seconds.value = s.browser_warmup_seconds;
}

document.getElementById('settings-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    try {
        await TM.api('/api/settings.php', {
            method: 'PUT',
            body: {
                scheduler_enabled: f.scheduler_enabled.checked,
                selenium_enabled: f.selenium_enabled.checked,
                watch_enabled: f.watch_enabled.checked,
                dev_mode: f.dev_mode.checked,
                verbose_logs: f.verbose_logs.checked,
                python_path: f.python_path.value,
                chrome_path: f.chrome_path.value,
                watch_interval_seconds: Number(f.watch_interval_seconds.value),
                browser_timeout_seconds: Number(f.browser_timeout_seconds.value),
                browser_warmup_seconds: Number(f.browser_warmup_seconds.value),
            },
        });
        TM.toast('Paramètres enregistrés');
    } catch (err) {
        TM.toast(err.message);
    }
});
loadSettings().catch((e) => TM.toast(e.message));

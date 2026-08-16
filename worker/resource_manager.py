from __future__ import annotations

import os
from pathlib import Path

from worker.json_store import JsonStore, _now


def snapshot(store: JsonStore, active_browsers: int = 0) -> dict:
    cpu = _cpu()
    ram = _ram()
    accounts = store.read("accounts").get("items") or []
    tasks = store.read("tasks").get("items") or []
    connected = sum(1 for a in accounts if (a.get("connection_status") or a.get("session_status")) == "connected")
    pending = sum(1 for t in tasks if t.get("status") in ("pending", "ready", "queued"))
    running = sum(1 for t in tasks if t.get("status") == "running")
    payload = {
        "accounts_registered": len(accounts),
        "accounts_connected": connected,
        "browsers_active": active_browsers,
        "tasks_pending": pending,
        "tasks_running": running,
        "cpu_percent": cpu,
        "ram_percent": ram,
        "updated_at": _now(),
    }
    store.mutate_doc("resource_state", lambda doc: {**doc, **payload})
    store.mutate_doc("worker_state", lambda doc: {**doc, "active_browsers": active_browsers})
    return payload


def _cpu() -> float:
    try:
        load1 = os.getloadavg()[0]
        cores = os.cpu_count() or 1
        return round(min(100.0, (load1 / cores) * 100.0), 1)
    except OSError:
        return 0.0


def _ram() -> float:
    path = Path("/proc/meminfo")
    if not path.is_file():
        return 0.0
    info = {}
    for line in path.read_text().splitlines():
        parts = line.split()
        if len(parts) >= 2:
            info[parts[0].rstrip(":")] = int(parts[1])
    total = info.get("MemTotal") or 0
    avail = info.get("MemAvailable") or 0
    if total <= 0:
        return 0.0
    return round(((total - avail) / total) * 100.0, 1)

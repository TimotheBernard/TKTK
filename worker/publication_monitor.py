from __future__ import annotations

import json
import os
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from worker.capability_resolver import resolve
from worker.json_store import JsonStore, _now


def _php(script: str, stdin: str | None = None) -> dict:
    cmd = ["php", str(ROOT / "bin" / script)]
    proc = subprocess.run(cmd, input=stdin, capture_output=True, text=True)
    try:
        return json.loads(proc.stdout or "{}")
    except json.JSONDecodeError:
        return {"success": False, "raw": proc.stdout, "stderr": proc.stderr}


def watch_once(store: JsonStore | None = None) -> dict:
    store = store or JsonStore()
    settings = store.read("settings")
    if not settings.get("watch_enabled", True):
        return {"skipped": True, "reason": "watch_disabled"}
    targets = _php("watch-targets.php")
    artists = ((targets.get("data") or {}).get("artists")) or []
    ingested = 0
    channel = resolve("LIST_PROFILE_POSTS", settings)
    store.mutate_doc(
        "watcher_state",
        lambda doc: {**doc, "status": "running", "last_run_at": _now(), "artists": len(artists), "channel": channel},
    )
    if channel == "api":
        store.mutate_doc("watcher_state", lambda doc: {**doc, "status": "idle", "note": "official API list not configured"})
        return {"skipped": False, "ingested": 0, "artists": len(artists), "channel": "api"}
    if channel == "simulate":
        store.mutate_doc("watcher_state", lambda doc: {**doc, "status": "idle"})
        return {"skipped": False, "ingested": 0, "artists": len(artists), "channel": "simulate"}

    python = settings.get("python_path") or sys.executable
    for artist in artists:
        username = artist.get("username") or ""
        cmd = [
            python,
            str(ROOT / "browser" / "runner.py"),
            "--profile",
            settings.get("watcher_profile") or "_watcher",
            "--action",
            "list_profile_posts",
            "--username",
            username,
            "--limit",
            str(settings.get("watch_posts_limit") or 8),
        ]
        try:
            proc = subprocess.run(cmd, capture_output=True, text=True, timeout=int(settings.get("browser_timeout_seconds") or 30) + 20)
            payload = json.loads(proc.stdout.strip() or "{}")
        except Exception as exc:
            store.mutate_doc("watcher_state", lambda doc, err=str(exc): {**doc, "last_error": err})
            continue
        for post in payload.get("posts") or []:
            ingest = {
                "artist_id": artist.get("id"),
                "username": username,
                "url": post.get("url"),
                "video_id": post.get("video_id"),
                "caption": post.get("caption") or "",
                "source": "monitor",
                "provider": "publication_monitor",
                "detected_at": _now(),
            }
            result = _php("ingest.php", json.dumps(ingest))
            if result.get("success") and not ((result.get("data") or {}).get("duplicate")):
                ingested += 1
    store.mutate_doc("watcher_state", lambda doc: {**doc, "status": "idle", "ingested": ingested, "last_error": None})
    return {"skipped": False, "ingested": ingested, "artists": len(artists), "channel": channel}

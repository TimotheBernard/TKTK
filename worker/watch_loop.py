from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from worker.json_store import JsonStore, _now
from worker.worker_state import watcher_state


def _php(script: str, stdin: str | None = None) -> dict:
    cmd = ["php", str(ROOT / "bin" / script)]
    proc = subprocess.run(cmd, input=stdin, capture_output=True, text=True)
    if proc.returncode != 0:
        raise RuntimeError(proc.stderr or proc.stdout or "php failed")
    return json.loads(proc.stdout)


def watch_once(store: JsonStore | None = None) -> dict:
    store = store or JsonStore()
    settings = store.read("settings")
    if not settings.get("watch_enabled", True) or not settings.get("selenium_enabled", True):
        watcher_state(store, status="idle")
        return {"skipped": True}

    targets = _php("watch-targets.php")
    artists = ((targets.get("data") or {}).get("artists")) or []
    python = settings.get("python_path") or sys.executable
    limit = int(settings.get("watch_posts_limit") or 8)
    ingested = 0
    errors = []

    watcher_state(store, status="running", last_cycle_at=_now())
    for artist in artists:
        cmd = [
            python,
            str(ROOT / "selenium" / "runner.py"),
            "--profile",
            settings.get("watcher_profile") or "_watcher",
            "--action",
            "list_profile_posts",
            "--username",
            artist.get("username") or "",
            "--limit",
            str(limit),
        ]
        try:
            proc = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=int(settings.get("browser_timeout_seconds") or 30) + 20,
            )
            payload = json.loads(proc.stdout.strip() or "{}")
        except Exception as exc:
            errors.append({"artist_id": artist.get("id"), "error": str(exc)})
            continue
        if not payload.get("success"):
            errors.append({"artist_id": artist.get("id"), "error": payload.get("error")})
            if (payload.get("error") or {}).get("code") in ("SESSION_EXPIRED",):
                watcher_state(store, session_status="expired", last_error="SESSION_EXPIRED")
            continue
        for post in payload.get("posts") or []:
            ingest = {
                "artist_id": artist.get("id"),
                "username": artist.get("username"),
                "url": post.get("url"),
                "video_id": post.get("video_id") or "",
                "caption": post.get("caption") or "",
                "published_at": post.get("published_at") or _now(),
                "detected_at": _now(),
                "source": "selenium_watch",
                "provider": "selenium_watch",
            }
            try:
                result = _php("ingest.php", json.dumps(ingest))
                if result.get("success") and not ((result.get("data") or {}).get("duplicate")):
                    ingested += 1
            except Exception as exc:
                errors.append({"artist_id": artist.get("id"), "error": str(exc)})
    watcher_state(
        store,
        status="idle",
        last_cycle_at=_now(),
        last_error=errors[0]["error"] if errors else None,
        session_status="connected" if not errors else store.read("watcher_state").get("session_status"),
    )
    return {"ingested": ingested, "errors": errors, "artists": len(artists)}

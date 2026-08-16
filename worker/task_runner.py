from __future__ import annotations

import json
import secrets
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from worker.json_store import JsonStore, _now
from worker.queue_manager import QueueManager


def _append_history(store: JsonStore, entry: dict) -> None:
    def mutator(items: list[dict]) -> list[dict]:
        items.append(
            {
                "id": "history_" + secrets.token_hex(4),
                "created_at": _now(),
                "status": entry.get("status", "success"),
                "duration_ms": entry.get("duration_ms", 0),
                "details": entry.get("details") or {},
                **{k: entry.get(k) for k in ("task_id", "account_id", "post_id", "event")},
            }
        )
        return items

    store.mutate_items("history", mutator)


def _update_account(store: JsonStore, account_id: str, changes: dict) -> None:
    store.update_item("accounts", account_id, changes)


def run_task(task: dict, settings: dict) -> dict:
    store = JsonStore()
    account_id = task["account_id"]
    post_id = task.get("post_id")
    posts = {p.get("id"): p for p in (store.read("posts").get("items") or [])}
    post = posts.get(post_id) or {}
    url = post.get("url") or ""

    _update_account(store, account_id, {"status": "busy", "last_activity": _now()})
    _append_history(store, {"task_id": task["id"], "account_id": account_id, "post_id": post_id, "event": "task_started"})

    if not settings.get("selenium_enabled", True):
        _append_history(
            store,
            {
                "task_id": task["id"],
                "account_id": account_id,
                "post_id": post_id,
                "event": "post_opened",
                "details": {"simulated": True, "url": url},
            },
        )
        _update_account(store, account_id, {"status": "idle"})
        return {"ok": True, "simulated": True}

    cmd = [
        settings.get("python_path") or sys.executable,
        str(ROOT / "selenium" / "runner.py"),
        "--account-id",
        account_id,
        "--action",
        "open_post",
        "--url",
        url,
    ]
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=int(settings.get("browser_timeout_seconds") or 30) + 15)
    except subprocess.TimeoutExpired:
        _update_account(store, account_id, {"status": "error", "error_code": "ACTION_TIMEOUT"})
        return {"ok": False, "code": "ACTION_TIMEOUT", "message": "runner timeout"}
    except Exception as exc:
        _update_account(store, account_id, {"status": "error", "error_code": "BROWSER_START_FAILED"})
        return {"ok": False, "code": "BROWSER_START_FAILED", "message": str(exc)}

    payload = {}
    try:
        payload = json.loads(proc.stdout.strip() or "{}")
    except json.JSONDecodeError:
        payload = {"raw": proc.stdout, "stderr": proc.stderr}

    if proc.returncode != 0 or not payload.get("success"):
        code = (payload.get("error") or {}).get("code") or "UNKNOWN_ERROR"
        message = (payload.get("error") or {}).get("message") or proc.stderr or "runner failed"
        status = "session_expired" if code == "SESSION_EXPIRED" else "error"
        _update_account(store, account_id, {"status": status, "error_code": code, "error_message": message})
        _append_history(
            store,
            {
                "task_id": task["id"],
                "account_id": account_id,
                "post_id": post_id,
                "event": "task_failed",
                "status": "error",
                "details": payload,
            },
        )
        return {"ok": False, "code": code, "message": message}

    session_status = payload.get("session_status") or "connected"
    _update_account(
        store,
        account_id,
        {
            "status": "idle",
            "session_status": session_status,
            "session_checked_at": _now(),
            "error_code": None,
            "error_message": None,
        },
    )
    _append_history(
        store,
        {
            "task_id": task["id"],
            "account_id": account_id,
            "post_id": post_id,
            "event": "post_opened",
            "details": {"url": url},
        },
    )
    return {"ok": True, "payload": payload}


if __name__ == "__main__":
    task_id = sys.argv[1]
    store = JsonStore()
    queue = QueueManager(store)
    task = next((t for t in queue.all_tasks() if t.get("id") == task_id), None)
    if not task:
        raise SystemExit("TASK_NOT_FOUND")
    settings = store.read("settings")
    print(json.dumps(run_task(task, settings)))

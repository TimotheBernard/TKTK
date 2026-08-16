from __future__ import annotations

import json
import os
import random
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from worker.capability_resolver import resolve
from worker.json_store import JsonStore, _now
from worker.queue_manager import QueueManager


def _append_history(store: JsonStore, entry: dict) -> None:
    def mutator(items: list[dict]) -> list[dict]:
        items.append(
            {
                "id": "history_" + os.urandom(4).hex(),
                "created_at": _now(),
                "status": entry.get("status", "success"),
                "duration_ms": entry.get("duration_ms", 0),
                "details": entry.get("details") or {},
                **{k: entry.get(k) for k in ("task_id", "account_id", "post_id", "publication_id", "scenario_id", "event")},
            }
        )
        return items

    store.mutate_items("history", mutator)


def _update_account(store: JsonStore, account_id: str, changes: dict) -> None:
    store.update_item("accounts", account_id, changes)


def _run_browser(settings: dict, flags: list[str]) -> dict:
    cmd = [settings.get("python_path") or sys.executable, str(ROOT / "browser" / "runner.py"), *flags]
    proc = subprocess.run(
        cmd,
        capture_output=True,
        text=True,
        timeout=int(settings.get("browser_timeout_seconds") or 30) + 15,
        env={**os.environ, "TKTK_DATA_PATH": str(JsonStore().base), "TKTK_PROFILES_PATH": os.environ.get("TKTK_PROFILES_PATH", str(ROOT / "profiles"))},
    )
    payload = {}
    try:
        payload = json.loads(proc.stdout.strip() or "{}")
    except json.JSONDecodeError:
        payload = {"raw": proc.stdout, "stderr": proc.stderr, "success": False}
    payload["exit_code"] = proc.returncode
    return payload


def execute_step(step: dict, task: dict, settings: dict, store: JsonStore) -> dict:
    action = str(step.get("type") or "")
    channel = resolve(action, settings)
    account_id = task.get("account_id") or ""
    posts = {p.get("id"): p for p in (store.read("posts").get("items") or [])}
    post = posts.get(task.get("post_id")) or {}
    publications = {p.get("id"): p for p in (store.read("publications").get("items") or [])}
    publication = publications.get(task.get("publication_id")) or {}

    if channel == "local":
        seconds = float(step.get("seconds") or 0)
        if action == "WAIT_RANDOM":
            seconds = random.uniform(0, seconds or 1)
        if seconds > 0:
            time.sleep(min(seconds, 3600))
        return {"ok": True, "channel": "local"}

    if channel == "user":
        _append_history(store, {"task_id": task.get("id"), "account_id": account_id, "event": "USER_CONFIRMATION", "details": {"pending": True}})
        return {"ok": True, "channel": "user", "deferred": True}

    if channel == "api":
        if action == "PUBLISH_POST":
            store.update_item("publications", publication.get("id") or "", {"status": "sent", "sent_at": _now()})
            _append_history(store, {"account_id": account_id, "publication_id": publication.get("id"), "event": "PUBLICATION_SENT", "details": {"channel": "api"}})
            return {"ok": True, "channel": "api"}
        return {"ok": True, "channel": "api", "note": "official API stub — no public posts endpoint configured"}

    if channel == "simulate":
        event = "PUBLICATION_SENT" if action == "PUBLISH_POST" else "SCENARIO_STARTED"
        if action == "PUBLISH_POST":
            store.update_item("publications", publication.get("id") or "", {"status": "sent", "sent_at": _now()})
            event = "PUBLICATION_SENT"
        elif action == "OPEN_POST":
            event = "post_opened"
        _append_history(store, {"task_id": task.get("id"), "account_id": account_id, "post_id": task.get("post_id"), "event": event, "details": {"simulated": True, "action": action}})
        return {"ok": True, "channel": "simulate"}

    flags = ["--account-id", account_id, "--action", action.lower()]
    if action == "OPEN_POST":
        flags = ["--account-id", account_id, "--action", "open_post", "--url", post.get("url") or ""]
    elif action == "OPEN_PROFILE":
        flags += ["--username", post.get("username") or ""]
    elif action == "OPEN_URL":
        flags = ["--account-id", account_id, "--action", "open_url", "--url", step.get("url") or post.get("url") or ""]
    elif action == "WATCH":
        flags = ["--account-id", account_id, "--action", "watch", "--url", post.get("url") or "", "--value", str(step.get("value") or "100%")]
    elif action == "PUBLISH_POST":
        flags = ["--account-id", account_id, "--action", "publish_post", "--caption", publication.get("caption") or ""]
    payload = _run_browser(settings, flags)
    if payload.get("exit_code") not in (0, None) or payload.get("success") is False:
        code = ((payload.get("error") or {}).get("code")) or "UNKNOWN_ERROR"
        return {"ok": False, "code": code, "message": (payload.get("error") or {}).get("message") or "browser failed", "payload": payload}
    return {"ok": True, "channel": "browser", "payload": payload}


def run_task(task: dict, settings: dict) -> dict:
    store = JsonStore()
    account_id = task["account_id"]
    _update_account(store, account_id, {"runtime_status": "starting", "status": "busy", "current_task_id": task.get("id"), "last_activity": _now()})
    kind = task.get("kind") or "scenario"
    event_start = "SCENARIO_STARTED" if kind == "scenario" else "PUBLICATION_SCHEDULED"
    _append_history(store, {"task_id": task["id"], "account_id": account_id, "post_id": task.get("post_id"), "scenario_id": task.get("scenario_id"), "publication_id": task.get("publication_id"), "event": event_start})
    _update_account(store, account_id, {"runtime_status": "busy", "status": "busy"})

    steps = list(task.get("steps") or [{"type": "OPEN_POST"}])
    try:
        for step in steps:
            result = execute_step(step, task, settings, store)
            if not result.get("ok"):
                _update_account(store, account_id, {"runtime_status": "error", "status": "error", "error_code": result.get("code"), "current_task_id": None})
                _append_history(store, {"task_id": task["id"], "account_id": account_id, "event": "SCENARIO_FAILED" if kind == "scenario" else "PUBLICATION_FAILED", "status": "error", "details": result})
                return result
        _update_account(store, account_id, {"runtime_status": "idle", "status": "idle", "current_task_id": None, "error_code": None, "error_message": None})
        _append_history(store, {"task_id": task["id"], "account_id": account_id, "event": "SCENARIO_COMPLETED" if kind == "scenario" else "PUBLICATION_SENT"})
        if kind == "publication" and task.get("publication_id"):
            store.update_item("publications", task["publication_id"], {"status": "sent", "sent_at": _now()})
        return {"ok": True}
    except Exception as exc:
        _update_account(store, account_id, {"runtime_status": "error", "status": "error", "error_code": "UNKNOWN_ERROR", "current_task_id": None})
        return {"ok": False, "code": "UNKNOWN_ERROR", "message": str(exc)}

#!/usr/bin/env python3
from __future__ import annotations

import os
import signal
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from worker.json_store import JsonStore, _now
from worker.publication_monitor import watch_once
from worker.queue_manager import QueueManager
from worker.resource_manager import snapshot
from worker.task_dispatcher import run_task
from worker.worker_state import heartbeat

STOP = False
ACTIVE_BROWSERS = 0


def _log(message: str) -> None:
    line = f"{_now()} {message}\n"
    log_dir = Path(os.environ.get("TKTK_LOG_PATH") or (ROOT / "logs"))
    log_dir.mkdir(parents=True, exist_ok=True)
    with (log_dir / "scheduler.log").open("a", encoding="utf-8") as fh:
        fh.write(line)
    print(line, end="", flush=True)


def _handle_stop(signum, frame) -> None:
    global STOP
    STOP = True
    _log(f"SIGNAL {signum}")


def _pid_lock(path: Path):
    path.parent.mkdir(parents=True, exist_ok=True)
    handle = open(path, "a+")
    try:
        import fcntl

        fcntl.flock(handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError as exc:
        handle.close()
        raise SystemExit(f"another worker holds {path}: {exc}") from exc
    handle.seek(0)
    handle.truncate()
    handle.write(str(os.getpid()))
    handle.flush()
    return handle


def _sleep_until(next_due: str | None, poll_s: float) -> None:
    if not next_due:
        time.sleep(poll_s)
        return
    try:
        due = datetime.strptime(next_due[:23] + "Z" if not next_due.endswith("Z") else next_due[:24], "%Y-%m-%dT%H:%M:%S.%fZ")
        due = due.replace(tzinfo=timezone.utc)
        wait = max(0.0, (due - datetime.now(timezone.utc)).total_seconds())
        time.sleep(min(poll_s, wait if wait > 0 else poll_s))
    except ValueError:
        time.sleep(poll_s)


def execute_one(task: dict, settings: dict, queue: QueueManager) -> None:
    global ACTIVE_BROWSERS
    claimed = queue.claim(task["id"])
    if claimed is None:
        return
    _log(f"TASK_STARTED {claimed['account_id']} {claimed['id']}")
    ACTIVE_BROWSERS += 1
    try:
        result = run_task(claimed, settings)
        if result.get("ok"):
            queue.finish(claimed["id"], "completed")
            _log(f"TASK_COMPLETED {claimed['account_id']} {claimed['id']}")
        else:
            queue.finish(claimed["id"], "failed", result.get("code"), result.get("message"))
            _log(f"TASK_FAILED {claimed['account_id']} {result.get('code')}")
    except Exception as exc:
        queue.finish(claimed["id"], "failed", "UNKNOWN_ERROR", str(exc))
        _log(f"TASK_FAILED {claimed.get('account_id')} UNKNOWN_ERROR {exc}")
    finally:
        ACTIVE_BROWSERS = max(0, ACTIVE_BROWSERS - 1)


def main() -> int:
    signal.signal(signal.SIGTERM, _handle_stop)
    signal.signal(signal.SIGINT, _handle_stop)
    lock = _pid_lock(Path(os.environ.get("TKTK_DATA_PATH") or (ROOT / "data")) / "worker.pid")
    store = JsonStore()
    queue = QueueManager(store)
    settings = store.read("settings")
    max_attempts = int(settings.get("max_attempts") or 5)
    retried = queue.reconcile_orphans(max_attempts)
    _log(f"WORKER_START pid={os.getpid()} retried={len(retried)}")
    heartbeat(store, pid=os.getpid(), started_at=_now(), status="running", last_error=None)

    last_watch = 0.0
    poll_ms = int(settings.get("worker_poll_ms") or 100) / 1000.0
    watch_interval = int(settings.get("watch_interval_seconds") or 15)

    while not STOP:
        settings = store.read("settings")
        snapshot(store, ACTIVE_BROWSERS)
        if not settings.get("scheduler_enabled", True):
            heartbeat(store, status="idle", next_due_at=None)
            time.sleep(0.5)
            continue

        now = _now()
        queue.mark_ready_if_due(now)
        due = queue.due_tasks(now)
        if due:
            _log(f"DISPATCH count={len(due)} at={now}")
            with ThreadPoolExecutor(max_workers=max(1, len(due))) as pool:
                futures = [pool.submit(execute_one, task, settings, queue) for task in due]
                for fut in as_completed(futures):
                    fut.result()
        else:
            next_due = queue.next_due_at()
            heartbeat(store, status="running", next_due_at=next_due, active_browsers=ACTIVE_BROWSERS)
            _sleep_until(next_due, poll_ms)

        if time.time() - last_watch >= watch_interval:
            last_watch = time.time()
            try:
                result = watch_once(store)
                if not result.get("skipped"):
                    _log(f"WATCH ingested={result.get('ingested')} artists={result.get('artists')} channel={result.get('channel')}")
            except Exception as exc:
                _log(f"WATCH_ERROR {exc}")

    heartbeat(store, status="stopped")
    _log("WORKER_STOP")
    lock.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

#!/usr/bin/env python3
from __future__ import annotations

import os
import signal
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from worker.json_store import JsonStore, _now
from worker.queue_manager import QueueManager
from worker.task_runner import run_task
from worker.watch_loop import watch_once
from worker.worker_state import heartbeat

STOP = False


def _log(message: str) -> None:
    line = f"{_now()} {message}\n"
    log_path = ROOT / "logs" / "scheduler.log"
    log_path.parent.mkdir(parents=True, exist_ok=True)
    with log_path.open("a", encoding="utf-8") as fh:
        fh.write(line)
    print(line, end="", flush=True)


def _handle_stop(signum, frame) -> None:
    global STOP
    STOP = True
    _log(f"SIGNAL {signum}")


def execute_one(task: dict, settings: dict, queue: QueueManager) -> None:
    claimed = queue.claim(task["id"])
    if claimed is None:
        return
    _log(f"TASK_STARTED {claimed['account_id']} {claimed['id']}")
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


def main() -> int:
    signal.signal(signal.SIGTERM, _handle_stop)
    signal.signal(signal.SIGINT, _handle_stop)
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
            heartbeat(store, status="running", next_due_at=next_due)
            time.sleep(poll_ms)

        if time.time() - last_watch >= watch_interval:
            last_watch = time.time()
            try:
                result = watch_once(store)
                if not result.get("skipped"):
                    _log(f"WATCH ingested={result.get('ingested')} artists={result.get('artists')}")
            except Exception as exc:
                _log(f"WATCH_ERROR {exc}")

    heartbeat(store, status="stopped")
    _log("WORKER_STOP")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

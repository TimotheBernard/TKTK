#!/usr/bin/env python3
from __future__ import annotations

import json
import shutil
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT))

from worker.json_store import JsonStore
from worker.queue_manager import QueueManager


def main() -> int:
    tmp = Path(tempfile.mkdtemp(prefix="tktk-py-"))
    try:
        for src in (ROOT / "data").glob("*.json"):
            shutil.copy(src, tmp / src.name)
        store = JsonStore(tmp)
        queue = QueueManager(store)
        store.write(
            "tasks",
            {
                "version": 1,
                "items": [
                    {
                        "id": "task_a",
                        "account_id": "acc_a",
                        "status": "running",
                        "attempts": 1,
                        "scheduled_at": "2026-08-16T18:00:05.000Z",
                    },
                    {
                        "id": "task_b",
                        "account_id": "acc_b",
                        "status": "pending",
                        "attempts": 0,
                        "scheduled_at": "2026-08-16T18:00:05.000Z",
                    },
                    {
                        "id": "task_c",
                        "account_id": "acc_c",
                        "status": "pending",
                        "attempts": 0,
                        "scheduled_at": "2026-08-16T18:00:05.000Z",
                    },
                ],
            },
        )
        retried = queue.reconcile_orphans(max_attempts=5)
        assert len(retried) == 1, retried
        assert retried[0]["status"] == "ready"
        due = queue.due_tasks("2026-08-16T18:00:05.000Z")
        ids = sorted(t["id"] for t in due)
        assert ids == ["task_a", "task_b", "task_c"], ids
        stamps = {t["scheduled_at"] for t in due}
        assert stamps == {"2026-08-16T18:00:05.000Z"}
        print("OK python scheduler tests")
        return 0
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    raise SystemExit(main())

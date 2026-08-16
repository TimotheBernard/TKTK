from __future__ import annotations

from worker.json_store import JsonStore, _now


class QueueManager:
    def __init__(self, store: JsonStore | None = None) -> None:
        self.store = store or JsonStore()

    def all_tasks(self) -> list[dict]:
        return list(self.store.read("tasks").get("items") or [])

    def due_tasks(self, now_iso: str) -> list[dict]:
        due = []
        for task in self.all_tasks():
            status = task.get("status")
            if status not in ("pending", "ready", "queued"):
                continue
            scheduled = task.get("scheduled_at") or ""
            if scheduled <= now_iso:
                due.append(task)
        return due

    def mark_ready_if_due(self, now_iso: str) -> None:
        def mutator(items: list[dict]) -> list[dict]:
            for item in items:
                if item.get("status") in ("pending", "queued") and (item.get("scheduled_at") or "") <= now_iso:
                    item["status"] = "ready"
            return items

        self.store.mutate_items("tasks", mutator)

    def claim(self, task_id: str) -> dict | None:
        claimed: dict | None = None

        def mutator(items: list[dict]) -> list[dict]:
            nonlocal claimed
            for item in items:
                if item.get("id") != task_id:
                    continue
                if item.get("status") not in ("pending", "ready", "queued"):
                    return items
                item["status"] = "running"
                item["started_at"] = _now()
                item["attempts"] = int(item.get("attempts") or 0) + 1
                claimed = dict(item)
            return items

        self.store.mutate_items("tasks", mutator)
        return claimed

    def finish(self, task_id: str, status: str, error_code: str | None = None, last_error: str | None = None) -> None:
        self.store.update_item(
            "tasks",
            task_id,
            {
                "status": status,
                "completed_at": _now(),
                "error_code": error_code,
                "last_error": last_error,
            },
        )

    def next_due_at(self) -> str | None:
        upcoming = [
            t.get("scheduled_at")
            for t in self.all_tasks()
            if t.get("status") in ("pending", "ready", "queued") and t.get("scheduled_at")
        ]
        return min(upcoming) if upcoming else None

    def reconcile_orphans(self, max_attempts: int) -> list[dict]:
        retried: list[dict] = []

        def mutator(items: list[dict]) -> list[dict]:
            for item in items:
                if item.get("status") != "running":
                    continue
                attempts = int(item.get("attempts") or 0)
                if attempts >= max_attempts:
                    item["status"] = "failed"
                    item["error_code"] = "WORKER_STALE_TASK"
                    item["last_error"] = "max attempts after worker restart"
                    item["completed_at"] = _now()
                else:
                    item["status"] = "ready"
                    item["started_at"] = None
                    item["error_code"] = "WORKER_RESTART_RETRY"
                    item["last_error"] = "auto retry after worker restart"
                    retried.append(dict(item))
            return items

        self.store.mutate_items("tasks", mutator)
        return retried

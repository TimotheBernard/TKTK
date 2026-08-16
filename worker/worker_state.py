from __future__ import annotations

from worker.json_store import JsonStore, _now


def heartbeat(store: JsonStore, **changes) -> None:
    store.mutate_doc("worker_state", lambda doc: {**doc, **changes, "heartbeat_at": _now()})

from __future__ import annotations

from worker.json_store import JsonStore, _now


def emit(store: JsonStore, event_type: str, payload: dict) -> dict:
    event = {
        "id": "event_" + _now().replace(":", "").replace(".", ""),
        "type": event_type,
        "event": event_type,
        "payload": payload,
        "created_at": _now(),
        "processed": False,
    }

    def mutator(items: list[dict]) -> list[dict]:
        items.append(event)
        return items

    store.mutate_items("events", mutator)
    return event

from worker.json_store import JsonStore, _now


def heartbeat(store: JsonStore, **fields) -> None:
    def mutator(doc: dict) -> dict:
        next_doc = dict(doc)
        next_doc.update(fields)
        next_doc["heartbeat_at"] = _now()
        return next_doc

    store.mutate_doc("worker_state", mutator)


def watcher_state(store: JsonStore, **fields) -> None:
    def mutator(doc: dict) -> dict:
        next_doc = dict(doc)
        next_doc.update(fields)
        return next_doc

    store.mutate_doc("watcher_state", mutator)

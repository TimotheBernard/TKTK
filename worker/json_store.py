from __future__ import annotations

import fcntl
import json
import os
import tempfile
import time
from contextlib import contextmanager
from pathlib import Path
from typing import Any, Callable

ROOT = Path(__file__).resolve().parent.parent
DATA = Path(os.environ.get("TKTK_DATA_PATH") or (ROOT / "data"))


class JsonStore:
    def __init__(self, base: Path | None = None) -> None:
        self.base = Path(base) if base else DATA
        self.base.mkdir(parents=True, exist_ok=True)

    def path(self, name: str) -> Path:
        safe = "".join(ch for ch in name if ch.isalnum() or ch in "_-")
        return self.base / f"{safe}.json"

    @contextmanager
    def lock(self, name: str, exclusive: bool = True):
        path = self.path(name)
        lock_path = Path(str(path) + ".lock")
        lock_path.parent.mkdir(parents=True, exist_ok=True)
        handle = open(lock_path, "a+")
        start = time.time()
        while True:
            try:
                fcntl.flock(handle.fileno(), fcntl.LOCK_EX if exclusive else fcntl.LOCK_SH)
                break
            except BlockingIOError:
                if time.time() - start > 5:
                    handle.close()
                    raise TimeoutError("STORAGE_ERROR")
                time.sleep(0.02)
        try:
            yield path
        finally:
            fcntl.flock(handle.fileno(), fcntl.LOCK_UN)
            handle.close()

    def read(self, name: str) -> dict[str, Any]:
        with self.lock(name, exclusive=False) as path:
            if not path.is_file():
                return {"version": 2, "items": []}
            with path.open("r", encoding="utf-8") as fh:
                return json.load(fh)

    def write(self, name: str, document: dict[str, Any]) -> None:
        with self.lock(name, exclusive=True) as path:
            self._atomic_write(path, document)

    def mutate_items(self, name: str, mutator: Callable[[list[dict[str, Any]]], list[dict[str, Any]]]) -> list[dict[str, Any]]:
        with self.lock(name, exclusive=True) as path:
            doc = {"version": 2, "items": []}
            if path.is_file():
                with path.open("r", encoding="utf-8") as fh:
                    doc = json.load(fh)
            items = mutator(list(doc.get("items") or []))
            doc["items"] = items
            doc["updated_at"] = _now()
            self._atomic_write(path, doc)
            return items

    def update_item(self, name: str, item_id: str, changes: dict[str, Any]) -> dict[str, Any] | None:
        found: dict[str, Any] | None = None

        def mutator(items: list[dict[str, Any]]) -> list[dict[str, Any]]:
            nonlocal found
            next_items = []
            for item in items:
                if item.get("id") == item_id:
                    item = {**item, **changes}
                    found = item
                next_items.append(item)
            return next_items

        self.mutate_items(name, mutator)
        return found

    def mutate_doc(self, name: str, mutator: Callable[[dict[str, Any]], dict[str, Any]]) -> dict[str, Any]:
        with self.lock(name, exclusive=True) as path:
            doc: dict[str, Any] = {}
            if path.is_file():
                with path.open("r", encoding="utf-8") as fh:
                    doc = json.load(fh)
            next_doc = mutator(doc)
            next_doc["updated_at"] = _now()
            self._atomic_write(path, next_doc)
            return next_doc

    def _atomic_write(self, path: Path, document: dict[str, Any]) -> None:
        fd, tmp = tempfile.mkstemp(prefix=path.name + ".", suffix=".tmp", dir=str(path.parent))
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as fh:
                json.dump(document, fh, ensure_ascii=False, indent=2)
                fh.write("\n")
            os.replace(tmp, path)
        except Exception:
            if os.path.exists(tmp):
                os.unlink(tmp)
            raise


def _now() -> str:
    return time.strftime("%Y-%m-%dT%H:%M:%S", time.gmtime()) + f".{int((time.time() % 1) * 1000):03d}Z"

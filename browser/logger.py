from __future__ import annotations

import json
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
LOG = Path(os.environ.get("TKTK_LOG_PATH") or (ROOT / "logs"))


def log(message: str) -> None:
    LOG.mkdir(parents=True, exist_ok=True)
    with (LOG / "selenium.log").open("a", encoding="utf-8") as fh:
        fh.write(message + "\n")


def emit(payload: dict) -> None:
    print(json.dumps(payload, ensure_ascii=False), flush=True)


def fail(code: str, message: str) -> None:
    emit({"success": False, "error": {"code": code, "message": message}})
    raise SystemExit(1)

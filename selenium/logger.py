from __future__ import annotations

import json
import sys
from pathlib import Path

SELENIUM_ROOT = Path(__file__).resolve().parent
ROOT = SELENIUM_ROOT.parent
LOG_DIR = SELENIUM_ROOT / "logs"
APP_LOG = ROOT / "logs" / "scheduler.log"


def now() -> str:
    import time

    return time.strftime("%Y-%m-%dT%H:%M:%S", time.gmtime()) + f".{int((time.time() % 1) * 1000):03d}Z"


def log(message: str) -> None:
    line = f"{now()} {message}\n"
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    with (LOG_DIR / "selenium.log").open("a", encoding="utf-8") as fh:
        fh.write(line)
    APP_LOG.parent.mkdir(parents=True, exist_ok=True)
    with APP_LOG.open("a", encoding="utf-8") as fh:
        fh.write(line)


def emit(payload: dict, exit_code: int = 0) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=False) + "\n")
    raise SystemExit(exit_code)


def fail(code: str, message: str, extra: dict | None = None) -> None:
    log(f"ERROR {code} {message}")
    payload = {"success": False, "data": extra, "error": {"code": code, "message": message}}
    emit(payload, 1)

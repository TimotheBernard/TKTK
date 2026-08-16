"""Compatibility entrypoint — V2 scheduler lives in scheduler.py."""
from worker.scheduler import main

if __name__ == "__main__":
    raise SystemExit(main())

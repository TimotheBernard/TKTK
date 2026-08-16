"""Abstract scenario recorder — never emit raw x,y or xpath as the scenario."""
from __future__ import annotations


def abstractize(events: list[dict]) -> list[dict]:
    steps = []
    for event in events:
        kind = str(event.get("type") or event.get("kind") or "").upper()
        if kind in {"CLICK", "MOUSE"}:
            continue
        if kind in {"NAVIGATE", "OPEN_PROFILE", "PROFILE"}:
            steps.append({"type": "OPEN_PROFILE", "username": event.get("username")})
        elif kind in {"OPEN_POST", "POST"}:
            steps.append({"type": "OPEN_POST"})
        elif kind in {"WATCH", "VIEW"}:
            steps.append({"type": "WATCH", "value": event.get("value") or "100%"})
        elif kind in {"WAIT", "IDLE"}:
            steps.append({"type": "WAIT", "seconds": int(event.get("seconds") or 0)})
        elif kind == "SCROLL":
            steps.append({"type": "SCROLL"})
        elif kind in {"OPEN_URL", "URL"}:
            steps.append({"type": "OPEN_URL", "url": event.get("url")})
    return steps

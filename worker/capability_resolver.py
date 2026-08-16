from __future__ import annotations

ACTION_CATALOG = {
    "OPEN_PROFILE": {"requires_browser": True},
    "OPEN_POST": {"requires_browser": True},
    "OPEN_URL": {"requires_browser": True},
    "WATCH": {"requires_browser": True},
    "SCROLL": {"requires_browser": True},
    "WAIT": {"requires_browser": False},
    "WAIT_RANDOM": {"requires_browser": False},
    "CHECK_PAGE": {"requires_browser": True},
    "CHECK_SESSION": {"requires_browser": True},
    "USER_CONFIRMATION": {"requires_browser": False, "requires_user": True},
    "PUBLISH_POST": {"requires_browser": False},
    "LIST_PROFILE_POSTS": {"requires_browser": True},
}


def resolve(action: str, settings: dict) -> str:
    """API first, browser second. Never encode the engine inside a scenario."""
    meta = ACTION_CATALOG.get(action, {"requires_browser": True})
    if action in ("WAIT", "WAIT_RANDOM"):
        return "local"
    if action == "USER_CONFIRMATION":
        return "user"
    if action == "PUBLISH_POST" and settings.get("tiktok_api_enabled") and settings.get("tiktok_client_key"):
        return "api"
    if action == "LIST_PROFILE_POSTS" and settings.get("tiktok_api_enabled") and settings.get("tiktok_client_key"):
        return "api"
    if not settings.get("selenium_enabled", True):
        return "simulate"
    if meta.get("requires_browser"):
        return "browser"
    if action == "PUBLISH_POST":
        return "browser"
    return "local"

#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE.parent))

from actions import BrowserActions
from browser_manager import BrowserManager
from errors import SeleniumError
from logger import emit, fail, log
from session_manager import SessionManager
from worker.json_store import JsonStore


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="TikTok Manager Selenium runner")
    parser.add_argument("--account-id")
    parser.add_argument("--profile")
    parser.add_argument("--action", required=True, choices=["open_url", "open_profile", "open_post", "check_session", "list_profile_posts"])
    parser.add_argument("--url", default="")
    parser.add_argument("--username", default="")
    parser.add_argument("--limit", type=int, default=8)
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    store = JsonStore()
    settings = store.read("settings")
    sessions = SessionManager()
    try:
        if args.account_id:
            accounts = store.read("accounts").get("items") or []
            account = sessions.verify_account(args.account_id, accounts)
            profile_dir = sessions.resolve(account_id=account.get("browser_profile") or args.account_id)
            username = args.username or account.get("username") or ""
        else:
            profile_dir = sessions.resolve(profile=args.profile or settings.get("watcher_profile") or "_watcher")
            username = args.username
        manager = BrowserManager(
            profile_dir,
            chrome_path=settings.get("chrome_path") or "",
            timeout=int(settings.get("browser_timeout_seconds") or 30),
        )
        driver = manager.start()
        actions = BrowserActions(driver)
        result: dict = {"success": True, "error": None}
        if args.action == "open_url":
            actions.open_url(args.url)
        elif args.action == "open_profile":
            actions.open_profile(username)
        elif args.action == "open_post":
            actions.open_post(args.url)
            result["current_url"] = actions.get_current_url()
        elif args.action == "check_session":
            status = actions.check_session()
            result["session_status"] = status
        elif args.action == "list_profile_posts":
            result["posts"] = actions.list_profile_posts(username, args.limit)
        manager.close()
        log(f"ACTION_OK {args.action}")
        emit(result)
    except SeleniumError as exc:
        fail(exc.code, exc.message)
    except Exception as exc:
        fail("UNKNOWN_ERROR", str(exc))


if __name__ == "__main__":
    main()

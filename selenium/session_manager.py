from __future__ import annotations

from pathlib import Path

from errors import SeleniumError
from logger import SELENIUM_ROOT, log

PROFILES = SELENIUM_ROOT / "profiles"


class SessionManager:
    def resolve(self, account_id: str | None = None, profile: str | None = None) -> Path:
        if profile:
            name = profile
        elif account_id:
            name = account_id
        else:
            raise SeleniumError("PROFILE_NOT_FOUND", "Missing account_id/profile")
        if "/" in name or ".." in name:
            raise SeleniumError("PROFILE_NOT_FOUND", "Invalid profile name")
        path = PROFILES / name
        path.mkdir(parents=True, exist_ok=True)
        log(f"SESSION_PROFILE {name}")
        return path

    def verify_account(self, account_id: str, accounts: list[dict]) -> dict:
        account = next((a for a in accounts if a.get("id") == account_id), None)
        if account is None:
            raise SeleniumError("ACCOUNT_NOT_FOUND", "Account not found")
        if not account.get("enabled", True) or account.get("status") == "disabled":
            raise SeleniumError("ACCOUNT_DISABLED", "Account disabled")
        expected = account.get("browser_profile") or account_id
        if expected != account_id and expected != account.get("browser_profile"):
            raise SeleniumError("PROFILE_NOT_FOUND", "Profile mismatch")
        path = self.resolve(account_id=expected)
        if not path.is_dir():
            raise SeleniumError("PROFILE_NOT_FOUND", "Chrome profile missing")
        return account

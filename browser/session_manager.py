from __future__ import annotations

import os
from pathlib import Path

from errors import SeleniumError

ROOT = Path(__file__).resolve().parent.parent
PROFILES = Path(os.environ.get("TKTK_PROFILES_PATH") or (ROOT / "profiles"))


class SessionManager:
    def resolve(self, account_id: str | None = None, profile: str | None = None) -> Path:
        name = profile or account_id
        if not name:
            raise SeleniumError("PROFILE_NOT_FOUND", "Missing profile")
        path = PROFILES / name
        path.mkdir(parents=True, exist_ok=True)
        return path

    def verify_account(self, account_id: str, accounts: list[dict]) -> dict:
        account = next((a for a in accounts if a.get("id") == account_id), None)
        if account is None:
            raise SeleniumError("ACCOUNT_NOT_FOUND", account_id)
        if not account.get("enabled", True) or (account.get("runtime_status") or account.get("status")) == "disabled":
            raise SeleniumError("ACCOUNT_DISABLED", account_id)
        expected = account.get("browser_profile") or account_id
        self.resolve(account_id=expected)
        return account

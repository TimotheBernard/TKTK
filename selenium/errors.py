CODES = [
    "ACCOUNT_NOT_FOUND",
    "ACCOUNT_DISABLED",
    "PROFILE_NOT_FOUND",
    "SESSION_EXPIRED",
    "POST_NOT_FOUND",
    "BROWSER_START_FAILED",
    "BROWSER_CRASHED",
    "ACTION_TIMEOUT",
    "STORAGE_ERROR",
    "UNKNOWN_ERROR",
]


class SeleniumError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code if code in CODES else "UNKNOWN_ERROR"
        self.message = message

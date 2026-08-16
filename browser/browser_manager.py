from __future__ import annotations

import os
import shutil
import subprocess
from pathlib import Path

from errors import SeleniumError
from logger import log


class BrowserManager:
    def __init__(self, user_data_dir: Path, chrome_path: str = "", timeout: int = 30) -> None:
        self.user_data_dir = user_data_dir
        self.chrome_path = chrome_path
        self.timeout = timeout
        self.driver = None

    @staticmethod
    def resolve_binary(chrome_path: str = "") -> str:
        candidates = []
        if chrome_path:
            candidates.append(chrome_path)
        for name in ("google-chrome", "google-chrome-stable", "chromium", "chromium-browser", "chrome"):
            found = shutil.which(name)
            if found:
                candidates.append(found)
        candidates.extend(
            [
                "/usr/local/bin/google-chrome",
                "/usr/bin/google-chrome",
                "/usr/bin/chromium",
                "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
            ]
        )
        for binary in candidates:
            if binary and Path(binary).exists():
                return binary
        raise SeleniumError("BROWSER_START_FAILED", "Chrome binary not found")

    def launch_detached(self, url: str) -> dict:
        binary = self.resolve_binary(self.chrome_path)
        self.user_data_dir.mkdir(parents=True, exist_ok=True)
        subprocess.Popen(
            [
                binary,
                f"--user-data-dir={self.user_data_dir}",
                "--no-first-run",
                "--no-default-browser-check",
                "--new-window",
                url or "https://www.tiktok.com/",
            ],
            start_new_session=True,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        log(f"BROWSER_LAUNCHED {self.user_data_dir.name} {binary}")
        return {"launched": True, "binary": binary, "profile": str(self.user_data_dir), "url": url}

    def start(self):
        try:
            from selenium import webdriver
            from selenium.webdriver.chrome.options import Options
        except ImportError as exc:
            raise SeleniumError("BROWSER_START_FAILED", f"Selenium not installed: {exc}") from exc

        options = Options()
        options.add_argument(f"--user-data-dir={self.user_data_dir}")
        options.add_argument("--no-first-run")
        options.add_argument("--no-default-browser-check")
        options.add_argument("--disable-dev-shm-usage")
        options.add_argument("--disable-gpu")
        options.add_argument("--window-size=1280,900")
        if os.environ.get("TKTK_HEADLESS", "") in ("1", "true"):
            options.add_argument("--headless=new")
        if self.chrome_path:
            options.binary_location = self.chrome_path
        try:
            self.driver = webdriver.Chrome(options=options)
            self.driver.set_page_load_timeout(self.timeout)
            log(f"BROWSER_STARTED {self.user_data_dir.name}")
            return self.driver
        except Exception as exc:
            raise SeleniumError("BROWSER_START_FAILED", str(exc)) from exc

    def close(self) -> None:
        if self.driver is None:
            return
        try:
            self.driver.quit()
            log(f"BROWSER_CLOSED {self.user_data_dir.name}")
        except Exception as exc:
            log(f"BROWSER_CLOSE_ERROR {exc}")
        finally:
            self.driver = None

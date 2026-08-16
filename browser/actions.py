from __future__ import annotations

import re
import time
from urllib.parse import urlparse

from errors import SeleniumError
from logger import log


class BrowserActions:
    VIDEO_RE = re.compile(r"/@([^/]+)/video/(\d+)")

    def __init__(self, driver) -> None:
        self.driver = driver

    def open_url(self, url: str) -> None:
        self.driver.get(url)
        log(f"OPEN_URL {url}")

    def open_profile(self, username: str) -> None:
        username = username if str(username).startswith("@") else f"@{username}"
        self.open_url(f"https://www.tiktok.com/{username}")

    def open_post(self, url: str) -> None:
        if not url:
            raise SeleniumError("POST_NOT_FOUND", "Missing post URL")
        self.open_url(url)
        current = self.get_current_url()
        if "login" in current.lower() or "signup" in current.lower():
            raise SeleniumError("SESSION_EXPIRED", "Login wall detected")
        log(f"POST_OPENED {url}")

    def watch(self, url: str, value: str = "100%") -> None:
        if url:
            self.open_post(url)
        seconds = 8
        if value.endswith("%"):
            seconds = 12
        elif value.endswith("s"):
            seconds = int(value[:-1] or 8)
        time.sleep(min(max(seconds, 1), 120))
        log(f"WATCH {value}")

    def scroll(self) -> None:
        self.driver.execute_script("window.scrollBy(0, 800)")
        log("SCROLL")

    def check_page(self) -> str:
        return self.get_current_url()

    def check_session(self) -> str:
        self.open_url("https://www.tiktok.com/")
        current = self.get_current_url().lower()
        source = ""
        try:
            source = self.driver.page_source.lower()
        except Exception:
            source = ""
        if "login" in current or "signup" in current or "log in" in source[:8000]:
            return "expired"
        return "connected"

    def get_current_url(self) -> str:
        return self.driver.current_url or ""

    def list_profile_posts(self, username: str, limit: int = 8) -> list[dict]:
        self.open_profile(username)
        current = self.get_current_url().lower()
        if "login" in current:
            raise SeleniumError("SESSION_EXPIRED", "Watcher session expired")
        hrefs = []
        try:
            from selenium.webdriver.common.by import By

            for link in self.driver.find_elements(By.TAG_NAME, "a"):
                href = link.get_attribute("href") or ""
                if self.VIDEO_RE.search(urlparse(href).path or href):
                    hrefs.append(href)
        except Exception as exc:
            raise SeleniumError("UNKNOWN_ERROR", str(exc)) from exc
        posts = []
        seen = set()
        for href in hrefs:
            match = self.VIDEO_RE.search(href)
            if not match:
                continue
            video_id = match.group(2)
            if video_id in seen:
                continue
            seen.add(video_id)
            user = match.group(1)
            posts.append(
                {
                    "url": f"https://www.tiktok.com/@{user}/video/{video_id}",
                    "video_id": video_id,
                    "caption": "",
                }
            )
            if len(posts) >= limit:
                break
        log(f"LIST_POSTS {username} count={len(posts)}")
        return posts

    def publish_post(self, caption: str = "") -> None:
        log(f"PUBLISH_POST caption_len={len(caption)}")

    def close(self) -> None:
        pass

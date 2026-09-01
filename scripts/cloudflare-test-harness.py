from __future__ import annotations

import json
import os
import sys
from urllib.parse import urlparse

from scrapling.fetchers import StealthyFetcher

"""
Development-only Cloudflare test harness.

This is intentionally separate from the Whatnot scraper. It may only run when
ALLOW_OWNED_TEST_TARGET=1 is explicitly set, and it refuses whatnot.com targets.
Use it only against infrastructure you own or are authorized to test.
"""

TARGET_URL = os.getenv("TEST_TARGET_URL", "https://example.com").strip()
ALLOW = os.getenv("ALLOW_OWNED_TEST_TARGET", "0").strip() == "1"
HEADLESS = os.getenv("TEST_TARGET_HEADLESS", "true").lower() not in {"0", "false", "no", "off"}
SOLVE_CLOUDFLARE = os.getenv("TEST_TARGET_SOLVE_CLOUDFLARE", "true").lower() not in {"0", "false", "no", "off"}
BLOCK_WEBRTC = os.getenv("TEST_TARGET_BLOCK_WEBRTC", "true").lower() not in {"0", "false", "no", "off"}
HIDE_CANVAS = os.getenv("TEST_TARGET_HIDE_CANVAS", "true").lower() not in {"0", "false", "no", "off"}


def fail(message: str, code: int = 2) -> None:
    print(message, file=sys.stderr, flush=True)
    raise SystemExit(code)


def validate_target(url: str) -> None:
    parsed = urlparse(url)
    host = (parsed.hostname or "").lower()

    if not ALLOW:
        fail("Set ALLOW_OWNED_TEST_TARGET=1 to confirm this is an owned/authorized test target.")

    if parsed.scheme not in {"http", "https"} or not host:
        fail("TEST_TARGET_URL must be an http(s) URL.")

    if host == "whatnot.com" or host.endswith(".whatnot.com"):
        fail("This test harness is not permitted to target whatnot.com.")


def main() -> None:
    validate_target(TARGET_URL)

    page = StealthyFetcher.fetch(
        TARGET_URL,
        headless=HEADLESS,
        solve_cloudflare=SOLVE_CLOUDFLARE,
        block_webrtc=BLOCK_WEBRTC,
        hide_canvas=HIDE_CANVAS,
    )

    # Keep output intentionally small and generic so this remains a connectivity
    # / challenge-handling test rather than a site-specific scraping workflow.
    title = None
    try:
        title = page.css("title::text").get()
    except Exception:
        pass

    result = {
        "ok": True,
        "target": TARGET_URL,
        "title": title,
        "solve_cloudflare": SOLVE_CLOUDFLARE,
        "headless": HEADLESS,
    }
    print(json.dumps(result, separators=(",", ":")))


if __name__ == "__main__":
    main()

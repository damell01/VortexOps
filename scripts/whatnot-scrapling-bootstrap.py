from __future__ import annotations

"""Seed the persistent Scrapling-owned Whatnot browser profile from saved cookies.

This utility does not submit credentials and does not solve or bypass anti-bot
challenges. It imports a previously authenticated Whatnot cookie snapshot into
the same persistent Chrome profile used by the production Scrapling scraper,
then verifies that Seller Hub is reachable.
"""

import json
import os
import re
import sys
from pathlib import Path
from typing import Any

from scrapling.fetchers import StealthySession

BASE = "https://www.whatnot.com"
ROOT = Path(__file__).resolve().parent.parent
PROFILE = Path(
    os.getenv("WHATNOT_USER_DATA_DIR", str(ROOT / "storage" / "whatnot-scrapling-profile"))
).resolve()
CHROME = os.getenv("PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH", "").strip()
HEADLESS = os.getenv("WHATNOT_HEADLESS", "false").strip().lower() not in {"0", "false", "no", "off"}


def env_bool(name: str, default: bool) -> bool:
    value = os.getenv(name)
    if value is None or not value.strip():
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


BLOCK_WEBRTC = env_bool("WHATNOT_SCRAPLING_BLOCK_WEBRTC", False)
HIDE_CANVAS = env_bool("WHATNOT_SCRAPLING_HIDE_CANVAS", False)
ALLOW_WEBGL = env_bool("WHATNOT_SCRAPLING_ALLOW_WEBGL", True)


def log(message: str) -> None:
    print(f"[whatnot:scrapling-bootstrap] {message}", file=sys.stderr, flush=True)


def fail(message: str, code: int = 1) -> None:
    print(message, file=sys.stderr, flush=True)
    raise SystemExit(code)


def cookie_candidates() -> list[Path]:
    configured = os.getenv("WHATNOT_COOKIES_FILE", "").strip()
    paths: list[Path] = []
    if configured:
        paths.append(Path(configured).expanduser().resolve())
    paths.extend(
        [
            ROOT / "storage" / "whatnot-live-cookies.json",
            ROOT / "storage" / "whatnot-cookies.json",
        ]
    )
    unique: list[Path] = []
    seen: set[str] = set()
    for path in paths:
        key = str(path)
        if key not in seen:
            seen.add(key)
            unique.append(path)
    return unique


def load_cookies() -> tuple[Path, list[dict[str, Any]]]:
    source = next((path for path in cookie_candidates() if path.is_file()), None)
    if source is None:
        searched = ", ".join(str(path) for path in cookie_candidates())
        fail(f"BOOTSTRAP_COOKIE_FILE_MISSING: no saved Whatnot cookie file found. Searched: {searched}", 3)

    try:
        raw = json.loads(source.read_text(encoding="utf-8"))
    except Exception as exc:
        fail(f"BOOTSTRAP_COOKIE_FILE_INVALID: could not read {source}: {exc}", 3)

    if isinstance(raw, dict) and isinstance(raw.get("cookies"), list):
        raw = raw["cookies"]
    if not isinstance(raw, list):
        fail(f"BOOTSTRAP_COOKIE_FILE_INVALID: expected a cookie array in {source}", 3)

    cookies: list[dict[str, Any]] = []
    blocked_names = {
        "cf_clearance",
        "__cf_bm",
        "__cfwaitingroom",
        "cf_chl_2",
        "cf_chl_prog",
        "cf_chl_rc_i",
        "cf_chl_rc_ni",
        "cf_chl_rc_m",
    }
    for item in raw:
        if not isinstance(item, dict):
            continue
        name = str(item.get("name") or "")
        value = str(item.get("value") or "")
        domain = str(item.get("domain") or ".whatnot.com")
        if not name or "whatnot.com" not in domain or name.lower() in blocked_names:
            continue

        cookie: dict[str, Any] = {
            "name": name,
            "value": value,
            "domain": domain,
            "path": str(item.get("path") or "/"),
            "httpOnly": bool(item.get("httpOnly", False)),
            "secure": bool(item.get("secure", True)),
        }
        expires = item.get("expires", item.get("expirationDate"))
        if isinstance(expires, (int, float)) and expires > 0:
            cookie["expires"] = float(expires)
        same_site = str(item.get("sameSite") or "").lower()
        if same_site in {"strict", "lax", "none"}:
            cookie["sameSite"] = same_site.capitalize()
        cookies.append(cookie)

    if not cookies:
        fail(f"BOOTSTRAP_COOKIE_FILE_INVALID: no reusable whatnot.com cookies found in {source}", 3)
    return source, cookies


def challenge_present(page) -> bool:
    try:
        title = str(page.title() or "")
    except Exception:
        title = ""
    try:
        body = str(page.locator("body").inner_text(timeout=2000) or "")[:12000]
    except Exception:
        body = ""
    return bool(
        re.search(
            r"just a moment|checking your browser|verify you are human|security verification|cloudflare|challenge-platform",
            f"{page.url}\n{title}\n{body}",
            re.I,
        )
    )


def main() -> None:
    if not CHROME:
        fail("BOOTSTRAP_CONFIG_ERROR: PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH is required", 2)

    source, cookies = load_cookies()
    PROFILE.mkdir(parents=True, exist_ok=True)
    log(f"profile={PROFILE}")
    log(f"cookie_source={source} cookies={len(cookies)}")

    result = {"authenticated": False, "url": ""}

    def seed(page) -> None:
        if challenge_present(page):
            fail(
                f"CLOUDFLARE_CHALLENGE: verification page appeared before session bootstrap at {page.url}. "
                "No challenge bypass was attempted.",
                4,
            )

        page.context.add_cookies(cookies)
        log(f"SESSION_BOOTSTRAP imported={len(cookies)}")
        page.goto(f"{BASE}/dashboard/home", wait_until="domcontentloaded", timeout=30000)
        try:
            page.wait_for_load_state("networkidle", timeout=6000)
        except Exception:
            pass
        page.wait_for_timeout(1500)
        result["url"] = str(page.url or "")

        if challenge_present(page):
            fail(
                f"CLOUDFLARE_CHALLENGE: Whatnot challenged the imported session at {page.url}. "
                "No challenge bypass was attempted.",
                4,
            )
        if re.search(r"/(login|signin|auth)(/|\?|$)", result["url"], re.I):
            fail(
                f"BOOTSTRAP_SESSION_EXPIRED: saved cookies redirected to login ({result['url']}). "
                "Export a fresh authenticated Whatnot cookie snapshot and rerun this bootstrap.",
                3,
            )

        result["authenticated"] = True
        log(f"SESSION_BOOTSTRAP verified url={result['url']}")

    with StealthySession(
        headless=HEADLESS,
        real_chrome=True,
        executable_path=CHROME,
        user_data_dir=str(PROFILE),
        disable_resources=False,
        google_search=False,
        locale="en-US",
        solve_cloudflare=False,
        block_webrtc=BLOCK_WEBRTC,
        hide_canvas=HIDE_CANVAS,
        allow_webgl=ALLOW_WEBGL,
    ) as session:
        session.fetch(
            f"{BASE}/login",
            page_action=seed,
            timeout=60000,
            network_idle=False,
            google_search=False,
        )

    if not result["authenticated"]:
        fail("BOOTSTRAP_FAILED: persistent Scrapling profile was not authenticated", 3)

    print(
        json.dumps(
            {
                "ok": True,
                "profile": str(PROFILE),
                "cookie_source": str(source),
                "url": result["url"],
            }
        )
    )


if __name__ == "__main__":
    main()

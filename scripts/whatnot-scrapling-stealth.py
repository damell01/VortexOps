from __future__ import annotations

"""Guarded Scrapling backend for the Whatnot scraper.

This module intentionally delegates challenge handling to Scrapling's documented
StealthySession. It does not implement a custom CAPTCHA/Turnstile solver, token
injection, audio automation, or solver-service integration.

The existing extraction/channel-switch logic remains in whatnot-scrapling.py.
This wrapper adds:
  * one persistent StealthySession for the entire scrape
  * Scrapling's built-in Cloudflare handling
  * bounded retries when a challenge is still present after Scrapling returns
  * challenge/login/channel guards before data extraction
  * sanitized JSON diagnostics that never store cookies, headers, or tokens
"""

import importlib.util
import json
import os
import re
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Callable

from scrapling.fetchers import StealthySession

HERE = Path(__file__).resolve().parent
LEGACY_PATH = HERE / "whatnot-scrapling.py"
DIAGNOSTICS_DIR = Path(
    os.getenv(
        "WHATNOT_SCRAPLING_DIAGNOSTICS_DIR",
        str(HERE.parent / "storage" / "logs" / "whatnot-scrapling"),
    )
)
RETRIES = max(0, min(5, int(os.getenv("WHATNOT_SCRAPLING_RETRIES", "2"))))
RETRY_DELAY = max(0.0, min(30.0, float(os.getenv("WHATNOT_SCRAPLING_RETRY_DELAY", "2"))))
SOLVE_CLOUDFLARE = os.getenv("WHATNOT_SCRAPLING_SOLVE_CLOUDFLARE", "true").lower() not in {
    "0",
    "false",
    "no",
    "off",
}
BLOCK_WEBRTC = os.getenv("WHATNOT_SCRAPLING_BLOCK_WEBRTC", "true").lower() not in {
    "0",
    "false",
    "no",
    "off",
}
HIDE_CANVAS = os.getenv("WHATNOT_SCRAPLING_HIDE_CANVAS", "true").lower() not in {
    "0",
    "false",
    "no",
    "off",
}

CHALLENGE_MARKERS = (
    "cf-turnstile",
    "challenges.cloudflare.com",
    "cf-chl-",
    "verify you are human",
    "verifying you are human",
    "checking your browser",
    "just a moment",
    "performing security verification",
)


class ChallengeBlocked(RuntimeError):
    pass


def load_legacy():
    spec = importlib.util.spec_from_file_location("vortex_whatnot_scrapling", LEGACY_PATH)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Unable to load {LEGACY_PATH}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


legacy = load_legacy()
_original_check_login = legacy.check_login
_original_prepare = legacy.prepare
_original_extract_show = legacy.extract_show
_original_extract_orders = legacy.extract_orders
_original_extract_shipments = legacy.extract_shipments


def safe_page_snapshot(page) -> dict[str, Any]:
    """Return challenge diagnostics without cookies, request headers, or HTML."""
    url = ""
    title = ""
    body = ""
    try:
        url = str(page.url or "")
    except Exception:
        pass
    try:
        title = str(page.title(timeout=1500) or "")[:300]
    except Exception:
        pass
    try:
        body = str(page.locator("body").inner_text(timeout=1500) or "")[:2000]
    except Exception:
        pass

    lowered = f"{url}\n{title}\n{body}".lower()
    matched = sorted({marker for marker in CHALLENGE_MARKERS if marker in lowered})
    return {
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "mode": legacy.MODE,
        "requested_channel": legacy.CHANNEL or None,
        "url": url,
        "title": title,
        "challenge_markers": matched,
    }


def challenge_present(page) -> tuple[bool, dict[str, Any]]:
    snapshot = safe_page_snapshot(page)
    if snapshot["challenge_markers"]:
        return True, snapshot

    # Selector checks are observational only. They detect a challenge that is
    # still on screen after Scrapling's built-in handling has returned.
    selectors = (
        "iframe[src*='challenges.cloudflare.com']",
        "[data-sitekey][class*='turnstile']",
        ".cf-turnstile",
        "#challenge-form",
    )
    for selector in selectors:
        try:
            if page.locator(selector).first.is_visible(timeout=500):
                snapshot["challenge_markers"] = [f"selector:{selector}"]
                return True, snapshot
        except Exception:
            pass
    return False, snapshot


def write_diagnostic(snapshot: dict[str, Any], phase: str) -> Path | None:
    try:
        DIAGNOSTICS_DIR.mkdir(parents=True, exist_ok=True)
        stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S.%fZ")
        clean_phase = re.sub(r"[^a-zA-Z0-9_.-]+", "-", phase)[:80]
        path = DIAGNOSTICS_DIR / f"{stamp}-{clean_phase}.json"
        payload = {**snapshot, "phase": phase, "status": "TURNSTILE_BLOCKED"}
        path.write_text(json.dumps(payload, indent=2, sort_keys=True), encoding="utf-8")
        return path
    except Exception as exc:
        legacy.info(f"diagnostic write failed: {exc}")
        return None


def guard(page, phase: str) -> None:
    blocked, snapshot = challenge_present(page)
    if not blocked:
        return
    path = write_diagnostic(snapshot, phase)
    suffix = f" diagnostic={path}" if path else ""
    raise ChallengeBlocked(
        f"TURNSTILE_BLOCKED: challenge still present after Scrapling handling; "
        f"phase={phase} url={snapshot.get('url') or '?'}{suffix}"
    )


def guarded_check_login(page) -> None:
    guard(page, "check-login")
    _original_check_login(page)


def guarded_prepare(page) -> str:
    guard(page, "prepare")
    active = _original_prepare(page)
    # Re-check after role switching. This is what prevents a failed switch from
    # silently carrying the previous seller's data forward.
    guard(page, "post-channel-switch")
    current = legacy.active_username(page)
    if legacy.norm(current) != legacy.norm(legacy.CHANNEL):
        legacy.fail(
            f"CHANNEL_CONTEXT_MISMATCH: refusing to scrape. "
            f"requested=@{legacy.CHANNEL} active=@{current or '?'}",
            3,
        )
    return active


def guarded_extract_show(page):
    guard(page, "extract-show")
    return _original_extract_show(page)


def guarded_extract_orders(page):
    guard(page, "extract-orders")
    return _original_extract_orders(page)


def guarded_extract_shipments(page):
    guard(page, "extract-shipments")
    return _original_extract_shipments(page)


legacy.check_login = guarded_check_login
legacy.prepare = guarded_prepare
legacy.extract_show = guarded_extract_show
legacy.extract_orders = guarded_extract_orders
legacy.extract_shipments = guarded_extract_shipments


def run_mode(session: StealthySession):
    if legacy.MODE == "analytics":
        return legacy.analytics(session)
    if legacy.MODE == "orders-batch":
        return legacy.batch(session, False)
    if legacy.MODE == "shipments-batch":
        return legacy.batch(session, True)
    if legacy.MODE == "ledger":
        return legacy.ledger(session)
    legacy.fail(f"SCRAPLING_MODE_UNSUPPORTED: {legacy.MODE}")


def main() -> None:
    if not legacy.PROFILE:
        legacy.fail("WHATNOT_USER_DATA_DIR is required")
    if not legacy.CHROME:
        legacy.fail("PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH is required")

    Path(legacy.PROFILE).mkdir(parents=True, exist_ok=True)
    legacy.info(
        "engine=Scrapling StealthySession "
        f"mode={legacy.MODE} profile={legacy.PROFILE} "
        f"solve_cloudflare={str(SOLVE_CLOUDFLARE).lower()} retries={RETRIES}"
    )

    try:
        # Keep one browser + user-data directory alive across the whole run so
        # authenticated session state survives page changes and channel checks.
        with StealthySession(
            headless=legacy.HEADLESS,
            real_chrome=True,
            executable_path=legacy.CHROME,
            user_data_dir=legacy.PROFILE,
            disable_resources=False,
            google_search=False,
            locale="en-US",
            solve_cloudflare=SOLVE_CLOUDFLARE,
            block_webrtc=BLOCK_WEBRTC,
            hide_canvas=HIDE_CANVAS,
        ) as session:
            last_error: ChallengeBlocked | None = None
            for attempt in range(1, RETRIES + 2):
                try:
                    legacy.info(f"stealth attempt {attempt}/{RETRIES + 1}")
                    result = run_mode(session)
                    json.dump(result, sys.stdout, separators=(",", ":"))
                    sys.stdout.write("\n")
                    return
                except ChallengeBlocked as exc:
                    last_error = exc
                    legacy.info(str(exc))
                    if attempt <= RETRIES:
                        time.sleep(RETRY_DELAY * attempt)
                        continue
                    break

            if last_error is not None:
                legacy.fail(str(last_error), 5)
    except SystemExit:
        raise
    except Exception as exc:
        legacy.fail(f"FETCH_FAILED: Scrapling stealth backend failed: {exc}", 1)


if __name__ == "__main__":
    main()

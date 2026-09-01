from __future__ import annotations

import json
import os
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

import requests

"""
Safe HTTP session-health adapter for the Whatnot scraper.

The session-lifecycle ideas here are intentionally limited to the non-bypass
parts of damell01/cloudscraper: request/session counters, age-based refresh,
403 health handling and structured diagnostics. It does NOT import or enable
Cloudflare solvers, Turnstile handling, CAPTCHA services, stealth/fingerprint
rotation, proxy rotation, JS challenge interpreters, or token manipulation.

A detected anti-bot challenge is terminal for this adapter and is reported as
CLOUDFLARE_BLOCKED. The browser backends decide what to do next.
"""

BASE = "https://www.whatnot.com"
HEALTH_URL = os.getenv("WHATNOT_HTTP_HEALTH_URL", f"{BASE}/dashboard/home").strip()
CHANNEL = os.getenv("WHATNOT_CHANNEL_NAME", "").strip()
COOKIES_FILE = os.getenv("WHATNOT_COOKIES_FILE", "").strip()
DIAGNOSTICS_DIR = Path(
    os.getenv("WHATNOT_HTTP_DIAGNOSTICS_DIR")
    or os.getenv("WHATNOT_SCRAPLING_DIAGNOSTICS_DIR")
    or "storage/logs/whatnot-http"
)
TIMEOUT = max(5, min(60, int(os.getenv("WHATNOT_HTTP_TIMEOUT", "20"))))
SESSION_REFRESH = max(60, int(os.getenv("WHATNOT_HTTP_SESSION_REFRESH", "3600")))
RETRY_DELAY = max(0.0, min(10.0, float(os.getenv("WHATNOT_HTTP_RETRY_DELAY", "1.5"))))
DEBUG = os.getenv("WHATNOT_DEBUG", "0") == "1"

EXIT_NETWORK = 1
EXIT_AUTH_REQUIRED = 3
EXIT_RATE_LIMITED = 4
EXIT_CHALLENGE = 5
EXIT_FORBIDDEN = 6


def info(message: str) -> None:
    print(f"[whatnot:http] {message}", file=sys.stderr, flush=True)


def validate_health_url(url: str) -> None:
    parsed = urlparse(url)
    if parsed.scheme != "https" or parsed.hostname not in {"whatnot.com", "www.whatnot.com"}:
        raise ValueError("WHATNOT_HTTP_HEALTH_URL must be an https://www.whatnot.com URL")


def load_cookie_rows(path: str) -> list[dict[str, Any]]:
    if not path:
        return []
    p = Path(path)
    if not p.is_file():
        return []
    try:
        payload = json.loads(p.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return []
    if isinstance(payload, dict) and isinstance(payload.get("cookies"), list):
        payload = payload["cookies"]
    return payload if isinstance(payload, list) else []


def apply_cookies(session: requests.Session, rows: list[dict[str, Any]]) -> int:
    applied = 0
    for row in rows:
        if not isinstance(row, dict) or not row.get("name"):
            continue
        domain = str(row.get("domain") or ".whatnot.com")
        if "whatnot.com" not in domain:
            continue
        try:
            session.cookies.set(
                str(row["name"]),
                str(row.get("value") or ""),
                domain=domain,
                path=str(row.get("path") or "/"),
            )
            applied += 1
        except Exception:
            continue
    return applied


def challenge_markers(response: requests.Response) -> list[str]:
    body = (response.text or "")[:250_000].lower()
    headers = {str(k).lower(): str(v).lower() for k, v in response.headers.items()}
    markers: list[str] = []

    checks = {
        "cf-mitigated": headers.get("cf-mitigated") == "challenge",
        "turnstile": "cf-turnstile" in body or "challenges.cloudflare.com" in body,
        "challenge-platform": "/cdn-cgi/challenge-platform/" in body,
        "just-a-moment": "just a moment" in body and "cloudflare" in body,
        "verify-human": "verify you are human" in body and "cloudflare" in body,
        "cf-chl": "cf-chl-" in body or "cf_chl_" in body,
    }
    for name, matched in checks.items():
        if matched:
            markers.append(name)
    return markers


def is_login_response(response: requests.Response) -> bool:
    final = urlparse(response.url)
    if final.path.lower().startswith(("/login", "/signin", "/auth")):
        return True
    body = (response.text or "")[:100_000].lower()
    return "input-login-email" in body and "input-login-password" in body


@dataclass
class SafeHttpSession:
    cookie_rows: list[dict[str, Any]]

    def __post_init__(self) -> None:
        self.started_at = time.time()
        self.request_count = 0
        self.refresh_count = 0
        self.session = self._new_session()

    def _new_session(self) -> requests.Session:
        session = requests.Session()
        session.headers.update({
            "Accept": "text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8",
            "Accept-Language": "en-US,en;q=0.9",
            "Cache-Control": "no-cache",
            "Pragma": "no-cache",
            "User-Agent": "VortexOps-Whatnot-Health/1.0",
        })
        apply_cookies(session, self.cookie_rows)
        return session

    def refresh(self, reason: str) -> None:
        # Health refresh only: rebuild the requests connection pool and reload
        # the same saved cookies. No fingerprint changes, solver tokens, proxy
        # rotation, or challenge manipulation occur here.
        try:
            self.session.close()
        except Exception:
            pass
        self.session = self._new_session()
        self.started_at = time.time()
        self.refresh_count += 1
        info(f"SESSION_REFRESH reason={reason} refresh_count={self.refresh_count}")

    def request(self, url: str) -> tuple[requests.Response, bool]:
        if time.time() - self.started_at >= SESSION_REFRESH:
            self.refresh("age")

        self.request_count += 1
        response = self.session.get(url, timeout=TIMEOUT, allow_redirects=True)
        markers = challenge_markers(response)
        if markers:
            return response, False

        # Borrow the useful session-health idea from cloudscraper without its
        # bypass behavior: an ordinary, non-challenge 403 gets one fresh
        # connection-pool/cookie reload retry. A challenge 403 is never retried.
        if response.status_code == 403:
            self.refresh("ordinary-403")
            if RETRY_DELAY:
                time.sleep(RETRY_DELAY)
            self.request_count += 1
            return self.session.get(url, timeout=TIMEOUT, allow_redirects=True), True

        return response, False


def write_diagnostic(payload: dict[str, Any]) -> str | None:
    try:
        DIAGNOSTICS_DIR.mkdir(parents=True, exist_ok=True)
        stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        channel = "".join(c for c in CHANNEL.lower() if c.isalnum() or c in "-_.") or "default"
        path = DIAGNOSTICS_DIR / f"http-health-{channel}-{stamp}.json"
        path.write_text(json.dumps(payload, indent=2, sort_keys=True), encoding="utf-8")
        return str(path)
    except OSError:
        return None


def main() -> None:
    try:
        validate_health_url(HEALTH_URL)
    except ValueError as exc:
        info(str(exc))
        raise SystemExit(EXIT_NETWORK)

    cookie_rows = load_cookie_rows(COOKIES_FILE)
    client = SafeHttpSession(cookie_rows)
    started = time.time()

    try:
        response, retried = client.request(HEALTH_URL)
    except requests.RequestException as exc:
        payload = {
            "ok": False,
            "status": "NETWORK_ERROR",
            "error": str(exc),
            "channel": CHANNEL or None,
            "url": HEALTH_URL,
            "cookie_count": len(cookie_rows),
        }
        write_diagnostic(payload)
        info(f"NETWORK_ERROR url={HEALTH_URL} error={exc}")
        print(json.dumps(payload, separators=(",", ":")))
        raise SystemExit(EXIT_NETWORK)

    markers = challenge_markers(response)
    status = "HEALTHY"
    exit_code = 0

    if markers:
        status = "CLOUDFLARE_BLOCKED"
        exit_code = EXIT_CHALLENGE
    elif response.status_code == 429:
        status = "RATE_LIMITED"
        exit_code = EXIT_RATE_LIMITED
    elif is_login_response(response):
        status = "LOGIN_REQUIRED"
        exit_code = EXIT_AUTH_REQUIRED
    elif response.status_code == 403:
        status = "FORBIDDEN"
        exit_code = EXIT_FORBIDDEN
    elif response.status_code >= 400:
        status = f"HTTP_{response.status_code}"
        exit_code = EXIT_NETWORK

    payload = {
        "ok": exit_code == 0,
        "status": status,
        "http_status": response.status_code,
        "requested_url": HEALTH_URL,
        "final_url": response.url,
        "channel": CHANNEL or None,
        "challenge_markers": markers,
        "cookie_count": len(cookie_rows),
        "request_count": client.request_count,
        "refresh_count": client.refresh_count,
        "retried_after_ordinary_403": retried,
        "elapsed_ms": round((time.time() - started) * 1000),
    }
    diagnostic = write_diagnostic(payload)
    if diagnostic:
        payload["diagnostic"] = diagnostic

    info(
        f"HTTP_HEALTH status={status} http={response.status_code} "
        f"channel=@{CHANNEL or '?'} requests={client.request_count} refreshes={client.refresh_count}"
    )
    if markers:
        info(f"CLOUDFLARE_BLOCKED markers={','.join(markers)}; no challenge retry attempted")
    elif DEBUG and diagnostic:
        info(f"diagnostic={diagnostic}")

    print(json.dumps(payload, separators=(",", ":")))
    raise SystemExit(exit_code)


if __name__ == "__main__":
    main()

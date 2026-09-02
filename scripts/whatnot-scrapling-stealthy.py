from __future__ import annotations

"""Run the Whatnot extractor through a Scrapling-owned StealthySession.

Production data modes launch their own persistent Chrome profile by default.
CDP attachment remains available only when WHATNOT_SCRAPLING_USE_CDP=1 is
explicitly requested for diagnostics/migration rollback.

Authentication is allowed to use the configured Whatnot credentials. MFA and
anti-bot challenges are never bypassed: the run stops with a clear diagnostic so
an operator can complete the required interaction in the persistent profile.
"""

import importlib.util
import os
import re
import sys
from pathlib import Path
from typing import Any

from scrapling.fetchers import StealthySession

HERE = Path(__file__).resolve().parent
BASE_SCRIPT = HERE / "whatnot-scrapling.py"
CDP_URL = os.getenv(
    "WHATNOT_SCRAPLING_CDP_URL",
    os.getenv("WHATNOT_ATTACH_CDP_URL", "http://127.0.0.1:9222"),
).strip()
USE_CDP = os.getenv("WHATNOT_SCRAPLING_USE_CDP", "0").strip() == "1"
EMAIL = os.getenv("WHATNOT_EMAIL", "").strip()
PASSWORD = os.getenv("WHATNOT_PASSWORD", "")


def env_bool(name: str, default: bool) -> bool:
    value = os.getenv(name)
    if value is None or not value.strip():
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


SCRAPLING_BLOCK_WEBRTC = env_bool("WHATNOT_SCRAPLING_BLOCK_WEBRTC", False)
SCRAPLING_HIDE_CANVAS = env_bool("WHATNOT_SCRAPLING_HIDE_CANVAS", False)
SCRAPLING_ALLOW_WEBGL = env_bool("WHATNOT_SCRAPLING_ALLOW_WEBGL", True)


def log(message: str) -> None:
    print(f"[whatnot:scrapling] {message}", file=sys.stderr, flush=True)


def stop(message: str, code: int = 3) -> None:
    print(message, file=sys.stderr, flush=True)
    raise SystemExit(code)


def load_base_module():
    spec = importlib.util.spec_from_file_location("vortexops_whatnot_scrapling", BASE_SCRIPT)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Unable to load {BASE_SCRIPT}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def page_text(page) -> str:
    try:
        return str(page.locator("body").inner_text(timeout=2000) or "")
    except Exception:
        return ""


def challenge_present(page) -> bool:
    url = str(getattr(page, "url", "") or "")
    title = ""
    try:
        title = str(page.title() or "")
    except Exception:
        pass
    text = page_text(page)[:12000]
    combined = f"{url}\n{title}\n{text}"
    return bool(
        re.search(
            r"just a moment|checking your browser|verify you are human|security verification|cloudflare|challenge-platform",
            combined,
            re.I,
        )
    )


def login_page(page) -> bool:
    url = str(getattr(page, "url", "") or "")
    if re.search(r"/(login|signin|auth)(/|\?|$)", url, re.I):
        return True
    try:
        return bool(page.locator('input[type="password"]').first.is_visible(timeout=500))
    except Exception:
        return False


def interaction_required(page) -> bool:
    text = page_text(page)[:10000]
    return bool(
        re.search(
            r"verification code|enter (?:the )?code|one[- ]time|two[- ]factor|2fa|authenticator|check your (?:email|phone)|mfa",
            text,
            re.I,
        )
    )


def first_visible(page, selectors: list[str]):
    for selector in selectors:
        try:
            node = page.locator(selector).first
            if node.is_visible(timeout=700):
                return node
        except Exception:
            pass
    return None


def click_first_visible(page, selectors: list[str]) -> bool:
    for selector in selectors:
        try:
            node = page.locator(selector).first
            if node.is_visible(timeout=700):
                node.click(timeout=5000)
                return True
        except Exception:
            pass
    return False


def ensure_authenticated(page) -> None:
    """Validate the persistent session and perform an ordinary login when needed.

    Supports both single-page and multi-step Whatnot credential forms. This
    deliberately does not solve or bypass Cloudflare/Turnstile/CAPTCHA and does
    not attempt to automate MFA. Those states fail closed with instructions to
    refresh the persistent browser profile interactively.
    """
    if challenge_present(page):
        stop(
            f"CLOUDFLARE_CHALLENGE: Whatnot presented an anti-bot verification page at {page.url}. "
            "No challenge bypass was attempted. Refresh/authenticate the persistent Scrapling profile and retry.",
            4,
        )

    if not login_page(page):
        return

    if not EMAIL or not PASSWORD:
        stop(
            f"LOGIN_REQUIRED: Scrapling profile is not authenticated at {page.url} and WHATNOT_EMAIL/WHATNOT_PASSWORD are not configured.",
            3,
        )

    email_selectors = [
        'input[type="email"]',
        'input[name="email"]',
        'input[name="username"]',
        'input[autocomplete="email"]',
        'input[autocomplete="username"]',
        'input[placeholder*="email" i]',
        'input[placeholder*="username" i]',
    ]
    password_selectors = [
        'input[type="password"]',
        'input[name="password"]',
        'input[autocomplete="current-password"]',
        'input[placeholder*="password" i]',
    ]
    continue_selectors = [
        'button[type="submit"]',
        'button:has-text("Continue")',
        'button:has-text("Next")',
        'button:has-text("Log in")',
        'button:has-text("Login")',
        'button:has-text("Sign in")',
    ]

    email = first_visible(page, email_selectors)
    password = first_visible(page, password_selectors)

    if email is None and password is None:
        stop(
            f"LOGIN_FORM_CHANGED: unable to locate an email/username or password field at {page.url}",
            3,
        )

    log("AUTH_BOOTSTRAP state=login-required action=credential-login")

    # Whatnot may render email/username first, then reveal the password field only
    # after Continue/Next. Handle that without assuming both fields coexist.
    if email is not None:
        email.fill(EMAIL)

    if password is None:
        advanced = click_first_visible(page, continue_selectors)
        if not advanced and email is not None:
            try:
                email.press("Enter")
                advanced = True
            except Exception:
                pass
        if not advanced:
            stop("LOGIN_FORM_CHANGED: unable to advance past the Whatnot email/username step", 3)

        page.wait_for_timeout(1500)
        if challenge_present(page):
            stop(
                f"CLOUDFLARE_CHALLENGE: verification was requested during login at {page.url}. "
                "No challenge bypass was attempted; complete it interactively in the persistent profile and retry.",
                4,
            )
        if interaction_required(page):
            stop(
                f"AUTH_INTERACTION_REQUIRED: Whatnot requires MFA/OTP at {page.url}. "
                "Complete the verification interactively once; the persistent Scrapling profile will retain the authenticated session.",
                3,
            )
        password = first_visible(page, password_selectors)
        if password is None:
            stop(
                f"LOGIN_FORM_CHANGED: email/username step advanced but no password field appeared at {page.url}",
                3,
            )

    password.fill(PASSWORD)

    submitted = click_first_visible(
        page,
        [
            'button[type="submit"]',
            'button:has-text("Log in")',
            'button:has-text("Login")',
            'button:has-text("Sign in")',
            'button:has-text("Continue")',
        ],
    )
    if not submitted:
        try:
            password.press("Enter")
            submitted = True
        except Exception:
            pass
    if not submitted:
        stop("LOGIN_FORM_CHANGED: unable to submit Whatnot login form", 3)

    page.wait_for_timeout(2500)

    if challenge_present(page):
        stop(
            f"CLOUDFLARE_CHALLENGE: verification was requested after login at {page.url}. "
            "No challenge bypass was attempted; complete it interactively in the persistent profile and retry.",
            4,
        )
    if interaction_required(page):
        stop(
            f"AUTH_INTERACTION_REQUIRED: Whatnot requires MFA/OTP at {page.url}. "
            "Complete the verification interactively once; the persistent Scrapling profile will retain the authenticated session.",
            3,
        )
    if login_page(page):
        stop(f"LOGIN_FAILED: Whatnot remained on the login page after credential submission ({page.url})", 3)

    log("AUTH_BOOTSTRAP state=authenticated")


def stealthy_session(**kwargs: Any):
    options: dict[str, Any] = {
        "headless": kwargs.get("headless", True),
        "disable_resources": kwargs.get("disable_resources", False),
        "google_search": False,
        "locale": kwargs.get("locale", "en-US"),
        # Do not automate anti-bot challenges. We detect them and fail closed.
        "solve_cloudflare": False,
        "block_webrtc": SCRAPLING_BLOCK_WEBRTC,
        "hide_canvas": SCRAPLING_HIDE_CANVAS,
        "allow_webgl": SCRAPLING_ALLOW_WEBGL,
    }

    if USE_CDP and CDP_URL:
        options["cdp_url"] = CDP_URL
        log(f"engine=StealthySession transport=cdp-diagnostic cdp={CDP_URL} solve_cloudflare=false")
    else:
        options["real_chrome"] = kwargs.get("real_chrome", True)
        if kwargs.get("executable_path"):
            options["executable_path"] = kwargs["executable_path"]
        if kwargs.get("user_data_dir"):
            options["user_data_dir"] = kwargs["user_data_dir"]
        profile = options.get("user_data_dir") or "(temporary)"
        log(
            "engine=StealthySession transport=owned-browser "
            f"profile={profile} solve_cloudflare=false "
            f"block_webrtc={str(SCRAPLING_BLOCK_WEBRTC).lower()} "
            f"hide_canvas={str(SCRAPLING_HIDE_CANVAS).lower()} "
            f"allow_webgl={str(SCRAPLING_ALLOW_WEBGL).lower()}"
        )

    return StealthySession(**options)


def main() -> None:
    module = load_base_module()
    # Keep show/order/shipment/ledger extraction in one source of truth. Only the
    # session constructor and authentication validator are replaced here.
    module.DynamicSession = stealthy_session
    module.check_login = ensure_authenticated
    module.main()


if __name__ == "__main__":
    main()

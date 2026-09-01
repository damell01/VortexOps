#!/usr/bin/env python3
"""
Whatnot seller dashboard scraper — Python/Playwright backend.

Dispatched by scripts/whatnot-runner.cjs when WHATNOT_BROWSER_BACKEND=scrapling.
Mirrors the mode/env/exit-code/JSON contract of scripts/whatnot-scraper.cjs so
app/Services/WhatnotScraper.php never needs to know which backend ran.

Modes (WHATNOT_MODE):
  analytics | orders-batch | shipments-batch | ledger

Env vars:
  WHATNOT_EMAIL, WHATNOT_PASSWORD    seller credentials (not used for automatic
                                      login here — see "Auth" below)
  WHATNOT_MODE                       analytics | orders-batch | shipments-batch | ledger
  WHATNOT_LIMIT                      max shows for analytics (default 50)
  WHATNOT_CHANNEL_NAME                whatnot_username to switch to before scraping
  WHATNOT_ORDER_SOURCES_FILE          JSON file of [{live_id, show_key}] for orders-batch
  WHATNOT_LEDGER_FROM / WHATNOT_LEDGER_TO   date window for ledger mode
  WHATNOT_HEADLESS                    "1"/"true" to run headless (default: headed)
  WHATNOT_DEBUG                       "1" for verbose stderr diagnostics
  WHATNOT_PROFILE_DIR                 override the persistent profile directory
                                       (default: storage/whatnot-browser-profile)

Exit codes (same contract as whatnot-scraper.cjs):
  0  success — JSON result on stdout
  1  misc runtime failure
  2  selector / page-layout miss
  3  auth required — LOGIN_REQUIRED or CHALLENGE_REQUIRED
  4  rate limited

── Scope note ───────────────────────────────────────────────────────────────
This backend classifies a Cloudflare/anti-bot challenge and STOPS the run
(exit 3) when it sees one. It does not attempt to solve, auto-answer, or
bypass Turnstile/Cloudflare challenges, and it launches a plain, unmodified
Chromium — it does not patch or spoof the browser's automation fingerprint.
That is a deliberate boundary, not a gap to fill in later: when a challenge
shows up, a human opens the persistent profile headed
(WHATNOT_HEADLESS=false) and clears it by hand, the same way an unexpected
login/2FA prompt already has to be handled today.

── What's implemented vs. stubbed ──────────────────────────────────────────
Session startup, auth/challenge classification, and channel verification
(CHANNEL_CONTEXT_VERIFIED) are fully implemented below and are the part of
this file that guards correctness and safety, so they're done first and
carefully. The four data-extraction modes are intentionally left as explicit
NotImplemented stops rather than a best-effort port: scripts/whatnot-scraper.cjs's
extraction functions encode a long list of empirically-discovered DOM
fallbacks tuned against the live site, and this environment has no
authenticated Whatnot session to validate a re-implementation against. Silently
shipping an unverified port of code that feeds revenue/payout data would be
worse than shipping nothing. Port and validate one mode at a time, headed,
against real data before trusting it — see the project's own staged rollout
notes for this integration (session -> dashboard -> channel verify ->
analytics limit=1 -> ... -> ledger).
"""
import json
import os
import sys
import time
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright, TimeoutError as PWTimeoutError
except ImportError:
    sys.stderr.write(
        "[whatnot:scrapling] playwright is not installed.\n"
        "  python3 -m pip install -r requirements-whatnot-scrapling.txt\n"
        "  python3 -m playwright install chromium\n"
    )
    sys.exit(1)

# ── Config ───────────────────────────────────────────────────────────────

MODE = os.environ.get("WHATNOT_MODE", "analytics")
DEBUG = os.environ.get("WHATNOT_DEBUG") == "1"
HEADLESS = os.environ.get("WHATNOT_HEADLESS", "false").strip().lower() in ("1", "true", "yes")
LIMIT = int(os.environ.get("WHATNOT_LIMIT", "50") or "50")
CHANNEL_NAME = os.environ.get("WHATNOT_CHANNEL_NAME", "").strip()

REPO_ROOT = Path(__file__).resolve().parent.parent
PROFILE_DIR = Path(os.environ.get("WHATNOT_PROFILE_DIR") or (REPO_ROOT / "storage" / "whatnot-browser-profile"))

URLS = {
    "home": "https://www.whatnot.com",
    "login": "https://www.whatnot.com/login",
    "dashboard_home": "https://www.whatnot.com/dashboard/home",
    "analytics": "https://www.whatnot.com/dashboard/analytics/overview",
    "dashboard": "https://www.whatnot.com/dashboard",
}

EXIT_OK = 0
EXIT_RUNTIME = 1
EXIT_SELECTOR_MISS = 2
EXIT_AUTH_REQUIRED = 3
EXIT_RATE_LIMITED = 4


# ── Logging — diagnostics only ever go to stderr. stdout is reserved for the
#    single final JSON result, exactly like whatnot-scraper.cjs's writeJsonAndExit. ──

def log(*parts):
    if DEBUG:
        sys.stderr.write("[whatnot:scrapling] " + " ".join(str(p) for p in parts) + "\n")


def info(*parts):
    sys.stderr.write("[whatnot] " + " ".join(str(p) for p in parts) + "\n")


def fail(exit_code, message):
    info(message)
    sys.exit(exit_code)


def emit(value):
    json.dump(value, sys.stdout)
    sys.stdout.write("\n")
    sys.stdout.flush()
    sys.exit(EXIT_OK)


def debug_shot(page, name):
    if not DEBUG:
        return
    try:
        page.screenshot(path=f"/tmp/whatnot-scrapling-debug-{name}.png")
        log("screenshot saved:", f"/tmp/whatnot-scrapling-debug-{name}.png")
    except Exception as e:
        log("screenshot failed:", str(e))


# ── Session / browser setup ─────────────────────────────────────────────
# One persistent Chromium profile, reused across runs, exactly like the Node
# backend's storage/whatnot-cookies.json + persistent context. Callers must
# hold app/Services/WhatnotScraper.php's shared browser lock before invoking
# this script — see withBrowserLock() in that file, which wraps BOTH backends
# so Playwright and this script never open the profile concurrently.

def create_session(playwright):
    PROFILE_DIR.mkdir(parents=True, exist_ok=True)
    log("launching persistent context at", str(PROFILE_DIR), "headless=", HEADLESS)

    context = playwright.chromium.launch_persistent_context(
        user_data_dir=str(PROFILE_DIR),
        headless=HEADLESS,
        viewport={"width": 1400, "height": 1000},
        timeout=30_000,
    )
    context.set_default_timeout(20_000)
    page = context.pages[0] if context.pages else context.new_page()
    return context, page


# ── Challenge / auth classification ─────────────────────────────────────
# Every navigation in this script goes through safe_goto(), which classifies
# the resulting page before anything else touches it. classify_page() only
# ever detects and reports state — it never tries to act on a challenge.

CHALLENGE_MARKERS = (
    "checking your browser",
    "just a moment",
    "attention required",
    "verify you are human",
    "verify you are a human",
    "cf-turnstile",
    "cf-chl",
    "ray id",
    "review the security of your connection",
)

RATE_LIMIT_MARKERS = (
    "too many requests",
    "rate limit exceeded",
)

BUYER_MODE_MARKERS = (
    "for brands",
    "start selling on whatnot",
)


def classify_page(page):
    """Returns one of: AUTHENTICATED, LOGIN_REQUIRED, CHALLENGE_REQUIRED,
    RATE_LIMITED, UNEXPECTED_PAGE."""
    url = page.url

    if "/login" in url or "/signin" in url or "/auth" in url:
        return "LOGIN_REQUIRED"

    try:
        body_text = (page.locator("body").inner_text(timeout=5000) or "").strip()
    except Exception:
        body_text = ""
    lower = body_text.lower()

    try:
        title = (page.title() or "").lower()
    except Exception:
        title = ""

    if any(marker in lower for marker in CHALLENGE_MARKERS) or any(marker in title for marker in CHALLENGE_MARKERS):
        return "CHALLENGE_REQUIRED"

    if any(marker in lower for marker in RATE_LIMIT_MARKERS):
        return "RATE_LIMITED"

    # Buyer-mode marketing page ("For Brands / Start Selling on Whatnot") means
    # we're not in the seller dashboard at all — same condition
    # scripts/whatnot-scraper.cjs's ensureSellerMode() detects and recovers
    # from. That recovery flow isn't ported here yet, so surface it as an
    # unexpected page rather than silently treating it as authenticated.
    if any(marker in lower for marker in BUYER_MODE_MARKERS):
        return "UNEXPECTED_PAGE"

    if len(body_text) < 30:
        return "UNEXPECTED_PAGE"

    return "AUTHENTICATED"


def fail_for_state(state, url):
    if state == "LOGIN_REQUIRED":
        fail(
            EXIT_AUTH_REQUIRED,
            f"LOGIN_REQUIRED at {url} — persistent profile is not authenticated. "
            "Not auto-submitting credentials from here; run the existing login/cookie-bootstrap "
            "flow (php artisan against the local backend, or WHATNOT_MODE=dump-cookies) to "
            "re-authenticate the shared profile, then re-run.",
        )
    if state == "CHALLENGE_REQUIRED":
        fail(
            EXIT_AUTH_REQUIRED,
            f"CHALLENGE_REQUIRED at {url} — a Cloudflare/anti-bot challenge was shown. "
            "This backend does not solve or bypass challenges. Open the persistent profile headed "
            f"(WHATNOT_HEADLESS=false) at {PROFILE_DIR} and clear it by hand, then re-run.",
        )
    if state == "RATE_LIMITED":
        fail(EXIT_RATE_LIMITED, f"RATE_LIMITED at {url} — backing off, re-run later.")
    if state == "UNEXPECTED_PAGE":
        fail(EXIT_SELECTOR_MISS, f"UNEXPECTED_PAGE at {url} — page did not render the expected authenticated content.")
    fail(EXIT_RUNTIME, f"Unknown page state {state!r} at {url}")


def safe_goto(page, url, *, retry_on_timeout=True):
    """The only sanctioned way any mode navigates. Classifies the destination
    and stops the run on anything but AUTHENTICATED."""
    try:
        page.goto(url, wait_until="domcontentloaded", timeout=30_000)
    except PWTimeoutError:
        if retry_on_timeout:
            log("navigation timeout, retrying once:", url)
            page.goto(url, wait_until="domcontentloaded", timeout=30_000)
        else:
            raise

    # Let the SPA render before judging the page — mirrors performLogin()'s
    # waitForFunction(body text length) gate in whatnot-scraper.cjs.
    try:
        page.wait_for_function("(document.body.innerText || '').trim().length > 30", timeout=8000)
    except PWTimeoutError:
        pass

    state = classify_page(page)
    log("safe_goto:", url, "->", state)
    if state != "AUTHENTICATED":
        debug_shot(page, "safe-goto-" + state.lower())
        fail_for_state(state, page.url)
    return page


# ── Channel identification / verification ───────────────────────────────
# Ported from getActiveChannelUsername()/switchToChannel() in
# whatnot-scraper.cjs. This is business logic (which seller channel is
# active), not challenge handling, so it's ported directly rather than
# stubbed — but it's still unverified against the live site from this
# environment, so treat it as untested until run headed against real data.

def normalize_channel_key(s):
    return "".join(ch for ch in (s or "").lower() if ch.isalnum())


def get_active_channel_username(page):
    try:
        return page.evaluate(
            """
            () => {
              for (const a of document.querySelectorAll('a[href^="/user/"]')) {
                const text = (a.textContent || '').trim();
                if (text.startsWith('@')) {
                  const m = (a.getAttribute('href') || '').match(/^\\/user\\/([^/?#]+)/);
                  if (m) return m[1];
                }
              }
              return null;
            }
            """
        )
    except Exception:
        return None


def switch_to_channel(page, channel_name):
    """Best-effort channel switch via the profile drawer's "Switch Role" list.
    Never claims success on its own — the caller (verify_channel_context)
    always re-reads the active channel afterward and fails closed if it
    doesn't match."""
    if not channel_name:
        return

    log(f'switching to channel: "{channel_name}"')
    debug_shot(page, "role-switch-pre")

    avatar_selectors = [
        'button[aria-haspopup="menu"]',
        'button[aria-haspopup="dialog"]',
        'button[aria-label*="profile" i]',
        'button[aria-label*="account" i]',
        'header button:has(img)',
        'nav button:has(img)',
        'button[aria-label*="menu" i]',
    ]

    drawer_opened = False
    for sel in avatar_selectors:
        trigger = page.locator(sel).first
        try:
            if not trigger.is_visible(timeout=1000):
                continue
        except Exception:
            continue
        try:
            trigger.click(timeout=5000)
        except Exception:
            continue
        page.wait_for_timeout(1200)
        try:
            role_visible = page.get_by_text("Switch Role", exact=False).first.is_visible(timeout=1000)
        except Exception:
            role_visible = False
        if role_visible:
            log("switchToChannel: drawer opened via", sel)
            drawer_opened = True
            break
        page.keyboard.press("Escape")
        page.wait_for_timeout(300)

    if not drawer_opened:
        info("switchToChannel: could not open the profile/role drawer — channel switch will likely fail")

    switch_role = page.get_by_text("Switch Role", exact=False).first
    try:
        if switch_role.is_visible(timeout=1000):
            switch_role.click(timeout=5000)
            page.wait_for_timeout(1000)
    except Exception:
        pass

    debug_shot(page, "role-switch-list")

    variants = list(dict.fromkeys([
        channel_name,
        "".join(f" {c}" if c.isupper() else c for c in channel_name).strip(),
    ]))

    clicked = False
    for variant in variants:
        target = page.get_by_text(variant, exact=False).first
        try:
            if target.is_visible(timeout=1000):
                target.click(timeout=5000)
                clicked = True
                break
        except Exception:
            continue

    if not clicked:
        info(f'switchToChannel: no visible option matched "{channel_name}" (tried: {variants})')
        debug_shot(page, "role-switch-no-match")
        return

    try:
        page.wait_for_function("document.body.innerText.includes('Seller Hub')", timeout=6000)
    except PWTimeoutError:
        pass
    debug_shot(page, "role-switch-done")


def verify_channel_context(page, requested_channel):
    """Fail-closed CHANNEL_CONTEXT_VERIFIED gate. Never infers the active
    seller from the requested name — only from what the loaded page actually
    reports (get_active_channel_username), same as items 7-8 of the
    integration plan."""
    active = get_active_channel_username(page)

    if requested_channel:
        requested_norm = normalize_channel_key(requested_channel)
        active_norm = normalize_channel_key(active)
        if active_norm != requested_norm:
            info(f"requested=@{requested_channel} active=@{active or '(unknown)'} — mismatch, attempting switch")
            switch_to_channel(page, requested_channel)
            active = get_active_channel_username(page)
            active_norm = normalize_channel_key(active)
            if active_norm != requested_norm:
                fail(
                    EXIT_AUTH_REQUIRED,
                    f"CHANNEL_CONTEXT_VERIFIED failed: requested=@{requested_channel} "
                    f"active=@{active or '(unknown)'} — refusing to scrape under an unverified channel.",
                )
    elif not active:
        fail(
            EXIT_AUTH_REQUIRED,
            "CHANNEL_CONTEXT_VERIFIED failed: could not positively read any active seller channel "
            "from the page — refusing to proceed.",
        )

    info(f"CHANNEL_CONTEXT_VERIFIED requested=@{requested_channel or active} active=@{active}")
    return active


# ── Mode dispatch ─────────────────────────────────────────────────────────
# Each mode is independent and receives the already-verified page/channel.
# See the module docstring for why extraction itself is stubbed rather than
# a blind port of whatnot-scraper.cjs's DOM heuristics.

def mode_analytics(page, active_channel):
    fail(
        EXIT_RUNTIME,
        "analytics: extraction not implemented in the scrapling backend yet. "
        "Port extractAnalyticsMetrics()/extractShowsListFromDom() from scripts/whatnot-scraper.cjs, "
        "validate headed (WHATNOT_HEADLESS=false) with WHATNOT_LIMIT=1 against a real show, "
        "then raise the limit. Do not enable WHATNOT_BROWSER_BACKEND=scrapling for analytics in "
        "production until that validation has passed.",
    )


def mode_orders_batch(page, active_channel):
    fail(
        EXIT_RUNTIME,
        "orders-batch: extraction not implemented in the scrapling backend yet. "
        "Port the /dashboard/orders?source=<live_id> table walk from whatnot-scraper.cjs and "
        "validate against one real show (one entry in WHATNOT_ORDER_SOURCES_FILE) before batching.",
    )


def mode_shipments_batch(page, active_channel):
    fail(
        EXIT_RUNTIME,
        "shipments-batch: not implemented — there is no shipments extraction in "
        "scripts/whatnot-scraper.cjs to port from yet either. Implement and validate the Node "
        "version first, or build this mode directly against a real shipments page.",
    )


def mode_ledger(page, active_channel):
    fail(
        EXIT_RUNTIME,
        "ledger: extraction not implemented in the scrapling backend yet. "
        "Port the ledger table walk from whatnot-scraper.cjs and validate against one real "
        "<=31-day window before wiring it into scheduled imports.",
    )


DISPATCH = {
    "analytics": mode_analytics,
    "orders-batch": mode_orders_batch,
    "shipments-batch": mode_shipments_batch,
    "ledger": mode_ledger,
}


def main():
    if MODE not in DISPATCH:
        fail(EXIT_RUNTIME, f"Unsupported mode for the scrapling backend: {MODE!r}")

    start = time.time()
    info(f"engine=scrapling mode={MODE} requested_channel={CHANNEL_NAME or '(default)'} headless={HEADLESS}")

    with sync_playwright() as playwright:
        context, page = create_session(playwright)
        try:
            safe_goto(page, URLS["dashboard_home"])
            info(f"authenticated dashboard reached: {page.url}")

            active_channel = verify_channel_context(page, CHANNEL_NAME)

            # Recheck right before extraction, not only once at startup — a
            # role switch or session hiccup between here and mode start
            # should still be caught (item 14 of the integration plan).
            state = classify_page(page)
            if state != "AUTHENTICATED":
                fail_for_state(state, page.url)

            result = DISPATCH[MODE](page, active_channel)
        finally:
            context.close()

    info(f"mode={MODE} complete in {time.time() - start:.1f}s")
    emit(result)


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception as e:
        info(f"unhandled error: {e}")
        sys.exit(EXIT_RUNTIME)

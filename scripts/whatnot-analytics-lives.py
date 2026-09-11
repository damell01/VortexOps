from __future__ import annotations

import importlib.util
import os
import re
from pathlib import Path
from typing import Any


HERE = Path(__file__).resolve().parent
BASE_HELPER = HERE / "whatnot-analytics-hardened.py"


def load_base_helper():
    spec = importlib.util.spec_from_file_location("vortexops_whatnot_analytics_base", BASE_HELPER)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Unable to load {BASE_HELPER}")
    helper = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(helper)
    return helper


base = load_base_helper()
clean = base.clean
normalize_identity = base.normalize_identity
parse_date = base.parse_date
extract_show = base.extract_show
has_useful_data = base.has_useful_data


def tab_locator(page, name: str):
    lowered = name.lower()
    if lowered == "current":
        return page.locator('button[data-testid="tab-current"][role="tab"]').first
    if lowered == "upcoming":
        return page.locator('button[data-testid="tab-upcoming"][role="tab"]').first
    return page.locator('ul[role="tablist"] button[role="tab"]', has_text=re.compile(r"^Past$", re.I)).first


def select_tab(module, page, name: str) -> bool:
    try:
        tab = tab_locator(page, name)
        if not tab.count():
            tab = page.get_by_role("tab", name=re.compile(rf"^{re.escape(name)}$", re.I)).first
        if not tab.count():
            module.info(f"analytics: {name} tab not found on /dashboard/lives")
            return False
        if tab.get_attribute("aria-selected") != "true":
            tab.click(timeout=8000)
        page.wait_for_timeout(1800)
        selected = tab.get_attribute("aria-selected")
        if selected != "true":
            try:
                tab.evaluate("el => el.click()")
                page.wait_for_timeout(1800)
                selected = tab.get_attribute("aria-selected")
            except Exception:
                pass
        ok = selected == "true"
        module.info(f"analytics: {name} tab {'selected' if ok else 'did not become selected'}")
        return ok
    except Exception as exc:
        module.info(f"analytics: unable to select {name} tab error={exc}")
        return False


def extract_show_rows(page) -> list[dict[str, Any]]:
    try:
        rows = page.locator('[data-testid="show-list-item"]').evaluate_all(r"""
        rows => rows.map(row => {
          const title = row.querySelector('[data-testid="show-list-item-title"]')?.textContent?.trim() || null;
          const open = row.querySelector('a[href^="/dashboard/live/"]');
          const analytics = [...row.querySelectorAll('a')].find(a => /^\s*See Analytics\s*$/i.test(a.textContent || ''));
          const shipments = [...row.querySelectorAll('a')].find(a => /^\s*View Shipments\s*$/i.test(a.textContent || ''));
          const openUrl = open?.getAttribute('href') || null;
          const liveId = (openUrl?.match(/\/dashboard\/live\/([0-9a-f-]{36})/i) || [])[1] || null;
          return {
            live_id: liveId,
            title,
            text: (row.innerText || '').replace(/\s+/g, ' ').trim(),
            open_url: openUrl,
            analytics_url: analytics?.getAttribute('href') || null,
            shipments_url: shipments?.getAttribute('href') || null,
          };
        })
        """) or []
        for row in rows:
            row["show_date"] = parse_date(row.get("text"))
        return rows
    except Exception:
        return []


def verify_identity(module, row: dict[str, Any], live_id: str, expected_title: str, expected_date: str | None) -> bool:
    actual_title = normalize_identity(row.get("title"))
    wanted_title = normalize_identity(expected_title)
    row_date = row.get("show_date") or parse_date(row.get("text"))
    title_ok = not wanted_title or actual_title == wanted_title
    date_ok = not expected_date or not row_date or row_date == expected_date
    module.info(
        f"analytics: exact Seller Hub row found uuid={live_id} title={row.get('title')!r} "
        f"date={row_date or 'unknown'} analytics_link={'yes' if row.get('analytics_url') else 'no'}"
    )
    if not title_ok:
        module.info("analytics: exact UUID row title does not match database show; refusing action")
        return False
    if not date_ok:
        module.info("analytics: exact UUID row date does not match database show; refusing action")
        return False
    return True


def find_target_row(module, page, live_id: str, expected_title: str, expected_date: str | None) -> tuple[dict[str, Any] | None, bool]:
    """Find an exact UUID in the selected tab.

    Returns (row, exhausted). exhausted is true only after the list stopped growing
    for several passes or the full 30-pass scan completed. PHP may only interpret
    absence as evidence when exhausted is true.
    """
    wanted = live_id.lower()
    seen: dict[str, dict[str, Any]] = {}
    stable_passes = 0
    previous_count = -1

    for attempt in range(1, 31):
        module.check_login(page)
        rows = extract_show_rows(page)
        for row in rows:
            candidate = clean(row.get("live_id")).lower()
            if candidate:
                seen[candidate] = row

        if wanted in seen:
            row = seen[wanted]
            if not verify_identity(module, row, live_id, expected_title, expected_date):
                return None, False
            return row, True

        if len(seen) == previous_count:
            stable_passes += 1
        else:
            stable_passes = 0
        previous_count = len(seen)

        if attempt in {1, 5, 10, 20, 30}:
            module.info(f"analytics: Seller Hub history scan pass {attempt}/30 rows_seen={len(seen)} target={live_id}")

        if stable_passes >= 5 and attempt >= 8:
            module.info(f"analytics: selected Seller Hub tab exhausted after {attempt} passes rows_seen={len(seen)}")
            return None, True

        try:
            page.evaluate(r"""
            () => {
              window.scrollTo(0, document.body.scrollHeight);
              const scrollers = [...document.querySelectorAll('*')]
                .filter(el => el.scrollHeight > el.clientHeight + 200);
              for (const el of scrollers.slice(-8)) el.scrollTop = el.scrollHeight;
            }
            """)
        except Exception:
            pass
        page.wait_for_timeout(1500)

    module.info(f"analytics: selected Seller Hub tab exhausted at 30 passes rows_seen={len(seen)}")
    return None, True


def find_target_in_short_tab(module, page, tab_name: str, live_id: str) -> tuple[bool, bool]:
    """Return (found, verified_scan) for Current/Upcoming."""
    if not select_tab(module, page, tab_name):
        return False, False

    wanted = live_id.lower()
    stable = 0
    previous_count = -1
    seen_ids: set[str] = set()

    for _ in range(6):
        module.check_login(page)
        rows = extract_show_rows(page)
        for row in rows:
            candidate = clean(row.get("live_id")).lower()
            if candidate:
                seen_ids.add(candidate)
        if wanted in seen_ids:
            module.info(f"analytics: uuid={live_id} exists in Seller Hub {tab_name} tab; preserving record")
            return True, True
        if len(seen_ids) == previous_count:
            stable += 1
        else:
            stable = 0
        previous_count = len(seen_ids)
        if stable >= 2:
            break
        page.wait_for_timeout(700)

    module.info(f"analytics: uuid={live_id} absent from Seller Hub {tab_name} tab rows_seen={len(seen_ids)}")
    return False, True


def scroll_to_bottom(page) -> None:
    try:
        page.evaluate(r"""
        () => {
          window.scrollTo(0, document.body.scrollHeight);
          const scrollers = [...document.querySelectorAll('*')]
            .filter(el => el.scrollHeight > el.clientHeight + 200);
          for (const el of scrollers.slice(-8)) el.scrollTop = el.scrollHeight;
        }
        """)
    except Exception:
        pass


def scan_selected_tab_index(module, page, tab_name: str, max_passes: int, stable_needed: int) -> tuple[dict[str, dict[str, Any]], bool, bool]:
    """Scan one Seller Hub tab once and return every UUID observed.

    The third return value is exhausted. Absence is only safe evidence when the
    tab selection succeeded AND the list stopped growing. Hitting max_passes while
    rows are still growing is deliberately fail-closed.
    """
    if not select_tab(module, page, tab_name):
        return {}, False, False

    seen: dict[str, dict[str, Any]] = {}
    previous_count = -1
    stable_passes = 0

    for attempt in range(1, max_passes + 1):
        module.check_login(page)
        for row in extract_show_rows(page):
            live_id = clean(row.get("live_id")).lower()
            if live_id:
                row["live_id"] = live_id
                seen[live_id] = row

        if len(seen) == previous_count:
            stable_passes += 1
        else:
            stable_passes = 0
        previous_count = len(seen)

        if attempt == 1 or attempt % 10 == 0:
            module.info(
                f"reconcile-index: {tab_name} scan pass {attempt}/{max_passes} "
                f"rows_seen={len(seen)} stable={stable_passes}"
            )

        if stable_passes >= stable_needed:
            module.info(
                f"reconcile-index: {tab_name} exhausted after {attempt} passes "
                f"rows_seen={len(seen)}"
            )
            return seen, True, True

        scroll_to_bottom(page)
        page.wait_for_timeout(900 if tab_name.lower() == "past" else 500)

    module.info(
        f"reconcile-index: {tab_name} reached max passes while list may still be growing; "
        f"rows_seen={len(seen)} absence will NOT be treated as verified"
    )
    return seen, True, False


def reconcile_index(module, session):
    """Build a channel-wide Seller Hub UUID index in one browser traversal."""
    max_passes = max(20, min(200, int(os.getenv("WHATNOT_RECONCILE_MAX_PASSES", "100"))))
    result: dict[str, Any] = {
        "_seller_hub_index": True,
        "past": [],
        "current_ids": [],
        "upcoming_ids": [],
        "past_selected": False,
        "past_exhausted": False,
        "current_verified": False,
        "upcoming_verified": False,
    }

    module.info(f"reconcile-index: building one Seller Hub index max_passes={max_passes}")

    def action(page):
        module.prepare(page)
        page.goto(f"{module.BASE}/dashboard/lives", wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(1800)
        module.check_login(page)

        past, past_selected, past_exhausted = scan_selected_tab_index(
            module, page, "Past", max_passes=max_passes, stable_needed=5
        )
        current, current_selected, current_exhausted = scan_selected_tab_index(
            module, page, "Current", max_passes=12, stable_needed=2
        )
        upcoming, upcoming_selected, upcoming_exhausted = scan_selected_tab_index(
            module, page, "Upcoming", max_passes=20, stable_needed=3
        )

        result["past"] = list(past.values())
        result["current_ids"] = sorted(current.keys())
        result["upcoming_ids"] = sorted(upcoming.keys())
        result["past_selected"] = past_selected
        result["past_exhausted"] = past_exhausted
        result["current_verified"] = current_selected and current_exhausted
        result["upcoming_verified"] = upcoming_selected and upcoming_exhausted
        result["counts"] = {
            "past": len(past),
            "current": len(current),
            "upcoming": len(upcoming),
        }

    session.fetch(
        f"{module.BASE}/dashboard/home",
        page_action=action,
        timeout=300000,
        network_idle=False,
        google_search=False,
    )

    module.info(
        "reconcile-index: complete "
        f"past={len(result['past'])} current={len(result['current_ids'])} "
        f"upcoming={len(result['upcoming_ids'])} past_exhausted={result['past_exhausted']}"
    )
    return result


def click_target_analytics(module, page, target: dict[str, Any]) -> bool:
    open_url = clean(target.get("open_url"))
    expected_href = clean(target.get("analytics_url"))
    if not open_url or not expected_href:
        return False

    for _ in range(16):
        row = page.locator('[data-testid="show-list-item"]', has=page.locator(f'a[href="{open_url}"]')).first
        try:
            if row.count() and row.is_visible(timeout=400):
                link = row.locator('a', has_text=re.compile(r"^See Analytics$", re.I)).first
                if link.count():
                    module.info(f"analytics: clicking See Analytics for uuid={target.get('live_id')} href={expected_href}")
                    link.click(timeout=8000)
                    page.wait_for_timeout(2500)
                    return True
        except Exception:
            pass
        try:
            page.mouse.wheel(0, -1400)
        except Exception:
            pass
        page.wait_for_timeout(400)

    module.info("analytics: exact show row could not be re-rendered for See Analytics click")
    return False


def wait_for_metrics(module, page, timeout_ms: int = 18000) -> dict[str, Any] | None:
    elapsed = 0
    stable = 0
    previous = None
    last = None
    while elapsed < timeout_ms:
        module.check_login(page)
        last = extract_show(page)
        signature = base.analytics_fingerprint(last)
        if signature == previous:
            stable += 1
        else:
            previous = signature
            stable = 1
        if has_useful_data(last) and stable >= 3:
            return last
        page.wait_for_timeout(500)
        elapsed += 500
    return last


def analytics(module, session):
    if not module.UUID_RE.fullmatch(module.START_UUID):
        module.fail("ANALYTICS_SEED_REQUIRED: WHATNOT_START_UUID is required")

    expected_title = clean(os.getenv("WHATNOT_EXPECTED_TITLE", ""))
    expected_date = clean(os.getenv("WHATNOT_EXPECTED_DATE", "")) or None
    live_id = module.START_UUID
    rows: list[dict[str, Any]] = []

    module.info(f"analytics: Seller Hub row-navigation seed={live_id} expected_title={expected_title!r} date={expected_date or 'any'}")

    def action(page):
        module.prepare(page)
        page.goto(f"{module.BASE}/dashboard/lives", wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(1800)
        module.check_login(page)

        if not select_tab(module, page, "Past"):
            return

        target, past_exhausted = find_target_row(module, page, live_id, expected_title, expected_date)
        if not target:
            if not past_exhausted:
                module.info(f"analytics: Past scan was not verifiable for uuid={live_id}; preserving record")
                return

            in_current, current_ok = find_target_in_short_tab(module, page, "Current", live_id)
            if in_current:
                return
            in_upcoming, upcoming_ok = find_target_in_short_tab(module, page, "Upcoming", live_id)
            if in_upcoming:
                return

            if current_ok and upcoming_ok:
                rows.append({
                    "whatnot_live_id": live_id,
                    "title": expected_title,
                    "show_date": expected_date,
                    "detail_url": f"{module.BASE}/dashboard/live/{live_id}",
                    "_seller_hub_verified": True,
                    "_seller_hub_absent_all_tabs": True,
                })
                module.info(f"analytics: uuid={live_id} absent from Past, Current, and Upcoming after verified scans")
            else:
                module.info(f"analytics: unable to verify every Seller Hub tab for uuid={live_id}; preserving record")
            return

        if not target.get("analytics_url"):
            rows.append({
                "whatnot_live_id": live_id,
                "title": expected_title or target.get("title"),
                "show_date": expected_date,
                "detail_url": f"{module.BASE}/dashboard/live/{live_id}",
                "_seller_hub_verified": True,
                "_analytics_unavailable": True,
                "_seller_hub_text": target.get("text"),
            })
            module.info(f"analytics: exact Past row has no See Analytics action uuid={live_id}")
            return

        if not click_target_analytics(module, page, target):
            return

        module.info(f"analytics: Whatnot navigated See Analytics to {page.url}")
        row = wait_for_metrics(module, page)
        if not row or not has_useful_data(row):
            module.info("analytics: See Analytics destination loaded but usable metrics did not stabilize")
            return

        row["whatnot_live_id"] = live_id
        if expected_title:
            row["title"] = expected_title
        if expected_date:
            row["show_date"] = expected_date
        row["detail_url"] = f"{module.BASE}/dashboard/live/{live_id}"

        row.pop("_preview", None)
        row.pop("_titles", None)
        row.pop("_dates", None)
        rows.append(row)
        module.info(
            f"analytics: verified Seller Hub row -> See Analytics succeeded uuid={live_id} "
            f"gross={row.get('gross_revenue')} net={row.get('whatnot_net')}"
        )

    session.fetch(
        f"{module.BASE}/dashboard/home",
        page_action=action,
        timeout=120000,
        network_idle=False,
        google_search=False,
    )

    module.info(f"analytics: collected {len(rows)} show(s)")
    return rows


def install(module) -> None:
    if os.getenv("WHATNOT_MODE", "").strip() == "reconcile-index":
        # Reuse the base module's well-tested analytics dispatch/session lifecycle
        # while replacing the analytics function with a one-pass channel index.
        module.MODE = "analytics"
        module.analytics = lambda session: reconcile_index(module, session)
        return

    module.analytics = lambda session: analytics(module, session)

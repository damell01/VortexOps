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


def click_past_tab(module, page) -> bool:
    try:
        past = page.locator('ul[role="tablist"] button[role="tab"]', has_text=re.compile(r"^Past$", re.I)).first
        if not past.count():
            past = page.get_by_role("tab", name=re.compile(r"^Past$", re.I)).first
        if not past.count():
            module.info("analytics: Past tab not found on /dashboard/lives")
            return False
        if past.get_attribute("aria-selected") != "true":
            past.click(timeout=8000)
        page.wait_for_timeout(1800)
        module.info("analytics: Past tab selected")
        return True
    except Exception as exc:
        module.info(f"analytics: unable to select Past tab error={exc}")
        return False


def extract_show_rows(page) -> list[dict[str, Any]]:
    try:
        return page.locator('[data-testid="show-list-item"]').evaluate_all(r"""
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
    except Exception:
        return []


def find_target_row(module, page, live_id: str, expected_title: str, expected_date: str | None) -> dict[str, Any] | None:
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
            actual_title = normalize_identity(row.get("title"))
            wanted_title = normalize_identity(expected_title)
            row_date = parse_date(row.get("text"))
            title_ok = not wanted_title or actual_title == wanted_title
            date_ok = not expected_date or not row_date or row_date == expected_date
            module.info(
                f"analytics: exact Seller Hub row found uuid={live_id} title={row.get('title')!r} "
                f"date={row_date or 'unknown'} analytics_link={'yes' if row.get('analytics_url') else 'no'}"
            )
            if not title_ok:
                module.info("analytics: exact UUID row title does not match database show; refusing click")
                return None
            if not date_ok:
                module.info("analytics: exact UUID row date does not match database show; refusing click")
                return None
            if not row.get("analytics_url"):
                module.info("analytics: exact UUID row has no See Analytics link yet")
                return None
            return row

        if len(seen) == previous_count:
            stable_passes += 1
        else:
            stable_passes = 0
        previous_count = len(seen)

        if attempt in {1, 5, 10, 20, 30}:
            module.info(f"analytics: Seller Hub history scan pass {attempt}/30 rows_seen={len(seen)} target={live_id}")

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

        # Give lazy loading several stable passes before concluding the show is absent.
        if stable_passes >= 5 and attempt >= 8:
            break

    module.info(f"analytics: requested UUID {live_id} was not found in Seller Hub Past rows (seen={len(seen)})")
    return None


def click_target_analytics(module, page, target: dict[str, Any]) -> bool:
    open_url = clean(target.get("open_url"))
    expected_href = clean(target.get("analytics_url"))
    if not open_url or not expected_href:
        return False

    # The exact UUID's row may have scrolled out of the rendered virtual list.
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

        if not click_past_tab(module, page):
            return

        target = find_target_row(module, page, live_id, expected_title, expected_date)
        if not target:
            return

        if not click_target_analytics(module, page, target):
            return

        module.info(f"analytics: Whatnot navigated See Analytics to {page.url}")
        row = wait_for_metrics(module, page)
        if not row or not has_useful_data(row):
            module.info("analytics: See Analytics destination loaded but usable metrics did not stabilize")
            return

        # Identity comes from the exact /dashboard/lives UUID row that Whatnot supplied.
        # Do not trust the SPA query string alone. Preserve the destination metrics,
        # but stamp the verified source-row identity so PHP can perform its own check.
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
        timeout=90000,
        network_idle=False,
        google_search=False,
    )

    module.info(f"analytics: collected {len(rows)} show(s)")
    return rows


def install(module) -> None:
    module.analytics = lambda session: analytics(module, session)

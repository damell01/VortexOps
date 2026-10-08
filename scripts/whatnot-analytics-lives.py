from __future__ import annotations

import importlib.util
import os
import re
import json
import random
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
    # Whatnot currently renders Upcoming/Past as plain text controls rather than
    # consistently exposing role=tab/data-testid attributes. Prefer semantic
    # selectors when available, then fall back to exact visible text.
    selectors = []
    if lowered == "current":
        selectors = [
            page.locator('button[data-testid="tab-current"][role="tab"]').first,
            page.get_by_role("tab", name=re.compile(r"^Current$", re.I)).first,
            page.get_by_text(re.compile(r"^Current$", re.I), exact=True).first,
        ]
    elif lowered == "upcoming":
        selectors = [
            page.locator('button[data-testid="tab-upcoming"][role="tab"]').first,
            page.get_by_role("tab", name=re.compile(r"^Upcoming$", re.I)).first,
            page.get_by_text(re.compile(r"^Upcoming$", re.I), exact=True).first,
        ]
    else:
        selectors = [
            page.locator('ul[role="tablist"] button[role="tab"]', has_text=re.compile(r"^Past$", re.I)).first,
            page.get_by_role("tab", name=re.compile(r"^Past$", re.I)).first,
            page.get_by_text(re.compile(r"^Past$", re.I), exact=True).first,
        ]
    for candidate in selectors:
        try:
            if candidate.count() and candidate.is_visible(timeout=1000):
                return candidate
        except Exception:
            continue
    return selectors[-1]


def select_tab(module, page, name: str) -> bool:
    try:
        # Seller Hub can finish the route before the tab strip hydrates,
        # especially immediately after a channel-role switch. Wait for the
        # exact Current/Upcoming/Past controls instead of treating the first
        # DOM snapshot as authoritative.
        tab = None
        for attempt in range(20):
            module.check_login(page)
            candidate = tab_locator(page, name)
            try:
                if candidate.count() and candidate.is_visible(timeout=500):
                    tab = candidate
                    break
            except Exception:
                pass
            page.wait_for_timeout(500)
        if tab is None:
            try:
                diag = page.evaluate(r"""
                () => ({
                  url: location.href,
                  text: (document.body?.innerText || '').replace(/\n+/g, ' | ').substring(0, 700),
                  buttons: [...document.querySelectorAll('button,[role="tab"],[role="button"]')]
                    .map(x => (x.innerText || x.textContent || '').trim()).filter(Boolean).slice(0, 40)
                })
                """)
                module.info("analytics: tab diagnostic " + json.dumps(diag, separators=(",", ":")))
            except Exception:
                pass
            module.info(f"analytics: {name} tab not found on /dashboard/lives after hydration wait")
            return False
        selected = tab.get_attribute("aria-selected")
        if selected != "true":
            tab.click(timeout=8000)
        page.wait_for_timeout(1800)
        selected = tab.get_attribute("aria-selected")
        # New Seller Hub tabs may not expose aria-selected. A successful click
        # plus a visible exact tab is sufficient; extraction verifies the rows.
        if selected is None:
            module.info(f"analytics: {name} tab clicked (no aria-selected attribute)")
            return True
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
          // Whatnot artwork can be an img, lazy source/srcset or CSS background.
          const candidates = [];
          const add = value => {
            try {
              const url = new URL(value || '', location.href);
              if (value && /^https?:$/.test(url.protocol)) candidates.push(url.href);
            } catch {}
          };
          const images = [...row.querySelectorAll('img')].filter(img =>
            !/avatar|profile-picture/i.test((img.alt || '') + ' ' + (img.getAttribute('data-testid') || ''))
          );
          for (const image of images) {
            add(image.currentSrc);
            add(image.getAttribute('data-src'));
            add(image.getAttribute('src'));
            const srcset = image.getAttribute('srcset') || image.getAttribute('data-srcset') || '';
            for (const entry of srcset.split(',')) add(entry.trim().split(/\s+/)[0]);
          }
          for (const node of row.querySelectorAll('source')) {
            for (const entry of (node.getAttribute('srcset') || '').split(',')) add(entry.trim().split(/\s+/)[0]);
          }
          if (!candidates.length) {
            for (const node of [row, ...row.querySelectorAll('*')]) {
              if (/avatar|profile-picture/i.test(node.getAttribute('data-testid') || '')) continue;
              const background = getComputedStyle(node).backgroundImage || '';
              for (const match of background.matchAll(/url\(["']?(.*?)["']?\)/g)) add(match[1]);
            }
          }
          const cover = candidates[0] || null;
          const open = row.querySelector('a[href^="/dashboard/live/"]');
          // Current Seller Hub renders these actions as buttons, not necessarily anchors.
          const analytics = [...row.querySelectorAll('a,button,[role="button"]')]
            .find(a => /^\s*See Analytics\s*$/i.test(a.textContent || '') ||
              /\/dashboard\/analytics\/overview\?.*tab=livestream.*live_id=/i.test(a.getAttribute('href') || ''));
          const shipments = [...row.querySelectorAll('a,button,[role="button"]')]
            .find(a => /^\s*View Shipments\s*$/i.test(a.textContent || ''));
          const openUrl = open?.getAttribute('href') || null;
          const liveId = (openUrl?.match(/\/dashboard\/live\/([0-9a-f-]{36})/i) || [])[1] || null;
          return {
            cover_image_url: cover,
            live_id: liveId,
            title,
            text: (row.innerText || '').replace(/\s+/g, ' ').trim(),
            open_url: openUrl,
            analytics_url: analytics?.getAttribute('href') || analytics?.getAttribute('formaction') || (analytics ? '__BUTTON__' : null),
            shipments_url: shipments?.getAttribute('href') || shipments?.getAttribute('formaction') || (shipments ? '__BUTTON__' : null),
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
    # The UUID comes from Whatnot's own /dashboard/live/<uuid> href and is the
    # canonical show identity. Titles are routinely reused and imported dates can
    # drift, so metadata mismatches are diagnostics, not grounds to reject an
    # otherwise exact UUID match.
    if not title_ok:
        module.info("analytics: exact UUID matched; database title differs from Seller Hub title (continuing by UUID)")
    if not date_ok:
        module.info("analytics: exact UUID matched; database date differs from Seller Hub date (continuing by UUID)")
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
        "current": [],
        "upcoming": [],
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
        result["current"] = list(current.values())
        result["upcoming"] = list(upcoming.values())
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
        f"{module.BASE}/dashboard/lives",
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
    """Open the analytics action captured from the verified Seller Hub row.

    The row index already verified the exact show UUID/title/date. Prefer its real
    analytics href directly so we do not depend on the virtualized Past row being
    rendered again after returning to the tab.
    """
    open_url = clean(target.get("open_url"))
    expected_href = clean(target.get("analytics_url"))
    live_id = clean(target.get("live_id"))
    if not open_url or not expected_href:
        return False

    if expected_href not in {"__BUTTON__", "__ACTION__"}:
        href = expected_href
        if href.startswith("/"):
            href = f"{module.BASE}{href}"
        module.info(f"analytics: opening verified See Analytics href uuid={live_id} href={expected_href}")
        try:
            page.goto(href, wait_until="domcontentloaded", timeout=30000)
            page.wait_for_timeout(1800)
            module.check_login(page)
            current = page.url
            if "dashboard/analytics/overview" in current and (
                not live_id or f"live_id={live_id}" in current.lower()
            ):
                return True
            module.info(
                f"analytics: verified See Analytics href did not reach expected destination "
                f"uuid={live_id} url={current}"
            )
        except Exception as exc:
            module.info(f"analytics: verified See Analytics href navigation failed uuid={live_id} error={exc}")

    # Fallback for Seller Hub variants that expose See Analytics only as a button.
    for _ in range(6):
        row = page.locator('[data-testid="show-list-item"]', has=page.locator(f'a[href="{open_url}"]')).first
        try:
            if row.count():
                try:
                    row.scroll_into_view_if_needed(timeout=1500)
                except Exception:
                    pass
                link = row.locator('a,button,[role="button"]', has_text=re.compile(r"^\\s*See Analytics\\s*$", re.I)).first
                if link.count():
                    module.info(f"analytics: clicking rendered See Analytics uuid={live_id}")
                    link.click(timeout=8000, force=True)
                    page.wait_for_timeout(1800)
                    return True
        except Exception:
            pass
        page.wait_for_timeout(400)

    module.info(f"analytics: verified show analytics action could not be opened uuid={live_id}")
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

        row["cover_image_url"] = target.get("cover_image_url")
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

    # Start directly on Seller Hub Shows. The dashboard home SPA has recently
    # stalled long enough to exhaust navigation retries before analytics begins.
    session.fetch(
        f"{module.BASE}/dashboard/lives",
        page_action=action,
        timeout=120000,
        network_idle=False,
        google_search=False,
    )

    module.info(f"analytics: collected {len(rows)} show(s)")
    return rows




def recent_past_analytics(module, session):
    """Import recent Seller Hub Past rows directly, then follow each row's See Analytics href."""
    limit = max(1, min(100, int(os.getenv("WHATNOT_LIMIT", "50"))))
    rows: list[dict[str, Any]] = []
    module.info(f"recent-past-analytics: loading newest Past shows limit={limit}")

    def action(page):
        module.prepare(page)
        if "/dashboard/lives" not in page.url:
            page.goto(f"{module.BASE}/dashboard/lives", wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(1800)
        module.check_login(page)
        if not select_tab(module, page, "Past"):
            return

        candidates: dict[str, dict[str, Any]] = {}
        stable = 0
        previous = -1
        for attempt in range(1, 12):
            module.check_login(page)
            for item in extract_show_rows(page):
                live_id = clean(item.get("live_id")).lower()
                if live_id and item.get("analytics_url"):
                    item["live_id"] = live_id
                    candidates[live_id] = item
            if len(candidates) >= limit:
                break
            if len(candidates) == previous:
                stable += 1
            else:
                stable = 0
            previous = len(candidates)
            if stable >= 3:
                break
            scroll_to_bottom(page)
            page.wait_for_timeout(900)

        selected = list(candidates.values())[:limit]
        module.info(
            f"recent-past-analytics: Past rows ready candidates={len(candidates)} processing={len(selected)}"
        )

        for index, item in enumerate(selected, 1):
            live_id = clean(item.get("live_id")).lower()
            analytics_url = clean(item.get("analytics_url"))
            if not live_id or not analytics_url:
                continue
            url = analytics_url if analytics_url.startswith("http") else f"{module.BASE}{analytics_url}"
            module.info(
                f"recent-past-analytics [{index}/{len(selected)}]: opening See Analytics "
                f"uuid={live_id} date={item.get('show_date') or '?'} title={item.get('title')!r}"
            )
            page.goto(url, wait_until="domcontentloaded", timeout=30000)
            page.wait_for_timeout(2200)
            module.check_login(page)
            metric = wait_for_metrics(module, page, timeout_ms=15000)
            if not metric or not has_useful_data(metric):
                module.info(f"recent-past-analytics [{index}/{len(selected)}]: no stable metrics uuid={live_id}")
                continue
            metric["whatnot_live_id"] = live_id
            metric["cover_image_url"] = item.get("cover_image_url")
            metric["title"] = item.get("title") or metric.get("title")
            metric["show_date"] = item.get("show_date") or metric.get("show_date")
            metric["detail_url"] = f"{module.BASE}/dashboard/live/{live_id}"
            metric.pop("_preview", None)
            metric.pop("_titles", None)
            metric.pop("_dates", None)
            rows.append(metric)
            module.info(
                f"recent-past-analytics [{index}/{len(selected)}]: collected uuid={live_id} "
                f"gross={metric.get('gross_revenue')} net={metric.get('whatnot_net')}"
            )

    session.fetch(
        f"{module.BASE}/dashboard/lives",
        page_action=action,
        timeout=max(180000, limit * 45000),
        network_idle=False,
        google_search=False,
    )
    module.info(f"recent-past-analytics: collected {len(rows)} show(s)")
    return rows


def historical_analytics(module, session):
    """Scan recent Seller Hub Past rows once, then collect matching DB targets."""
    since = clean(os.getenv("WHATNOT_ANALYTICS_SINCE", ""))
    batch_size = max(1, min(10, int(os.getenv("WHATNOT_ANALYTICS_BATCH_SIZE", "5"))))
    target_ids = [
        value.strip().lower()
        for value in os.getenv("WHATNOT_ANALYTICS_TARGET_IDS", "").split(",")
        if value.strip()
    ]
    try:
        target_meta = json.loads(os.getenv("WHATNOT_ANALYTICS_TARGET_META", "{}") or "{}")
        if not isinstance(target_meta, dict):
            target_meta = {}
        target_meta = {str(k).lower(): v for k, v in target_meta.items() if isinstance(v, dict)}
    except Exception:
        target_meta = {}

    targets = target_ids[:batch_size]
    target_set = set(targets)
    rows: list[dict[str, Any]] = []
    module.info(
        f"historical-analytics: full Past scan -> See Analytics "
        f"since={since or 'all'} targets={len(target_ids)} batch={len(targets)}"
    )

    def recent_index(page) -> dict[str, dict[str, Any]]:
        seen: dict[str, dict[str, Any]] = {}
        stable_passes = 0
        previous_count = -1
        target_dates = [
            clean(target_meta.get(live_id, {}).get("show_date"))
            for live_id in target_set
            if clean(target_meta.get(live_id, {}).get("show_date"))
        ]
        oldest_target = min(target_dates) if target_dates else since or None

        # Historical analytics must be able to walk the full requested range.
        # Keep the fast exit when all UUIDs are found, but do not cap the scan to
        # the first ~100 recent rows. Stop only after we have moved past the
        # oldest target date, reached the requested --since cutoff, or the
        # virtual list has genuinely stopped growing.
        for attempt in range(1, 121):
            module.check_login(page)
            visible = extract_show_rows(page)
            for row in visible:
                live_id = clean(row.get("live_id")).lower()
                if live_id:
                    row["live_id"] = live_id
                    seen[live_id] = row

            found = target_set.intersection(seen.keys())
            loaded_dates = [
                clean(row.get("show_date"))
                for row in seen.values()
                if clean(row.get("show_date"))
            ]
            oldest_loaded = min(loaded_dates) if loaded_dates else None

            if len(seen) == previous_count:
                stable_passes += 1
            else:
                stable_passes = 0
            previous_count = len(seen)

            if attempt == 1 or attempt % 10 == 0 or found == target_set:
                module.info(
                    f"historical-analytics: Past scan pass {attempt}/120 "
                    f"rows_seen={len(seen)} matched={len(found)}/{len(target_set)} "
                    f"oldest={oldest_loaded or 'unknown'}"
                )

            if found == target_set:
                break

            # If every target has a known DB date and the Seller Hub scan has
            # moved older than the oldest one, any still-missing UUID is not in
            # the relevant Past range. Likewise never scan older than --since.
            boundary = oldest_target or since or None
            if boundary and oldest_loaded and oldest_loaded < boundary:
                module.info(
                    f"historical-analytics: scanned past target boundary "
                    f"loaded={oldest_loaded} boundary={boundary}; stopping"
                )
                break

            if since and oldest_loaded and oldest_loaded < since:
                module.info(
                    f"historical-analytics: reached reporting cutoff "
                    f"loaded={oldest_loaded} since={since}; stopping"
                )
                break

            if stable_passes >= 6 and attempt >= 10:
                module.info(
                    f"historical-analytics: Past list exhausted after {attempt} passes "
                    f"rows_seen={len(seen)}"
                )
                break

            scroll_to_bottom(page)
            page.wait_for_timeout(1100)

        return seen

    def action(page):
        module.prepare(page)
        page.goto(f"{module.BASE}/dashboard/lives", wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(1800)
        module.check_login(page)
        if not select_tab(module, page, "Past"):
            return

        pending = list(targets)
        total = len(pending)
        index = recent_index(page)
        matched = [live_id for live_id in pending if live_id in index]
        module.info(
            f"historical-analytics: Past index complete rows={len(index)} "
            f"matched={len(matched)}/{total}"
        )

        for position, live_id in enumerate(pending, 1):
            meta = target_meta.get(live_id, {})
            expected_title = clean(meta.get("title"))
            expected_date = clean(meta.get("show_date")) or None
            target = index.get(live_id)

            if not target:
                module.info(
                    f"historical-analytics [{position}/{total}]: uuid={live_id} not found in scanned Past history; "
                    "leaving due for later/historical retry"
                )
                rows.append({
                    "whatnot_live_id": live_id,
                    "_analytics_transient_failure": True,
                    "_analytics_failure_note": "Show was not present in the scanned Seller Hub Past history.",
                })
                continue
            if not verify_identity(module, target, live_id, expected_title, expected_date):
                rows.append({
                    "whatnot_live_id": live_id,
                    "_analytics_transient_failure": True,
                    "_analytics_failure_note": "Seller Hub row identity did not match the database target.",
                })
                continue

            # Re-open Past for each matched target because See Analytics navigates away.
            if position > 1:
                try:
                    page.goto(f"{module.BASE}/dashboard/lives", wait_until="domcontentloaded", timeout=30000)
                except Exception as exc:
                    module.info(f"historical-analytics: return to Seller Hub warning={exc}")
                page.wait_for_timeout(1000)
                module.check_login(page)
                if not select_tab(module, page, "Past"):
                    break

            if not click_target_analytics(module, page, target):
                rows.append({
                    "whatnot_live_id": live_id,
                    "_analytics_transient_failure": True,
                    "_analytics_failure_note": "Past row found but See Analytics could not be opened.",
                })
                continue

            module.info(
                f"historical-analytics [{position}/{total}]: See Analytics opened uuid={live_id} url={page.url}"
            )
            metric = wait_for_metrics(module, page, timeout_ms=30000)
            if not metric or not has_useful_data(metric):
                rows.append({
                    "whatnot_live_id": live_id,
                    "_analytics_transient_failure": True,
                    "_analytics_failure_note": "See Analytics loaded but metrics are not stable/ready yet.",
                })
                module.info(
                    f"historical-analytics [{position}/{total}]: metrics not ready uuid={live_id}; leaving due"
                )
                continue

            metric["whatnot_live_id"] = live_id
            metric["cover_image_url"] = target.get("cover_image_url")
            metric["title"] = expected_title or metric.get("title")
            metric["show_date"] = expected_date or metric.get("show_date")
            metric["detail_url"] = f"{module.BASE}/dashboard/live/{live_id}"
            metric.pop("_preview", None)
            metric.pop("_titles", None)
            metric.pop("_dates", None)
            rows.append(metric)
            module.info(
                f"historical-analytics [{position}/{total}]: collected uuid={live_id} "
                f"gross={metric.get('gross_revenue')} net={metric.get('whatnot_net')} "
                f"duration_min={metric.get('show_duration')}"
            )

        module.info(
            f"historical-analytics: batch finished targets={total} matched={len(matched)} "
            f"results={len(rows)}"
        )

    session.fetch(
        f"{module.BASE}/dashboard/home",
        page_action=action,
        timeout=600000,
        network_idle=False,
        google_search=False,
    )
    return rows


def install(module) -> None:
    """Install Seller Hub-backed analytics/index modes into the Scrapling base."""
    module.analytics = lambda session: analytics(module, session)
    module.reconcile_index = lambda session: reconcile_index(module, session)
    module.historical_analytics = lambda session: historical_analytics(module, session)

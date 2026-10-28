from __future__ import annotations

"""Harden Whatnot order pagination before destructive reconciliation.

The dashboard commonly renders up to 100 order rows per page. A full 100-row
page without a trustworthy pagination control is ambiguous: it may be the whole
show, or it may be a truncated first page. In that state we abort the batch so
Laravel never receives a partial result that could replace existing rows.
"""

import json
import os
from urllib.parse import urlsplit, parse_qsl
from pathlib import Path


def install(module) -> None:
    original_batch = module.batch

    next_selectors = [
        'button:has(svg[aria-label="Next page"])',
        'button:has(svg[aria-label="Next Page"])',
        'button[aria-label="Next page"]',
        'button[aria-label="Next Page"]',
        '[role="button"]:has(svg[aria-label="Next page"])',
        '[role="button"]:has(svg[aria-label="Next Page"])',
        '[role="button"][aria-label="Next page"]',
        '[role="button"][aria-label="Next Page"]',
    ]

    def pagination_summary(page) -> str | None:
        try:
            return page.evaluate(r"""
            () => {
              const text = document.body?.innerText || '';
              const match = text.match(/Showing\s+\d+\s*-\s*\d+\s+of\s+(?:many|\d+)/i);
              return match ? match[0].replace(/\s+/g, ' ').trim() : null;
            }
            """)
        except Exception:
            return None

    def inspect_next(page, wait_ms: int = 3500) -> dict:
        """Find Whatnot's Next control, including aria-label on nested SVG.

        Whatnot currently renders the control as:
          <button ...><div><svg aria-label="Next page">...</svg></div></button>
        The control can appear shortly after the 100 table rows render, so wait
        briefly before declaring a full page ambiguous.
        """
        attempts = max(1, wait_ms // 250)
        last_error = None

        for attempt in range(attempts):
            for selector in next_selectors:
                try:
                    locator = page.locator(selector).first
                    if locator.count() < 1:
                        continue
                    if not locator.is_visible(timeout=250):
                        continue

                    state = locator.evaluate(r"""
                    button => {
                      const disabled = Boolean(button.disabled)
                        || button.getAttribute('aria-disabled') === 'true'
                        || button.matches?.('[disabled]')
                        || button.classList?.contains('cursor-not-allowed')
                        || button.classList?.contains('disabled');
                      const svg = button.querySelector?.('svg[aria-label]');
                      return {
                        exists: true,
                        disabled,
                        href: button.getAttribute?.('href') || null,
                        text: (button.innerText || button.textContent || '').trim().substring(0, 80),
                        aria: button.getAttribute?.('aria-label') || svg?.getAttribute('aria-label') || null,
                        title: button.getAttribute?.('title') || null,
                        selector: null,
                        url: location.href
                      };
                    }
                    """)
                    if isinstance(state, dict):
                        state["selector"] = selector
                        state["summary"] = pagination_summary(page)
                        return state
                except Exception as exc:
                    last_error = str(exc)

            # DOM fallback for engines where :has() locator support differs.
            try:
                state = page.evaluate(r"""
                () => {
                  const svgs = [...document.querySelectorAll('svg[aria-label]')];
                  const svg = svgs.find(el => /^next\s+page$/i.test((el.getAttribute('aria-label') || '').trim()));
                  const direct = [...document.querySelectorAll('button,[role="button"]')]
                    .find(el => /^next\s+page$/i.test((el.getAttribute('aria-label') || '').trim()));
                  const button = svg?.closest('button,[role="button"]') || direct || null;
                  if (!button) return null;
                  const rect = button.getBoundingClientRect?.();
                  const style = window.getComputedStyle?.(button);
                  if (rect && rect.width <= 0 && rect.height <= 0) return null;
                  if (style && (style.visibility === 'hidden' || style.display === 'none')) return null;
                  const disabled = Boolean(button.disabled)
                    || button.getAttribute('aria-disabled') === 'true'
                    || button.matches?.('[disabled]')
                    || button.classList?.contains('cursor-not-allowed')
                    || button.classList?.contains('disabled');
                  return {
                    exists: true,
                    disabled,
                    href: button.getAttribute?.('href') || null,
                    text: (button.innerText || button.textContent || '').trim().substring(0, 80),
                    aria: button.getAttribute?.('aria-label') || svg?.getAttribute('aria-label') || null,
                    title: button.getAttribute?.('title') || null,
                    selector: 'dom-svg-closest',
                    url: location.href
                  };
                }
                """)
                if isinstance(state, dict) and state.get("exists"):
                    state["summary"] = pagination_summary(page)
                    return state
            except Exception as exc:
                last_error = str(exc)

            if attempt < attempts - 1:
                page.wait_for_timeout(250)

        return {
            "exists": False,
            "disabled": None,
            "href": None,
            "text": None,
            "aria": None,
            "title": None,
            "selector": None,
            "summary": pagination_summary(page),
            "url": page.url,
            "error": last_error,
        }

    def click_next(page, previous_signature: str) -> tuple[bool, str]:
        clicked = False

        for selector in next_selectors:
            try:
                locator = page.locator(selector).first
                if locator.count() < 1 or not locator.is_visible(timeout=250):
                    continue
                locator.scroll_into_view_if_needed(timeout=2000)
                locator.click(timeout=5000, force=True)
                clicked = True
                break
            except Exception:
                continue

        if not clicked:
            try:
                clicked = bool(page.evaluate(r"""
                () => {
                  const svg = [...document.querySelectorAll('svg[aria-label]')]
                    .find(el => /^next\s+page$/i.test((el.getAttribute('aria-label') || '').trim()));
                  const button = svg?.closest('button,[role="button"]') || null;
                  if (!button || button.disabled || button.getAttribute('aria-disabled') === 'true') return false;
                  button.scrollIntoView({block:'center'});
                  button.click();
                  return true;
                }
                """))
            except Exception:
                clicked = False

        if not clicked:
            return False, previous_signature

        for _ in range(32):
            page.wait_for_timeout(250)
            module.check_login(page)
            current_signature = module.page_signature(page, False)
            if current_signature and current_signature != previous_signature:
                return True, current_signature

        return False, previous_signature

    def hardened_batch(session, shipments=False):
        # Shipment pagination is not destructive in the same way order
        # reconciliation is, so retain the existing shipment implementation.
        if shipments:
            return original_batch(session, True)

        source_file = os.getenv("WHATNOT_ORDER_SOURCES_FILE", "").strip()
        if not source_file or not Path(source_file).exists():
            module.fail("WHATNOT_ORDER_SOURCES_FILE is required")

        sources = json.loads(Path(source_file).read_text())
        probe_max_pages = int(os.getenv('WHATNOT_ORDER_PROBE_MAX_PAGES', '0') or '0')
        if probe_max_pages < 0 or probe_max_pages > 2:
            module.fail('Invalid probe page limit; only 1 or 2 allowed', 2)
        output = []
        # Fail closed if Whatnot ignores the source filter and repeats orders across shows.
        observed_order_owners = {}

        def action(page):
            module.prepare(page)
            # Diagnostic-only network metadata. Never log tokens, request bodies,
            # cookies, response bodies, or query values.
            network_endpoints = set()
            if probe_max_pages:
                def observe_response(response):
                    try:
                        request = response.request
                        if request.resource_type not in ('xhr', 'fetch'):
                            return
                        parsed = urlsplit(response.url)
                        if not parsed.hostname or not (
                            parsed.hostname == 'whatnot.com'
                            or parsed.hostname.endswith('.whatnot.com')
                        ):
                            return
                        keys = tuple(sorted({key for key, _ in parse_qsl(parsed.query)}))
                        network_endpoints.add((request.method, parsed.hostname, parsed.path, keys))
                    except Exception:
                        pass
                page.on('response', observe_response)

            for idx, source in enumerate(sources, 1):
                live_id = source.get("live_id")
                key = source.get("show_key")
                if not live_id:
                    output.append({"show_key": key, "live_id": None, "order_count": 0, "orders": []})
                    continue

                page.goto(
                    f"{module.BASE}/dashboard/orders?source={live_id}&first=100",
                    wait_until="domcontentloaded",
                    timeout=30000,
                )
                page.wait_for_timeout(1200)

                found = {}
                visited_signatures: set[str] = set()
                page_number = 1

                while True:
                    module.check_login(page)
                    current_rows = module.extract_orders(page)
                    signature = module.page_signature(page, False)

                    if signature and signature in visited_signatures:
                        module.fail(
                            f"ORDER_PAGINATION_INCOMPLETE: show #{key} repeated page signature on page {page_number}; refusing partial reconciliation",
                            2,
                        )
                    if signature:
                        visited_signatures.add(signature)

                    for row in current_rows:
                        dedupe_key = str(
                            row.get("order_id")
                            or f"{row.get('buyer')}|{row.get('item_name')}|{row.get('raw_text')}"
                        )
                        found[dedupe_key] = row

                    # Wait for pagination on a full page because Whatnot can render
                    # the footer controls after the table itself is already ready.
                    state = inspect_next(page, 3500 if len(current_rows) >= 100 else 750)
                    module.info(
                        f"orders-batch: [{idx}/{len(sources)}] {key} "
                        f"page={page_number} page_rows={len(current_rows)} "
                        f"total_unique={len(found)} pagination={json.dumps(state, separators=(',', ':'))}"
                    )

                    # Probe mode samples at most two pages per show, even if Next is enabled.
                    # This is diagnostic only; never use these partial rows for imports.
                    if probe_max_pages and page_number >= probe_max_pages:
                        module.info(f'ORDER_SOURCE_PROBE_SAMPLE show={key} pages={page_number} rows={len(found)} (NOT complete; no import permitted)')
                        break

                    # A short page proves that there cannot be another full page
                    # behind a hidden/missing Next control.
                    if len(current_rows) < 100:
                        break

                    # A real, visible disabled Next control is affirmative evidence
                    # that this is the final page, even when it contains 100 rows.
                    if state.get("exists") and state.get("disabled") is True:
                        break

                    # A full page with no trustworthy Next control is ambiguous.
                    # Abort the entire scrape BEFORE Laravel receives any rows.
                    if not state.get("exists"):
                        module.fail(
                            f"ORDER_PAGINATION_AMBIGUOUS: show #{key} returned exactly {len(current_rows)} rows on page {page_number}, but no Next control could be verified. summary={state.get('summary')!r}. Existing orders were NOT changed.",
                            2,
                        )

                    if state.get("disabled") is not False:
                        module.fail(
                            f"ORDER_PAGINATION_AMBIGUOUS: show #{key} pagination state could not be proven on page {page_number}. Existing orders were NOT changed.",
                            2,
                        )

                    advanced, next_signature = click_next(page, signature)
                    if not advanced:
                        module.fail(
                            f"ORDER_PAGINATION_INCOMPLETE: show #{key} had an enabled Next control on page {page_number}, but the table did not advance. Existing orders were NOT changed.",
                            2,
                        )
                    if next_signature and next_signature in visited_signatures:
                        module.fail(
                            f"ORDER_PAGINATION_INCOMPLETE: show #{key} Next resolved to an already-visited page. Existing orders were NOT changed.",
                            2,
                        )

                    page_number += 1

                if probe_max_pages:
                    module.info('ORDER_SOURCE_NETWORK_ENDPOINTS show=' + str(key) + ' ' +
                                json.dumps([{'method': method, 'host': host, 'path': path, 'query_keys': keys}
                                            for method, host, path, keys in sorted(network_endpoints)][:60],
                                           separators=(',', ':')))
                    network_endpoints.clear()
                rows = list(found.values())
                for row in rows:
                    order_id = str(row.get('order_id') or '').strip()
                    if not order_id or order_id == '-':
                        continue
                    owner = observed_order_owners.get(order_id)
                    if owner is not None and owner != str(live_id):
                        module.fail(
                            f'ORDER_SOURCE_FILTER_COLLISION: order {order_id} appeared for both live IDs '
                            f'{owner} and {live_id}. The source filter cannot be trusted; '
                            'aborting without returning an import payload.',
                            2,
                        )
                    observed_order_owners[order_id] = str(live_id)
                module.info(
                    f"orders-batch: [{idx}/{len(sources)}] {key} complete=true "
                    f"pages={page_number} -> {len(rows)} row(s)"
                )
                output.append(
                    {
                        "show_key": key,
                        "live_id": live_id,
                        "order_count": len(rows),
                        "pagination_complete": not bool(probe_max_pages),
                        "probe_sample": bool(probe_max_pages),
                        "pages": page_number,
                        "orders": rows,
                    }
                )

        session.fetch(
            f"{module.BASE}/dashboard/home",
            page_action=action,
            timeout=60000,
            network_idle=False,
            google_search=False,
        )
        return output

    module.batch = hardened_batch

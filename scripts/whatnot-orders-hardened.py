from __future__ import annotations

"""Harden Whatnot order pagination before destructive reconciliation.

The dashboard commonly renders up to 100 order rows per page. A full 100-row
page without a trustworthy pagination control is ambiguous: it may be the whole
show, or it may be a truncated first page. In that state we abort the batch so
Laravel never receives a partial result that could replace existing rows.
"""

import json
import os
from pathlib import Path


def install(module) -> None:
    original_batch = module.batch

    def inspect_next(page) -> dict:
        try:
            state = page.evaluate(r"""
            () => {
              const candidates = [];
              const add = el => { if (el && !candidates.includes(el)) candidates.push(el); };

              for (const sel of [
                'button[aria-label="Next page"]',
                'button[aria-label="Next Page"]',
                'button[title="Next page"]',
                'button[title="Next Page"]',
                '[role="button"][aria-label="Next page"]',
                '[role="button"][aria-label="Next Page"]'
              ]) add(document.querySelector(sel));

              for (const svg of document.querySelectorAll(
                'svg[aria-label="Next page"], svg[aria-label="Next Page"]'
              )) add(svg.closest('button,[role="button"]'));

              for (const button of document.querySelectorAll('button,[role="button"]')) {
                const text = (button.innerText || button.textContent || '').trim();
                if (/^next(?:\s+page)?$/i.test(text)) add(button);
              }

              const button = candidates.find(el => {
                const rect = el.getBoundingClientRect?.();
                const style = window.getComputedStyle?.(el);
                return !rect || ((rect.width > 0 || rect.height > 0) && style?.visibility !== 'hidden');
              }) || candidates[0] || null;

              if (!button) {
                return {
                  exists: false,
                  disabled: null,
                  href: null,
                  text: null,
                  url: location.href
                };
              }

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
                aria: button.getAttribute?.('aria-label') || null,
                title: button.getAttribute?.('title') || null,
                url: location.href
              };
            }
            """)
            return state if isinstance(state, dict) else {"exists": False, "disabled": None}
        except Exception as exc:
            module.info(f"orders pagination inspection failed: {exc}")
            return {"exists": False, "disabled": None, "error": str(exc)}

    def hardened_batch(session, shipments=False):
        # Shipment pagination is not destructive in the same way order
        # reconciliation is, so retain the existing shipment implementation.
        if shipments:
            return original_batch(session, True)

        source_file = os.getenv("WHATNOT_ORDER_SOURCES_FILE", "").strip()
        if not source_file or not Path(source_file).exists():
            module.fail("WHATNOT_ORDER_SOURCES_FILE is required")

        sources = json.loads(Path(source_file).read_text())
        output = []

        def action(page):
            module.prepare(page)

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
                page.wait_for_timeout(900)

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

                    state = inspect_next(page)
                    module.info(
                        f"orders-batch: [{idx}/{len(sources)}] {key} "
                        f"page={page_number} page_rows={len(current_rows)} "
                        f"total_unique={len(found)} pagination={json.dumps(state, separators=(',', ':'))}"
                    )

                    # A short page proves that there cannot be another full page
                    # behind a hidden/missing Next control.
                    if len(current_rows) < 100:
                        break

                    # A real, visible disabled Next control is also affirmative
                    # evidence that this is the final page, even when it contains
                    # exactly 100 rows.
                    if state.get("exists") and state.get("disabled") is True:
                        break

                    # A full page with no trustworthy Next control is ambiguous.
                    # Abort the entire scrape BEFORE Laravel receives any rows.
                    if not state.get("exists"):
                        module.fail(
                            f"ORDER_PAGINATION_AMBIGUOUS: show #{key} returned exactly {len(current_rows)} rows on page {page_number}, but no Next control could be verified. Existing orders were NOT changed.",
                            2,
                        )

                    if state.get("disabled") is not False:
                        module.fail(
                            f"ORDER_PAGINATION_AMBIGUOUS: show #{key} pagination state could not be proven on page {page_number}. Existing orders were NOT changed.",
                            2,
                        )

                    advanced, next_signature = module.advance_next_page(page, signature, False)
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

                rows = list(found.values())
                module.info(
                    f"orders-batch: [{idx}/{len(sources)}] {key} complete=true "
                    f"pages={page_number} -> {len(rows)} row(s)"
                )
                output.append(
                    {
                        "show_key": key,
                        "live_id": live_id,
                        "order_count": len(rows),
                        "pagination_complete": True,
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

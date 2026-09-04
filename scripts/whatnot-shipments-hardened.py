from __future__ import annotations

import base64
import importlib.util
import json
import os
import re
import sys
from pathlib import Path
from typing import Any
from urllib.parse import quote

HERE = Path(__file__).resolve().parent
BASE_SCRIPT = HERE / "whatnot-scrapling.py"
STEALTHY_SCRIPT = HERE / "whatnot-scrapling-stealthy.py"
BASE = "https://www.whatnot.com"
PROFILE = os.getenv("WHATNOT_USER_DATA_DIR", "").strip()
CHROME = os.getenv("PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH", "").strip()
SOURCE_FILE = os.getenv("WHATNOT_ORDER_SOURCES_FILE", "").strip()
HEADLESS = os.getenv("WHATNOT_HEADLESS", "false").lower() != "false"


def load_module(path: Path, name: str):
    spec = importlib.util.spec_from_file_location(name, path)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Unable to load {path}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def info(message: str) -> None:
    print(f"[whatnot:scrapling] {message}", file=sys.stderr, flush=True)


def absolute_url(value: str | None) -> str | None:
    value = str(value or "").strip()
    if not value:
        return None
    if value.startswith(("http://", "https://")):
        return value
    return BASE + (value if value.startswith("/") else "/" + value)


def ui_filter_url(live_id: str) -> str:
    payload = {
        "salesChannel": {"type": "single", "value": live_id},
        "status": {"type": "single", "value": "all"},
    }
    encoded = base64.b64encode(json.dumps(payload, separators=(",", ":")).encode()).decode()
    return f"{BASE}/dashboard/shipments?filters={quote(encoded, safe='')}"


def find_show_actions(page, live_id: str) -> dict[str, Any] | None:
    page.goto(f"{BASE}/dashboard/lives", wait_until="domcontentloaded", timeout=30000)
    page.wait_for_timeout(700)
    try:
        past = page.get_by_text(re.compile(r"^Past$", re.I)).first
        if past.is_visible(timeout=1200):
            past.click(timeout=3000)
            page.wait_for_timeout(700)
    except Exception:
        pass

    for _ in range(30):
        result = page.evaluate(
            r"""
            liveId => {
              const wanted = `/dashboard/live/${liveId}`;
              for (const item of document.querySelectorAll('section[data-testid="show-list-item"]')) {
                const links = [...item.querySelectorAll('a[href]')];
                const open = links.find(a => (a.getAttribute('href') || '').split('?')[0] === wanted);
                if (!open) continue;
                const shipment = links.find(a => /\/dashboard\/shipments(?:\?|$)/.test(a.getAttribute('href') || ''));
                const analytics = links.find(a => /\/dashboard\/(?:analytics\/overview)|\/account\/analytics/.test(a.getAttribute('href') || ''));
                return {
                  live_id: liveId,
                  title: item.querySelector('[data-testid="show-list-item-title"]')?.textContent?.trim() || null,
                  open_show_url: open.getAttribute('href') || null,
                  shipment_url: shipment?.getAttribute('href') || null,
                  analytics_url: analytics?.getAttribute('href') || null,
                  row_preview: (item.innerText || '').replace(/\s+/g, ' ').trim().substring(0, 350),
                };
              }
              return null;
            }
            """,
            live_id,
        )
        if result:
            return result
        before = page.evaluate("() => document.documentElement.scrollHeight")
        page.evaluate("() => window.scrollTo(0, document.documentElement.scrollHeight)")
        page.wait_for_timeout(500)
        after = page.evaluate("() => document.documentElement.scrollHeight")
        if after == before:
            page.wait_for_timeout(500)
    return None


def expand_rows(page) -> int:
    clicked = 0
    for selector in [
        'button[aria-label="Expand All"]',
        'button[aria-label="Expand all"]',
        'button:has-text("Expand All")',
        'button:has-text("Expand all")',
    ]:
        try:
            button = page.locator(selector).first
            if button.is_visible(timeout=300):
                button.click(timeout=2000)
                page.wait_for_timeout(350)
                return 1
        except Exception:
            pass

    # Current Seller Hub renders an Expand button in each shipment row.
    try:
        buttons = page.get_by_role("button", name=re.compile(r"^Expand$", re.I))
        count = min(buttons.count(), 100)
        for i in range(count):
            try:
                button = buttons.nth(i)
                if button.is_visible(timeout=150):
                    button.click(timeout=1200)
                    clicked += 1
            except Exception:
                continue
        if clicked:
            page.wait_for_timeout(500)
    except Exception:
        pass
    return clicked


def extract_shipments(page) -> list[dict[str, Any]]:
    return page.evaluate(r"""
    () => {
      const money = value => {
        const m = String(value || '').match(/-?\$?\s*([\d,]+(?:\.\d+)?)/);
        if (!m) return null;
        const n = parseFloat(m[0].replace(/[^0-9.-]/g, ''));
        return Number.isFinite(n) ? n : null;
      };
      const number = value => {
        const n = parseInt(String(value || '').replace(/[^0-9]/g, ''), 10);
        return Number.isFinite(n) ? n : null;
      };
      const weightOz = value => {
        const text = String(value || '');
        const lb = text.match(/(\d+(?:\.\d+)?)\s*lb\b/i);
        const oz = text.match(/(\d+(?:\.\d+)?)\s*oz\b/i);
        if (!lb && !oz) return null;
        return (lb ? parseFloat(lb[1]) * 16 : 0) + (oz ? parseFloat(oz[1]) : 0);
      };
      const statusOf = text => {
        if (/delivered/i.test(text)) return 'delivered';
        if (/returned/i.test(text)) return 'returned';
        if (/in\s*transit/i.test(text)) return 'in_transit';
        if (/shipping|shipped/i.test(text)) return 'shipped';
        if (/label\s*created/i.test(text)) return 'label_created';
        if (/ready\s*to\s*ship/i.test(text)) return 'ready_to_ship';
        if (/packed/i.test(text)) return 'packed';
        return null;
      };
      const shipmentRows = [...document.querySelectorAll('tr[data-testid^="shipments-"][data-testid$="-row"]')];
      const rows = shipmentRows.length ? shipmentRows : [...document.querySelectorAll('[data-testid="shipments-table-body"] tr')];
      const unique = rows.filter((row, index, all) => all.indexOf(row) === index);
      const out = [];

      for (const tr of unique) {
        const cells = [...tr.querySelectorAll(':scope > td')];
        const main = tr.innerText || '';
        let detail = '';
        let next = tr.nextElementSibling;
        if (next && next.tagName === 'TR' && !/^shipments-.*-row$/.test(next.getAttribute('data-testid') || '')) {
          detail = next.innerText || '';
        }
        const text = `${main}\n${detail}`;
        const normalized = main.replace(/\s+/g, ' ').trim();
        const testid = tr.getAttribute('data-testid') || '';
        const shipmentNode = (testid.match(/^shipments-(.+)-row$/) || [])[1] || null;

        // Seller Hub renders a table-body placeholder row when a filtered show
        // has no shipments. It is not shipment data and must never become a
        // fake buyer/order. Real current shipment rows have a shipments-*-row
        // test id; the fallback is kept only for layout changes and must still
        // look like an actual shipment before we accept it.
        if (/no results to show|clear all filters/i.test(normalized)) continue;
        if (!shipmentNode && cells.length < 4) continue;
        if (!shipmentNode && !statusOf(text) && !weightOz(text) && !/\b(?:USPS|UPS|FedEx|DHL)\b/i.test(text)) continue;

        const orderText = text.match(/Order\s*#\s*(\d+)/i);
        const orderHref = [...tr.querySelectorAll('a[href]')]
          .map(a => a.getAttribute('href') || '')
          .find(href => /\/dashboard\/orders\//.test(href));
        const hrefOrder = orderHref && orderHref.match(/\/dashboard\/orders\/(\d+)(?:[/?#]|$)/);
        const orderId = orderText ? orderText[1] : (hrefOrder ? hrefOrder[1] : null);

        const buyerCell = cells[0]?.innerText || '';
        const buyer = buyerCell.replace(/\b(New|Expand|Collapse)\b/gi, ' ').replace(/\s+/g, ' ').trim() || null;
        const qty = number(cells[2]?.innerText || '');
        const value = money(cells[3]?.innerText || '');
        const weight = weightOz(cells[4]?.innerText || text);
        const dimsText = cells[5]?.innerText || text;
        const dims = dimsText.match(/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*in\b/i);
        const carrierText = cells[8]?.innerText || text;
        const carrier = carrierText.match(/\b(USPS|UPS|FedEx|DHL)\b/i);
        const statusText = cells[7]?.innerText || text;

        let tracking = null;
        const trackingLink = [...tr.querySelectorAll('a[href]')].find(a => /track|tracking/i.test(a.getAttribute('href') || ''));
        if (trackingLink) {
          const href = trackingLink.getAttribute('href') || '';
          const candidates = href.match(/[A-Z0-9]{10,34}/ig) || [];
          tracking = candidates.sort((a,b) => b.length - a.length)[0] || null;
        }
        if (!tracking) {
          const visible = (cells[8]?.innerText || text).match(/\b([A-Z0-9]{10,34})\b/i);
          if (visible && !visible[1].includes('...')) tracking = visible[1];
        }

        out.push({
          order_id: orderId,
          whatnot_shipment_id: shipmentNode,
          buyer,
          item_name: null,
          lot_number: null,
          quantity: qty || 1,
          unit_price: null,
          total_price: value,
          status: 'completed',
          raw_text: text.replace(/\s+/g, ' ').trim().substring(0, 1200),
          weight_oz: weight,
          box_length_in: dims ? parseFloat(dims[1]) : null,
          box_width_in: dims ? parseFloat(dims[2]) : null,
          box_height_in: dims ? parseFloat(dims[3]) : null,
          shipping_carrier: carrier ? carrier[1].toUpperCase() : null,
          shipping_service: carrierText.replace(/\s+/g, ' ').trim() || null,
          shipping_status_scraped: statusOf(statusText),
          tracking_number: tracking,
        });
      }
      return out;
    }
    """) or []


def page_signature(page) -> str:
    try:
        return str(page.evaluate(r"""
        () => {
          const rows = [...document.querySelectorAll('tr[data-testid^="shipments-"][data-testid$="-row"]')];
          const ids = rows.map(tr => tr.getAttribute('data-testid') || '');
          return `${ids.length}:${ids[0] || ''}:${ids[ids.length - 1] || ''}`;
        }
        """) or "")
    except Exception:
        return ""


def pagination_state(page) -> dict[str, Any]:
    try:
        return page.evaluate(r"""
        () => {
          const svg = document.querySelector('svg[aria-label="Next page"]');
          const button = svg ? svg.closest('button') : document.querySelector('button[aria-label="Next page"]');
          const body = document.body?.innerText || '';
          const showing = (body.match(/Showing\s+([\d,]+)\s*[-–]\s*([\d,]+)\s+of\s+([\d,]+)/i) || []).slice(1);
          return {exists: !!button, disabled: !button || !!button.disabled || button.getAttribute('aria-disabled') === 'true', showing};
        }
        """) or {}
    except Exception:
        return {}


def page_diagnostic(page) -> dict[str, Any]:
    try:
        return page.evaluate(r"""
        () => {
          const body = (document.body?.innerText || '').replace(/\s+/g, ' ').trim();
          const testids = [...document.querySelectorAll('[data-testid]')]
            .map(el => el.getAttribute('data-testid')).filter(Boolean)
            .filter((value, index, all) => all.indexOf(value) === index)
            .slice(0, 30);
          return {url: location.href, title: document.title, testids, preview: body.substring(0, 1600)};
        }
        """) or {}
    except Exception:
        return {}


def advance_page(page, previous_signature: str) -> bool:
    state = pagination_state(page)
    if not state.get("exists") or state.get("disabled"):
        return False
    try:
        svg = page.locator('svg[aria-label="Next page"]').first
        button = svg.locator('xpath=ancestor::button[1]')
        button.scroll_into_view_if_needed(timeout=1500)
        button.click(timeout=3000)
    except Exception:
        try:
            clicked = page.evaluate(r"""
            () => {
              const svg = document.querySelector('svg[aria-label="Next page"]');
              const button = svg ? svg.closest('button') : document.querySelector('button[aria-label="Next page"]');
              if (!button || button.disabled || button.getAttribute('aria-disabled') === 'true') return false;
              button.click(); return true;
            }
            """)
            if not clicked:
                return False
        except Exception:
            return False
    for _ in range(40):
        page.wait_for_timeout(250)
        current = page_signature(page)
        if current and current != previous_signature:
            return True
    return False


def main() -> None:
    if not PROFILE:
        raise SystemExit("WHATNOT_USER_DATA_DIR is required")
    if not CHROME:
        raise SystemExit("PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH is required")
    if not SOURCE_FILE or not Path(SOURCE_FILE).exists():
        raise SystemExit("WHATNOT_ORDER_SOURCES_FILE is required")

    sources = json.loads(Path(SOURCE_FILE).read_text())
    base = load_module(BASE_SCRIPT, "vortexops_whatnot_scrapling_shipments_base")
    stealthy = load_module(STEALTHY_SCRIPT, "vortexops_whatnot_shipments_stealthy")
    base.check_login = stealthy.ensure_authenticated
    output: list[dict[str, Any]] = []

    def action(page):
        base.prepare(page)
        for idx, source in enumerate(sources, 1):
            live_id = source.get("live_id")
            show_key = source.get("show_key")
            if not live_id:
                output.append({"show_key": show_key, "live_id": None, "order_count": 0, "orders": []})
                continue

            actions = find_show_actions(page, live_id)
            if actions and actions.get("shipment_url"):
                target = absolute_url(actions.get("shipment_url"))
                route_source = "show-row"
            else:
                target = ui_filter_url(live_id)
                route_source = "ui-filter-fallback"
            info(f"SHIPMENT_ROUTE show={show_key} live_id={live_id} source={route_source} url={target}")

            page.goto(target, wait_until="domcontentloaded", timeout=30000)
            for _ in range(60):
                base.check_login(page)
                state = pagination_state(page)
                if page_signature(page) or state.get("exists"):
                    break
                page.wait_for_timeout(250)

            found: dict[str, dict[str, Any]] = {}
            page_number = 1
            for _ in range(100):
                base.check_login(page)
                expanded = expand_rows(page)
                rows = extract_shipments(page)
                for row in rows:
                    key = str(row.get("whatnot_shipment_id") or row.get("order_id") or row.get("tracking_number") or len(found))
                    found[key] = row
                state = pagination_state(page)
                info(
                    f"shipments-batch: [{idx}/{len(sources)}] {show_key} page={page_number} "
                    f"page_rows={len(rows)} expanded={expanded} total_unique={len(found)} "
                    f"pagination={json.dumps(state, separators=(',', ':'))}"
                )
                signature = page_signature(page)
                if not signature or not advance_page(page, signature):
                    break
                page_number += 1

            rows = list(found.values())
            if not rows:
                info("SHIPMENT_ZERO_DIAGNOSTIC " + json.dumps({"show_key": show_key, "live_id": live_id, **page_diagnostic(page)}, separators=(",", ":")))
            else:
                sample = rows[0]
                info(
                    "SHIPMENT_PARSE_SAMPLE "
                    + json.dumps({
                        "show_key": show_key,
                        "shipment_id": sample.get("whatnot_shipment_id"),
                        "order_id": sample.get("order_id"),
                        "buyer": sample.get("buyer"),
                        "status": sample.get("shipping_status_scraped"),
                        "tracking": sample.get("tracking_number"),
                        "weight_oz": sample.get("weight_oz"),
                    }, separators=(",", ":"))
                )
            info(f"shipments-batch: [{idx}/{len(sources)}] {show_key} -> {len(rows)} row(s) across {page_number} page(s)")
            output.append({"show_key": show_key, "live_id": live_id, "order_count": len(rows), "orders": rows, "show_actions": actions or {}})

    with stealthy.stealthy_session(
        headless=HEADLESS,
        real_chrome=True,
        executable_path=CHROME,
        user_data_dir=PROFILE,
        disable_resources=False,
        google_search=False,
        locale="en-US",
    ) as session:
        session.fetch(f"{BASE}/dashboard/home", page_action=action, timeout=60000, network_idle=False, google_search=False)

    json.dump(output, sys.stdout, separators=(",", ":"))
    sys.stdout.write("\n")


if __name__ == "__main__":
    main()

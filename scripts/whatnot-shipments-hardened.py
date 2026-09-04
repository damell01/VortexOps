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
    """Expand current Seller Hub shipment bundles so order/item details exist in DOM."""
    try:
        clicked = page.evaluate(r"""
        () => {
          let clicked = 0;
          const controls = [...document.querySelectorAll('button, [role="button"]')]
            .filter(el => (el.textContent || '').trim().toLowerCase() === 'expand');
          for (const el of controls) {
            if (el.disabled || el.getAttribute('aria-disabled') === 'true') continue;
            try { el.click(); clicked++; } catch (_) {}
          }
          return clicked;
        }
        """) or 0
        if clicked:
            page.wait_for_timeout(700)
        return int(clicked)
    except Exception:
        return 0


def extract_shipments(page) -> list[dict[str, Any]]:
    return page.evaluate(r"""
    () => {
      const clean = value => String(value || '').replace(/\s+/g, ' ').trim();
      const money = value => {
        const m = String(value || '').match(/-?\$\s*([\d,]+(?:\.\d+)?)/);
        if (!m) return null;
        const n = parseFloat(m[1].replace(/,/g, ''));
        return Number.isFinite(n) ? n : null;
      };
      const integer = value => {
        const m = clean(value).match(/^\d{1,5}$/);
        return m ? parseInt(m[0], 10) : null;
      };
      const weightOz = value => {
        const text = String(value || '');
        const lb = text.match(/(\d+(?:\.\d+)?)\s*lb\b/i);
        const oz = text.match(/(\d+(?:\.\d+)?)\s*oz\b/i);
        if (!lb && !oz) return null;
        return (lb ? parseFloat(lb[1]) * 16 : 0) + (oz ? parseFloat(oz[1]) : 0);
      };
      const statusOf = text => {
        if (/\bdelivered\b/i.test(text)) return 'delivered';
        if (/\breturned\b/i.test(text)) return 'returned';
        if (/\bin\s*transit\b/i.test(text)) return 'in_transit';
        if (/\bshipping\b|\bshipped\b/i.test(text)) return 'shipped';
        if (/\blabel\s*created\b/i.test(text)) return 'label_created';
        if (/\bready\s*to\s*ship\b/i.test(text)) return 'ready_to_ship';
        if (/\bpacked\b/i.test(text)) return 'packed';
        return null;
      };
      const dateCellIndex = cells => cells.findIndex(td => /\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},\s+20\d{2}\b/i.test(clean(td.innerText)));
      const shipmentRows = [...document.querySelectorAll('tr[data-testid^="shipments-"][data-testid$="-row"]')];
      const rows = shipmentRows.length ? shipmentRows : [...document.querySelectorAll('[data-testid="shipments-table-body"] tr')];
      const unique = rows.filter((row, index, all) => all.indexOf(row) === index);
      const out = [];

      for (const tr of unique) {
        const cells = [...tr.querySelectorAll(':scope > td')];
        const main = tr.innerText || '';
        const normalized = clean(main);
        const testid = tr.getAttribute('data-testid') || '';
        const shipmentNode = (testid.match(/^shipments-(.+)-row$/) || [])[1] || null;

        if (/no results to show|clear all filters/i.test(normalized)) continue;
        if (!shipmentNode && cells.length < 4) continue;

        const detailRoots = [];
        let next = tr.nextElementSibling;
        while (next && next.tagName === 'TR' && !/^shipments-.*-row$/.test(next.getAttribute('data-testid') || '')) {
          detailRoots.push(next);
          next = next.nextElementSibling;
        }
        const detailText = detailRoots.map(row => row.innerText || '').join('\n');
        const text = `${main}\n${detailText}`;

        const buyerLink = tr.querySelector('a[href*="/dashboard/inbox"]');
        const buyer = clean(buyerLink?.textContent) || null;

        // Current Seller Hub header layout is anchored from the date cell so a
        // year such as 2026 can never be misread as the item quantity.
        const dateIndex = dateCellIndex(cells);
        const orderDate = dateIndex >= 0 ? clean(cells[dateIndex]?.innerText) : null;
        const headerQty = dateIndex >= 0 ? integer(cells[dateIndex + 1]?.innerText) : null;
        const shipmentValue = dateIndex >= 0 ? money(cells[dateIndex + 2]?.innerText) : null;

        const weightCell = cells.find(td => /\b(?:\d+(?:\.\d+)?\s*lb|\d+(?:\.\d+)?\s*oz)\b/i.test(clean(td.innerText)));
        const weight = weightOz(weightCell?.innerText || '');
        const dimsCell = cells.find(td => /\d+(?:\.\d+)?\s*[×x]\s*\d+(?:\.\d+)?\s*[×x]\s*\d+(?:\.\d+)?\s*in\b/i.test(clean(td.innerText)));
        const dimsText = clean(dimsCell?.innerText || '');
        const dims = dimsText.match(/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*in\b/i);
        const status = statusOf(main);

        const allLinks = [tr, ...detailRoots].flatMap(root => [...root.querySelectorAll('a[href]')]);
        const trackingLink = allLinks.find(a => /tools\.usps\.com|track|tracking/i.test(a.getAttribute('href') || ''));
        let tracking = null;
        if (trackingLink) {
          const href = trackingLink.getAttribute('href') || '';
          try {
            const url = new URL(href, location.origin);
            tracking = url.searchParams.get('tLabels') || url.searchParams.get('trackingNumber') || null;
          } catch (_) {}
          if (!tracking) {
            const candidates = href.match(/[A-Z0-9]{10,34}/ig) || [];
            tracking = candidates.sort((a,b) => b.length - a.length)[0] || null;
          }
        }

        const carrierMatch = main.match(/\b(USPS|UPS|FedEx|DHL)\b/i);
        const carrier = carrierMatch ? carrierMatch[1].toUpperCase() : null;
        const serviceCell = cells.find(td => /\b(?:USPS|UPS|FedEx|DHL)\b/i.test(clean(td.innerText)) || td.querySelector('a[href*="TrackConfirmAction"], a[href*="tracking"]'));
        let service = clean(serviceCell?.innerText || '');
        if (tracking) service = clean(service.replace(tracking, '').replace(/\d+\.\.\.\d+/g, ''));

        const shipmentEditLink = allLinks.find(a => /\/dashboard\/shipments\/.+\/edit(?:\?|$)/.test(a.getAttribute('href') || ''));
        const shipmentUrl = shipmentEditLink ? shipmentEditLink.getAttribute('href') : null;

        const bundledItems = [];
        const orderIds = [];
        for (const root of detailRoots) {
          const orderLinks = [...root.querySelectorAll('a[href*="/dashboard/orders/"]')];
          for (const link of orderLinks) {
            const row = link.closest('tr') || link.parentElement || root;
            const rowText = clean(row?.innerText || link.parentElement?.innerText || '');
            const orderMatch = rowText.match(/Order\s*#\s*(\d+)/i);
            if (!orderMatch) continue;
            const orderId = orderMatch[1];
            if (!orderIds.includes(orderId)) orderIds.push(orderId);
            const title = clean((rowText.split(/Order\s*#\s*\d+/i)[0] || '').replace(/^Items?\s*\(Bundled\)\s*/i, '')) || null;
            const rowCells = row && row.tagName === 'TR' ? [...row.querySelectorAll(':scope > td')] : [];
            const childQtyCell = rowCells.find(td => /^\d{1,4}$/.test(clean(td.innerText)));
            const childMoney = rowCells.map(td => money(td.innerText)).filter(v => v !== null);
            const childWeightCell = rowCells.find(td => /\b\d+(?:\.\d+)?\s*oz\b|\b\d+(?:\.\d+)?\s*lb\b/i.test(clean(td.innerText)));
            bundledItems.push({
              order_id: orderId,
              order_url: link.getAttribute('href') || null,
              item_name: title,
              quantity: childQtyCell ? integer(childQtyCell.innerText) : 1,
              item_price: childMoney.length ? childMoney[0] : null,
              shipping_amount: childMoney.length > 1 ? childMoney[1] : null,
              weight_oz: weightOz(childWeightCell?.innerText || ''),
            });
          }
        }

        if (!orderIds.length) {
          for (const match of text.matchAll(/Order\s*#\s*(\d+)/ig)) {
            if (!orderIds.includes(match[1])) orderIds.push(match[1]);
          }
        }

        const bundledQty = bundledItems.reduce((sum, item) => sum + (item.quantity || 0), 0);
        const shippingSpend = bundledItems.reduce((sum, item) => sum + (Number.isFinite(item.shipping_amount) ? item.shipping_amount : 0), 0);
        const hasShippingSpend = bundledItems.some(item => Number.isFinite(item.shipping_amount));
        const qty = headerQty || bundledQty || 1;

        // Keep bundle metadata scalar because the PHP persistence layer also
        // scans row values as text. Nested arrays there caused "Array to string
        // conversion" and aborted an otherwise successful scrape.
        out.push({
          parser_version: 3,
          order_id: orderIds[0] || null,
          order_ids: orderIds.join(','),
          order_count: orderIds.length,
          whatnot_shipment_id: shipmentNode,
          shipment_url: shipmentUrl,
          buyer,
          item_name: bundledItems.length === 1 ? bundledItems[0].item_name : null,
          bundled_items: JSON.stringify(bundledItems),
          lot_number: null,
          quantity: qty,
          unit_price: null,
          total_price: shipmentValue,
          shipment_value: shipmentValue,
          shipping_cost_scraped: hasShippingSpend ? Math.round(shippingSpend * 100) / 100 : null,
          ordered_at: orderDate,
          status: 'completed',
          raw_text: clean(text).substring(0, 8000),
          weight_oz: weight,
          box_length_in: dims ? parseFloat(dims[1]) : null,
          box_width_in: dims ? parseFloat(dims[2]) : null,
          box_height_in: dims ? parseFloat(dims[3]) : null,
          shipping_carrier: carrier,
          shipping_service: service || null,
          shipping_status_scraped: status,
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
                        "order_ids": sample.get("order_ids"),
                        "buyer": sample.get("buyer"),
                        "items": sample.get("quantity"),
                        "shipment_value": sample.get("shipment_value"),
                        "shipping_spend": sample.get("shipping_cost_scraped"),
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

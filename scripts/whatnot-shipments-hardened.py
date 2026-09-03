from __future__ import annotations

import importlib.util
import json
import os
import re
import sys
from pathlib import Path
from typing import Any

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


def extract_shipments(page) -> list[dict[str, Any]]:
    return page.evaluate(r"""
    () => {
      const rows = [...document.querySelectorAll('tr[data-testid^="shipments-"], tbody tr')]
        .filter((row, index, all) => all.indexOf(row) === index && /Order\s*#\s*\d+/i.test(row.innerText || ''));
      const out = [];
      for (const tr of rows) {
        const main = tr.innerText || '';
        const next = tr.nextElementSibling;
        const detail = next && next.tagName === 'TR' ? (next.innerText || '') : '';
        const text = `${main}\n${detail}`;
        const oid = text.match(/Order\s*#\s*(\d+)/i);
        if (!oid) continue;
        const buyer = tr.querySelector('a[href*="/dashboard/inbox"], a[href*="participantId"]');
        const weight = text.match(/(\d+(?:\.\d+)?)\s*oz\b/i);
        const dims = text.match(/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*in\b/i);
        const carrier = text.match(/\b(USPS|UPS|FedEx|DHL)\b\s*([A-Za-z\d\s\-/]*[A-Za-z\d])?/i);
        const tracking = text.match(/(?:tracking\s*(?:number|#)?|label\s*#?)\s*[:#-]?\s*([A-Z0-9]{8,34})/i);
        let st = null;
        if (/delivered/i.test(text)) st = 'delivered';
        else if (/returned/i.test(text)) st = 'returned';
        else if (/in\s*transit/i.test(text)) st = 'in_transit';
        else if (/shipped/i.test(text)) st = 'shipped';
        else if (/label\s*created/i.test(text)) st = 'label_created';
        else if (/ready\s*to\s*ship/i.test(text)) st = 'ready_to_ship';
        else if (/packed/i.test(text)) st = 'packed';
        out.push({
          order_id: oid[1],
          buyer: buyer ? (buyer.textContent || '').trim() || null : null,
          item_name: null,
          lot_number: null,
          quantity: 1,
          unit_price: null,
          total_price: null,
          status: 'completed',
          raw_text: text.replace(/\s+/g, ' ').trim().substring(0, 800),
          weight_oz: weight ? parseFloat(weight[1]) : null,
          box_length_in: dims ? parseFloat(dims[1]) : null,
          box_width_in: dims ? parseFloat(dims[2]) : null,
          box_height_in: dims ? parseFloat(dims[3]) : null,
          shipping_carrier: carrier ? carrier[1].toUpperCase() : null,
          shipping_service: carrier && carrier[2] ? carrier[2].trim() : null,
          shipping_status_scraped: st,
          tracking_number: tracking ? tracking[1] : null,
        });
      }
      return out;
    }
    """) or []


def page_signature(page) -> str:
    try:
        return str(page.evaluate(r"""
        () => {
          const ids = [...document.querySelectorAll('tr[data-testid^="shipments-"], tbody tr')]
            .map(tr => ((tr.innerText || '').match(/Order\s*#\s*(\d+)/i) || [])[1])
            .filter(Boolean);
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


def expand_all(page) -> None:
    selectors = [
        'button[aria-label="Expand All"]',
        'button[aria-label="Expand all"]',
        'button:has-text("Expand All")',
        'button:has-text("Expand all")',
    ]
    for selector in selectors:
        try:
            button = page.locator(selector).first
            if button.is_visible(timeout=400):
                button.click(timeout=2000)
                page.wait_for_timeout(250)
                return
        except Exception:
            pass


def advance_page(page, previous_signature: str) -> bool:
    state = pagination_state(page)
    if not state.get("exists") or state.get("disabled"):
        return False
    try:
        svg = page.locator('svg[aria-label="Next page"]').first
        button = svg.locator('xpath=ancestor::button[1]')
        if button.is_visible(timeout=1000) and not button.is_disabled():
            button.scroll_into_view_if_needed(timeout=1500)
            button.click(timeout=3000)
        else:
            return False
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

            target = f"{BASE}/dashboard/shipments?source={live_id}"
            page.goto(target, wait_until="domcontentloaded", timeout=30000)
            for _ in range(40):
                base.check_login(page)
                if page_signature(page) or pagination_state(page).get("exists"):
                    break
                page.wait_for_timeout(250)

            found: dict[str, dict[str, Any]] = {}
            page_number = 1
            for _ in range(100):
                base.check_login(page)
                expand_all(page)
                rows = extract_shipments(page)
                for row in rows:
                    found[str(row.get("order_id") or len(found))] = row
                state = pagination_state(page)
                info(f"shipments-batch: [{idx}/{len(sources)}] {show_key} page={page_number} page_rows={len(rows)} total_unique={len(found)} pagination={json.dumps(state, separators=(',', ':'))}")
                signature = page_signature(page)
                if not signature or not advance_page(page, signature):
                    break
                page_number += 1

            rows = list(found.values())
            info(f"shipments-batch: [{idx}/{len(sources)}] {show_key} -> {len(rows)} row(s) across {page_number} page(s)")
            output.append({"show_key": show_key, "live_id": live_id, "order_count": len(rows), "orders": rows})

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

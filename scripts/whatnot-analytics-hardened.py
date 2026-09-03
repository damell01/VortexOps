from __future__ import annotations

import json
import os
import re
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any


MONTHS = {
    "jan": 1, "january": 1, "feb": 2, "february": 2,
    "mar": 3, "march": 3, "apr": 4, "april": 4, "may": 5,
    "jun": 6, "june": 6, "jul": 7, "july": 7, "aug": 8, "august": 8,
    "sep": 9, "sept": 9, "september": 9, "oct": 10, "october": 10,
    "nov": 11, "november": 11, "dec": 12, "december": 12,
}

GENERIC_TITLE_RE = re.compile(
    r"^(?:whatnot|seller hub|account\s*[-–—|:]?\s*analytics|analytics|"
    r"livestream analytics|show analytics|overview|performance|orders|buyers|"
    r"completed|ended|live|dashboard|see older show|see newer show)$",
    re.I,
)


def clean(value: Any) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip()


def parse_money(value: Any) -> float | None:
    raw = re.sub(r"[^0-9.-]", "", str(value or ""))
    if raw in {"", "-", ".", "-."}:
        return None
    try:
        return float(raw)
    except ValueError:
        return None


def parse_int(value: Any) -> int | None:
    raw = re.sub(r"[^0-9]", "", str(value or ""))
    return int(raw) if raw else None


def parse_duration(value: Any) -> int | None:
    text = clean(value)
    if not text:
        return None
    hm = re.search(r"(\d+)\s*h(?:r|our)?s?\s*(?:(\d+)\s*m)?", text, re.I)
    if hm:
        return int(hm.group(1)) * 60 + int(hm.group(2) or 0)
    mm = re.search(r"(\d+)\s*m(?:in)?", text, re.I)
    if mm:
        return int(mm.group(1))
    clock = re.search(r"\b(\d+):(\d{2})(?::\d{2})?\b", text)
    return int(clock.group(1)) * 60 + int(clock.group(2)) if clock else None


def parse_date(*values: Any) -> str | None:
    for value in values:
        text = clean(value)
        if not text:
            continue
        m = re.search(r"\b(20\d{2})[-/](\d{1,2})[-/](\d{1,2})\b", text)
        if m:
            return f"{int(m.group(1)):04d}-{int(m.group(2)):02d}-{int(m.group(3)):02d}"
        m = re.search(r"\b(\d{1,2})[/-](\d{1,2})[/-](20\d{2})\b", text)
        if m:
            return f"{int(m.group(3)):04d}-{int(m.group(1)):02d}-{int(m.group(2)):02d}"
        m = re.search(
            r"\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|"
            r"Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)"
            r"\s+(\d{1,2})(?:st|nd|rd|th)?[,]?\s+(20\d{2})\b",
            text,
            re.I,
        )
        if m:
            return f"{int(m.group(3)):04d}-{MONTHS[m.group(1).lower()]:02d}-{int(m.group(2)):02d}"
        try:
            return datetime.fromisoformat(text.replace("Z", "+00:00")).date().isoformat()
        except Exception:
            pass
    return None


def pick_title(candidates: list[Any]) -> str | None:
    for value in candidates:
        title = clean(value)
        if not 3 <= len(title) <= 180:
            continue
        title = re.sub(r"\s*[|–—-]\s*Whatnot(?: Seller Hub)?$", "", title, flags=re.I).strip()
        title = re.sub(r"^Whatnot\s*[|–—-]\s*", "", title, flags=re.I).strip()
        if not title or GENERIC_TITLE_RE.match(title):
            continue
        if parse_date(title):
            continue
        if re.fullmatch(r"[$+\-]?\d[\d,.% ]*", title):
            continue
        if re.search(r"^(?:estimated sales|gross revenue|revenue|total estimated earnings|estimated earnings|completed earnings|units sold|orders|buyers|first time buyers|returning buyers|shares|show duration|max concurrent viewers|total views|average order value|avg order value|giveaway spend|giveaways)\b", title, re.I):
            continue
        return title
    return None


def snapshot(page) -> dict[str, Any]:
    return page.evaluate(r"""
    () => {
      const text = document.body?.innerText || '';
      const liveId = (location.href.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i) || [])[0] || null;
      const wanted = [
        'Estimated Sales','Gross Revenue','Revenue','Total Estimated Earnings','Estimated Earnings','Net Revenue',
        'Completed Earnings','Units Sold','Orders','Buyers','First Time Buyers','Returning Buyers','Shares',
        'Show Duration','Max Concurrent Viewers','Total Views','Average Order Value','Avg Order Value',
        'Giveaway Spend','Giveaways'
      ];
      const labels = {};
      const all = [...document.querySelectorAll('body *')];
      for (const label of wanted) {
        const lower = label.toLowerCase();
        const hit = all.find(el => el.childElementCount === 0 && (el.textContent || '').trim().toLowerCase() === lower);
        if (!hit) continue;
        let node = hit.parentElement;
        for (let depth = 0; node && depth < 8; depth++, node = node.parentElement) {
          const values = [...node.querySelectorAll('*')]
            .filter(el => el !== hit && el.childElementCount === 0)
            .map(el => (el.textContent || '').trim())
            .filter(v => v && v.length < 100 && v.toLowerCase() !== lower);
          const value = values.find(v => /^[-+$]?[$]?\d[\d,.]*(?:\s*%|\s*k|\s*m|\s*h|\s*hr|\s*hrs|\s*min)?$/i.test(v));
          if (value) { labels[label] = value; break; }
        }
      }
      const titleCandidates = [];
      const push = value => {
        value = String(value || '').replace(/\s+/g, ' ').trim();
        if (value && !titleCandidates.includes(value)) titleCandidates.push(value);
      };
      for (const el of document.querySelectorAll('a[href],button,[role="combobox"],[aria-haspopup="listbox"]')) {
        const href = el.getAttribute?.('href') || '';
        const value = (el.innerText || el.textContent || '').trim();
        if ((liveId && href.includes(liveId)) || href.includes('live_id=') || el.getAttribute?.('role') === 'combobox' || el.getAttribute?.('aria-haspopup') === 'listbox') push(value);
      }
      for (const el of document.querySelectorAll('h1,h2,h3,[data-testid*="title" i],[class*="title" i]')) push(el.textContent);
      push(document.querySelector('meta[property="og:title"]')?.getAttribute('content'));
      push(document.querySelector('meta[name="twitter:title"]')?.getAttribute('content'));
      push(document.title);
      const dates = [];
      for (const el of document.querySelectorAll('time,[datetime],[data-testid*="date" i],[class*="date" i]')) {
        const value = el.getAttribute?.('datetime') || el.textContent || '';
        if (value) dates.push(String(value).trim());
      }
      return {
        live_id: liveId,
        url: location.href,
        text: text.substring(0, 50000),
        preview: text.replace(/\s+/g, ' ').trim().substring(0, 1600),
        labels,
        title_candidates: titleCandidates.slice(0, 30),
        date_candidates: dates.slice(0, 30),
      };
    }
    """)


def extract_show(page) -> dict[str, Any]:
    raw = snapshot(page)
    labels = raw.get("labels") or {}
    row = {
        "title": pick_title(raw.get("title_candidates") or []),
        "show_date": parse_date(*(raw.get("date_candidates") or []), raw.get("text")),
        "whatnot_live_id": raw.get("live_id"),
        "detail_url": f"https://www.whatnot.com/dashboard/live/{raw.get('live_id')}" if raw.get("live_id") else raw.get("url"),
        "gross_revenue": parse_money(labels.get("Estimated Sales") or labels.get("Gross Revenue") or labels.get("Revenue")),
        "whatnot_net": parse_money(labels.get("Total Estimated Earnings") or labels.get("Estimated Earnings") or labels.get("Net Revenue")),
        "completed_earnings": parse_money(labels.get("Completed Earnings")),
        "units_sold": parse_int(labels.get("Units Sold") or labels.get("Orders")),
        "buyers_count": parse_int(labels.get("Buyers")),
        "first_time_buyers": parse_int(labels.get("First Time Buyers")),
        "returning_buyers": parse_int(labels.get("Returning Buyers")),
        "shares_count": parse_int(labels.get("Shares")),
        "show_duration": parse_duration(labels.get("Show Duration")),
        "max_concurrent_viewers": parse_int(labels.get("Max Concurrent Viewers")),
        "total_views": parse_int(labels.get("Total Views")),
        "avg_order_value": parse_money(labels.get("Average Order Value") or labels.get("Avg Order Value")),
        "giveaway_spend": parse_money(labels.get("Giveaway Spend")),
        "giveaways_count": parse_int(labels.get("Giveaways")),
    }
    row["_preview"] = raw.get("preview")
    row["_titles"] = raw.get("title_candidates") or []
    row["_dates"] = raw.get("date_candidates") or []
    return row


def has_useful_data(row: dict[str, Any]) -> bool:
    return bool(row.get("title") or row.get("show_date")) and any(
        row.get(k) is not None for k in (
            "gross_revenue", "whatnot_net", "completed_earnings", "units_sold",
            "buyers_count", "total_views", "avg_order_value", "show_duration",
        )
    )


def wait_for_row(module, page, previous_live_id: str | None = None, timeout_ms: int = 15000) -> dict[str, Any]:
    elapsed = 0
    last: dict[str, Any] | None = None
    while elapsed < timeout_ms:
        module.check_login(page)
        last = extract_show(page)
        changed = previous_live_id is None or (
            last.get("whatnot_live_id") and last.get("whatnot_live_id") != previous_live_id
        )
        if changed and has_useful_data(last):
            return last
        page.wait_for_timeout(500)
        elapsed += 500
    return last or extract_show(page)


def analytics(module, session):
    if not module.UUID_RE.fullmatch(module.START_UUID):
        module.fail("ANALYTICS_SEED_REQUIRED: WHATNOT_START_UUID is required")
    rows: list[dict[str, Any]] = []
    seen: set[str] = set()
    end_date = (date.today() + timedelta(days=7)).isoformat()
    target = f"{module.BASE}/account/analytics?tab=livestream&live_id={module.START_UUID}&start_dt=2019-01-01&end_dt={end_date}"
    module.info(f"analytics: range end={end_date} seed={module.START_UUID}")

    def action(page):
        module.prepare(page)
        page.goto(target, wait_until="domcontentloaded", timeout=30000)
        previous_live_id = None
        for index in range(module.LIMIT):
            row = wait_for_row(module, page, previous_live_id)
            key = row.get("whatnot_live_id") or f"{row.get('title')}|{row.get('show_date')}"
            if not key or key in seen:
                break
            seen.add(key)
            if not has_useful_data(row):
                module.info("ANALYTICS_DIAGNOSTIC " + json.dumps({
                    "index": index + 1,
                    "live_id": row.get("whatnot_live_id"),
                    "title": row.get("title"),
                    "show_date": row.get("show_date"),
                    "titles": (row.get("_titles") or [])[:8],
                    "dates": (row.get("_dates") or [])[:8],
                    "preview": row.get("_preview"),
                }, separators=(",", ":")))
            row.pop("_preview", None)
            row.pop("_titles", None)
            row.pop("_dates", None)
            rows.append(row)
            previous_live_id = row.get("whatnot_live_id")
            older = page.get_by_text(re.compile(r"see older show", re.I)).first
            try:
                if not older.is_visible(timeout=2000):
                    break
                older.click(timeout=5000)
            except Exception:
                break
        module.info(f"analytics: collected {len(rows)} show(s)")

    session.fetch(
        f"{module.BASE}/dashboard/home",
        page_action=action,
        timeout=60000,
        network_idle=False,
        google_search=False,
    )
    return rows


def extract_orders_hardened(page) -> list[dict[str, Any]]:
    return page.evaluate(r"""
    () => {
      const money = value => {
        const text = String(value || '').trim();
        if (!text || text === '-') return null;
        const match = text.match(/-?\$?\s*([\d,]+(?:\.\d+)?)/);
        if (!match) return null;
        const number = parseFloat(match[0].replace(/[^0-9.-]/g, ''));
        return Number.isFinite(number) ? number : null;
      };
      const rows = [...document.querySelectorAll('tbody[data-testid="orders-table-body"] tr, tbody tr, tr[data-testid^="orders-"]')]
        .filter((row, index, all) => all.indexOf(row) === index && /Order\s*#\s*\d+/i.test(row.innerText || ''));
      return rows.map((tr, rowIndex) => {
        const cells = [...tr.querySelectorAll(':scope > td')];
        const text = tr.innerText || '';
        const orderMatch = text.match(/Order\s*#\s*(\d+)/i);
        const titleNode = tr.querySelector('span[title], strong[title], img[alt]');
        const buyerLink = tr.querySelector('a[href*="participantId"], a[href*="/dashboard/inbox"]');
        const detailLink = [...tr.querySelectorAll('a[href]')].find(a => /\/dashboard\/orders\//.test(a.getAttribute('href') || ''));
        const qtyText = cells[3]?.innerText || '';
        const qty = parseInt(qtyText.replace(/[^0-9]/g, ''), 10);
        const item = titleNode ? (titleNode.getAttribute('title') || titleNode.getAttribute('alt') || titleNode.textContent || '').trim() : null;
        return {
          order_id: orderMatch ? orderMatch[1] : null,
          order_hash: detailLink ? (detailLink.getAttribute('href') || '').split('/').pop()?.split('?')[0] || null : null,
          order_detail_url: detailLink ? new URL(detailLink.getAttribute('href'), location.origin).href : null,
          buyer: buyerLink ? (buyerLink.textContent || '').trim() || null : (cells[2]?.innerText || '').trim() || null,
          item_name: item,
          lot_number: null,
          quantity: Number.isFinite(qty) ? qty : 1,
          unit_price: money(cells[5]?.innerText || ''),
          total_price: money(cells[5]?.innerText || ''),
          net_earnings: money(cells[7]?.innerText || ''),
          sales_channel: (cells[4]?.innerText || '').trim() || null,
          status: (cells[6]?.innerText || '').trim() || 'completed',
          raw_text: text.replace(/\s+/g, ' ').trim().substring(0, 800),
          _row_index: rowIndex,
        };
      });
    }
    """)


def order_page_signature(page) -> str:
    try:
        return str(page.evaluate(r"""
        () => {
          const ids = [...document.querySelectorAll('tbody tr, tr[data-testid^="orders-"]')]
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
          return {
            exists: !!button,
            disabled: !button || !!button.disabled || button.getAttribute('aria-disabled') === 'true',
            showing,
            url: location.href,
          };
        }
        """) or {}
    except Exception:
        return {}


def advance_orders_page(module, page, previous_signature: str) -> bool:
    state = pagination_state(page)
    if not state.get("exists") or state.get("disabled"):
        return False

    module.info(f"orders-batch: pagination next state={json.dumps(state, separators=(',', ':'))}")

    # Prefer a real browser click. It follows React's event path more reliably
    # than calling element.click() from page.evaluate().
    try:
        button = page.locator('svg[aria-label="Next page"]').first.locator('xpath=ancestor::button[1]')
        if button.is_visible(timeout=1500) and not button.is_disabled():
            button.scroll_into_view_if_needed(timeout=2000)
            button.click(timeout=5000)
        else:
            return False
    except Exception as first_exc:
        module.info(f"orders-batch: locator next click failed error={first_exc}; trying DOM click")
        try:
            clicked = page.evaluate(r"""
            () => {
              const svg = document.querySelector('svg[aria-label="Next page"]');
              const button = svg ? svg.closest('button') : document.querySelector('button[aria-label="Next page"]');
              if (!button || button.disabled || button.getAttribute('aria-disabled') === 'true') return false;
              button.click();
              return true;
            }
            """)
            if not clicked:
                return False
        except Exception:
            return False

    for _ in range(40):
        page.wait_for_timeout(250)
        module.check_login(page)
        current = order_page_signature(page)
        if current and current != previous_signature:
            return True

    module.info(
        f"orders-batch: pagination stalled previous={previous_signature!r} "
        f"state={json.dumps(pagination_state(page), separators=(',', ':'))}"
    )
    return False


def extract_order_sidebar(page) -> dict[str, Any]:
    return page.evaluate(r"""
    () => {
      const root = document.querySelector('[data-testid="orders-details-sidebar"]');
      if (!root) return {};
      const text = root.innerText || '';
      const money = value => {
        const m = String(value || '').match(/-?\$\s*[\d,]+(?:\.\d+)?/);
        if (!m) return null;
        const n = parseFloat(m[0].replace(/[^0-9.-]/g, ''));
        return Number.isFinite(n) ? n : null;
      };
      const pair = label => {
        const nodes = [...root.querySelectorAll('strong,span')];
        const hit = nodes.find(el => (el.textContent || '').trim().toLowerCase() === label.toLowerCase());
        if (!hit) return null;
        const section = hit.closest('section') || hit.parentElement;
        if (!section) return null;
        const vals = [...section.querySelectorAll('strong,span,a')]
          .map(el => (el.textContent || '').trim())
          .filter(Boolean);
        return vals.find(v => v.toLowerCase() !== label.toLowerCase()) || null;
      };
      const shipmentLink = root.querySelector('a[href*="/dashboard/shipments/"]');
      const shipmentHref = shipmentLink?.getAttribute('href') || null;
      const shipmentId = shipmentHref ? (shipmentHref.match(/\/dashboard\/shipments\/(\d+)/) || [])[1] || null : null;
      const orderId = (text.match(/Order\s*#\s*(\d+)/i) || [])[1] || null;
      const buyerPaid = text.match(/Buyer paid[\s\S]*?Price\s+(-?\$[\d,.]+)[\s\S]*?Taxes paid by buyer\s+(-?\$[\d,.]+)[\s\S]*?Shipping paid by buyer\s+(-?\$[\d,.]+)[\s\S]*?Order total\s+(-?\$[\d,.]+)/i);
      const earnings = text.match(/Your earnings[\s\S]*?Price\s+(-?\$[\d,.]+)[\s\S]*?(?:Shipping\s+(-?\$[\d,.]+)[\s\S]*?)?Net earnings\s+(-?\$[\d,.]+)/i);
      return {
        order_id: orderId,
        shipment_id: shipmentId,
        shipment_url: shipmentHref ? new URL(shipmentHref, location.origin).href : null,
        order_date: pair('Order date'),
        order_time: pair('Order time'),
        buyer: pair('Buyer'),
        product_category: pair('Product Category'),
        quantity_detail: pair('Quantity'),
        show_name: pair('Show'),
        show_category: pair('Show Category'),
        buyer_price: buyerPaid ? money(buyerPaid[1]) : null,
        tax_amount: buyerPaid ? money(buyerPaid[2]) : null,
        shipping_paid_by_buyer: buyerPaid ? money(buyerPaid[3]) : null,
        order_total_detail: buyerPaid ? money(buyerPaid[4]) : null,
        earnings_price: earnings ? money(earnings[1]) : null,
        seller_shipping_cost: earnings ? money(earnings[2]) : null,
        net_earnings_detail: earnings ? money(earnings[3]) : null,
      };
    }
    """) or {}


def enrich_order_row(module, page, row: dict[str, Any]) -> dict[str, Any]:
    order_id = str(row.get("order_id") or "")
    row_index = row.get("_row_index")
    if not order_id and row_index is None:
        return row

    try:
        candidates = page.locator('tbody[data-testid="orders-table-body"] tr, tbody tr, tr[data-testid^="orders-"]')
        target = None
        count = min(candidates.count(), 200)
        for i in range(count):
            candidate = candidates.nth(i)
            try:
                text = candidate.inner_text(timeout=400) or ""
            except Exception:
                continue
            if order_id and re.search(rf"Order\s*#\s*{re.escape(order_id)}\b", text, re.I):
                target = candidate
                break
        if target is None and isinstance(row_index, int) and row_index < count:
            target = candidates.nth(row_index)
        if target is None:
            return row

        target.click(timeout=4000, force=True)
        sidebar = page.locator('[data-testid="orders-details-sidebar"]').first
        if not sidebar.is_visible(timeout=3000):
            return row
        detail = extract_order_sidebar(page)
        if detail:
            row.update({k: v for k, v in detail.items() if v is not None and v != ""})
            if row.get("order_total_detail") is not None:
                row["total_price"] = row.get("order_total_detail")
            if row.get("net_earnings_detail") is not None:
                row["net_earnings"] = row.get("net_earnings_detail")
        try:
            close = sidebar.locator('[aria-label="Close order details"]').first
            if close.is_visible(timeout=500):
                close.click(timeout=2000)
            else:
                page.keyboard.press('Escape')
        except Exception:
            try:
                page.keyboard.press('Escape')
            except Exception:
                pass
        page.wait_for_timeout(100)
    except Exception as exc:
        module.info(f"orders-batch: detail enrichment failed order={order_id or row_index} error={exc}")
    return row


def orders_batch(module, session):
    source_file = os.getenv("WHATNOT_ORDER_SOURCES_FILE", "").strip()
    if not source_file or not Path(source_file).exists():
        module.fail("WHATNOT_ORDER_SOURCES_FILE is required")
    sources = json.loads(Path(source_file).read_text())
    output: list[dict[str, Any]] = []

    enrich_raw = os.getenv("WHATNOT_ORDER_DETAIL_ENRICH", "").strip().lower()
    enrich_details = enrich_raw in {"1", "true", "yes", "on"} if enrich_raw else len(sources) <= 5
    detail_limit = max(0, int(os.getenv("WHATNOT_ORDER_DETAIL_MAX", "500") or "500"))
    module.info(
        f"orders-batch: detail_enrichment={'on' if enrich_details else 'off'} "
        f"sources={len(sources)} detail_max={detail_limit}"
    )

    def action(page):
        module.prepare(page)
        for idx, source in enumerate(sources, 1):
            live_id = source.get("live_id")
            show_key = source.get("show_key")
            if not live_id:
                output.append({"show_key": show_key, "live_id": None, "order_count": 0, "orders": []})
                continue

            target = f"{module.BASE}/dashboard/orders?source={live_id}&first=100"
            page.goto(target, wait_until="domcontentloaded", timeout=30000)
            for _ in range(40):
                module.check_login(page)
                if order_page_signature(page):
                    break
                page.wait_for_timeout(250)

            found: dict[str, dict[str, Any]] = {}
            page_number = 1
            enriched_count = 0
            for _ in range(100):
                module.check_login(page)
                rows = extract_orders_hardened(page)

                if enrich_details and enriched_count < detail_limit:
                    for row in rows:
                        if enriched_count >= detail_limit:
                            break
                        enrich_order_row(module, page, row)
                        enriched_count += 1

                for row in rows:
                    row.pop("_row_index", None)
                    key = str(row.get("order_id") or row.get("order_hash") or len(found))
                    found[key] = row

                state = pagination_state(page)
                module.info(
                    f"orders-batch: [{idx}/{len(sources)}] {show_key} "
                    f"page={page_number} page_rows={len(rows)} total_unique={len(found)} "
                    f"pagination={json.dumps(state, separators=(',', ':'))}"
                )

                signature = order_page_signature(page)
                if not signature or not advance_orders_page(module, page, signature):
                    break
                page_number += 1

            rows = list(found.values())
            module.info(
                f"orders-batch: [{idx}/{len(sources)}] {show_key} -> "
                f"{len(rows)} row(s) across {page_number} page(s) enriched={enriched_count}"
            )
            output.append({
                "show_key": show_key,
                "live_id": live_id,
                "order_count": len(rows),
                "orders": rows,
            })

    session.fetch(
        f"{module.BASE}/dashboard/home",
        page_action=action,
        timeout=60000,
        network_idle=False,
        google_search=False,
    )
    return output


def install(module) -> None:
    module.extract_show = extract_show
    module.analytics = lambda session: analytics(module, session)
    original_batch = module.batch
    module.batch = lambda session, shipments=False: (
        original_batch(session, True) if shipments else orders_batch(module, session)
    )

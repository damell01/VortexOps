from __future__ import annotations

import json
import os
import re
import sys
from datetime import date
from pathlib import Path
from typing import Any

from scrapling.fetchers import DynamicSession

BASE = "https://www.whatnot.com"
MODE = os.getenv("WHATNOT_MODE", "analytics").strip()
CHANNEL = os.getenv("WHATNOT_CHANNEL_NAME", "").strip()
PROFILE = os.getenv("WHATNOT_USER_DATA_DIR", "").strip()
CHROME = os.getenv("PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH", "").strip()
HEADLESS = os.getenv("WHATNOT_HEADLESS", "true").lower() != "false"
LIMIT = max(1, min(500, int(os.getenv("WHATNOT_LIMIT", "50"))))
START_UUID = os.getenv("WHATNOT_START_UUID", "").strip()

UUID_RE = re.compile(r"[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}", re.I)


def info(message: str) -> None:
    print(f"[whatnot:scrapling] {message}", file=sys.stderr, flush=True)


def fail(message: str, code: int = 1) -> None:
    print(message, file=sys.stderr, flush=True)
    raise SystemExit(code)


def normalize_username(value: str | None) -> str:
    return re.sub(r"[^a-z0-9]", "", (value or "").lower())


def active_username(page) -> str | None:
    selectors = [
        'button:has(svg[aria-label="Current account"]) img.z-avatar-image[alt]',
        'img.z-avatar-image[alt][width="40"][height="40"]:visible',
        'header a[href^="/user/"], nav a[href^="/user/"], aside a[href^="/user/"]',
    ]
    for selector in selectors:
        loc = page.locator(selector).first
        try:
            if selector.startswith("header"):
                href = loc.get_attribute("href", timeout=1200)
                if href:
                    m = re.match(r"^/user/([^/?#]+)", href)
                    if m:
                        return m.group(1)
            else:
                alt = loc.get_attribute("alt", timeout=1200)
                if alt:
                    return alt
        except Exception:
            pass
    return None


def ensure_channel(page, requested: str) -> str:
    if not requested:
        fail("CHANNEL_CONTEXT_MISMATCH: WHATNOT_CHANNEL_NAME is required for Scrapling desktop collection.")

    current = active_username(page)
    if current and normalize_username(current) == normalize_username(requested):
        info(f"CHANNEL_CONTEXT_VERIFIED requested=@{requested} active=@{current}")
        return current

    profile = page.locator("#team-invite-profile-menu-anchor").first
    try:
        profile.click(timeout=5000, force=True)
    except Exception as exc:
        fail(f"CHANNEL_SWITCH_FAILED: profile menu did not open for @{requested}: {exc}")

    switch_role = page.locator("#team-invite-switch-role-anchor").first
    try:
        switch_role.click(timeout=5000, force=True)
    except Exception as exc:
        fail(f"CHANNEL_SWITCH_FAILED: Switch Role was not available for @{requested}: {exc}")

    target_key = normalize_username(requested)
    target = page.locator(f'button:has(img.z-avatar-image[alt="{requested.lower()}"])').first
    try:
        if not target.is_visible(timeout=2500):
            buttons = page.locator('button[formaction="/api/v1/auth/switch-role"]')
            for index in range(min(buttons.count(), 20)):
                candidate = buttons.nth(index)
                text = candidate.inner_text(timeout=1000)
                if target_key in normalize_username(text):
                    target = candidate
                    break
        target.click(timeout=5000, force=True)
    except Exception as exc:
        fail(f"CHANNEL_SWITCH_FAILED: requested channel @{requested} was not available: {exc}")

    try:
        page.wait_for_timeout(1200)
        page.goto(f"{BASE}/dashboard/home", wait_until="domcontentloaded", timeout=20000)
        page.wait_for_timeout(1000)
    except Exception:
        pass

    verified = None
    for _ in range(15):
        verified = active_username(page)
        if verified and normalize_username(verified) == target_key:
            info(f"CHANNEL_CONTEXT_VERIFIED requested=@{requested} active=@{verified}")
            return verified
        page.wait_for_timeout(500)

    fail(f"CHANNEL_CONTEXT_MISMATCH: refusing to scrape. requested=@{requested} active=@{verified or '?'}")
    return ""


def check_login(page) -> None:
    url = page.url
    if re.search(r"/(login|signin|auth)(/|\?|$)", url, re.I):
        fail(f"LOGIN_REQUIRED: dedicated Whatnot profile redirected to login. URL={url}", 3)


def prepare_page(page) -> None:
    check_login(page)
    ensure_channel(page, CHANNEL)


def extract_show(page) -> dict[str, Any]:
    raw = page.evaluate(
        """
        () => {
          const text = document.body?.innerText || '';
          const title = document.querySelector('h1,h2')?.textContent?.trim() || null;
          const dateLine = [...document.querySelectorAll('body *')]
            .map(el => (el.textContent || '').trim())
            .find(t => /\d{1,2}\/\d{1,2}\/20\d\d/.test(t) && t.length < 120) || null;
          const labels = {};
          const wanted = [
            'Gross Revenue','Estimated Earnings','Completed Earnings','Units Sold','Orders','Buyers',
            'First Time Buyers','Returning Buyers','Shares','Show Duration','Max Concurrent Viewers',
            'Total Views','Average Order Value','Avg Order Value','Giveaway Spend','Giveaways'
          ];
          for (const label of wanted) {
            const node = [...document.querySelectorAll('*')].find(el =>
              el.childElementCount === 0 && (el.textContent || '').trim().toLowerCase() === label.toLowerCase()
            );
            if (!node) continue;
            let parent = node.parentElement;
            for (let i=0; parent && i<5; i++, parent=parent.parentElement) {
              const values = [...parent.querySelectorAll('*')]
                .filter(el => el !== node && el.childElementCount === 0)
                .map(el => (el.textContent || '').trim())
                .filter(v => v && v.length < 80 && v !== label);
              const value = values.find(v => /^[-+$]?[$]?\d[\d,.]*(?:%|k|m|h|min)?$/i.test(v));
              if (value) { labels[label] = value; break; }
            }
          }
          const m = location.href.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
          return { title, dateLine, labels, live_id: m ? m[0] : null, url: location.href, text: text.substring(0,2000) };
        }
        """
    )

    def money(value: Any) -> float | None:
        if value is None:
            return None
        cleaned = re.sub(r"[^0-9.-]", "", str(value))
        try:
            return float(cleaned)
        except ValueError:
            return None

    def integer(value: Any) -> int | None:
        if value is None:
            return None
        cleaned = re.sub(r"[^0-9]", "", str(value))
        return int(cleaned) if cleaned else None

    labels = raw.get("labels") or {}
    date_match = re.search(r"(\d{1,2})/(\d{1,2})/(20\d\d)", raw.get("dateLine") or raw.get("text") or "")
    show_date = None
    if date_match:
        show_date = f"{date_match.group(3)}-{int(date_match.group(1)):02d}-{int(date_match.group(2)):02d}"

    duration = labels.get("Show Duration")
    duration_minutes = None
    if duration:
        h = re.search(r"(\d+)\s*h", duration, re.I)
        m = re.search(r"(\d+)\s*m", duration, re.I)
        duration_minutes = (int(h.group(1)) * 60 if h else 0) + (int(m.group(1)) if m else 0)

    return {
        "title": raw.get("title"),
        "show_date": show_date,
        "whatnot_live_id": raw.get("live_id"),
        "detail_url": f"{BASE}/dashboard/live/{raw.get('live_id')}" if raw.get("live_id") else raw.get("url"),
        "gross_revenue": money(labels.get("Gross Revenue")),
        "whatnot_net": money(labels.get("Estimated Earnings")),
        "completed_earnings": money(labels.get("Completed Earnings")),
        "units_sold": integer(labels.get("Units Sold") or labels.get("Orders")),
        "buyers_count": integer(labels.get("Buyers")),
        "first_time_buyers": integer(labels.get("First Time Buyers")),
        "returning_buyers": integer(labels.get("Returning Buyers")),
        "shares_count": integer(labels.get("Shares")),
        "show_duration": duration_minutes,
        "max_concurrent_viewers": integer(labels.get("Max Concurrent Viewers")),
        "total_views": integer(labels.get("Total Views")),
        "avg_order_value": money(labels.get("Average Order Value") or labels.get("Avg Order Value")),
        "giveaway_spend": money(labels.get("Giveaway Spend")),
        "giveaways_count": integer(labels.get("Giveaways")),
    }


def analytics_mode(session: DynamicSession) -> list[dict[str, Any]]:
    seed = START_UUID
    if not UUID_RE.fullmatch(seed):
        fail("ANALYTICS_SEED_REQUIRED: desktop Scrapling analytics requires WHATNOT_START_UUID from VortexOps bootstrap.")

    today = date.today().isoformat()
    url = f"{BASE}/account/analytics?tab=livestream&live_id={seed}&start_dt=2019-01-01&end_dt={today}"
    rows: list[dict[str, Any]] = []
    seen: set[str] = set()

    def action(page):
        prepare_page(page)
        page.goto(url, wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(1000)
        for _ in range(LIMIT):
            check_login(page)
            current = extract_show(page)
            live_id = current.get("whatnot_live_id")
            key = live_id or f"{current.get('title')}|{current.get('show_date')}"
            if key in seen:
                break
            seen.add(key)
            rows.append(current)

            older = page.get_by_text(re.compile(r"see older show", re.I)).first
            try:
                if not older.is_visible(timeout=1200):
                    break
                older.click(timeout=4000)
                page.wait_for_timeout(900)
            except Exception:
                break

    session.fetch(f"{BASE}/dashboard/home", page_action=action, timeout=60000, network_idle=False, google_search=False)
    info(f"analytics: collected {len(rows)} show(s)")
    return rows


def extract_orders(page) -> list[dict[str, Any]]:
    return page.evaluate(
        """
        () => {
          const parsePrice = (s) => {
            if (!s) return null;
            const neg = /-\s*\$/.test(s);
            const v = parseFloat(s.replace(/[^0-9.]/g,''));
            return Number.isFinite(v) ? (neg ? -v : v) : null;
          };
          const out = [];
          for (const tr of document.querySelectorAll('tbody[data-testid="orders-table-body"] tr, tr[data-testid^="orders-"]')) {
            const tds = [...tr.querySelectorAll(':scope > td')];
            if (tds.length < 6) continue;
            const orderCell = tds[0];
            const titleEl = orderCell.querySelector('span[title],strong');
            const orderId = (orderCell.innerText || '').match(/Order\s*#\s*(\d+)/i);
            const qty = parseInt((tds[3]?.innerText || '').replace(/[^0-9]/g,''),10);
            const earningsEl = tds[7]?.querySelector('strong');
            out.push({
              order_id: orderId ? orderId[1] : null,
              buyer: (tds[2]?.innerText || '').trim() || null,
              item_name: titleEl ? (titleEl.getAttribute('title') || titleEl.textContent || '').trim() : null,
              lot_number: null,
              quantity: Number.isFinite(qty) ? qty : 1,
              unit_price: parsePrice(tds[5]?.innerText || ''),
              total_price: parsePrice(tds[5]?.innerText || ''),
              net_earnings: parsePrice(earningsEl ? earningsEl.textContent : (tds[7]?.innerText || '')),
              sales_channel: (tds[4]?.innerText || '').trim() || null,
              status: (tds[6]?.innerText || '').trim() || 'completed',
              raw_text: (tr.innerText || '').replace(/\s+/g,' ').trim().substring(0,400),
            });
          }
          return out;
        }
        """
    )


def batch_mode(session: DynamicSession, shipments: bool) -> list[dict[str, Any]]:
    src = os.getenv("WHATNOT_ORDER_SOURCES_FILE", "").strip()
    if not src or not Path(src).exists():
        fail("WHATNOT_ORDER_SOURCES_FILE is required")
    sources = json.loads(Path(src).read_text(encoding="utf-8"))
    output: list[dict[str, Any]] = []

    def action(page):
        prepare_page(page)
        for index, source in enumerate(sources, start=1):
            live_id = source.get("live_id")
            show_key = source.get("show_key")
            if not live_id:
                output.append({"show_key": show_key, "live_id": None, "order_count": 0, "orders": []})
                continue
            path = "shipments" if shipments else "orders"
            page.goto(f"{BASE}/dashboard/{path}?source={live_id}", wait_until="domcontentloaded", timeout=30000)
            page.wait_for_timeout(900)
            by_id: dict[str, dict[str, Any]] = {}
            for _ in range(40):
                if shipments:
                    try:
                        page.locator('button[aria-label="Expand All"]').first.click(timeout=1200)
                        page.wait_for_timeout(350)
                    except Exception:
                        pass
                rows = extract_shipments(page) if shipments else extract_orders(page)
                for row in rows:
                    key = str(row.get("order_id") or f"{row.get('buyer')}|{row.get('item_name')}|{len(by_id)}")
                    by_id[key] = row
                advanced = page.evaluate(
                    """() => { const svg=document.querySelector('svg[aria-label="Next page"]'); let b=svg?svg.closest('button'):document.querySelector('button[aria-label="Next page"]'); if(b && !b.disabled && b.getAttribute('aria-disabled')!=='true'){b.click();return true;} return false;}"""
                )
                if not advanced:
                    break
                page.wait_for_timeout(700)
            rows = list(by_id.values())
            info(f"{'shipments' if shipments else 'orders'}-batch: [{index}/{len(sources)}] {show_key} -> {len(rows)} row(s)")
            output.append({"show_key": show_key, "live_id": live_id, "order_count": len(rows), "orders": rows})

    session.fetch(f"{BASE}/dashboard/home", page_action=action, timeout=60000, network_idle=False, google_search=False)
    return output


def extract_shipments(page) -> list[dict[str, Any]]:
    return page.evaluate(
        """
        () => {
          const rows = [];
          for (const tr of document.querySelectorAll('tr[data-testid^="shipments-"]')) {
            const main = tr.innerText || '';
            const detail = tr.nextElementSibling?.tagName === 'TR' ? (tr.nextElementSibling.innerText || '') : '';
            const text = main + '\n' + detail;
            const order = text.match(/Order\s*#\s*(\d+)/i);
            if (!order) continue;
            const buyerLink = tr.querySelector('a[href*="/dashboard/inbox"]');
            const weight = text.match(/(\d+(?:\.\d+)?)\s*oz\b/i);
            const dims = text.match(/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*in\b/i);
            const carrier = text.match(/\b(USPS|UPS|FedEx|DHL)\b\s*([A-Za-z\d\s\-/]*[A-Za-z\d])?/i);
            const tracking = text.match(/(?:tracking\s*#?|label\s*#?)\s*([0-9]{12,})/i);
            let shippingStatus = null;
            if (/ready\s*to\s*ship/i.test(text)) shippingStatus='ready_to_ship';
            else if (/label\s*created/i.test(text)) shippingStatus='label_created';
            else if (/delivered/i.test(text)) shippingStatus='delivered';
            else if (/returned/i.test(text)) shippingStatus='returned';
            else if (/packed/i.test(text)) shippingStatus='packed';
            else if (/shipped/i.test(text)) shippingStatus='shipped';
            else if (/in\s*transit/i.test(text)) shippingStatus='in_transit';
            rows.push({
              order_id: order[1],
              buyer: buyerLink ? (buyerLink.textContent || '').trim() : null,
              item_name: null,
              lot_number: null,
              quantity: 1,
              unit_price: null,
              total_price: null,
              status: 'completed',
              raw_text: main.replace(/\s+/g,' ').trim().substring(0,400),
              weight_oz: weight ? parseFloat(weight[1]) : null,
              box_length_in: dims ? parseFloat(dims[1]) : null,
              box_width_in: dims ? parseFloat(dims[2]) : null,
              box_height_in: dims ? parseFloat(dims[3]) : null,
              shipping_carrier: carrier ? carrier[1].toUpperCase() : null,
              shipping_service: carrier && carrier[2] ? carrier[2].trim() : null,
              shipping_status_scraped: shippingStatus,
              tracking_number: tracking ? tracking[1] : null,
            });
          }
          return rows;
        }
        """
    )


def ledger_mode(session: DynamicSession) -> list[dict[str, Any]]:
    start = os.getenv("WHATNOT_LEDGER_FROM", "").strip()
    end = os.getenv("WHATNOT_LEDGER_TO", "").strip()
    output: list[dict[str, Any]] = []

    def action(page):
        prepare_page(page)
        page.goto(f"{BASE}/dashboard/ledger/overview", wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(900)
        if start and end:
            try:
                page.get_by_text(re.compile(r"edit dates", re.I)).first.click(timeout=3500)
            except Exception:
                try:
                    page.locator('button[title="Edit Dates"]').first.click(timeout=3500)
                except Exception:
                    pass
            try:
                inputs = page.locator('input[type="date"]')
                if inputs.count() >= 2:
                    inputs.nth(inputs.count() - 2).fill(start)
                    inputs.nth(inputs.count() - 1).fill(end)
                    page.get_by_role("button", name=re.compile(r"^update$", re.I)).click(timeout=3500)
                    page.wait_for_timeout(1800)
            except Exception:
                pass

        seen: dict[str, dict[str, Any]] = {}
        for _ in range(60):
            rows = page.evaluate(
                """
                () => [...document.querySelectorAll('table tbody tr')].map(tr => {
                  const c=[...tr.querySelectorAll(':scope > td')].map(td => (td.innerText||'').trim());
                  if(c.length<6) return null;
                  const orderLink=tr.querySelector('a[href*="/dashboard/orders/"]');
                  const orderId=(c[3]||'').match(/(\d+)/);
                  const listing=(c[2]||'').match(/(\d+)/);
                  return {created_date:c[0]||null,amount:c[1]||null,listing_id:listing?listing[1]:null,order_id:orderId?orderId[1]:null,order_hash:orderLink?(orderLink.getAttribute('href')||'').split('/').pop():null,message:c[4]||null,status:c[5]||null,transaction_type:c[6]||null,completed_date:c[7]||null};
                }).filter(Boolean)
                """
            )
            for row in rows:
                key = "|".join(str(row.get(k) or "") for k in ("order_id", "listing_id", "created_date", "amount", "transaction_type"))
                seen[key] = row
            advanced = page.evaluate("""() => { const svg=document.querySelector('svg[aria-label="Next page"]'); let b=svg?svg.closest('button'):document.querySelector('button[aria-label="Next page"]'); if(b && !b.disabled && b.getAttribute('aria-disabled')!=='true'){b.click();return true;} return false;}""")
            if not advanced:
                break
            page.wait_for_timeout(700)
        output.extend(seen.values())

    session.fetch(f"{BASE}/dashboard/home", page_action=action, timeout=60000, network_idle=False, google_search=False)
    info(f"ledger: extracted {len(output)} entries for {start or '?'}..{end or '?'}")
    return output


def main() -> None:
    if not PROFILE:
        fail("WHATNOT_USER_DATA_DIR is required")
    if not CHROME:
        fail("PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH is required")

    Path(PROFILE).mkdir(parents=True, exist_ok=True)
    info(f"engine=Scrapling DynamicSession profile={PROFILE} headless={HEADLESS}")

    # DynamicSession is intentionally used instead of StealthySession. We use the
    # operator's real Chrome + persistent authenticated profile, with no
    # solve_cloudflare, fingerprint spoofing, proxy rotation, or challenge bypass.
    with DynamicSession(
        headless=HEADLESS,
        real_chrome=True,
        executable_path=CHROME,
        user_data_dir=PROFILE,
        disable_resources=False,
        google_search=False,
        locale="en-US",
    ) as session:
        if MODE == "analytics":
            result = analytics_mode(session)
        elif MODE == "orders-batch":
            result = batch_mode(session, shipments=False)
        elif MODE == "shipments-batch":
            result = batch_mode(session, shipments=True)
        elif MODE == "ledger":
            result = ledger_mode(session)
        else:
            fail(f"Unsupported Scrapling desktop mode: {MODE}")

    json.dump(result, sys.stdout, separators=(",", ":"))
    sys.stdout.write("\n")
    sys.stdout.flush()


if __name__ == "__main__":
    main()

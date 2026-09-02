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
HEADLESS = os.getenv("WHATNOT_HEADLESS", "false").lower() != "false"
LIMIT = max(1, min(500, int(os.getenv("WHATNOT_LIMIT", "50"))))
START_UUID = os.getenv("WHATNOT_START_UUID", "").strip()
UUID_RE = re.compile(r"[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}", re.I)


def info(message: str) -> None:
    print(f"[whatnot:scrapling] {message}", file=sys.stderr, flush=True)


def fail(message: str, code: int = 1) -> None:
    print(message, file=sys.stderr, flush=True)
    raise SystemExit(code)


def norm(value: str | None) -> str:
    return re.sub(r"[^a-z0-9]", "", (value or "").lower())


def active_username(page) -> str | None:
    for selector in [
        'button:has(svg[aria-label="Current account"]) img.z-avatar-image[alt]',
        '#team-invite-profile-menu-anchor img[alt]',
        'img.z-avatar-image[alt][width="40"][height="40"]:visible',
    ]:
        try:
            alt = page.locator(selector).first.get_attribute("alt", timeout=1200)
            if alt:
                return alt
        except Exception:
            pass
    for selector in ['header a[href^="/user/"]', 'nav a[href^="/user/"]', 'aside a[href^="/user/"]']:
        try:
            href = page.locator(selector).first.get_attribute("href", timeout=1200)
            m = re.match(r"^/user/([^/?#]+)", href or "")
            if m:
                return m.group(1)
        except Exception:
            pass
    return None


def check_login(page) -> None:
    if re.search(r"/(login|signin|auth)(/|\?|$)", page.url, re.I):
        fail(f"LOGIN_REQUIRED: saved Whatnot session redirected to login. CURRENT_URL: {page.url}", 3)


def ensure_channel(page, requested: str) -> str:
    if not requested:
        fail("CHANNEL_CONTEXT_MISMATCH: WHATNOT_CHANNEL_NAME is required", 3)

    current = None
    for _ in range(10):
        check_login(page)
        current = active_username(page)
        if current and norm(current) == norm(requested):
            info(f"CHANNEL_CONTEXT_VERIFIED requested=@{requested} active=@{current}")
            return current
        page.wait_for_timeout(400)

    info(
        f"CHANNEL_CONTEXT_CHECK requested=@{requested} "
        f"active=@{current or '?'} action=switch-required"
    )

    menu = page.locator("#team-invite-profile-menu-anchor").first
    try:
        menu.click(timeout=8000, force=True)
    except Exception as first_exc:
        try:
            clicked = page.evaluate(r"""
            () => {
              const el = document.querySelector('#team-invite-profile-menu-anchor');
              if (!el) return false;
              el.click();
              return true;
            }
            """)
            if not clicked:
                raise RuntimeError("profile menu element not found")
        except Exception as fallback_exc:
            fail(
                f"CHANNEL_SWITCH_FAILED: could not open profile menu for "
                f"@{requested}: {first_exc}; fallback={fallback_exc}",
                3,
            )

    page.wait_for_timeout(600)

    switcher = page.locator("#team-invite-switch-role-anchor").first
    try:
        switcher.click(timeout=8000, force=True)
    except Exception as first_exc:
        try:
            clicked = page.evaluate(r"""
            () => {
              const el = document.querySelector('#team-invite-switch-role-anchor');
              if (!el) return false;
              el.click();
              return true;
            }
            """)
            if not clicked:
                raise RuntimeError("switch-role element not found")
        except Exception as fallback_exc:
            fail(
                f"CHANNEL_SWITCH_FAILED: could not open role picker for "
                f"@{requested}: {first_exc}; fallback={fallback_exc}",
                3,
            )

    page.wait_for_timeout(700)
    target = None

    candidate_selectors = [
        'button[formaction*="switch-role"]',
        'form[action*="switch-role"] button',
        'button:has(img[alt])',
        '[role="button"]:has(img[alt])',
    ]

    for selector in candidate_selectors:
        candidates = page.locator(selector)
        try:
            count = min(candidates.count(), 50)
        except Exception:
            count = 0

        for index in range(count):
            candidate = candidates.nth(index)
            try:
                text = candidate.inner_text(timeout=700) or ""
            except Exception:
                text = ""
            alt = ""
            try:
                alt = candidate.locator("img[alt]").first.get_attribute("alt", timeout=500) or ""
            except Exception:
                pass

            if norm(requested) in {norm(text), norm(alt)}:
                target = candidate
                info(
                    f"CHANNEL_ROLE_FOUND requested=@{requested} "
                    f"text={text!r} alt={alt!r}"
                )
                break
        if target is not None:
            break

    if target is None:
        try:
            found = page.evaluate(
                r"""
                requested => {
                  const norm = value =>
                    String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
                  const wanted = norm(requested);
                  const elements = [
                    ...document.querySelectorAll(
                      'img[alt], button, [role="button"], form[action*="switch-role"]'
                    )
                  ];

                  for (const el of elements) {
                    const alt = el.getAttribute?.('alt') || '';
                    const text = el.innerText || el.textContent || '';
                    if (norm(alt) !== wanted && norm(text) !== wanted) continue;

                    const clickable =
                      el.closest('button') ||
                      el.closest('[role="button"]') ||
                      el.closest('form[action*="switch-role"]');
                    if (!clickable) continue;

                    clickable.setAttribute('data-vortex-role-target', requested);
                    return {
                      found: true,
                      text: text.trim().substring(0, 100),
                      alt
                    };
                  }
                  return {found: false};
                }
                """,
                requested,
            )
            if found and found.get("found"):
                info(
                    f"CHANNEL_ROLE_FOUND requested=@{requested} fallback=true "
                    f"text={found.get('text')!r} alt={found.get('alt')!r}"
                )
                target = page.locator('[data-vortex-role-target]').first
        except Exception:
            pass

    if target is None:
        try:
            diagnostic = page.evaluate(r"""
            () => [...document.querySelectorAll(
                'button, [role="button"], form[action*="switch-role"]'
            )]
            .slice(0, 50)
            .map(el => ({
              tag: el.tagName,
              text: (el.innerText || el.textContent || '').trim().substring(0, 120),
              action: el.getAttribute('action'),
              formaction: el.getAttribute('formaction'),
              alt: el.querySelector('img[alt]')?.getAttribute('alt') || null
            }))
            """)
            info("CHANNEL_ROLE_DIAGNOSTIC " + json.dumps(diagnostic, separators=(",", ":")))
        except Exception as exc:
            info(f"CHANNEL_ROLE_DIAGNOSTIC failed={exc}")

        fail(f"CHANNEL_SWITCH_FAILED: @{requested} was not present in role picker", 3)

    try:
        target.click(timeout=8000, force=True)
    except Exception as exc:
        fail(f"CHANNEL_SWITCH_FAILED: could not select @{requested}: {exc}", 3)

    page.wait_for_timeout(1200)
    try:
        page.goto(f"{BASE}/dashboard/home", wait_until="domcontentloaded", timeout=30000)
    except Exception:
        pass

    verified = None
    for _ in range(20):
        check_login(page)
        verified = active_username(page)
        if verified and norm(verified) == norm(requested):
            info(f"CHANNEL_CONTEXT_VERIFIED requested=@{requested} active=@{verified}")
            return verified
        page.wait_for_timeout(500)

    fail(
        f"CHANNEL_CONTEXT_MISMATCH: refusing to scrape. "
        f"requested=@{requested} active=@{verified or '?'}",
        3,
    )
    return ""


def prepare(page) -> str:
    check_login(page)
    return ensure_channel(page, CHANNEL)


def extract_show(page) -> dict[str, Any]:
    raw = page.evaluate(r"""
    () => {
      const title=document.querySelector('h1,h2')?.textContent?.trim()||null;
      const text=document.body?.innerText||'';
      const labels={};
      const wanted=['Gross Revenue','Estimated Earnings','Completed Earnings','Units Sold','Orders','Buyers','First Time Buyers','Returning Buyers','Shares','Show Duration','Max Concurrent Viewers','Total Views','Average Order Value','Avg Order Value','Giveaway Spend','Giveaways'];
      for(const label of wanted){
        const node=[...document.querySelectorAll('*')].find(el=>el.childElementCount===0&&(el.textContent||'').trim().toLowerCase()===label.toLowerCase());
        if(!node)continue;
        let p=node.parentElement;
        for(let i=0;p&&i<5;i++,p=p.parentElement){
          const vals=[...p.querySelectorAll('*')].filter(el=>el!==node&&el.childElementCount===0).map(el=>(el.textContent||'').trim()).filter(v=>v&&v.length<80&&v!==label);
          const value=vals.find(v=>/^[-+$]?[$]?\d[\d,.]*(?:%|k|m|h|min)?$/i.test(v));
          if(value){labels[label]=value;break;}
        }
      }
      const id=(location.href.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i)||[])[0]||null;
      return {title,text:text.substring(0,2500),labels,live_id:id,url:location.href};
    }
    """)
    labels = raw.get("labels") or {}
    dm = re.search(r"(\d{1,2})/(\d{1,2})/(20\d\d)", raw.get("text") or "")
    show_date = f"{dm.group(3)}-{int(dm.group(1)):02d}-{int(dm.group(2)):02d}" if dm else None

    def money(v):
        try:
            return float(re.sub(r"[^0-9.-]", "", str(v))) if v is not None else None
        except ValueError:
            return None

    def integer(v):
        s = re.sub(r"[^0-9]", "", str(v or ""))
        return int(s) if s else None

    duration = labels.get("Show Duration") or ""
    hm = re.search(r"(\d+)\s*h", duration, re.I)
    mm = re.search(r"(\d+)\s*m", duration, re.I)
    duration_minutes = ((int(hm.group(1)) * 60 if hm else 0) + (int(mm.group(1)) if mm else 0)) if duration else None
    return {
        "title": raw.get("title"), "show_date": show_date, "whatnot_live_id": raw.get("live_id"),
        "detail_url": f"{BASE}/dashboard/live/{raw.get('live_id')}" if raw.get("live_id") else raw.get("url"),
        "gross_revenue": money(labels.get("Gross Revenue")), "whatnot_net": money(labels.get("Estimated Earnings")),
        "completed_earnings": money(labels.get("Completed Earnings")), "units_sold": integer(labels.get("Units Sold") or labels.get("Orders")),
        "buyers_count": integer(labels.get("Buyers")), "first_time_buyers": integer(labels.get("First Time Buyers")),
        "returning_buyers": integer(labels.get("Returning Buyers")), "shares_count": integer(labels.get("Shares")),
        "show_duration": duration_minutes, "max_concurrent_viewers": integer(labels.get("Max Concurrent Viewers")),
        "total_views": integer(labels.get("Total Views")), "avg_order_value": money(labels.get("Average Order Value") or labels.get("Avg Order Value")),
        "giveaway_spend": money(labels.get("Giveaway Spend")), "giveaways_count": integer(labels.get("Giveaways")),
    }


def analytics(session: DynamicSession):
    if not UUID_RE.fullmatch(START_UUID):
        fail("ANALYTICS_SEED_REQUIRED: WHATNOT_START_UUID is required")
    rows = []
    seen = set()
    today = date.today().isoformat()
    target = f"{BASE}/account/analytics?tab=livestream&live_id={START_UUID}&start_dt=2019-01-01&end_dt={today}"

    def action(page):
        prepare(page)
        page.goto(target, wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(1000)
        for _ in range(LIMIT):
            check_login(page)
            row = extract_show(page)
            key = row.get("whatnot_live_id") or f"{row.get('title')}|{row.get('show_date')}"
            if key in seen:
                break
            seen.add(key)
            rows.append(row)
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


def shows(session: DynamicSession):
    output: list[dict[str, Any]] = []
    seen: set[str] = set()

    def action(page):
        prepare(page)
        page.goto(f"{BASE}/dashboard/lives?status=completed", wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(1200)
        check_login(page)

        for _ in range(20):
            rows = page.evaluate(r"""
            () => {
              const generic=/^(open show|edit show|clone items?|copy show link|start sharing|end show|enable private mode|schedule a show|show tools?|view show|show details?|see analytics|view shipments|restart show|cancel show|going live help|obs tools)$/i;
              const results=[];
              const seen=new Set();
              const anchors=[...document.querySelectorAll('a[href]')];
              for(const a of anchors){
                const href=a.getAttribute('href')||'';
                let liveId=null;
                let m=href.match(/\/dashboard\/live\/([0-9a-f-]{36})/i);
                if(m) liveId=m[1];
                if(!liveId){
                  try{ liveId=new URL(href,location.origin).searchParams.get('live_id'); }catch(e){}
                }
                if(!liveId || seen.has(liveId)) continue;
                seen.add(liveId);

                let container=a;
                for(let i=0;i<12 && container.parentElement;i++){
                  container=container.parentElement;
                  if(((container.innerText||'').trim().length)>100) break;
                }
                const text=(container.innerText||container.textContent||'').trim();
                const lines=text.split('\n').map(v=>v.trim()).filter(Boolean);
                const own=(a.innerText||a.textContent||'').trim();
                const first=lines[0]||'';
                const dash=first.indexOf(' — ');
                let title=(own.length>5&&!generic.test(own))?own:(dash>3?first.substring(0,dash).trim():null);
                if(!title){
                  title=lines.find(v=>v.length>5&&!generic.test(v)&&!/^\$/.test(v)&&!/^\d{1,2}[\/-]\d{1,2}/.test(v)&&!/^(Live|Ended|Cancelled|Completed|Upcoming)$/i.test(v))||null;
                }

                let showDate=null;
                const iso=text.match(/\b(20\d\d)[\/-](0[1-9]|1[0-2])[\/-](0[1-9]|[12]\d|3[01])\b/);
                if(iso) showDate=`${iso[1]}-${iso[2]}-${iso[3]}`;
                if(!showDate){
                  const d=text.match(/\b(\d{1,2})[\/-](\d{1,2})[\/-](20\d\d)\b/);
                  if(d) showDate=`${d[3]}-${d[1].padStart(2,'0')}-${d[2].padStart(2,'0')}`;
                }
                const price=[...text.matchAll(/\$[\d,]+(?:\.\d+)?/g)].map(x=>parseFloat(x[0].replace(/[^0-9.]/g,''))).filter(Number.isFinite);
                const units=text.match(/(\d{1,6})\s*(?:orders?|lots?\s+sold|units?\s+sold|sales)/i);
                const views=text.match(/(\d{1,6})\s*(?:viewers?|views?)/i);
                results.push({
                  title,
                  show_date:showDate,
                  whatnot_live_id:liveId,
                  detail_url:`https://www.whatnot.com/dashboard/live/${liveId}`,
                  gross_revenue:price.length?Math.max(...price):null,
                  whatnot_net:null,
                  completed_earnings:null,
                  units_sold:units?parseInt(units[1],10):null,
                  buyers_count:null,
                  first_time_buyers:null,
                  returning_buyers:null,
                  shares_count:null,
                  show_duration:null,
                  max_concurrent_viewers:null,
                  total_views:views?parseInt(views[1],10):null,
                  avg_order_value:null,
                  giveaway_spend:null,
                  giveaways_count:null
                });
              }
              return results;
            }
            """)

            for row in rows:
                key = str(row.get("whatnot_live_id") or row.get("detail_url") or row.get("title") or "")
                if not key or key in seen:
                    continue
                seen.add(key)
                output.append(row)
                if len(output) >= LIMIT:
                    return

            try:
                advanced = page.evaluate(r"""
                () => {
                  const s=document.querySelector('svg[aria-label="Next page"]');
                  const b=s?s.closest('button'):document.querySelector('button[aria-label="Next page"]');
                  if(b&&!b.disabled&&b.getAttribute('aria-disabled')!=='true'){b.click();return true}
                  return false;
                }
                """)
            except Exception:
                advanced = False
            if not advanced:
                break
            page.wait_for_timeout(800)

    session.fetch(f"{BASE}/dashboard/home", page_action=action, timeout=60000, network_idle=False, google_search=False)
    info(f"shows: collected {len(output)} show(s)")
    return output[:LIMIT]


def extract_orders(page):
    return page.evaluate(r"""() => { const price=s=>{if(!s)return null;const v=parseFloat(s.replace(/[^0-9.]/g,''));return Number.isFinite(v)?v:null}; const out=[]; for(const tr of document.querySelectorAll('tbody[data-testid="orders-table-body"] tr, tr[data-testid^="orders-"]')){const t=[...tr.querySelectorAll(':scope > td')];if(t.length<6)continue;const c=t[0], title=c.querySelector('span[title],strong'), oid=(c.innerText||'').match(/Order\s*#\s*(\d+)/i), qty=parseInt((t[3]?.innerText||'').replace(/[^0-9]/g,''),10);out.push({order_id:oid?oid[1]:null,buyer:(t[2]?.innerText||'').trim()||null,item_name:title?(title.getAttribute('title')||title.textContent||'').trim():null,lot_number:null,quantity:Number.isFinite(qty)?qty:1,unit_price:price(t[5]?.innerText||''),total_price:price(t[5]?.innerText||''),net_earnings:price(t[7]?.innerText||''),sales_channel:(t[4]?.innerText||'').trim()||null,status:(t[6]?.innerText||'').trim()||'completed',raw_text:(tr.innerText||'').replace(/\s+/g,' ').trim().substring(0,400)});} return out;}""")


def extract_shipments(page):
    return page.evaluate(r"""() => { const out=[];for(const tr of document.querySelectorAll('tr[data-testid^="shipments-"]')){const main=tr.innerText||'',detail=tr.nextElementSibling?.tagName==='TR'?(tr.nextElementSibling.innerText||''):'',text=main+'\n'+detail,oid=text.match(/Order\s*#\s*(\d+)/i);if(!oid)continue;const buyer=tr.querySelector('a[href*="/dashboard/inbox"]'),weight=text.match(/(\d+(?:\.\d+)?)\s*oz\b/i),dims=text.match(/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*in\b/i),carrier=text.match(/\b(USPS|UPS|FedEx|DHL)\b\s*([A-Za-z\d\s\-/]*[A-Za-z\d])?/i),tracking=text.match(/(?:tracking\s*#?|label\s*#?)\s*([0-9]{12,})/i);let st=null;if(/ready\s*to\s*ship/i.test(text))st='ready_to_ship';else if(/label\s*created/i.test(text))st='label_created';else if(/delivered/i.test(text))st='delivered';else if(/returned/i.test(text))st='returned';else if(/packed/i.test(text))st='packed';else if(/shipped/i.test(text))st='shipped';else if(/in\s*transit/i.test(text))st='in_transit';out.push({order_id:oid[1],buyer:buyer?(buyer.textContent||'').trim():null,item_name:null,lot_number:null,quantity:1,unit_price:null,total_price:null,status:'completed',raw_text:main.replace(/\s+/g,' ').trim().substring(0,400),weight_oz:weight?parseFloat(weight[1]):null,box_length_in:dims?parseFloat(dims[1]):null,box_width_in:dims?parseFloat(dims[2]):null,box_height_in:dims?parseFloat(dims[3]):null,shipping_carrier:carrier?carrier[1].toUpperCase():null,shipping_service:carrier&&carrier[2]?carrier[2].trim():null,shipping_status_scraped:st,tracking_number:tracking?tracking[1]:null});}return out;}""")


def batch(session: DynamicSession, shipments=False):
    source_file = os.getenv("WHATNOT_ORDER_SOURCES_FILE", "").strip()
    if not source_file or not Path(source_file).exists():
        fail("WHATNOT_ORDER_SOURCES_FILE is required")
    sources = json.loads(Path(source_file).read_text())
    output = []

    def action(page):
        prepare(page)
        for idx, source in enumerate(sources, 1):
            lid = source.get("live_id")
            key = source.get("show_key")
            if not lid:
                output.append({"show_key": key, "live_id": None, "order_count": 0, "orders": []})
                continue
            page.goto(f"{BASE}/dashboard/{'shipments' if shipments else 'orders'}?source={lid}", wait_until="domcontentloaded", timeout=30000)
            page.wait_for_timeout(900)
            found = {}
            for _ in range(40):
                if shipments:
                    try:
                        page.locator('button[aria-label="Expand All"]').first.click(timeout=1000)
                        page.wait_for_timeout(300)
                    except Exception:
                        pass
                for row in (extract_shipments(page) if shipments else extract_orders(page)):
                    found[str(row.get("order_id") or len(found))] = row
                advanced = page.evaluate(r"""() => {const s=document.querySelector('svg[aria-label="Next page"]');const b=s?s.closest('button'):document.querySelector('button[aria-label="Next page"]');if(b&&!b.disabled&&b.getAttribute('aria-disabled')!=='true'){b.click();return true}return false}""")
                if not advanced:
                    break
                page.wait_for_timeout(700)
            rows = list(found.values())
            info(f"{'shipments' if shipments else 'orders'}-batch: [{idx}/{len(sources)}] {key} -> {len(rows)} row(s)")
            output.append({"show_key": key, "live_id": lid, "order_count": len(rows), "orders": rows})

    session.fetch(f"{BASE}/dashboard/home", page_action=action, timeout=60000, network_idle=False, google_search=False)
    return output


def ledger(session: DynamicSession):
    start = os.getenv("WHATNOT_LEDGER_FROM", "").strip()
    end = os.getenv("WHATNOT_LEDGER_TO", "").strip()
    output = []

    def action(page):
        prepare(page)
        page.goto(f"{BASE}/dashboard/ledger/overview", wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(900)
        if start and end:
            try:
                page.get_by_text(re.compile(r"edit dates", re.I)).first.click(timeout=3000)
            except Exception:
                pass
            try:
                inputs = page.locator('input[type="date"]')
                if inputs.count() >= 2:
                    inputs.nth(inputs.count() - 2).fill(start)
                    inputs.nth(inputs.count() - 1).fill(end)
                    page.get_by_role("button", name=re.compile(r"^update$", re.I)).click(timeout=3000)
                    page.wait_for_timeout(1500)
            except Exception:
                pass
        seen = {}
        for _ in range(60):
            rows = page.evaluate(r"""() => [...document.querySelectorAll('table tbody tr')].map(tr=>{const c=[...tr.querySelectorAll(':scope > td')].map(td=>(td.innerText||'').trim());if(c.length<6)return null;const link=tr.querySelector('a[href*="/dashboard/orders/"]'),oid=(c[3]||'').match(/(\d+)/),lid=(c[2]||'').match(/(\d+)/);return {created_date:c[0]||null,amount:c[1]||null,listing_id:lid?lid[1]:null,order_id:oid?oid[1]:null,order_hash:link?(link.getAttribute('href')||'').split('/').pop():null,message:c[4]||null,status:c[5]||null,transaction_type:c[6]||null,completed_date:c[7]||null}}).filter(Boolean)""")
            for row in rows:
                seen['|'.join(str(row.get(k) or '') for k in ('order_id', 'listing_id', 'created_date', 'amount', 'transaction_type'))] = row
            advanced = page.evaluate(r"""() => {const s=document.querySelector('svg[aria-label="Next page"]');const b=s?s.closest('button'):document.querySelector('button[aria-label="Next page"]');if(b&&!b.disabled&&b.getAttribute('aria-disabled')!=='true'){b.click();return true}return false}""")
            if not advanced:
                break
            page.wait_for_timeout(700)
        output.extend(seen.values())

    session.fetch(f"{BASE}/dashboard/home", page_action=action, timeout=60000, network_idle=False, google_search=False)
    info(f"ledger: extracted {len(output)} entries")
    return output


def main():
    if not PROFILE:
        fail("WHATNOT_USER_DATA_DIR is required")
    if not CHROME:
        fail("PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH is required")
    Path(PROFILE).mkdir(parents=True, exist_ok=True)
    info(f"engine=Scrapling DynamicSession mode={MODE} profile={PROFILE}")
    with DynamicSession(
        headless=HEADLESS,
        real_chrome=True,
        executable_path=CHROME,
        user_data_dir=PROFILE,
        disable_resources=False,
        google_search=False,
        locale="en-US",
    ) as session:
        if MODE == 'shows':
            result = shows(session)
        elif MODE == 'analytics':
            result = analytics(session)
        elif MODE == 'orders-batch':
            result = batch(session, False)
        elif MODE == 'shipments-batch':
            result = batch(session, True)
        elif MODE == 'ledger':
            result = ledger(session)
        else:
            fail(f"SCRAPLING_MODE_UNSUPPORTED: {MODE}")
    json.dump(result, sys.stdout, separators=(',', ':'))
    sys.stdout.write('\n')


if __name__ == '__main__':
    main()

from __future__ import annotations

import json
import re
from datetime import date, datetime, timedelta
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
        const text = (el.innerText || el.textContent || '').trim();
        if ((liveId && href.includes(liveId)) || href.includes('live_id=') || el.getAttribute?.('role') === 'combobox' || el.getAttribute?.('aria-haspopup') === 'listbox') push(text);
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

    # Give the page a small future cushion. The VPS clock has previously lagged
    # the browser/app date, and end_dt that is even one day too old makes Whatnot
    # render the generic Account - Analytics shell instead of the selected show.
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


def install(module) -> None:
    module.extract_show = extract_show
    module.analytics = lambda session: analytics(module, session)

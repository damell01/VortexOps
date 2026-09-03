from __future__ import annotations

import json
import re
from datetime import date, datetime
from typing import Any


MONTHS = {
    "jan": 1, "january": 1,
    "feb": 2, "february": 2,
    "mar": 3, "march": 3,
    "apr": 4, "april": 4,
    "may": 5,
    "jun": 6, "june": 6,
    "jul": 7, "july": 7,
    "aug": 8, "august": 8,
    "sep": 9, "sept": 9, "september": 9,
    "oct": 10, "october": 10,
    "nov": 11, "november": 11,
    "dec": 12, "december": 12,
}

GENERIC_TITLE_RE = re.compile(
    r"^(?:whatnot|seller hub|analytics|livestream analytics|show analytics|"
    r"see older show|see newer show|overview|performance|orders|buyers|"
    r"completed|ended|live|dashboard)$",
    re.I,
)


def _clean(value: Any) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip()


def _money(value: Any) -> float | None:
    if value is None:
        return None
    raw = re.sub(r"[^0-9.-]", "", str(value))
    if not raw or raw in {"-", ".", "-."}:
        return None
    try:
        return float(raw)
    except ValueError:
        return None


def _integer(value: Any) -> int | None:
    if value is None:
        return None
    raw = re.sub(r"[^0-9]", "", str(value))
    return int(raw) if raw else None


def _duration_minutes(value: Any) -> int | None:
    text = _clean(value)
    if not text:
        return None
    hm = re.search(r"(\d+)\s*h(?:r|our)?s?\s*(?:(\d+)\s*m)?", text, re.I)
    if hm:
        return int(hm.group(1)) * 60 + int(hm.group(2) or 0)
    mm = re.search(r"(\d+)\s*m(?:in)?", text, re.I)
    if mm:
        return int(mm.group(1))
    clock = re.search(r"\b(\d+):(\d{2})(?::\d{2})?\b", text)
    if clock:
        return int(clock.group(1)) * 60 + int(clock.group(2))
    return None


def _parse_date(*values: Any) -> str | None:
    for value in values:
        text = _clean(value)
        if not text:
            continue

        iso = re.search(r"\b(20\d{2})[-/](0?[1-9]|1[0-2])[-/](0?[1-9]|[12]\d|3[01])\b", text)
        if iso:
            return f"{int(iso.group(1)):04d}-{int(iso.group(2)):02d}-{int(iso.group(3)):02d}"

        slash = re.search(r"\b(0?[1-9]|1[0-2])[/-](0?[1-9]|[12]\d|3[01])[/-](20\d{2})\b", text)
        if slash:
            return f"{int(slash.group(3)):04d}-{int(slash.group(1)):02d}-{int(slash.group(2)):02d}"

        month_first = re.search(
            r"\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|"
            r"Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)"
            r"\s+(\d{1,2})(?:st|nd|rd|th)?[,]?\s+(20\d{2})\b",
            text,
            re.I,
        )
        if month_first:
            month = MONTHS[month_first.group(1).lower()]
            return f"{int(month_first.group(3)):04d}-{month:02d}-{int(month_first.group(2)):02d}"

        day_first = re.search(
            r"\b(\d{1,2})(?:st|nd|rd|th)?\s+"
            r"(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|"
            r"Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)"
            r"[,]?\s+(20\d{2})\b",
            text,
            re.I,
        )
        if day_first:
            month = MONTHS[day_first.group(2).lower()]
            return f"{int(day_first.group(3)):04d}-{month:02d}-{int(day_first.group(1)):02d}"

        try:
            parsed = datetime.fromisoformat(text.replace("Z", "+00:00"))
            return parsed.date().isoformat()
        except Exception:
            pass

    return None


def _pick_title(candidates: list[Any]) -> str | None:
    for candidate in candidates:
        title = _clean(candidate)
        if not title or len(title) < 3 or len(title) > 180:
            continue
        title = re.sub(r"\s*[|–—-]\s*Whatnot(?: Seller Hub)?$", "", title, flags=re.I).strip()
        title = re.sub(r"^Whatnot\s*[|–—-]\s*", "", title, flags=re.I).strip()
        if not title or GENERIC_TITLE_RE.match(title):
            continue
        if re.fullmatch(r"[$+\-]?\d[\d,.% ]*", title):
            continue
        if _parse_date(title):
            continue
        if re.search(r"^(?:gross revenue|estimated sales|estimated earnings|total estimated earnings|completed earnings|units sold|orders|buyers|first time buyers|returning buyers|shares|show duration|max concurrent viewers|total views|average order value|avg order value|giveaway spend|giveaways)\b", title, re.I):
            continue
        return title
    return None


def _snapshot(page) -> dict[str, Any]:
    return page.evaluate(r"""
    () => {
      const text = document.body?.innerText || '';
      const liveId = (location.href.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i) || [])[0] || null;
      const wanted = [
        'Estimated Sales', 'Gross Revenue', 'Revenue',
        'Total Estimated Earnings', 'Estimated Earnings', 'Net Revenue',
        'Completed Earnings', 'Units Sold', 'Orders', 'Buyers',
        'First Time Buyers', 'Returning Buyers', 'Shares', 'Show Duration',
        'Max Concurrent Viewers', 'Total Views', 'Average Order Value',
        'Avg Order Value', 'Giveaway Spend', 'Giveaways'
      ];
      const labels = {};
      const leafs = [...document.querySelectorAll('body *')]
        .filter(el => el.childElementCount === 0)
        .map(el => ({ el, text: (el.textContent || '').trim() }))
        .filter(x => x.text);

      for (const label of wanted) {
        const wantedLower = label.toLowerCase();
        const hit = leafs.find(x => x.text.toLowerCase() === wantedLower);
        if (!hit) continue;

        let node = hit.el;
        for (let depth = 0; node && depth < 7; depth++, node = node.parentElement) {
          const values = [...node.querySelectorAll('*')]
            .filter(el => el !== hit.el && el.childElementCount === 0)
            .map(el => (el.textContent || '').trim())
            .filter(v => v && v.length < 100 && v.toLowerCase() !== wantedLower);
          const value = values.find(v => /^[-+$]?[$]?\d[\d,.]*(?:\s*%|\s*k|\s*m|\s*h|\s*hr|\s*hrs|\s*min)?$/i.test(v));
          if (value) { labels[label] = value; break; }
        }
      }

      const titleCandidates = [];
      const push = value => {
        value = String(value || '').replace(/\s+/g, ' ').trim();
        if (value && !titleCandidates.includes(value)) titleCandidates.push(value);
      };

      // A show selector/link is stronger evidence than a generic page heading.
      for (const el of document.querySelectorAll('a[href], button, [role="combobox"], [aria-haspopup="listbox"]')) {
        const href = el.getAttribute?.('href') || '';
        const own = (el.innerText || el.textContent || '').trim();
        if ((liveId && href.includes(liveId)) || /live_id=/.test(href) || el.getAttribute?.('aria-haspopup') === 'listbox' || el.getAttribute?.('role') === 'combobox') {
          push(own);
        }
      }
      for (const el of document.querySelectorAll('h1,h2,h3,[data-testid*="title" i],[class*="title" i]')) push(el.textContent);
      push(document.querySelector('meta[property="og:title"]')?.getAttribute('content'));
      push(document.querySelector('meta[name="twitter:title"]')?.getAttribute('content'));
      push(document.title);

      const dateCandidates = [];
      for (const el of document.querySelectorAll('time,[datetime],[data-testid*="date" i],[class*="date" i]')) {
        const value = el.getAttribute?.('datetime') || el.textContent || '';
        if (value) dateCandidates.push(String(value).trim());
      }

      // Next/React payloads often contain the exact show metadata even when the
      // visible heading has not hydrated yet. Search JSON script data for the
      // object that contains the current live UUID and collect common fields.
      const embedded = [];
      const visit = (value, depth = 0) => {
        if (!value || depth > 12) return;
        if (Array.isArray(value)) {
          for (const item of value.slice(0, 500)) visit(item, depth + 1);
          return;
        }
        if (typeof value !== 'object') return;
        let containsLive = false;
        for (const [k, v] of Object.entries(value)) {
          if (typeof v === 'string' && liveId && v.includes(liveId)) containsLive = true;
          if (/^(id|uuid|live_id|livestream_id|livestreamId)$/i.test(k) && liveId && String(v) === liveId) containsLive = true;
        }
        if (containsLive) {
          embedded.push({
            title: value.title || value.show_title || value.name || value.show_name || null,
            date: value.show_date || value.started_at || value.start_time || value.startTime || value.scheduled_at || value.scheduledAt || value.created_at || value.date || null,
          });
        }
        for (const child of Object.values(value)) visit(child, depth + 1);
      };
      for (const script of document.querySelectorAll('script[type="application/json"],script#__NEXT_DATA__')) {
        const raw = script.textContent || '';
        if (!raw || raw.length > 5000000) continue;
        try { visit(JSON.parse(raw)); } catch (_) {}
      }
      for (const item of embedded) {
        push(item.title);
        if (item.date) dateCandidates.push(String(item.date));
      }

      return {
        live_id: liveId,
        url: location.href,
        text: text.substring(0, 20000),
        text_preview: text.replace(/\s+/g, ' ').trim().substring(0, 1400),
        labels,
        title_candidates: titleCandidates.slice(0, 30),
        date_candidates: dateCandidates.slice(0, 30),
      };
    }
    """)


def extract_show(page) -> dict[str, Any]:
    raw = _snapshot(page)
    labels = raw.get("labels") or {}
    title = _pick_title(raw.get("title_candidates") or [])
    show_date = _parse_date(*(raw.get("date_candidates") or []), raw.get("text"))

    duration = labels.get("Show Duration")
    return {
        "title": title,
        "show_date": show_date,
        "whatnot_live_id": raw.get("live_id"),
        "detail_url": f"https://www.whatnot.com/dashboard/live/{raw.get('live_id')}" if raw.get("live_id") else raw.get("url"),
        "gross_revenue": _money(labels.get("Estimated Sales") or labels.get("Gross Revenue") or labels.get("Revenue")),
        "whatnot_net": _money(labels.get("Total Estimated Earnings") or labels.get("Estimated Earnings") or labels.get("Net Revenue")),
        "completed_earnings": _money(labels.get("Completed Earnings")),
        "units_sold": _integer(labels.get("Units Sold") or labels.get("Orders")),
        "buyers_count": _integer(labels.get("Buyers")),
        "first_time_buyers": _integer(labels.get("First Time Buyers")),
        "returning_buyers": _integer(labels.get("Returning Buyers")),
        "shares_count": _integer(labels.get("Shares")),
        "show_duration": _duration_minutes(duration),
        "max_concurrent_viewers": _integer(labels.get("Max Concurrent Viewers")),
        "total_views": _integer(labels.get("Total Views")),
        "avg_order_value": _money(labels.get("Average Order Value") or labels.get("Avg Order Value")),
        "giveaway_spend": _money(labels.get("Giveaway Spend")),
        "giveaways_count": _integer(labels.get("Giveaways")),
        "_analytics_preview": raw.get("text_preview"),
        "_title_candidates": raw.get("title_candidates") or [],
        "_date_candidates": raw.get("date_candidates") or [],
    }


def _has_metrics(row: dict[str, Any]) -> bool:
    return any(
        row.get(key) is not None
        for key in (
            "gross_revenue", "whatnot_net", "completed_earnings", "units_sold",
            "buyers_count", "total_views", "avg_order_value", "show_duration",
        )
    )


def wait_for_show_ready(module, page, previous_live_id: str | None = None, timeout_ms: int = 15000) -> dict[str, Any]:
    elapsed = 0
    last = None
    while elapsed < timeout_ms:
        module.check_login(page)
        try:
            last = extract_show(page)
        except Exception:
            last = None

        if last:
            changed = previous_live_id is None or (
                last.get("whatnot_live_id") and last.get("whatnot_live_id") != previous_live_id
            )
            identified = bool(last.get("title") or last.get("show_date"))
            if changed and identified and (_has_metrics(last) or elapsed >= 2500):
                return last

        page.wait_for_timeout(500)
        elapsed += 500

    return last or extract_show(page)


def analytics(module, session):
    start_uuid = module.START_UUID
    if not module.UUID_RE.fullmatch(start_uuid):
        module.fail("ANALYTICS_SEED_REQUIRED: WHATNOT_START_UUID is required")

    rows: list[dict[str, Any]] = []
    seen: set[str] = set()
    today = date.today().isoformat()
    target = f"{module.BASE}/account/analytics?tab=livestream&live_id={start_uuid}&start_dt=2019-01-01&end_dt={today}"

    def action(page):
        module.prepare(page)
        page.goto(target, wait_until="domcontentloaded", timeout=30000)

        previous_live_id = None
        for index in range(module.LIMIT):
            row = wait_for_show_ready(module, page, previous_live_id=previous_live_id)
            key = row.get("whatnot_live_id") or f"{row.get('title')}|{row.get('show_date')}"

            if not key or key in seen:
                break
            seen.add(key)

            if not row.get("title") and not row.get("show_date"):
                module.info(
                    "ANALYTICS_DIAGNOSTIC "
                    + json.dumps(
                        {
                            "index": index + 1,
                            "live_id": row.get("whatnot_live_id"),
                            "url": row.get("detail_url"),
                            "title_candidates": (row.get("_title_candidates") or [])[:8],
                            "date_candidates": (row.get("_date_candidates") or [])[:8],
                            "preview": row.get("_analytics_preview"),
                        },
                        separators=(",", ":"),
                    )
                )

            # Internal diagnostics are useful on stderr but should not pollute
            # the Laravel raw payload or ingestion-log comparison logic.
            row.pop("_analytics_preview", None)
            row.pop("_title_candidates", None)
            row.pop("_date_candidates", None)
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

    def hardened_analytics(session):
        return analytics(module, session)

    module.analytics = hardened_analytics

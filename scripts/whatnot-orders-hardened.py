from __future__ import annotations

"""Harden Whatnot order pagination before destructive reconciliation.

The dashboard commonly renders up to 100 order rows per page. A full 100-row
page without a trustworthy pagination control is ambiguous: it may be the whole
show, or it may be a truncated first page. In that state we abort the batch so
Laravel never receives a partial result that could replace existing rows.
"""

import base64
import json
import os
from urllib.parse import urlsplit, parse_qsl, urlencode
from pathlib import Path


def order_dashboard_url(base, live_id):
    """Use Seller Hub's native single-show filter, copied from its UI."""
    filters = {"salesChannel": {"type": "single", "value": str(live_id)}}
    encoded = base64.b64encode(json.dumps(filters, separators=(',', ':')).encode()).decode()
    return f"{base}/dashboard/orders?{urlencode({'filters': encoded, 'first': 100})}"


def filtered_response_order_ids(entry, body, live_id):
    """Only order-node IDs returned by a successful, single-show native query."""
    import re
    variables = entry.get('variables', {})
    channels = variables.get('salesChannels')
    if entry.get('operationName') != 'SellerHubGetMyOrders' or not isinstance(channels, list) or len(channels) != 1:
        return set()
    if not isinstance(channels[0], dict) or channels[0].get('id') != live_id or not isinstance(body, dict) or body.get('errors'):
        return set()
    orders = ((body.get('data') or {}).get('me') or {}).get('orders') or {}
    result = set()
    for edge in orders.get('edges', []):
        node = edge.get('node') or {}
        for key in ('id', 'uuid', 'orderNumber', 'orderId'):
            value = str(node.get(key) or '')
            if re.fullmatch(r'[0-9]+', value):
                result.add(value)
            else:
                try:
                    decoded = base64.b64decode(value, validate=True).decode()
                    match = re.fullmatch(r'Order(?:Record)?(?:Node)?:([0-9]+)', decoded)
                    if match:
                        result.add(match[1])
                except (ValueError, UnicodeError):
                    pass
    return result


def summarize_order_source_evidence(variables, payload, expected_live_id, query_document=None):
    """Field paths/types and expected-source comparisons only; never emit values."""
    import re
    def walk(value, path='', depth=0):
        if depth > 15:
            return []
        if isinstance(value, dict):
            out = []
            for key, child in list(value.items())[:100]:
                if not isinstance(key, str) or not re.fullmatch(r'[A-Za-z_][A-Za-z_0-9]{0,79}', key):
                    continue
                if any(word in key.lower() for word in ['token', 'password', 'cookie', 'authorization']):
                    continue
                out.extend(walk(child, path+'.'+key if path else key, depth+1))
            return out[:250]
        if isinstance(value, list):
            out = [{'path': path, 'type': 'array'}]
            for child in value[:2]:
                out.extend(walk(child, path+'[]', depth+1))
            return out[:250]
        kind = 'null' if value is None else ('boolean' if isinstance(value, bool) else ('number' if isinstance(value, (int,float)) else 'string'))
        return [{'path': path, 'type': kind, 'matches_expected_live': isinstance(value,str) and value == expected_live_id}]
    def unique(rows):
        return [json.loads(item) for item in sorted({json.dumps(row,sort_keys=True) for row in rows})]
    return {'variables': unique(walk(variables)), 'response': unique(walk(payload)),
            'variable_definitions': re.findall(r'\$([A-Za-z_][A-Za-z_0-9]*)\s*:\s*([A-Za-z_][A-Za-z_0-9!\[\]]*|\[[A-Za-z_][A-Za-z_0-9!\[\]]*)', query_document or '')}


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
            graphql_operations = set()
            order_source_evidence = []
            active_source = {"live_id": None}
            verified_response_ids = set()
            response_shapes = set()
            def observe_response(response):
                try:
                    request = response.request
                    parsed = urlsplit(response.url)
                    if 'graphql' in parsed.path:
                        status = getattr(response, 'status', None)
                        response_shapes.add(json.dumps({'graphql_endpoint': parsed.path, 'graphql_host': parsed.hostname, 'whatnot_host': bool(parsed.hostname and (parsed.hostname == 'whatnot.com' or parsed.hostname.endswith('.whatnot.com'))), 'method_is_post': request.method == 'POST', 'http_status': status if isinstance(status, int) else None}))
                    if not (parsed.hostname and (parsed.hostname == 'whatnot.com' or parsed.hostname.endswith('.whatnot.com'))) or parsed.path != '/services/graphql/' or request.method != 'POST' or response.status != 200:
                        return
                    payload = request.post_data_json
                    entries = payload if isinstance(payload, list) else [payload]
                    url_operation = dict(parse_qsl(parsed.query)).get('operationName')
                    body = response.json()
                    bodies = body if isinstance(body, list) else [body]
                    for index, entry in enumerate(entries):
                        if not isinstance(entry, dict) or index >= len(bodies):
                            continue
                        if not entry.get('operationName') and url_operation:
                            entry = dict(entry, operationName=url_operation)
                        name = entry.get('operationName')
                        if isinstance(name,str) and name.isidentifier():
                            response_shapes.add(json.dumps({'operation':name,'data_fields': sorted((bodies[index].get('data') or {}).keys())}))
                        if entry.get('operationName') == 'SellerHubGetMyOrders':
                            import re
                            body_part = bodies[index]
                            nodes = (((body_part.get('data') or {}).get('me') or {}).get('orders') or {}).get('edges', [])
                            fields = {}
                            for field, value in ((nodes[0].get('node') or {}) if nodes else {}).items():
                                if field not in ('id', 'uuid', 'orderNumber', 'orderId'):
                                    continue
                                text = str(value or '')
                                shape = {'numeric': text.isdecimal(), 'length': len(text)}
                                try:
                                    decoded = base64.b64decode(text, validate=True).decode()
                                    prefix = decoded.split(':')[0]
                                    shape['relay_type'] = prefix if re.fullmatch(r'Order[A-Za-z0-9_]{0,30}', prefix) else 'other'
                                    shape['numeric_tail'] = bool(re.search(r':[0-9]+$', decoded))
                                    shape['decoded_length'] = len(decoded)
                                except (ValueError, UnicodeError):
                                    pass
                                fields[field] = shape
                            channels = entry.get('variables', {}).get('salesChannels')
                            response_shapes.add(json.dumps({'channel_count': len(channels) if isinstance(channels, list) else None,
                                'filter_matches': isinstance(channels, list) and any(isinstance(c,dict) and c.get('id') == active_source['live_id'] for c in channels),
                                'node_id_fields': fields, 'node_keys': sorted((nodes[0].get('node') or {}).keys()) if nodes else []}, sort_keys=True))
                        verified_response_ids.update(filtered_response_order_ids(entry, bodies[index], active_source['live_id']))
                        if probe_max_pages and entry.get('operationName') == 'SellerHubGetMyOrders' and len(order_source_evidence) < 4:
                            order_source_evidence.append(summarize_order_source_evidence(entry.get('variables', {}), bodies[index], active_source['live_id'], entry.get('query')))
                except Exception as exc:
                    # Only exception class names; never response/customer values.
                    response_shapes.add(json.dumps({'observer_error': type(exc).__name__}))
            page.on('response', observe_response)

            for idx, source in enumerate(sources, 1):
                live_id = source.get("live_id")
                key = source.get("show_key")
                if not live_id:
                    output.append({"show_key": key, "live_id": None, "order_count": 0, "orders": []})
                    continue

                verified_response_ids.clear()
                response_shapes.clear()
                active_source["live_id"] = str(live_id)
                page.goto(
                    order_dashboard_url(module.BASE, live_id),
                    wait_until="domcontentloaded",
                    timeout=30000,
                )
                page.wait_for_timeout(1200)
                if not probe_max_pages and not verified_response_ids:
                    # Initial rows may be server-rendered, without a browser
                    # GraphQL response. Request a fresh native page size so
                    # ownership can be checked against an actual response.
                    size = page.locator('select:has(option[value="20"])').first
                    if size.count() > 0:
                        size.select_option('20')
                        page.wait_for_timeout(1500)
                    else:
                        # Seller Hub also renders this as a custom text control.
                        import re
                        try:
                            trigger = page.get_by_text(re.compile(r'^Show\s*100(?:\D|$)')).last
                            if trigger.count() > 0:
                                trigger.click(timeout=3000)
                                option = page.get_by_text(re.compile(r'^(?:Show\s*)?20$')).last
                                option.wait_for(state='visible', timeout=5000)
                                option.click(timeout=3000)
                                page.wait_for_timeout(1500)
                        except Exception:
                            pass
                if probe_max_pages:
                    controls = page.evaluate(r"""() => [...document.querySelectorAll('button,input,select,[role=combobox]')].filter(el=>!el.closest('tbody,tr,[data-testid*=row]')).map(el=>({tag:el.tagName,role:el.getAttribute('role'),testid:el.getAttribute('data-testid'),label:el.getAttribute('aria-label')||el.getAttribute('placeholder')||(el.innerText||'').trim()})).filter(el=>/filter|channel|source|live|show|search/i.test(el.label||'')).slice(0,30)""")
                    module.info('ORDER_SOURCE_EVIDENCE '+json.dumps([{'ui_controls':controls}]))


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
                    if len(current_rows) < 100 and not state.get("exists"):
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
                    module.info('ORDER_SOURCE_EVIDENCE ' + json.dumps(order_source_evidence,separators=(',', ':')))
                    order_source_evidence.clear()
                    module.info('ORDER_SOURCE_GRAPHQL_OPERATIONS show=' + str(key) + ' ' + json.dumps(sorted(graphql_operations)))
                    graphql_operations.clear()
                    module.info('ORDER_SOURCE_NETWORK_ENDPOINTS show=' + str(key) + ' ' +
                                json.dumps([{'method': method, 'host': host, 'path': path, 'query_keys': keys}
                                            for method, host, path, keys in sorted(network_endpoints)][:60],
                                           separators=(',', ':')))
                    network_endpoints.clear()
                rows = list(found.values())
                if rows and not verified_response_ids:
                    module.info('ORDER_PROVENANCE_SCHEMA ' + json.dumps([json.loads(v) for v in sorted(response_shapes)]))
                    controls = page.evaluate(r"""() => ({selects:[...document.querySelectorAll('select')].map(el=>({role:el.getAttribute('role'),options:[...el.options].map(o=>({label:o.textContent,value:o.value}))})),pageButtons:[...document.querySelectorAll('button,[role=button],[role=combobox],div,span')].filter(el=>!el.closest('tbody,tr')&&/^Show\s*(?:20|50|100)(?:\D|$)/i.test((el.innerText||'').trim())&&(el.innerText||'').length<100).slice(-20).map(el=>({tag:el.tagName,role:el.getAttribute('role'),label:el.innerText.trim()}))})""")
                    module.info('ORDER_PROVENANCE_SCHEMA ' + json.dumps([{'pagination_controls':controls}]))
                for row in rows:
                    if not probe_max_pages and str(row.get('order_id') or '') in verified_response_ids:
                        row['_source_evidence'] = {
                            'version': 1, 'method': 'seller_hub_filtered_response',
                            'live_id': str(live_id), 'order_id': str(row['order_id']),
                            'pagination_complete': True,
                        }
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
                    f"orders-batch: [{idx}/{len(sources)}] {key} complete={not bool(probe_max_pages)} "
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

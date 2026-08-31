from pathlib import Path

p = Path('scripts/whatnot-production-sync.cjs')
s = p.read_text()

start_marker = "    // Land on the public site before the Seller Hub, the way whatnot-scraper\n"
end_marker = "    if(!await clickShows(page))throw fail(EXIT.SELECTORS,'Could not reach Shows'); out.stages.shows=await state(page);\n"

start = s.find(start_marker)
end = s.find(end_marker, start)
if start < 0 or end < 0:
    raise SystemExit(f'entry navigation markers not found: start={start} end={end}')

replacement = """    // Match the working multi-channel scraper: go directly to Seller Hub.\n    // The public-home warmup was triggering Cloudflare before historical enrichment.\n    const resp=await page.goto('https://www.whatnot.com/dashboard/home',{waitUntil:'domcontentloaded',timeout:30000}).catch(()=>null);\n    await page.waitForLoadState('networkidle',{timeout:7000}).catch(()=>{});\n    await page.waitForTimeout(2200);\n    await settleChallenge(page);\n    out.stages.home={status:resp?resp.status():null,...await state(page)};\n    if(out.stages.home.challenged){\n      await reportBlockingPage(page,'challenge-home');\n      throw fail(EXIT.CHALLENGE,'Seller Hub home challenged — Cloudflare served a check instead of the dashboard'+(proxy?' (via proxy '+proxy+')':'')+'.');\n    }\n"""

p.write_text(s[:start] + replacement + s[end:])

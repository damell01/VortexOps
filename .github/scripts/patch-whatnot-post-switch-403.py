from pathlib import Path

p = Path('scripts/whatnot-scraper.cjs')
s = p.read_text()

old = r'''      if (await directTarget.isVisible({ timeout: 5000 }).catch(() => false)) {
        info(`switchToChannel: direct path clicking target role @${channelName}`);
        await directTarget.click({ force: true, timeout: 3000 }).catch(async () => {
          await directTarget.evaluate(el => el.click()).catch(() => {});
        });

        const verifiedDirect = await waitForActiveChannel(page, channelName, Math.min(SWITCH_CHANNEL_TIMEOUT_MS, 15000));
        await debugShot(page, 'role-switch-04-verified-direct');
        info(`switchToChannel: VERIFIED requested=@${channelName} active=@${verifiedDirect} URL=${page.url()} (direct path)`);
        return verifiedDirect;
      }
'''

new = r'''      if (await directTarget.isVisible({ timeout: 5000 }).catch(() => false)) {
        info(`switchToChannel: direct path clicking target role @${channelName}`);

        // Whatnot currently redirects successful role switches to
        // /dashboard/home?toast=switched-roles. On this VPS that exact GET is
        // challenged by Cloudflare even though a clean /dashboard/home is 200.
        // Let the role-switch mutation itself happen, but suppress only that
        // toast navigation and immediately reload the clean Seller Hub route.
        // This avoids turning a successful account switch into an endless 403
        // challenge loop while still requiring a positive active-account check.
        let switchedRoleRedirectSeen = false;
        const switchedRoleRoutePattern = '**/dashboard/home?toast=switched-roles*';
        const suppressSwitchedRoleRedirect = async (route) => {
          const req = route.request();
          if (req.isNavigationRequest() && req.method() === 'GET') {
            switchedRoleRedirectSeen = true;
            info('switchToChannel: intercepted post-switch toast navigation; reloading clean Seller Hub route instead');
            await route.abort().catch(() => {});
            return;
          }
          await route.continue().catch(() => {});
        };

        await page.route(switchedRoleRoutePattern, suppressSwitchedRoleRedirect).catch(() => {});
        try {
          await directTarget.click({ force: true, timeout: 3000 }).catch(async () => {
            await directTarget.evaluate(el => el.click()).catch(() => {});
          });

          // Give the switch-role request/cookie update a moment to finish. If the
          // normal toast redirect fired, our route handler has already blocked it.
          await page.waitForTimeout(1200);
        } finally {
          await page.unroute(switchedRoleRoutePattern, suppressSwitchedRoleRedirect).catch(() => {});
        }

        if (switchedRoleRedirectSeen) {
          await page.goto(URLs.sellerHub, { waitUntil: 'domcontentloaded', timeout: 15000 }).catch((e) => {
            info('switchToChannel: clean Seller Hub reload after role switch failed:', e.message);
          });
          await page.waitForTimeout(1200);
        }

        // If Whatnot changes the redirect behavior, verification below still
        // fails closed. Never accept the toast redirect alone as proof that the
        // requested account is active.
        const verifiedDirect = await waitForActiveChannel(page, channelName, Math.min(SWITCH_CHANNEL_TIMEOUT_MS, 20000));
        await debugShot(page, 'role-switch-04-verified-direct');
        info(`switchToChannel: VERIFIED requested=@${channelName} active=@${verifiedDirect} URL=${page.url()} (direct path)`);
        return verifiedDirect;
      }
'''

if s.count(old) != 1:
    raise SystemExit(f'direct target block expected once, found {s.count(old)}')

p.write_text(s.replace(old, new, 1))

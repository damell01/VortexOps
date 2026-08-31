from pathlib import Path

p = Path('scripts/whatnot-scraper.cjs')
s = p.read_text()

old = r'''  const SWITCH_ROLE_SEL = '#team-invite-switch-role-anchor, div.eCoev h4.ogVNN';
  const activeBeforeDirect = await getActiveChannelUsername(page);
  if (activeBeforeDirect) {
    const profileSelector = `img.z-avatar-image[alt="${activeBeforeDirect}"][width="40"][height="40"]`;
    const profileAvatar = page.locator(profileSelector).first();
    if (await profileAvatar.isVisible({ timeout: 1500 }).catch(() => false)) {
      info(`switchToChannel: direct path clicking active profile avatar alt="${activeBeforeDirect}"`);
      await profileAvatar.click({ force: true, timeout: 3000 }).catch(async () => {
        await profileAvatar.evaluate(el => el.click()).catch(() => {});
      });

      const directSwitchRole = page.locator('#team-invite-switch-role-anchor').first();
      if (await directSwitchRole.isVisible({ timeout: 4000 }).catch(() => false)) {
        info('switchToChannel: direct path clicking #team-invite-switch-role-anchor');
        await directSwitchRole.click({ force: true, timeout: 3000 });

        const directTarget = page.locator(`button:has(img.z-avatar-image[alt="${channelName.toLowerCase()}"])`).first();
        if (await directTarget.isVisible({ timeout: 5000 }).catch(() => false)) {
          info(`switchToChannel: direct path clicking target role @${channelName}`);
          await directTarget.click({ force: true, timeout: 3000 });

          const verifiedDirect = await waitForActiveChannel(page, channelName, Math.min(SWITCH_CHANNEL_TIMEOUT_MS, 15000));
          await debugShot(page, 'role-switch-04-verified-direct');
          info(`switchToChannel: VERIFIED requested=@${channelName} active=@${verifiedDirect} URL=${page.url()} (direct path)`);
          return verifiedDirect;
        }

        info(`switchToChannel: direct path could not find target role @${channelName}; falling back to generic role-picker logic`);
        await page.keyboard.press('Escape').catch(() => {});
      } else {
        info('switchToChannel: direct profile click did not expose Switch Role; falling back to generic trigger scan');
      }
    }
  }
'''

new = r'''  const SWITCH_ROLE_SEL = '#team-invite-switch-role-anchor, div.eCoev h4.ogVNN';

  // Current Whatnot markup gives the profile trigger a stable id. Click the button
  // itself rather than the nested image so React receives the event on the element
  // that owns the menu handler.
  const directProfileButton = page.locator('#team-invite-profile-menu-anchor').first();
  if (await directProfileButton.isVisible({ timeout: 2500 }).catch(() => false)) {
    const activeBeforeDirect = await getActiveChannelUsername(page);
    info(`switchToChannel: direct path clicking #team-invite-profile-menu-anchor${activeBeforeDirect ? ` active=@${activeBeforeDirect}` : ''}`);

    await directProfileButton.click({ force: true, timeout: 3000 }).catch(async () => {
      await directProfileButton.evaluate(el => el.click()).catch(() => {});
    });

    const directSwitchRole = page.locator('#team-invite-switch-role-anchor').first();
    if (await directSwitchRole.isVisible({ timeout: 5000 }).catch(() => false)) {
      info('switchToChannel: direct path clicking #team-invite-switch-role-anchor');
      await directSwitchRole.click({ force: true, timeout: 3000 }).catch(async () => {
        await directSwitchRole.evaluate(el => el.click()).catch(() => {});
      });

      const targetAlt = channelName.toLowerCase();
      const directTarget = page.locator(`button:has(img.z-avatar-image[alt="${targetAlt}"])`).first();
      if (await directTarget.isVisible({ timeout: 5000 }).catch(() => false)) {
        info(`switchToChannel: direct path clicking target role @${channelName}`);
        await directTarget.click({ force: true, timeout: 3000 }).catch(async () => {
          await directTarget.evaluate(el => el.click()).catch(() => {});
        });

        const verifiedDirect = await waitForActiveChannel(page, channelName, Math.min(SWITCH_CHANNEL_TIMEOUT_MS, 15000));
        await debugShot(page, 'role-switch-04-verified-direct');
        info(`switchToChannel: VERIFIED requested=@${channelName} active=@${verifiedDirect} URL=${page.url()} (direct path)`);
        return verifiedDirect;
      }

      info(`switchToChannel: direct path could not find target role @${channelName}; falling back to generic role-picker logic`);
      await page.keyboard.press('Escape').catch(() => {});
    } else {
      info('switchToChannel: profile menu button click did not expose #team-invite-switch-role-anchor; falling back to generic trigger scan');
    }
  } else {
    info('switchToChannel: #team-invite-profile-menu-anchor not visible; falling back to generic trigger scan');
  }
'''

if s.count(old) != 1:
    raise SystemExit(f'direct switch block expected once, found {s.count(old)}')
s = s.replace(old, new, 1)

old2 = "  const avatarTriggers = [\n"
new2 = "  const avatarTriggers = [\n    '#team-invite-profile-menu-anchor',\n"
if s.count(old2) != 1:
    raise SystemExit(f'avatarTriggers start expected once, found {s.count(old2)}')
s = s.replace(old2, new2, 1)

p.write_text(s)

# Chatify v2 — Vortex Ops Dev integration

Status: dependency declared in composer.json; NOT installed or enabled on the running Dev server.

## Safety constraints
- Work only on branch Dev and the isolated Dev database. Do not deploy to Production.
- Keep the existing owner-only Communications module and employee delivery disabled until access controls and migration tests pass.
- Do not remove existing team_conversations, team_messages, attachments or announcements tables.
- Do not publish Chatify's public-facing UI/API until authorization and role isolation are tested.

## Install in a disposable Dev checkout first
1. Confirm PHP 8.3+, Laravel 13, current Composer state and clean git status.
2. Run `composer update munafio/chatify --with-all-dependencies` to resolve and generate a correct composer.lock, then commit composer.lock to Dev. Do not run `composer install` against the mismatched lock file before doing this.
3. Run `php artisan chatify:install --with-ui` on a disposable development checkout; review all generated routes, migrations and config before committing.
4. Add `Chatify\Traits\InteractsWithChatify` to `App\Models\User` only after the package is installed and Composer autoload has been updated.
5. Set up Reverb or an equivalent broadcasting driver and confirm authenticated private channels. Check existing Reverb setup first.
6. Run migrations on a disposable database and verify no conflicts with existing Vortex Ops tables.
7. Verify upload storage directories, Livewire temporary uploads, PHP size limits and web server permissions. Chatify will not automatically fix server upload failures.

## UI integration
- Use Chatify's headless JSON API to power the existing floating Messages widget and the Communications workspace.
- Keep Filament's top navigation, light/dark themes and purple Vortex Ops styling.
- Recipient picker: one person = direct chat; multiple people = group; selecting a Spatie role expands to its users. Apply backend access checks to every recipient and role.
- Keep the existing announcements builder separate until a deliberate migration is designed.
- Only expose Chatify routes to authorized owner users during preview; do not rely on hidden navigation links as security.

## Data and release
- Build a read-only mapping/report of existing conversations, memberships, messages, attachments and read markers to Chatify v2 schema before any migration.
- Use an idempotent, reversible migration with validation counts and a rollback plan.
- Test owner authorization, unauthorized users, group membership, direct message uniqueness, attachments, real-time broadcasts, and light/dark/mobile layouts.
- Once Chatify is verified, switch the floating widget to the new backend behind a feature flag. Do not remove the legacy implementation until data integrity and parity are verified.
- Push notifications remain OFF until explicit approval.

Official docs: https://www.chatifyphp.com/docs/getting-started

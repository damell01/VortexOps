#!/usr/bin/env bash
set -euo pipefail

# Run in a CLEAN, isolated checkout of the Dev branch; NEVER on production.
if [[ "${VORTEX_CHATIFY_INSTALL_DEV:-}" != "YES" ]]; then
  echo "Set VORTEX_CHATIFY_INSTALL_DEV=YES only in an isolated Dev checkout." >&2
  exit 1
fi
if [[ "$(git branch --show-current)" != "Dev" ]]; then
  echo "Refusing: checkout the Dev branch." >&2
  exit 1
fi
if [[ -n "$(git status --porcelain)" ]]; then
  echo "Refusing: working tree is not clean. Commit or stash first." >&2
  exit 1
fi
if [[ -f .env ]] && grep -Eq '^APP_ENV=(production|prod)$' .env; then
  echo "Refusing: production APP_ENV." >&2
  exit 1
fi
if [[ -f .env ]] && grep -Eq '^APP_URL=https?://(www\.)?vortexops\.tech(/|$)' .env; then
  echo "Refusing: production APP_URL." >&2
  exit 1
fi

echo "Resolving Chatify v2 and lockfile..."
composer update munafio/chatify --with-all-dependencies --no-interaction
echo "Installing Chatify's UI and migrations into checkout (no database migration yet)..."
php artisan chatify:install --with-ui

echo "Review newly published files and routes before enabling Chatify."
echo "Add Chatify\\Traits\\InteractsWithChatify to App\\Models\\User after package installation."
echo "Commit composer.lock and published assets on Dev."
echo "Then test migrations against an isolated copy of the Dev database."
echo "Leave VORTEX_CHATIFY_PREVIEW_ENABLED=false until authorization and uploads pass."

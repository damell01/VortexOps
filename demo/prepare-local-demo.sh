#!/usr/bin/env bash
# Run only against an isolated local checkout of feature/dbellops-demo.
set -euo pipefail
if [[ ! -f artisan || ! -f composer.json ]]; then echo "Run from the VortexOps repository root." >&2; exit 1; fi
if [[ "${APP_ENV:-}" == production ]]; then echo "Refusing production environment." >&2; exit 1; fi
if ! command -v composer >/dev/null; then echo "Install Composer before running." >&2; exit 1; fi
if ! command -v npm >/dev/null; then echo "Install Node/npm before running." >&2; exit 1; fi
if [[ ! -f .env ]]; then cp .env.example .env; fi
# Never import production data. Use a dedicated SQLite file.
mkdir -p database
touch database/dbellops-demo.sqlite
python3 - <<'PY'
from pathlib import Path
p=Path('.env')
s=p.read_text()
replacements={
 'APP_NAME':'DBellOps',
 'APP_ENV':'local',
 'APP_DEBUG':'true',
 'APP_URL':'http://127.0.0.1:8088',
 'DBELLOPS_DEMO':'true',
 'DB_CONNECTION':'sqlite',
 'DB_DATABASE':str((Path.cwd()/'database/dbellops-demo.sqlite').resolve()),
 'QUEUE_CONNECTION':'sync',
 'CACHE_STORE':'file',
 'SESSION_DRIVER':'file',
}
for k,v in replacements.items():
 lines=s.splitlines()
 lines=[line for line in lines if not line.startswith(k+'=')]
 lines.append(f'{k}={v}')
 s='\n'.join(lines)+'\n'
p.write_text(s)
PY
composer install --no-interaction --prefer-dist
npm ci
php artisan key:generate --force
php artisan migrate --force
# IMPORTANT: do not invoke DatabaseSeeder or DemoDataSeeder: they contain client-specific names.
npm run build
echo "DBellOps local app prepared. Start: php artisan serve --host=127.0.0.1 --port=8088"
echo "A demo user and safe fixture seeder must be prepared before recording authenticated pages."

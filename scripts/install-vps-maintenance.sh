#!/usr/bin/env bash
set -Eeuo pipefail
if [[ ${EUID} -ne 0 ]]; then echo "Run with sudo: sudo bash scripts/install-vps-maintenance.sh"; exit 1; fi
command -v logrotate >/dev/null || { echo "logrotate is required"; exit 1; }
install -d -m 0755 /usr/local/sbin /var/log/vortexops-maintenance
cat > /usr/local/sbin/vortexops-disk-check <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
usage=$(df -P / | awk 'NR==2 {gsub(/%/,"",$5); print $5}')
if (( usage >= 90 )); then level=critical; elif (( usage >= 80 )); then level=warning; else exit 0; fi
msg="VortexOps disk ${level}: root filesystem ${usage}% used on $(hostname)"
logger -t vortexops-disk -p "user.$([[ $level == critical ]] && echo err || echo warning)" "$msg"
echo "$(date -Is) $msg" >> /var/log/vortexops-maintenance/alerts.log
if [[ -n "${VORTEXOPS_ALERT_EMAIL:-}" ]] && command -v mail >/dev/null; then printf '%s\n' "$msg" | mail -s "$msg" "$VORTEXOPS_ALERT_EMAIL"; fi
EOF
cat > /usr/local/sbin/vortexops-weekly-maintenance <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
logger -t vortexops-maintenance 'Starting weekly safe maintenance'
command -v apt-get >/dev/null && apt-get clean
command -v journalctl >/dev/null && journalctl --vacuum-time=14d >/dev/null
if command -v snap >/dev/null; then
  snap list --all 2>/dev/null | awk 'NR>1 && /disabled/ {print $1, $3}' | while read -r name rev; do
    [[ "$rev" =~ ^[0-9]+$ ]] || continue
    snap remove "$name" --revision="$rev" || logger -t vortexops-maintenance "Failed removing disabled snap $name revision $rev"
  done
fi
logger -t vortexops-maintenance 'Finished weekly safe maintenance'
EOF
cat > /usr/local/sbin/vortexops-storage-report <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
out="/var/log/vortexops-maintenance/storage-$(date +%Y-%m).txt"
{
 date -Is
 df -h /
 du -xhd1 /var/lib /var/www/vortexops/storage 2>/dev/null || true
 if command -v docker >/dev/null; then
   docker system df -v
   docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Image}}'
   docker exec ollama ollama list 2>/dev/null || true
 fi
} > "$out" 2>&1
logger -t vortexops-maintenance "Storage report saved: $out"
EOF
chmod 0755 /usr/local/sbin/vortexops-*
cat > /etc/logrotate.d/vortexops-laravel <<'EOF'
/var/www/vortexops/storage/logs/laravel.log {
 daily
 rotate 14
 missingok
 notifempty
 compress
 delaycompress
 copytruncate
 su root root
}
/var/log/vortexops-maintenance/alerts.log {
 weekly
 rotate 8
 missingok
 notifempty
 compress
 copytruncate
}
/var/log/vortexops-maintenance/storage-*.txt {
 monthly
 rotate 3
 missingok
 notifempty
 compress
 copytruncate
}
EOF
cat > /etc/cron.d/vortexops-maintenance <<'EOF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
*/30 * * * * root /usr/local/sbin/vortexops-disk-check
15 3 * * 0 root /usr/local/sbin/vortexops-weekly-maintenance
30 3 1 * * root /usr/local/sbin/vortexops-storage-report
EOF
chmod 0644 /etc/cron.d/vortexops-maintenance
logrotate -d /etc/logrotate.d/vortexops-laravel >/dev/null 2>&1 || { echo "Logrotate validation failed"; exit 1; }
echo "Installed safe maintenance jobs. MySQL, active backfill logs, Docker volumes and Ollama models were not changed."
echo "Alerts log locally; email requires working mail transport and VORTEXOPS_ALERT_EMAIL in cron environment."

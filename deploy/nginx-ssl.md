# Nginx + SSL Setup (Let's Encrypt)

VortexOps runs on port 8080 inside Docker. Nginx sits in front as a reverse proxy and terminates SSL.

## Prerequisites

- Ubuntu 22.04 / 24.04 VPS
- Domain DNS A record pointing to this server's public IP (must propagate before cert issue)
- Ports 80 and 443 open (the VPS setup script does this via UFW)
- Docker stack already running (`docker compose up -d`)

---

## 1. Install Nginx and Certbot

```bash
sudo apt-get update
sudo apt-get install -y nginx certbot python3-certbot-nginx
```

Verify Nginx starts:
```bash
sudo systemctl enable nginx
sudo systemctl start nginx
sudo nginx -t
```

---

## 2. Deploy the Nginx config

Replace `ops.vortexbreaks.com` with your actual domain:

```bash
export DOMAIN=ops.vortexbreaks.com

sudo sed "s/YOUR_DOMAIN/${DOMAIN}/g" /opt/vortexops/deploy/nginx.conf \
    | sudo tee /etc/nginx/sites-available/vortexops

sudo ln -sf /etc/nginx/sites-available/vortexops /etc/nginx/sites-enabled/vortexops

# Remove the default site if it exists
sudo rm -f /etc/nginx/sites-enabled/default

sudo nginx -t && sudo systemctl reload nginx
```

At this point HTTP should proxy to the app. Verify:
```bash
curl -I http://${DOMAIN}/health
# Expected: HTTP/1.1 200 OK
```

---

## 3. Issue the SSL certificate

```bash
sudo certbot --nginx -d ${DOMAIN} \
    --non-interactive \
    --agree-tos \
    --email dbellcreations@gmail.com \
    --redirect
```

Certbot will:
1. Issue a certificate from Let's Encrypt
2. Automatically update `/etc/nginx/sites-available/vortexops` with the cert paths
3. Reload Nginx

Verify HTTPS:
```bash
curl -I https://${DOMAIN}/health
# Expected: HTTP/2 200
```

---

## 4. Auto-renewal

Certbot installs a systemd timer automatically. Confirm it's active:

```bash
sudo systemctl status certbot.timer
# Should show: active (waiting)
```

Test a dry-run renewal:
```bash
sudo certbot renew --dry-run
```

Certs renew automatically every 60 days (Let's Encrypt certs expire after 90).

---

## 5. Update APP_URL

Edit `/opt/vortexops/.env.docker` and set:
```
APP_URL=https://ops.vortexbreaks.com
```

Then restart the app container so Laravel picks up the new URL:
```bash
docker compose -f /opt/vortexops/docker-compose.yml \
    --env-file /opt/vortexops/.env.docker \
    restart app worker scheduler ai-worker
```

And clear the config cache:
```bash
docker compose -f /opt/vortexops/docker-compose.yml exec -T app php artisan optimize
```

---

## Troubleshooting

**502 Bad Gateway**
The app container isn't responding on port 8080. Check:
```bash
docker compose -f /opt/vortexops/docker-compose.yml ps
curl -I http://127.0.0.1:8080/health
```

**Certificate not issuing — port 80 blocked**
```bash
sudo ufw status          # 80/tcp should show ALLOW
curl http://${DOMAIN}    # test from outside the server
```

**Mixed content / redirect loops**
Make sure `APP_URL` in `.env.docker` starts with `https://` and that `TRUSTED_PROXIES` isn't blocking forwarded headers. The Nginx config already sets `X-Forwarded-Proto: $scheme`.

**Check Nginx error log**
```bash
sudo tail -f /var/log/nginx/vortexops_error.log
```

**Check app error log**
```bash
docker compose -f /opt/vortexops/docker-compose.yml exec app tail -f storage/logs/laravel.log
```

---

## Config file locations

| File | Purpose |
|------|---------|
| `/etc/nginx/sites-available/vortexops` | Main Nginx config (managed by Certbot after SSL) |
| `/etc/letsencrypt/live/<domain>/` | SSL certs (managed by Certbot, do not edit) |
| `/var/log/nginx/vortexops_access.log` | Access log |
| `/var/log/nginx/vortexops_error.log` | Error log |

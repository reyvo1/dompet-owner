# Deploy Dompet Owner ke VPS (24 jam non-stop)

Aplikasi = PHP 8.4 + MySQL 8.4 + bot polling PHP. Butuh VPS 1-2 GB RAM (Ubuntu 24.04).

## 1. Upload kode
```bash
rsync -av --exclude backups/ --exclude tools/*.log --exclude src/config.php . user@VPS:/var/www/dompet/
```
## 2. Install stack
```bash
apt update && apt install -y php8.4 php8.4-mysql php8.4-curl php8.4-mbstring mysql-server git
mysql -e "CREATE DATABASE dompet_owner CHARACTER SET utf8mb4; CREATE USER 'dompet'@'localhost' IDENTIFIED BY 'PASSWORD-KUAT'; GRANT ALL ON dompet_owner.* TO 'dompet'@'localhost';"
mysql dompet_owner < db/schema.sql && for f in db/upgrade-*.sql; do mysql dompet_owner < $f; done
```
## 3. Konfigurasi
Salin `src/config.php` (edit DB_USER/DB_PASS + port 3306). Buat `src/secrets.php` kalau mau env-based.
Import data lama: `mysqldump` di PC → import di VPS.
## 4. Web server (HTTPS wajib untuk webhook Midtrans)
```bash
apt install -y nginx certbot python3-certbot-nginx
# server block: root /var/www/dompet/public;  fastcgi 127.0.0.1:9000 (php-fpm)
certbot --nginx -d domainkamu.com
```
## 5. Bot & cron
```bash
DOMPET_BOT_TOKEN=xxx nohup php bot/telegram.php >> tools/bot.log 2>&1 &   # atau systemd unit
crontab -e:
0 6 * * * /usr/bin/php /var/www/dompet/tools/fx-cron.php
0 6 * * * /usr/bin/php /var/www/dompet/tools/subinv-cron.php
0 6 * * * /usr/bin/php /var/www/dompet/tools/bill-notify.php
30 6 * * * /usr/bin/php /var/www/dompet/tools/daily-report.php
0 2 * * * /usr/bin/php /var/www/dompet/tools/backup-db.php   (buat versi Linux jika perlu)
```
## 6. Midtrans
1. dashboard.midtrans.com → Settings → API Keys → salin Server/Client Key ke Pengaturan.
2. Settings → Configuration → Payment Notification URL: `https://domainkamu.com/midtrans.php`
3. Buat kwitansi → tombol "Bayar Online" aktif → QRIS dinamis → status lunas otomatis.

## 7. Checklist selesai
- [ ] Bot /start jalan dari VPS
- [ ] Cron harian kirim notif
- [ ] Webhook Midtrans 201 di dashboard
- [ ] Backup otomatis harian (tombol "Backup + Kirim Cloud" tetap bisa dipakai)

## 8. Folder uploads
Otomatis dibuat saat upload pertama (api.php & bot). Di VPS pastikan writable:
```bash
mkdir -p /var/www/dompet/public/uploads && chown -R www-data:www-data /var/www/dompet/public/uploads
```

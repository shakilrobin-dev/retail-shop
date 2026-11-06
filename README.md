# Retail Shop – Inventory, POS & Repair

Web app for a mobile accessories / spare parts / electronics shop with repair service.
Bangla + English, multi-branch, barcode based, made to run on cheap shared hosting.

Full requirements: [REQUIREMENTS.md](REQUIREMENTS.md)

## Features

- **POS**: scan barcode to sell, box/piece units, split payment (cash, card, bKash, Nagad, Rocket…), hold/resume sale, discount limits, optional VAT, receipt printing ON / OFF / Ask (Rongta 80 mm, 58 mm or A4), keyboard shortcuts (F2, F8, F10).
- **Products**: categories with sub-categories, brands, base unit + packs (e.g. Box = 50 pcs), several barcodes per unit, scan existing or generate barcode, barcode label printing (A4 sheet or thermal label printer).
- **Purchases**: suppliers, stock in by scanning, average cost, return to supplier.
- **Stock**: stock per branch, history of every change, damaged/lost adjustments, stock count by scanning the shelf, transfers between branches.
- **Repairs**: job intake with job slip + device tag, status tracking, spare parts from stock, advance payment, delivery bill, repair warranty, warranty claims, cancel with refund.
- **Sales**: history, reprint, returns/exchange with refund, void with stock return.
- **Customers**: purchase and repair history, product warranty dates.
- **Cash register**: open with starting cash, live expected cash, close with count (shows short/extra).
- **Expenses**, **Reports** (profit & loss, daily sales, top products, categories, payment methods, staff, dead stock, CSV export).
- **Settings**: shop info & logo, receipt, labels, VAT, POS rules, repair terms, optional technicians, optional SMS, branches, users, role permissions, payment methods, backups (automatic daily + download).

## Tech

Laravel 13, PHP 8.3+, MySQL, Livewire 4, Alpine.js, Tailwind CSS 4, Preline UI 4 (light & dark theme).
No Node.js, Redis, queue worker or WebSockets are needed on the server.

## Run on your computer

```bash
composer install
cp .env.example .env
php artisan key:generate
# create a MySQL database named retail_shop, set DB_* in .env
php artisan migrate --seed
php artisan db:seed --class=DemoDataSeeder   # optional sample products
php artisan serve --port=8080
```

Open http://127.0.0.1:8080 and log in with username `admin`, password `password`
(change it right away in Settings → Users).

To change CSS/JS: `npm install && npm run build`. The built files in `public/build` are committed, so the server does not need Node.

## Deploy to shared hosting (cPanel)

Hosting must have: **PHP 8.3+**, MySQL, SSH access, Composer, cron jobs, free SSL.

1. Create a MySQL database and user in cPanel.
2. Upload the project (or `git clone`) to a folder **outside** `public_html`, e.g. `~/retail_shop`.
3. Point the domain's document root to `~/retail_shop/public`.
   If the host does not allow that, copy the contents of `public/` into `public_html` and edit `index.php` paths to `../retail_shop/vendor/autoload.php` and `../retail_shop/bootstrap/app.php`.
4. In SSH:
   ```bash
   cd ~/retail_shop
   composer install --no-dev --optimize-autoloader
   cp .env.example .env   # set APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*
   php artisan key:generate
   php artisan migrate --seed --force
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
5. Add **one** cron job (every minute) for automatic backups:
   ```
   * * * * * cd ~/retail_shop && php artisan schedule:run >> /dev/null 2>&1
   ```
6. Log in, change the admin password, then fill Settings (shop name, branch address, receipt, VAT…).

### Update later

```bash
cd ~/retail_shop && git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

## Printer (Rongta) setup

1. Install the Rongta Windows driver, set paper width 80 mm (or 58 mm).
2. Make Rongta the default printer.
3. For printing without the dialog, add `--kiosk-printing` to the Chrome shortcut.
4. In the print dialog once: Margins = None, Headers and footers = off.
5. Cash drawer (optional): enable "open cash drawer" in the Rongta driver and in Settings → Receipt.

## Backups

- Automatic every night at 02:00 (last 14 kept) in `storage/app/private/backups`.
- Settings → Backup: make a backup now or download one. Keep a copy off the server every week.
- Restore: import the `.sql.gz` file in phpMyAdmin (or `gunzip -c file.sql.gz | mysql -u user -p database`).

## Tests

```bash
php artisan test
```

# LeadGen MVP — Complete Setup Guide
## Pan-India B2B Lead Generation Platform

---

## What you're building

```
Python Scraper (Google Maps)
        ↓  POST /api/leads
Laravel 12 API (MySQL + Redis)
        ↓  Queue job
AI Scoring (GPT-4o-mini)
        ↓
WhatsApp Business API (Meta)
        ↓  Webhooks
Next.js Dashboard (React CRM)
```

---

## Server requirements (VPS)

Buy a VPS from Contabo (~₹700/mo) or Hetzner (~₹1200/mo).

**Minimum specs:** 2 vCPU, 4 GB RAM, 80 GB SSD, Ubuntu 22.04

```bash
# After SSH into your VPS:
sudo apt update && sudo apt upgrade -y

# Install PHP 8.3
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install php8.3 php8.3-fpm php8.3-mysql php8.3-redis \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install MySQL
sudo apt install mysql-server -y
sudo mysql_secure_installation
# Create DB:
mysql -u root -p
CREATE DATABASE leadgen CHARACTER SET utf8mb4;
CREATE USER 'leadgen'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL ON leadgen.* TO 'leadgen'@'localhost';
FLUSH PRIVILEGES;

# Install Redis
sudo apt install redis-server -y
sudo systemctl enable redis

# Install Node.js (for Next.js frontend)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
sudo apt install nodejs -y

# Install Nginx
sudo apt install nginx -y

# Install Python 3.11+ and Playwright
sudo apt install python3 python3-pip -y
pip3 install playwright httpx
playwright install chromium --with-deps
```

---

## Laravel Backend Setup

```bash
cd /var/www
composer create-project laravel/laravel leadgen-backend
cd leadgen-backend

# Install packages
composer require laravel/horizon predis/predis openai-php/laravel

# Copy your .env
cp .env.example .env
php artisan key:generate

# Edit .env with your values (DB, Redis, OpenAI, WhatsApp)
nano .env

# Publish configs
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
php artisan horizon:install

# Place the files from laravel-setup/ into the correct locations:
# 01_migration_businesses.php  → database/migrations/
# 02_models_services_jobs.php  → split into app/Models/, app/Services/, app/Jobs/
# 03_routes_scheduler.php      → routes/api.php additions

# Run migrations
php artisan migrate

# Set permissions
chown -R www-data:www-data /var/www/leadgen-backend
chmod -R 775 storage bootstrap/cache
```

---

## Nginx config (Laravel)

```nginx
# /etc/nginx/sites-available/leadgen
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/leadgen-backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/leadgen /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## Supervisor (keep queue worker running)

```bash
sudo apt install supervisor -y

# Create config file:
sudo nano /etc/supervisor/conf.d/leadgen-worker.conf
```

```ini
[program:leadgen-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/leadgen-backend/artisan queue:work redis --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/leadgen-backend/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start leadgen-worker:*
```

---

## Cron (for scheduled follow-ups)

```bash
sudo crontab -e -u www-data
# Add this line:
* * * * * cd /var/www/leadgen-backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## WhatsApp Business API Setup

1. Go to **developers.facebook.com** → Create App → Business
2. Add **WhatsApp** product to your app
3. Get your **Phone Number ID** and **Access Token**
4. Set webhook URL: `https://your-domain.com/api/whatsapp/webhook`
5. Verify token: set `WHATSAPP_VERIFY_TOKEN` in your .env
6. Subscribe to: `messages`, `message_status_updates`

Add to `.env`:
```
WHATSAPP_TOKEN=EAAxxxxxxxxxxxxx
WHATSAPP_PHONE_NUMBER_ID=123456789012345
WHATSAPP_VERIFY_TOKEN=my_custom_verify_token_123
```

---

## Python Scraper Setup

```bash
mkdir /opt/leadgen-scraper
cd /opt/leadgen-scraper
# Copy scraper.py here, edit the LARAVEL_API_URL and LARAVEL_API_KEY

# Test run
python3 scraper.py

# Add to cron to run daily at 6 AM
crontab -e
0 6 * * * cd /opt/leadgen-scraper && python3 scraper.py >> scraper.log 2>&1
```

---

## Next.js Dashboard Setup

```bash
cd /var/www
npx create-next-app@latest leadgen-frontend
cd leadgen-frontend

# Copy Dashboard.jsx to app/page.jsx (or pages/index.jsx for Pages Router)
# Replace MOCK_LEADS with actual API fetch from your Laravel backend

npm run build
npm run start
```

For Next.js fetching from Laravel:
```js
// In your page component:
const res = await fetch('https://your-domain.com/api/leads?page=1&status=all', {
  headers: { 'Authorization': 'Bearer YOUR_DASHBOARD_TOKEN' }
});
const data = await res.json();
```

---

## Week-by-week action plan

| Week | Task | Goal |
|------|------|------|
| 1 | VPS setup + MySQL + Laravel migrations | DB running |
| 2 | Python scraper → API → DB working | First leads in DB |
| 3 | AI scoring (GPT-4o-mini) + queue | Leads auto-scored |
| 4 | WhatsApp Business API connected | First messages sent |
| 5 | Dashboard deployed + filters working | Full visibility |
| 6 | Follow-up automation + webhook replies | Hands-free outreach |
| 7 | Review first 200 leads, iterate messages | Higher reply rate |
| 8 | Add Justdial / IndiaMART scrapers | More lead sources |

---

## Daily message limits (safe ramp-up)

| Period | Max messages/day |
|--------|-----------------|
| Week 1–2 | 20 |
| Week 3–4 | 50 |
| Month 2+ | 100 |

Never send more than this. WhatsApp bans numbers that spike suddenly.

---

## Cost estimate (monthly)

| Service | Cost |
|---------|------|
| Contabo VPS (4 GB) | ₹700 |
| OpenAI GPT-4o-mini | ₹200–800 (per 1000 leads) |
| WhatsApp Business API | Free up to 1000 conv/month |
| Domain + SSL | ₹100 |
| **Total** | **~₹1,200–1,800/mo** |

---

## Files included

```
leadgen-mvp/
├── scraper/
│   └── scraper.py              ← Python Google Maps scraper
├── laravel-setup/
│   ├── 00_setup_notes.php      ← Install commands + .env template
│   ├── 01_migration_businesses.php
│   ├── 02_models_services_jobs.php  ← Models, AI service, WhatsApp service, Queue job, Controller
│   └── 03_routes_scheduler.php ← API routes + cron scheduler
└── dashboard-guide/
    └── Dashboard.jsx           ← React CRM dashboard (AI-powered reply generator included)
```

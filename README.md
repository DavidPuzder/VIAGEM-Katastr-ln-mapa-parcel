# VIAGEM – Katastrální mapa parcel – okres Jičín

Webová aplikace pro zobrazení a vyhledávání katastrálních parcel v okrese Jičín. Postavena na Laravel (backend/API) a Vue 3 s Leaflet (interaktivní mapa).

## Požadavky na zařízení

Před instalací se ujistěte, že máte nainstalováno:

- **PHP** 8.2 nebo vyšší (s rozšířením `pdo_sqlite`)
- **Composer** 2.x ([getcomposer.org](https://getcomposer.org))
- **Node.js** 18.x nebo vyšší a **npm**
- **Git**

> Databáze používá **SQLite**, není tedy potřeba instalovat žádný samostatný databázový server (MySQL/PostgreSQL).

Ověřte verze v terminálu:

```bash
php -v
composer -V
node -v
npm -v
```

Ověřte, že máte povolené rozšíření SQLite:

```bash
php -m | grep sqlite
```

Pokud rozšíření chybí, doinstalujte ho (např. na Ubuntu/Debian):

```bash
sudo apt install php-sqlite3
```

## 1. Naklonování repozitáře

```bash
git clone https://github.com/DavidPuzder/VIAGEM-Katastr-ln-mapa-parcel.git
cd VIAGEM-Katastr-ln-mapa-parcel
```

## 2. Instalace PHP závislostí (Laravel)

```bash
composer install
```

## 3. Instalace JavaScript závislostí (Vue + Leaflet)

```bash
npm install
```

## 4. Konfigurace prostředí

Projekt obsahuje přednastavený soubor `.env.production` s produkční konfigurací. Vytvořte z něj vlastní `.env`:

```bash
cp .env.production .env
```

Obsah `.env.production` (a tedy i výsledného `.env`) vypadá takto:

```env
APP_NAME="VIAGEM - Katastrální mapa parcel – okres Jičín"
APP_ENV=production
APP_KEY=base64:fyfpNzKS7IjtQ4Zh7RSWWz4jX62smGSZ3P8UExS8RWM=
APP_DEBUG=false
APP_URL=http://localhost

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=file

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"
```

> `APP_KEY` je již v `.env.production` vyplněný, takže **nemusíte** spouštět `php artisan key:generate`

### Vytvoření SQLite databázového souboru

Laravel s SQLite potřebuje existující databázový soubor. Vytvořte ho:

```bash
touch database/database.sqlite
```

Na Windows (PowerShell):

```powershell
New-Item -ItemType File -Path database\database.sqlite
```

## 5. Migrace databáze

Vytvoří potřebné tabulky (včetně sessions a queue jobs, které tato konfigurace vyžaduje v databázi):

```bash
php artisan migrate
```

Pokud projekt obsahuje seed data (např. import parcel z katastru):

```bash
php artisan db:seed
```

## 6. Sestavení frontendu

Vzhledem k tomu, že `.env` je nastaven na `APP_ENV=production` a `APP_DEBUG=false`, doporučujeme vždy sestavit produkční build frontendu (ne dev server):

```bash
npm run build
```

Pouze pro lokální vývoj a testování s hot-reload (nedoporučeno pro reálné nasazení):

```bash
npm run dev
```

## 7. Spuštění aplikace

Pro produkční prostředí doporučujeme nasadit aplikaci na skutečný webserver (Nginx/Apache), který směruje na složku `public/`. Pro rychlé ověření funkčnosti lze použít i vestavěný server:

```bash
php artisan serve
```

Aplikace poběží na adrese [http://localhost:8000](http://localhost:8000) (nebo dle nastaveného `APP_URL`).
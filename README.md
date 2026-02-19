# FixPulse

Monorepo con Laravel 12 + Node runner local para auditar URLs con Lighthouse.

## Requisitos Locales
- PHP 8.3
- Composer 2.x
- Node 20+ y npm
- PostgreSQL
- Redis
- Chrome o Chromium instalado (para Lighthouse)

## Estructura
```txt
fixpulse/
  apps/
    web/      # Laravel 12
    runner/   # Fastify + Lighthouse
  deploy/
    nginx/
    systemd/
```

## Inicio Rapido (1 comando)
Desde la raiz del repo:
```powershell
cd m:\NODE\fixpulse
.\start-dev.ps1
```

Para apagar todo:
```powershell
cd m:\NODE\fixpulse
.\stop-dev.ps1
```

M:\NODE\fixpulse\apps\web
php artisan serve

cd m:\NODE\fixpulse\apps\web
php artisan queue:work --queue=scans,default --tries=2 --timeout=120

cd m:\NODE\fixpulse\apps\runner
npm install
npm run dev

http://localhost:8000/register
http://localhost:4321/ astro

fast:
cd m:\NODE\fixpulse
.\start-dev.ps1

Parar todo:
cd m:\NODE\fixpulse
.\stop-dev.ps1

Opcional (si no quieres que limpie procesos antes de arrancar):
.\start-dev.ps1 -NoClean

all:
cd m:\NODE\fixpulse
.\start-demo.ps1

Opcional:
.\start-demo.ps1 -AstroPort 4322
.\start-demo.ps1 -NoClean


## Configuración Local
### 1) Laravel (`apps/web`)
Desde PowerShell:
```powershell
cd m:\NODE\fixpulse\apps\web
Copy-Item .env.example .env
```

Edita `apps/web/.env` y asegura esto:
```env
APP_NAME=FixPulse
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fixpulse
DB_USERNAME=fixpulse
DB_PASSWORD=secret

QUEUE_CONNECTION=database
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

RUNNER_URL=http://127.0.0.1:3333
RUNNER_KEY=change-me
WHATCMS_API_KEY=
WHATCMS_ENDPOINT=https://whatcms.org/API/Tech
WHATCMS_PRIVATE=true
```

Instala dependencias y prepara app:
```powershell
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### 2) Runner (`apps/runner`)
```powershell
cd m:\NODE\fixpulse\apps\runner
Copy-Item .env.example .env
```

Edita `apps/runner/.env`:
```env
RUNNER_HOST=127.0.0.1
RUNNER_PORT=3333
RUNNER_KEY=change-me
RUNNER_TIMEOUT_MS=90000
CHROME_PATH=
```

`RUNNER_KEY` debe ser igual en Laravel y runner.

Instala dependencias:
```powershell
npm install
```

## Ejecutar el Proyecto (3 terminales)
### Terminal 1: Laravel
```powershell
cd m:\NODE\fixpulse\apps\web
php artisan serve --host=127.0.0.1 --port=8000
```

### Terminal 2: Queue Worker
```powershell
cd m:\NODE\fixpulse\apps\web
php artisan queue:work --queue=scans,default --tries=2 --timeout=120
```

### Terminal 3: Node Runner
```powershell
cd m:\NODE\fixpulse\apps\runner
npm run dev
```

## Probar Flujo MVP
1. Abre `http://127.0.0.1:8000/register` y crea un usuario.
2. En `/dashboard`, crea un proyecto.
3. Entra al proyecto y lanza un scan (`mobile` o `desktop`).
4. Revisa el detalle del scan: summary, issues y fix plan.

## Verificación Rápida
- Laravel: `http://127.0.0.1:8000`
- Runner health: `http://127.0.0.1:3333/health`
- Worker procesando jobs: salida en terminal `queue:work`

## Comando Útil
Purgar artifacts viejos (mantiene issues/plan en DB):
```powershell
cd m:\NODE\fixpulse\apps\web
php artisan scans:purge-artifacts --days=30
```

## API de detección de tecnologías (WhatCMS + fallback)
Endpoint protegido:
- `POST /api/tech/analyze`

Payload:
```json
{
  "url": "https://example.com"
}
```

Respuesta:
- `analysis.primary`: tecnología principal detectada (ej. WordPress, Next.js, React, etc.).
- `analysis.source`: `whatcms` si hay API key, `local_fallback` si no.

Nota:
- Si quieres detección online gratuita más estable, configura `WHATCMS_API_KEY`.

## SQL para PostgreSQL
Archivo: `deploy/postgres/init.sql`

Ejecutar:
```powershell
cd m:\NODE\fixpulse
psql -U postgres -f deploy/postgres/init.sql
```

Luego en Laravel:
```powershell
cd m:\NODE\fixpulse\apps\web
php artisan migrate
```

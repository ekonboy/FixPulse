Eres un senior full-stack. Quiero que generes el proyecto “FixPulse” (monorepo) con Laravel 12 + Node runner local (sin Docker). Objetivo: dada una URL, ejecutar auditorías con Lighthouse (Chrome headless), normalizar resultados a “issues” y generar un “fix plan” priorizado. Debe incluir API + UI mínima y estar listo para desplegar en un VPS Ubuntu 22.04. El dominio público será https://fixpulse.infinit2.es (Laravel). El Node runner NO será público: solo escuchará en 127.0.0.1:3333.

RESTRICCIONES IMPORTANTES
- NO Docker.
- NO subida/lectura de ZIP ni indexado de repos.
- Runner Node debe ejecutarse como servicio (systemd o PM2), escuchando SOLO en localhost.
- Laravel orquesta: API + UI + DB + Auth + Jobs/Queue.
- DB: PostgreSQL (JSONB para evidence/fix/plan/summary).
- Cola: Redis desde el inicio (recomendado con Horizon opcional).
- Auth MVP: Laravel Breeze (email/password).
- UI MVP: Blade + Tailwind.
- PHP objetivo: 8.3
- Node objetivo: 20 LTS
- Git: main estable + rama inicial feat/bootstrap-mvp (no trabajar directo en main).

ARQUITECTURA
Monorepo:
- apps/web = Laravel 12 (API + UI + DB + Auth + Jobs)
- apps/runner = Node.js (Fastify) para ejecutar Lighthouse

EJECUCIÓN (FLUJO)
1) Usuario crea Project (name, base_url)
2) Usuario lanza Scan (target_url, device mobile/desktop, mode=lighthouse)
3) Laravel encola RunLighthouseJob
4) RunLighthouseJob llama al runner por HTTP local:
   POST http://127.0.0.1:3333/audit/lighthouse
   headers: X-Runner-Key: <shared_secret>
   body: { url, device, locale: "es-ES" }
5) Runner ejecuta Lighthouse y devuelve LHR JSON
6) Laravel guarda artifact (lhr.json) y extrae métricas resumen
7) Laravel normaliza a issues y construye un fix plan priorizado

SEGURIDAD CRÍTICA (SSRF + OPERACIÓN)
Implementar validación SSRF estricta ANTES de ejecutar cualquier audit:
- Permitir SOLO http/https
- Bloquear userinfo en URL (user:pass@)
- Resolver DNS y bloquear si cualquier A/AAAA cae en rangos:
  127.0.0.0/8, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 169.254.0.0/16,
  ::1, fc00::/7, fe80::/10, etc.
- Bloquear hostnames con TLDs: .local, .internal, .lan
- Permitir SOLO puertos 80 y 443
- Seguir redirects máximo 3 y re-validar SSRF en cada redirect
- Rate limiting por usuario y por proyecto
Runner:
- Escuchar solo 127.0.0.1
- Requerir API key (X-Runner-Key) obligatoria desde MVP

LÍMITES DE ESCANEO
- Timeout Lighthouse por scan: 90s (hard timeout del job: 120s)
- Concurrencia global: máximo 2 scans simultáneos (lock/semaphore)
- Concurrencia por usuario/proyecto: 1 scan activo a la vez

RETENCIÓN
- Guardar siempre el LHR completo (artifact lhr.json)
- Política recomendada: purgar artifacts antiguos (ej. 30 días) dejando issues/plan/summary en DB

FUNCIONALIDADES MVP (FASE 1)
1) Projects:
   - Crear / listar / ver detalle (name, base_url)
2) Scans:
   - Crear scan en proyecto (target_url, device)
   - Estado scan: queued|running|done|failed
3) Artifacts:
   - Guardar lhr.json en storage/app y registrar en scan_artifacts
4) Normalización a issues:
   - Convertir audits principales a tabla issues:
     key, category (perf|seo|a11y|best_practices),
     title, severity (info|low|med|high|critical),
     impact_score (0-100), effort_score (0-100),
     estimated_saving_ms, estimated_saving_kb,
     evidence_jsonb, fix_jsonb
   - issue_resources con assets implicados (scripts/styles/images/fonts) si existen en LHR
5) Fix Plan:
   - Ordenar issues por prioridad (impact_score vs effort_score con ponderación)
   - Buckets: Quick Wins, Medium, Structural
   - Guardar plan_jsonb en fix_plans
6) API Endpoints:
   - POST /api/projects
   - GET  /api/projects
   - GET  /api/projects/{id}
   - POST /api/projects/{id}/scans
   - GET  /api/scans/{id}
   - GET  /api/scans/{id}/issues
   - GET  /api/scans/{id}/plan
7) UI (Blade + Tailwind):
   - Dashboard: lista proyectos
   - Proyecto: historial de scans
   - Scan detail: resumen métricas + tabla issues + plan

NODE RUNNER (apps/runner)
- Fastify server:
  - POST /audit/lighthouse { url, device, locale }
    - valida input mínimo (url)
    - ejecuta Lighthouse con Chrome/Chromium headless
    - devuelve { ok: true, lhr: <json> } o { ok:false, error:{code,message,details} }
- Debe manejar timeouts, errores de navegación y devolver errores estructurados
- Debe permitir configurar path a Chrome si es necesario

POSTGRESQL (apps/web)
Usar columnas JSONB en migraciones:
- scans.summary_jsonb
- scan_artifacts.meta_jsonb
- issues.evidence_jsonb
- issues.fix_jsonb
- fix_plans.plan_jsonb

ENTREGABLES EXACTOS QUE DEBES GENERAR
A) Árbol monorepo completo:
fixpulse/
  apps/web (Laravel 12)
  apps/runner (Node Fastify)
  deploy/nginx/fixpulse.conf
  deploy/systemd/fixpulse-runner.service
  deploy/systemd/fixpulse-queue.service
  README.md

B) Código Laravel 12:
- migraciones: projects, scans, scan_artifacts, issues, issue_resources, fix_plans
- modelos Eloquent y relaciones
- Form Requests para validación (URL + SSRF)
- controllers y routes API (con auth)
- jobs:
  - RunLighthouseJob
  - NormalizeIssuesJob
  - BuildFixPlanJob
- services:
  - RunnerClient (Guzzle) con API key
  - SsrFGuard (validación de host/ips/redirects/ports)
  - IssueNormalizer (LHR -> issues)
- Blade + Tailwind (pantallas mínimas)

C) Código Node runner:
- servidor Fastify
- endpoint /audit/lighthouse
- wrapper Lighthouse con flags mobile/desktop, locale es-ES
- config por env: RUNNER_PORT=3333, RUNNER_KEY=..., CHROME_PATH=...

D) Despliegue en VPS Ubuntu 22.04:
- Nginx vhost para Laravel (root public, php-fpm)
- Systemd (o PM2) para:
  - runner Node
  - Laravel queue worker (php artisan queue:work --tries=2 --timeout=120)
- README con:
  - instalación de Node 20, PHP 8.3, Redis, Postgres
  - instalación de Chrome/Chromium para Lighthouse
  - pasos para levantar runner + Laravel
  - cómo ejecutar un scan de prueba
  - variables .env ejemplo

E) Git
- Instrucciones: rama inicial feat/bootstrap-mvp, PR a main, convenciones de commits

IMPORTANTE
- No inventes dependencias raras. Usa librerías estándar y mantenibles.
- Todo debe ser ejecutable sin Docker.
- Evita SSRF: la validación debe ser seria y estar antes del runner.
- Genera código completo y coherente, no pseudocódigo.


INFRA
fixpulse/
  apps/
    web/                # Laravel 12
      app/
      database/
      routes/
      resources/
      public/
      storage/
      composer.json
      artisan
    runner/             # Node runner
      src/
      package.json
      ecosystem.config.js (PM2)  [o service systemd]
  deploy/
    nginx/
      fixpulse.conf
    systemd/
      fixpulse-runner.service
      fixpulse-queue.service
  README.md

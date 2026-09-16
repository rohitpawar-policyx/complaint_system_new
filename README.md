# Complaint Management System

A Laravel-based web application for submitting, tracking, and resolving complaints, with separate customer and admin experiences.

## Features

- Customer complaint submission with attachments, status tracking, and history
- Payment proof required on submission, with automatic transaction ID extraction via local OCR (Tesseract) — never blocks submission if OCR fails, and flags (not rejects) a transaction ID already used by another complaint
- Duplicate-submission protection: an `Idempotency-Key` on complaint creation means a double-click, network retry, or timeout-then-retry can never create two complaints from one logical submission
- Real-time chat between a customer and any admin on a complaint (Laravel Reverb / WebSockets), gated by complaint status — blocked while `pending`, read-only once `resolved`/`closed`/`rejected`
- Admin dashboard for managing complaints, assignment, status updates, and complaint history
- Role and user management (roles, approval workflow, block/unblock users)
- Configurable complaint reasons
- In-app notifications (complaint created/assigned/status changed, user approved/blocked) with a live notification bell that polls for updates every 2 minutes on both the customer and admin sides

## Tech stack

- PHP 8.2 / Laravel 10
- MySQL locally, PostgreSQL on Render (see [Deployment](#deployment))
- Laravel Reverb (WebSocket broadcasting) + Laravel Echo/pusher-js for live chat
- Tesseract OCR (`tesseract-ocr`, via the `thiagoalessio/tesseract_ocr` PHP wrapper) for payment proof transaction ID extraction
- Vite, Axios, vanilla JS (no frontend framework)
- Docker for deployment, GitHub Actions for CI (see [Continuous Integration](#continuous-integration))

## Getting started

1. Install PHP dependencies:
   ```bash
   composer install
   ```
2. Install JS dependencies:
   ```bash
   npm install
   ```
3. Copy the environment file and set your database credentials:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Run migrations:
   ```bash
   php artisan migrate
   ```
5. Build frontend assets:
   ```bash
   npm run dev   # or: npm run build
   ```
6. Serve the application:
   ```bash
   php artisan serve
   ```
7. (Optional, for live chat) Start Reverb in a second terminal:
   ```bash
   php artisan reverb:start
   ```
   Set matching `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` in `.env` first (any values work locally, they just need to match). Without this running, everything else still works — chat messages simply won't arrive in real time until you refresh.
8. (Optional, for OCR) Install Tesseract locally so transaction ID extraction actually runs:
   ```bash
   # Debian/Ubuntu
   sudo apt-get install tesseract-ocr tesseract-ocr-eng
   ```
   Without it, complaint submission still works exactly the same — OCR failures never block complaint creation, they just leave the transaction ID unextracted (see `PaymentProofOcrService`).

## Branching & workflow

- `main` — production, protected (PR required, no direct pushes)
- `uat` — staging, protected (PR required, no direct pushes)
- Feature branches go off `uat` → PR into `uat` → test on the UAT deployment → PR from `uat` into `main` to promote to production

## Deployment

Hosted on [Render](https://render.com). The app uses **MySQL locally** but **PostgreSQL on Render** (Render only offers managed Postgres natively) — see `config/database.php`'s `pgsql` connection and `.env.example` for details. No code changes are needed between the two: migrations and queries are portable (no raw SQL, no MySQL-specific syntax).

**Render setup:**
- Two separate Postgres instances: one for UAT, one for Production — never shared, so UAT can't touch production data.
- Two main Web Services (Docker environment, using the repo's `Dockerfile`): one tracking the `uat` branch, one tracking `main`. Each auto-deploys on push/merge to its branch.
- Two more Web Services, one per environment, running **Laravel Reverb** for WebSocket chat — same `Dockerfile`/image, but with the Docker Command overridden to `php artisan reverb:start --host=0.0.0.0 --port=$PORT` instead of the default entrypoint.
  - The main app service and its matching Reverb service must share identical `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` (the app authenticates broadcasts *to* Reverb using these).
  - On the main app service, `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` point at the Reverb service's own `.onrender.com` URL (port `443`, scheme `https`) — see the comments in `.env.example` for the full explanation.
- Per-service environment variables (set in Render's dashboard, never committed):
  - `DB_CONNECTION=pgsql`
  - `DATABASE_URL=<that environment's Postgres instance's Internal Database URL>`
  - `APP_KEY=<generate separately per environment with php artisan key:generate --show>`
  - `APP_ENV` / `APP_DEBUG` / `APP_URL` set appropriately per environment
  - `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` / `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` as above

**Local Docker build/run** (to test the production image before deploying):
```bash
docker build -t complaint-system:local .
docker run --rm -p 8080:8080 \
  -e APP_KEY=<your local APP_KEY> \
  -e DB_CONNECTION=mysql -e DB_HOST=<host> -e DB_PORT=3306 \
  -e DB_DATABASE=<db> -e DB_USERNAME=<user> -e DB_PASSWORD=<pass> \
  -e PORT=8080 \
  complaint-system:local
```
The container's entrypoint (`docker/entrypoint.sh`) caches config/routes/views, runs `php artisan migrate --force`, then serves on `$PORT` — matching exactly what Render runs in production.

## Testing

Tests run against a **separate** MySQL database from your dev database — `RefreshDatabase` rebuilds the whole schema on every run, which would otherwise wipe real local data. Create it once:

```bash
mysql -e "CREATE DATABASE IF NOT EXISTS complaint_system_new_testing;"
```

Then:

```bash
php artisan test
```

Code style is enforced with [Laravel Pint](https://laravel.com/docs/pint):

```bash
./vendor/bin/pint --test   # check only
./vendor/bin/pint          # auto-fix
```

## Continuous Integration

Every pull request into `uat` (or `main`) triggers a GitHub Actions workflow (`.github/workflows/ci.yml`) that:

1. Builds frontend assets (`npm ci && npm run build`) — several views render through `@vite()`, which fails without a build present
2. Installs PHP dependencies and runs the full test suite (`php artisan test`) against a real MySQL service container
3. Checks code style (`./vendor/bin/pint --test`)

The `uat` branch ruleset requires this check to pass before a PR can be merged — a broken build or a failing test blocks the merge button entirely, rather than reaching Render at all. Once merged, Render's own auto-deploy picks up the new commit and redeploys automatically — CI and CD are two separate, sequential gates: CI decides *if* a change should go out, Render's auto-deploy is what actually ships it.

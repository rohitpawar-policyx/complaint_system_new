# Complaint Management System

A Laravel-based web application for submitting, tracking, and resolving complaints, with separate customer and admin experiences.

## Features

- Customer complaint submission with attachments, status tracking, and history
- Admin dashboard for managing complaints, assignment, status updates, and complaint history
- Role and user management (roles, approval workflow, block/unblock users)
- Configurable complaint reasons
- In-app notifications (complaint created/assigned/status changed, user approved/blocked) with a live notification bell that polls for updates every 2 minutes on both the customer and admin sides

## Tech stack

- PHP 8.2 / Laravel 10
- MySQL locally, PostgreSQL on Render (see [Deployment](#deployment))
- Vite, Axios, vanilla JS (no frontend framework)
- Docker for deployment

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

## Branching & workflow

- `main` — production, protected (PR required, no direct pushes)
- `uat` — staging, protected (PR required, no direct pushes)
- Feature branches go off `uat` → PR into `uat` → test on the UAT deployment → PR from `uat` into `main` to promote to production

## Deployment

Hosted on [Render](https://render.com). The app uses **MySQL locally** but **PostgreSQL on Render** (Render only offers managed Postgres natively) — see `config/database.php`'s `pgsql` connection and `.env.example` for details. No code changes are needed between the two: migrations and queries are portable (no raw SQL, no MySQL-specific syntax).

**Render setup:**
- Two separate Postgres instances: one for UAT, one for Production — never shared, so UAT can't touch production data.
- Two Web Services (Docker environment, using the repo's `Dockerfile`): one tracking the `uat` branch, one tracking `main`. Each auto-deploys on push/merge to its branch.
- Per-service environment variables (set in Render's dashboard, never committed):
  - `DB_CONNECTION=pgsql`
  - `DATABASE_URL=<that environment's Postgres instance's Internal Database URL>`
  - `APP_KEY=<generate separately per environment with php artisan key:generate --show>`
  - `APP_ENV` / `APP_DEBUG` / `APP_URL` set appropriately per environment

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

```bash
php artisan test
```

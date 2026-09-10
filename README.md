# Complaint Management System

A Laravel-based web application for submitting, tracking, and resolving complaints, with separate customer and admin experiences.

## Features

- Customer complaint submission with attachments, status tracking, and history
- Admin dashboard for managing complaints, assignment, status updates, and complaint history
- Role and user management (roles, approval workflow, block/unblock users)
- Configurable complaint reasons
- In-app notifications (complaint created/assigned/status changed, user approved/blocked) with a live notification bell that polls for updates every 2 minutes on both the customer and admin sides

## Tech stack

- PHP 8.1 / Laravel 10
- MySQL
- Vite, Axios, vanilla JS (no frontend framework)

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

## Testing

```bash
php artisan test
```

# CampusDesk API

This directory contains the Laravel 12 API for CampusDesk. The active Vue application is in [`../Frontend/`](../Frontend/), and the project documentation starts at [`../README.md`](../README.md).

The API supports Sanctum token authentication, student request submission and tracking, staff stage processing, department administration, Super Admin management, in-app notifications, and protected attachment access. See [`../Docs/API.md`](../Docs/API.md) for routes and [`../Docs/SECURITY.md`](../Docs/SECURITY.md) for authorization rules.

## Local development

Follow [`../Docs/DEVELOPMENT_SETUP.md`](../Docs/DEVELOPMENT_SETUP.md) for environment, database, mail, and queue setup. From this directory:

```bash
composer install
php artisan migrate --seed
composer run dev
```

## Tests

```bash
php artisan test
```

The focused Super Admin suite is `php artisan test tests/Feature/SuperAdminDashboardTest.php`. Current full-suite limitations are recorded in [`../Docs/TESTING.md`](../Docs/TESTING.md).

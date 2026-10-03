# SPMS — Claude Code Bootstrap Prompt
# Copy this entire prompt into Claude Code in your terminal

---

You are building a Strategy & Performance Management System (SPMS) using:
- Laravel 11 + MySQL + Vue 3 + Inertia.js + Tailwind CSS

## Step 1 — Create Laravel project

```bash
composer create-project laravel/laravel spms
cd spms
```

## Step 2 — Install packages

```bash
composer require laravel/breeze inertiajs/inertia-laravel tightenco/ziggy
composer require barryvdh/laravel-dompdf spatie/laravel-permission
npm install @inertiajs/vue3 @vitejs/plugin-vue vue
npm install apexcharts vue3-apexcharts
```

## Step 3 — Install Breeze with Vue + Inertia

```bash
php artisan breeze:install vue
```

Upgrade Tailwind to v4 and configure Vite:

```bash
npm install -D tailwindcss@latest @tailwindcss/vite
```

In `vite.config.js`, import `tailwindcss` from `@tailwindcss/vite` and add
`tailwindcss()` to the Vite plugins. In `resources/css/app.css`, replace the
`@tailwind` directives with:

```css
@import 'tailwindcss';
```

## Step 4 — Configure .env

Set the following in .env:
```
APP_NAME="SPMS"
APP_MODE=standalone
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spms
DB_USERNAME=root
DB_PASSWORD=
```

## Step 5 — Create the database

```bash
mysql -u root -e "CREATE DATABASE spms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Step 6 — Create all migration files

Create these files in database/migrations/ with the exact content provided below.

### Migration 1: companies
[PASTE CONTENT OF: 2025_01_01_000001_create_companies_table.php]

### Migration 2: users  
[PASTE CONTENT OF: 2025_01_01_000002_create_users_table.php]

### Migration 3: strategy house
[PASTE CONTENT OF: 2025_01_01_000003_create_strategy_house_tables.php]

### Migration 4: departments + objectives
[PASTE CONTENT OF: 2025_01_01_000004_create_departments_and_objectives_tables.php]

### Migration 5: KPIs
[PASTE CONTENT OF: 2025_01_01_000005_create_kpis_tables.php]

### Migration 6: projects + initiatives
[PASTE CONTENT OF: 2025_01_01_000006_create_projects_initiatives_tables.php]

### Migration 7: corrective + SaaS tables
[PASTE CONTENT OF: 2025_01_01_000007_create_corrective_saas_tables.php]

## Step 7 — Run migrations

```bash
php artisan migrate
```

## Step 8 — Copy all model files

Copy the model PHP files into app/Models/ splitting each class into its own file:
- Company.php
- User.php (extend existing)
- StrategyHouse.php
- CoreValue.php
- BscPerspective.php
- StrategicObjective.php
- Department.php
- Kpi.php
- KpiTarget.php
- KpiEntry.php
- Project.php
- Initiative.php
- ExecutionPlanTask.php
- CorrectiveProposal.php
- PerformanceSnapshot.php

## Step 9 — Copy seeder and run

```bash
# Copy DatabaseSeeder.php to database/seeders/
php artisan db:seed
```

## Step 10 — Build frontend assets

```bash
npm run dev
```

## Step 11 — Serve

```bash
php artisan serve
# Visit http://localhost:8000
# Login: admin@spms.test / password
```

---

## What to build next after scaffold is working:

1. CompanyController — multi-tenant scoping middleware
2. DashboardController — aggregate KPI scores for executive view
3. KpiController — CRUD with target entry
4. KpiEntryController — monthly actual value logging
5. Vue pages: Dashboard.vue, KpiIndex.vue, KpiShow.vue, KpiEntry.vue

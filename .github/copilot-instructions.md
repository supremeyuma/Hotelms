# AI Copilot Instructions for Hotel Management System

## Project Overview
Hotel Management System (HotelMS) is a Laravel 12 web application using:
- **Backend**: Laravel Framework (^12.0), PHP ^8.2
- **Frontend**: Vite + Tailwind CSS v4
- **Database**: SQLite (development), configurable via `config/database.php`
- **Testing**: PHPUnit 11.5+ with Feature and Unit test suites
- **Build System**: Composer + npm with concurrent dev server

**Key Purpose**: Hotel management operations (bookings, guests, rooms, staff management)

## Architecture & Key Files

### Folder Structure
- `app/Models/` - Eloquent ORM models (e.g., User.php)
- `app/Http/Controllers/` - Route handlers (add new controllers here)
- `app/Providers/` - Service providers for dependency injection
- `database/migrations/` - Schema changes (numbered: `XXXX_01_01_XXXXXX_*.php`)
- `database/seeders/` - Database seed data (DatabaseSeeder.php is entry point)
- `resources/views/` - Blade templates (e.g., welcome.blade.php)
- `routes/web.php` - Web route definitions
- `tests/Feature/` & `tests/Unit/` - Test files (same namespace structure as `app/`)

### Service Registration
- `app/Providers/AppServiceProvider.php` - Register bindings in `register()`, bootstrap logic in `boot()`
- `config/*.php` - Configuration files (app name, database, mail, cache, auth, etc.)

## Critical Workflows

### Local Development
```bash
# Full setup (PHP, npm, migrations, build)
composer run setup

# Concurrent dev server: Laravel + queue listener + logs + Vite
composer run dev
# Serves on http://localhost:8000 with hot module reload

# Individual commands
php artisan serve                    # Start Laravel (port 8000)
php artisan queue:listen --tries=1   # Process jobs locally
npm run dev                          # Start Vite dev server
npm run build                        # Production build (resources/css/js → public/)
```

### Database & Migrations
```bash
# Run all pending migrations (fresh database)
php artisan migrate

# Create new migration (generates `database/migrations/` file)
php artisan make:migration create_<table>_table

# Rollback last batch and re-run
php artisan migrate:refresh

# Seed test data
php artisan db:seed
```

### Testing
```bash
# Run ALL tests (Feature + Unit) with cache clear
composer run test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run with coverage
php artisan test --coverage

# PHPUnit config: phpunit.xml uses SQLite :memory: in testing env
```

### Code Quality
- **PHP Linting/Formatting**: Laravel Pint (`./vendor/bin/pint`)
- **Real-time Logs**: `php artisan pail` (Pail v1.2.2)
- **Debugging**: Laravel Tinker REPL (`php artisan tinker`)

## Project-Specific Patterns

### Model & Eloquent Usage
- **User Model** (`app/Models/User.php`): Base authenticatable class with `HasFactory`, `Notifiable`
- **Mass Assignment**: Always define `$fillable` array on models
- **Hidden Attributes**: Set `$protected $hidden` for passwords, tokens
- **Type Casting**: Use `casts()` method for datetime and password hashing (not magic `$casts` array)
```php
protected function casts(): array {
    return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
}
```

### Database Migrations
- Use **named migrations** (not timestamps): `0001_01_01_000000_create_users_table.php`
- Return migration class as anonymous: `return new class extends Migration { ... }`
- Use `Schema::create()` for new tables, always define up/down methods

### Routing
- Web routes in `routes/web.php` (no API routes currently)
- Use `Route::get()`, `Route::post()`, etc. with closures or controllers
- Example: `Route::get('/', function () { return view('welcome'); });`

### Views & Frontend
- **Blade Templates** in `resources/views/` (e.g., `welcome.blade.php`)
- **CSS**: `resources/css/app.css` compiled via Tailwind v4
- **JavaScript**: `resources/js/app.js` (with Axios for HTTP requests, bootstrap.js)
- **Vite Integration**: Automatically injects built assets; use `@vite()` in views

### Factory & Seeding
- **Factories**: `database/factories/UserFactory.php` uses `HasFactory` trait
- **Seeders**: Extend `Seeder`, call models via factory in `run()` method
- Entry point: `database/seeders/DatabaseSeeder.php`

## Common Conventions

### Namespace & Autoloading
- **PSR-4 Autoload**:
  - `App\` → `app/`
  - `Database\Factories\` → `database/factories/`
  - `Database\Seeders\` → `database/seeders/`
  - `Tests\` → `tests/`
- Always match class namespace to directory structure

### Testing Structure
- **Feature Tests**: Test HTTP routes & integration (`tests/Feature/`)
- **Unit Tests**: Test individual classes (`tests/Unit/`)
- Inherit from `Tests\TestCase` (not `PHPUnit\Framework\TestCase`)
- Use SQLite in-memory DB for speed (configured in `phpunit.xml`)

### Environment Configuration
- **`.env` Variables**: Copy `.env.example` → `.env`; set `APP_KEY` via `php artisan key:generate`
- **Config Files**: Database, mail, queue, cache settings in `config/`
- Never commit `.env`; use `.env.example` for template

## Integration Points

### Service Container & Dependency Injection
- Register services in `AppServiceProvider::register()`
- Inject dependencies via constructor in controllers/commands
- Use `app()` helper or type-hint in method signatures

### Queue System
- Jobs processed via `php artisan queue:listen` during dev
- Configure queue driver in `config/queue.php` (default: sync for testing)

### Mail & Notifications
- Configure in `config/mail.php` (array driver for testing)
- Use `Notification` facade or model's `notify()` method

## Key Dependencies
- **laravel/framework**: Core framework
- **laravel/tinker**: REPL for development
- **laravel/pail**: Real-time log viewer
- **laravel/pint**: Code formatter
- **tailwindcss**: Utility-first CSS framework v4
- **vite**: Module bundler for frontend assets
- **mockery**, **phpunit**: Testing libraries

## Quick Debug Tips
1. Enable `APP_DEBUG=true` in `.env` to see detailed error pages
2. Use `dd()` (dump & die) in code to inspect variables
3. Run `php artisan tinker` to test code interactively
4. Check `storage/logs/laravel.log` for application errors
5. Use `php artisan pail` to stream logs in real-time

---
**Last Updated**: December 2025 | Laravel 12 | PHP 8.2

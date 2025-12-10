# AI Copilot Instructions for Hotel Management System

## Project Overview
Hotel Management System (HotelMS) is a Laravel 12 + Inertia.js + Vue 3 web application using:
- **Backend**: Laravel Framework (^12.0), PHP ^8.2
- **Frontend**: Inertia.js + Vue 3 + Vite + Tailwind CSS v4
- **Database**: SQLite (development), configurable via `config/database.php`
- **Testing**: PHPUnit 11.5+ with Feature and Unit test suites
- **Build System**: Composer + npm with concurrent dev server
- **Routing**: Multi-layer routes (public, staff, admin, API) with role-based middleware

**Key Purpose**: Complete hotel operations system with room bookings, guest management, room service ordering, staff workflows, inventory, maintenance, and comprehensive admin dashboards

## Architecture & Key Files

### Folder Structure
- `app/Models/` - Eloquent ORM models (User, Room, Booking, Order, Staff, etc.)
- `app/Http/Controllers/` - Root controllers (PublicController, BookingController, OrderController, RoomServiceController)
- `app/Http/Controllers/Admin/` - Admin module (RoomController, StaffController, InventoryController, ReportController, etc.)
- `app/Http/Controllers/Staff/` - Staff portal (StaffDashboardController, StaffActionController, StaffOrderController)
- `app/Http/Controllers/Auth/` - Authentication (Breeze-generated)
- `app/Http/Controllers/Api/` - API endpoints (RoomApiController, BookingApiController)
- `app/Services/` - Business logic (BookingService, OrderService, InventoryService, AuditLoggerService, etc.)
- `app/Policies/` - Authorization policies (RoomPolicy, BookingPolicy, StaffPolicy, etc.)
- `app/Providers/` - Service providers for dependency injection
- `database/migrations/` - Schema (timestamped format, not numbered)
- `database/seeders/` - Database seed data
- `resources/js/Pages/` - Vue 3 pages for Inertia.js (Admin/, Staff/, Public/)
- `resources/css/` - Tailwind CSS (app.css)
- `routes/web.php` - Primary web routes (public, room service, staff, admin, orders)
- `routes/auth.php` - Authentication routes (login, register, password reset)
- `routes/api.php` - API routes (JSON endpoints)
- `tests/Feature/` & `tests/Unit/` - Test files

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

# Create new migration (generates `database/migrations/` file with timestamp)
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

## Routing Architecture

### Route File Organization
- `routes/web.php` - Main application routes (public, room service, staff, admin, orders)
- `routes/auth.php` - Authentication flows (login, register, password reset)
- `routes/api.php` - API endpoints for mobile/integrations

### Key Route Groups & Middleware
```php
// Public routes - No auth required
Route::get('/', [PublicController::class, 'home'])->name('home');

// Room Service - QR-based entry via signed URLs
Route::prefix('/room/{room}/service')->group(function () { ... })

// Staff Portal - Requires auth + role:staff|manager|md
Route::middleware(['auth', 'role:staff|manager|md'])->prefix('staff')->group(function () { ... })

// Admin - Requires auth + role:manager|md
Route::middleware(['auth', 'role:manager|md'])->prefix('admin')->group(function () { ... })

// Order workflows - Room service operations
Route::post('/order', [OrderController::class, 'store'])->name('order.store');
Route::get('/orders/{department}', [OrderController::class, 'queueByDepartment']);
```

### Route Naming Conventions
- Public pages: `home`, `rooms.index`, `rooms.show`, `gallery`, `contact`
- Room service: `room.service`, `room.service.kitchen`, `room.service.laundry`
- Staff pages: `staff.dashboard`, `staff.quick-action`, `staff.orders.queue`, `staff.profile`
- Admin resources: `admin.rooms.index/create/edit`, `admin.staff.*`, `admin.inventory.*`
- Orders: `order.store`, `order.status.update`, `orders.kitchen`, `orders.laundry`
- Auth: `login`, `register`, `password.request`, `verification.notice`

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
- **Inertia.js Integration**: Controllers return `Inertia::render('Component/Path', ['props' => $data])`
  - Vue components in `resources/js/Pages/Admin/`, `Staff/`, `Public/`
  - Props passed from Laravel controller are reactive in Vue
- **CSS**: `resources/css/app.css` compiled via Tailwind v4
- **JavaScript**: `resources/js/app.js` (Inertia + Ziggy router + Axios)
- **Vite Integration**: `vite.config.js` configured with Inertia resolver
  - Entry point: `['resources/css/app.css', 'resources/js/app.js']`
  - Auto-inject via `@vite()` in Blade

### Factory & Seeding
- **Factories**: `database/factories/UserFactory.php` uses `HasFactory` trait
- **Seeders**: Extend `Seeder`, call models via factory in `run()` method
- Entry point: `database/seeders/DatabaseSeeder.php`

### Inertia.js + Vue 3 Integration
- **Controller Response Pattern**: `return Inertia::render('Page/Component', ['prop' => $data]);`
  - Component path maps to `resources/js/Pages/Page/Component.vue`
  - Props become reactive Vue component data
- **Route + Component Mapping**: Route names must match Vue page navigation
  - Use `route()` helper in controllers to generate URLs
  - Use `route('name')` in Vue via Ziggy for URL generation
- **Setup in `resources/js/app.js`**: 
  - `createInertiaApp()` initializes the app
  - `setup()` function creates Vue app, registers plugins, mounts to DOM
  - Plugins: `plugin` (Inertia), `ZiggyVue` (route helpers)
- **Middleware**: Staff/Admin routes use `role:staff|manager|md` and `role:manager|md`
  - Enforced in controller `__construct()` via `$this->middleware()`
  - Or via route middleware group

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

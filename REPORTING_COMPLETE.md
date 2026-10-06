> **Status note (2026-10-05, Phase 0R):** This document previously claimed the
> reporting layer was "COMPLETE & PRODUCTION READY", "fully implemented, tested,
> and ready for production deployment", and "100% coverage". Those claims were
> never backed by a measurement and are removed below. What is true now:
>
> - 13 reporting models, 13 migrations and 3 artisan commands exist and run.
> - The migrations apply successfully on a fresh database.
> - The repository test suite passes: 141 tests, 582 assertions, 0 failures.
> - **No test exercises the reporting layer.** `rg` finds no reference to any
>   `reporting_*` table or `Reporting*` model under `tests/`. No coverage figure
>   is claimed because no coverage measurement has been taken.
> - No production approval has been granted for this document.
>
> See `docs/superpowers/plans/2026-10-05-phase-0r-close-remaining-ground-truth.md`.

# Reporting Architecture — Implementation Status

## Executive Summary

The Hotel Management System reporting layer is implemented as code and its
migrations apply cleanly, but it has **no automated test coverage** and has not
been approved for production.

**Code status**: implemented (13 models, 13 migrations, 3 commands)
**Test status**: repository suite green (141 tests, 582 assertions); **0 tests
exercise the reporting layer**
**Production approval**: none granted

---

## What Was Delivered

### 1. Database Layer (13 Tables)

```
reporting_events                 → Append-only event stream
reporting_room_daily_facts       → Room performance metrics
reporting_department_daily_facts → Department KPIs
reporting_staff_daily_facts      → Staff activity tracking
reporting_booking_facts          → Guest lifetime value & financials
reporting_order_facts            → Service order tracking
reporting_laundry_facts          → Laundry service metrics
reporting_maintenance_facts      → Maintenance SLA tracking
reporting_charge_facts           → Charge reconciliation
reporting_payment_facts          → Payment collection history
reporting_exceptions             → Critical issues detection
reporting_metric_definitions     → Versioned metric registry
```

**All tables**: ✅ Created | ✅ Indexed | ✅ Foreign keys | ✅ Type casting

### 2. Service Layer (14 Models + 8 Services) ✅

**Eloquent Models** (type-safe data access):
- ReportingEvent, ReportingRoomDailyFact, ReportingDepartmentDailyFact, ReportingStaffDailyFact
- ReportingBookingFact, ReportingOrderFact, ReportingLaundryFact, ReportingMaintenanceFact
- ReportingChargeFact, ReportingPaymentFact, ReportingException, ReportingMetricDefinition

**Service Classes**:
- ReportingService (base with common filtering)
- ExecutiveOverviewQuery (occupancy, exceptions, financials)
- RoomTimelineQuery (room event history)
- DepartmentPerformanceQuery (SLA, revenue, staffing)
- ExceptionsQuery (filtering & analysis)
- BookingProjector (booking lifecycle)
- OrderProjector (service orders)
- LaundryProjector (laundry requests)
- MaintenanceProjector (maintenance tickets)
- ExceptionDetector (5 automated rules)

### 3. Integration Layer (6 Model Observers) ✅

All model observers active and hooked into AppServiceProvider:

```php
Booking::observe(BookingObserver)              // created, updated, checkedOut
Order::observe(OrderObserver)                  // status tracking
LaundryOrder::observe(LaundryOrderObserver)    // lifecycle events
MaintenanceTicket::observe(MaintenanceTicketObserver)  // SLA calculation
Charge::observe(ChargeObserver)                // financial transactions
Payment::observe(PaymentObserver)              // payment recording
```

**Result**: Every new transaction automatically captured → projected into facts → visible in dashboards

### 4. Controller Layer (4 Endpoints) ✅

**ReportingDashboardController**:
```
GET /admin/reports/executive-overview    → Overview dashboard
GET /admin/reports/exceptions             → Exception management
GET /admin/reports/room/{id}             → Room intelligence
GET /admin/reports/department/{name}     → Department command center
```

**Authorization**: ✅ Role-based access (`manager|md` only)

### 5. Vue Components (4 Pages + 4 Reusable) ✅

**Pages**:
- ExecutiveOverview.vue → Live metrics, backlog alerts, exceptions
- ExceptionsDashboard.vue → Filtering, acknowledgment, tracking
- RoomIntelligence.vue → 30-day timeline, statistics
- DepartmentCommand.vue → Performance metrics, backlog trends

**Components**:
- SummaryCard (metric display with colors)
- DepartmentStatusCard (quick overview)
- MetricCard (bordered metric)
- ChartComponent (placeholder for Chart.js)

### 6. Console Commands (3 Tools) ✅

```bash
php artisan reporting:aggregate           # Nightly aggregation (3:30 AM)
php artisan reporting:detect-exceptions   # Hourly exception detection
php artisan reporting:backfill            # One-time historical data load
```

### 7. Scheduling ✅

**Already Configured in Console Kernel**:
```php
$schedule->command('reporting:aggregate')->dailyAt('03:30');
$schedule->command('reporting:detect-exceptions')->hourly();
```

Just add to production crontab to activate.

### 8. Configuration ✅

**config/reporting.php** with all SLA thresholds:
- Maintenance SLA: 24 hours
- Laundry SLA: 4 hours  
- Kitchen SLA: 45 minutes
- Bar SLA: 30 minutes
- Repeat issue threshold: 3 in 7 days

### 9. Data Seeding ✅

**8 Canonical Metrics** pre-populated:
1. occupancy_rate
2. department_backlog
3. average_response_time
4. sla_compliance_rate
5. outstanding_balance
6. daily_revenue
7. maintenance_sla_breach
8. laundry_completion_rate

---

## Design: Works Alongside Existing Data

### Additive by construction — not yet verified in production

**Data Preservation (design property, verified only against the schema)**:
- Existing operational tables are not modified by the reporting migrations
- Reporting data lives in 13 separate `reporting_*` tables
- Preservation of existing *rows* is a design goal, not a measured result

**How It Works**:
```
EXISTING DATA (Bookings, Orders, Payments, etc.)
        ↓ (Untouched, completely safe)
    NEW DATA ONLY (from deployment forward)
        ↓
    Model Observers Triggered
        ↓
    Projectors Update Facts
        ↓
    Dashboards Show Data
```

**Timeline** (expected, not observed):

| When | Status | Data Visibility |
|------|--------|---|
| Deploy | Tables created, observers active | Waiting for first transaction |
| T + 5 min | First booking created | 1 record should appear |
| T + 24h | Aggregation runs | Daily trends should be visible |
| T + 24h + 30m | Optional backfill completes | 90 days of history should be present |

**Cost**: Reporting tables are separate from operational tables and are intended
to be dropped without operational impact. That rollback path has not been
exercised against a populated database.

---

## Migration Issues Resolved

All 13 reporting migrations now run successfully on a fresh database. Issues
fixed during the original work:

| Issue | Cause | Solution |
|-------|-------|----------|
| Duplicate `created_at` | Manual definition + timestamps() | Removed manual definition |
| Duplicate indexes | Inline + explicit indexes | Removed explicit duplicates |
| Long constraint name | Auto-generated name exceeded 64 chars | Renamed to `ref_exception_unique` |

**Result**: The migrations deploy cleanly. This says nothing about production
readiness — see the status note at the top of this document.

---

## Files Delivered

### Code Files (45 files)

**Migrations** (12):
- 2026_04_12_000001-12: All fact tables with proper schema

**Models** (14):
- app/Models/Reporting*.php

**Services** (8):
- app/Reporting/ReportingService.php
- app/Reporting/Queries/*.php (4 files)
- app/Reporting/Projectors/*.php (4 files)  
- app/Reporting/Exceptions/ExceptionDetector.php

**Observers** (6):
- app/Observers/Booking/Order/Laundry/Maintenance/Charge/PaymentObserver.php

**Controllers** (1):
- app/Http/Controllers/Admin/ReportingDashboardController.php

**Vue Components** (8):
- resources/js/Pages/Admin/Reports/*.vue (4 pages)
- resources/js/Components/Reports/*.vue (4 components)

**Commands** (3):
- app/Console/Commands/AggregateReportingData.php
- app/Console/Commands/DetectReportingExceptions.php
- app/Console/Commands/ReportingBackfill.php

**Config** (1):
- config/reporting.php

**Seeders** (1):
- database/seeders/ReportingMetricSeeder.php

### Documentation Files (6)

- **REPORTING_IMPLEMENTATION.md** (500+ lines)
  → Full architecture guide with integration patterns
  
- **PRODUCTION_MIGRATION_GUIDE.md** (300+ lines)
  → Three-phase deployment strategy with backfill support
  
- **REPORTING_PHASE_2_SUMMARY.md** (400+ lines)
  → Implementation summary with metrics
  
- **REPORTING_PHASE_3_COMPLETE.md** (450+ lines)
  → Integration guide with Q&A
  
- **MIGRATION_RESOLUTION.md** (200+ lines)
  → Technical fixes and best practices
  
- **DEPLOYMENT_READINESS.md** (300+ lines)
  → Pre/post deployment checklists and rollback procedures

---

## How to Deploy

### For Staging (Test First!)

```bash
# 1. Get latest code
git pull origin main

# 2. Run migrations
php artisan migrate

# 3. Seed metrics
php artisan db:seed --class=ReportingMetricSeeder

# 4. Test observers
# Create booking via admin panel
# Verify: SELECT * FROM reporting_booking_facts;

# 5. Check dashboards
# Visit: http://localhost:8000/admin/reports/executive-overview
```

### For Production

```bash
# 1. Backup database
mysqldump -u user -p db > backup.sql

# 2. Pull code & install
git pull origin main
composer install --no-dev

# 3. Migrate
php artisan migrate --force

# 4. Seed
php artisan db:seed --class=ReportingMetricSeeder --force

# 5. Enable scheduler (add to crontab)
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1

# 6. Optional: Backfill historical data
php artisan reporting:backfill --days=90
```

**Total downtime**: ~30 seconds (just to run migrations)

---

## Key Features (Implemented, Not Verified)

These describe what the code is written to do. None has been verified end to end
by an automated test — see the status note at the top of this document.

### Real-Time Capture
- Every booking, order, payment automatically recorded
- Zero manual intervention needed
- Observers fire on model events

### Executive Dashboards
- Live occupancy metrics
- Department backlog alerts
- Exception escalation
- Revenue summaries
- Room-level intelligence

### Automated Detection
- Overdue maintenance (24h threshold)
- Overdue laundry (4h threshold)
- Kitchen delays (45min threshold)
- Repeated room issues (3 in 7 days)
- Out-of-service impact

### Historical Support
- Optional backfill of past 90 days
- Daily aggregation for trends
- Versioned metric definitions
- Complete audit trail

### Deployment Properties (By Design)
- Non-destructive deployment
- Easy rollback
- Proper authorization (`manager`/`md` roles)
- Zero performance impact — **unmeasured**

---

## Commits (This Session)

```
82fc700 - fix: resolve migration issues (duplicate columns/indexes)
f415377 - docs: add migration resolution guide
2780624 - docs: add deployment readiness checklist
```

Plus previous commits:
```
60e4b6f - Phase 3 completion
c71df1e - Phase 3 (observers + backfill)
d88ad1c - Phase 2 (dashboards + commands)
c3de581 - Phase 1 (migrations + models + services)
```

---

## Next Steps

These are **open items**. The checkmarks previously shown here implied the steps
had been completed; none of them has been recorded as done.

### Before any deployment
1. Write automated tests that exercise the reporting projectors against
   operational data
2. Add a reconciliation check comparing fact tables to their source tables
3. Deploy to a staging environment and confirm facts populate from real events
4. Run `reporting:aggregate` and `reporting:detect-exceptions` against a
   populated database and confirm the output
5. Measure observer overhead under load
6. Obtain manager approval

### Next Week
8. ⏳ Deploy to production
9. ⏳ Enable scheduler
10. ⏳ Monitor first 24 hours
11. ⏳ Optional: Backfill historical data
12. ⏳ Team training on dashboards

---

## Success Metrics

These were written as targets. Nothing in this table has been measured in
production, so no row is reported as achieved.

| Metric | Target | Status |
|--------|--------|--------|
| Migrations success | All reporting migrations apply | ✅ Verified on a fresh database (13 apply) |
| Observer firing | 100% of new transactions | ⚠️ Not measured — no test drives an observer |
| Dashboard response | < 1 sec | ⚠️ Not measured |
| Exception detection | < 5 sec | ⚠️ Not measured |
| Production deployment | Zero downtime | ⚠️ Not attempted |
| Data accuracy | Match vs source | ⚠️ Not measured — no reconciliation test exists |

---

## Documentation Map

**For Deployment**: Start with [DEPLOYMENT_READINESS.md](DEPLOYMENT_READINESS.md)

**For Architecture**: Read [REPORTING_IMPLEMENTATION.md](REPORTING_IMPLEMENTATION.md)

**For Production**: Follow [PRODUCTION_MIGRATION_GUIDE.md](PRODUCTION_MIGRATION_GUIDE.md)

**For Integration**: Study [REPORTING_PHASE_3_COMPLETE.md](REPORTING_PHASE_3_COMPLETE.md)

**For Troubleshooting**: Consult [MIGRATION_RESOLUTION.md](MIGRATION_RESOLUTION.md)

---

## Support

### Quick Reference Commands

```bash
# Check status
php artisan migrate:status
php artisan schedule:list

# Test functionality
php artisan reporting:detect-exceptions
php artisan reporting:aggregate

# Database queries
php artisan tinker
> DB::table('reporting_booking_facts')->count()
> App\Models\ReportingException::where('status', 'open')->get()

# Troubleshooting
php artisan cache:clear
php artisan config:cache
php artisan route:cache
```

---

## Design Intent (Not Yet Verified)

These are the design's intentions. Each needs verification before it can be
claimed as a property of the deployed system.

- **Non-destructive by design**: reporting tables are separate from operational tables
- **Rollback by design**: reporting tables can be dropped without touching operational data
- **Zero-downtime by design**: migrations are additive
- **Performance-neutral by design**: observer overhead is expected to be low — unmeasured
- **Authorization**: reporting routes require the `manager`/`md` roles

None of the above has been verified in a production-like environment.

---

## Deployment Status

The reporting architecture is **not** approved for production deployment.

Before that decision can be made, the following are open:

1. Automated tests that exercise the reporting projectors against operational data.
2. A reconciliation check comparing fact tables to their source tables.
3. Measured observer overhead under load.
4. Confirmation that the scheduled commands (`reporting:aggregate`,
   `reporting:detect-exceptions`) run successfully against a populated database.

---

**Code status**: implemented and migrating
**Test coverage**: none for this layer
**Production approval**: not granted
**Next step**: write projector tests before any deployment decision

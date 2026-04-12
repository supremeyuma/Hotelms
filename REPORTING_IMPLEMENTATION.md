# Reporting Architecture Implementation Guide

## Overview

The reporting architecture has been implemented to provide production-grade oversight for absentee leadership. This document explains the structure, components, and usage patterns.

## Architecture Components

### 1. Database Layer

#### Reporting Tables

- **reporting_events** - Append-only event stream for all hotel operations
- **reporting_room_daily_facts** - Daily operational metrics per room
- **reporting_department_daily_facts** - Daily performance metrics per department
- **reporting_staff_daily_facts** - Daily activity metrics per staff member
- **reporting_booking_facts** - Booking lifecycle and financial metrics
- **reporting_order_facts** - Service order (kitchen/bar) performance
- **reporting_laundry_facts** - Laundry request tracking and SLA
- **reporting_maintenance_facts** - Maintenance ticket tracking and resolution
- **reporting_charge_facts** - Posted charges with reconciliation
- **reporting_payment_facts** - Payment collection tracking
- **reporting_exceptions** - Critical issues requiring leadership attention
- **reporting_metric_definitions** - Versioned metric definitions

### 2. Service Layer

#### Query Objects (`app/Reporting/Queries/`)

- **ExecutiveOverviewQuery** - Executive dashboard metrics
  - Occupancy data
  - Department status
  - Exceptions summary
  - Financial pulse

- **RoomTimelineQuery** - Room-level event history
  - Chronological event feed
  - Department involvement
  - Financial impact

- **DepartmentPerformanceQuery** - Department-level analytics
  - Backlog trends
  - SLA performance
  - Revenue/cost impact
  - Staffing metrics

- **ExceptionsQuery** - Exception filtering and analysis
  - By severity, status, department
  - Aging exceptions
  - Summary statistics

#### Projectors (`app/Reporting/Projectors/`)

Projectors transform transactional events into reporting facts. Each projector handles denormalization and event recording:

- **BookingProjector** - Booking lifecycle events
- **MaintenanceProjector** - Maintenance ticket tracking
- **OrderProjector** - Service order tracking
- **LaundryProjector** - Laundry request tracking

**Usage Pattern:**
```php
// When a booking is created/updated
BookingProjector::project($booking);

// On status change
BookingProjector::projectOnStatusChange($booking, $oldStatus);

// For financial transactions
BookingProjector::projectFinancialTransaction($booking, 'payment_received', $amount);
```

#### Exception Detection (`app/Reporting/Exceptions/ExceptionDetector`)

Automated detection of critical exceptions:

- Overdue maintenance tickets
- Overdue laundry orders
- Kitchen orders beyond SLA
- Repeated room issues
- Out-of-service maintenance impact

**Usage:**
```php
// Run all detections
ExceptionDetector::runAllDetections();

// Or individual types
ExceptionDetector::detectOverdueMaintenanceTickets();
ExceptionDetector::detectRepeatedRoomIssues($roomId);
```

### 3. Controllers & Routes

#### ReportingDashboardController

**Routes:**
```
GET  /admin/reports/executive-overview    → executiveOverview()
GET  /admin/reports/exceptions             → exceptions()
GET  /admin/reports/room/{room}            → roomIntelligence()
GET  /admin/reports/department/{dept}      → departmentCommand()
```

**Authorization:** `role:manager|md` only

### 4. Vue Components

**Location:** `resources/js/Pages/Admin/Reports/` and `resources/js/Components/Reports/`

- **ExecutiveOverview.vue** - Main executive dashboard
- **ExceptionsDashboard.vue** - Exception management
- **SummaryCard.vue** - Reusable metric card
- **DepartmentStatusCard.vue** - Department status card

## Data Flow

### 1. Event Recording

When important actions occur, events are recorded:

```php
// Example: When order is placed
$order->update(['status' => 'pending']);

OrderProjector::projectOnStatusChange($order, $oldStatus);
// → Creates ReportingEvent
// → Updates ReportingOrderFact
```

### 2. Daily Fact Aggregation

Daily facts are calculated from events (typically done via queue job):

```php
// Compute daily facts for a room
$facts = ReportingRoomDailyFact::updateOrCreate(
    ['room_id' => $room->id, 'date' => today()],
    [
        'occupied' => $isOccupied,
        'housekeeping_completed' => $cleaned,
        'maintenance_issue_count' => $issues,
        // ... etc
    ]
);
```

### 3. Exception Detection

Real-time or scheduled exception detection:

```php
// Scheduled in console/Kernel.php
$schedule->call(function () {
    ExceptionDetector::runAllDetections();
})->hourly();
```

### 4. Query and Report Generation

Leadership accesses reports through dashboard:

```php
// Controller fetches data
$query = new ExecutiveOverviewQuery();
$overview = $query->getTodayOverview();
// → Returns dashboard data structure
```

## Configuration

**File:** `config/reporting.php`

Key settings:

```php
'maintenance_sla_hours' => 24,      // Maintenance must start within 24 hours
'laundry_sla_hours' => 4,           // Laundry must complete within 4 hours
'kitchen_sla_minutes' => 45,        // Kitchen orders within 45 minutes
'bar_sla_minutes' => 30,            // Bar orders within 30 minutes
'repeat_issue_threshold' => 3,      // Alert after 3 issues in 7 days
```

Customize via `.env`:

```
MAINTENANCE_SLA_HOURS=24
LAUNDRY_SLA_HOURS=4
KITCHEN_SLA_MINUTES=45
```

## Integration Checklist

### For Existing Models

Add projector calls to observers or model events:

```php
// app/Observers/BookingObserver.php
public function updated(Booking $booking)
{
    BookingProjector::project($booking);
    
    if ($booking->wasChanged('status')) {
        BookingProjector::projectOnStatusChange(
            $booking, 
            $booking->getOriginal('status')
        );
    }
}
```

Or in service classes:

```php
// app/Services/BookingService.php
public function checkout(Booking $booking)
{
    // ... checkout logic
    BookingProjector::project($booking);
}
```

### Scheduled Jobs

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run exception detection hourly
    $schedule->call(function () {
        ExceptionDetector::runAllDetections();
    })->hourly();
    
    // Aggregate daily facts nightly
    $schedule->call(function () {
        // Generate daily facts for improved dashboard performance
    })->daily()->at('23:55');
}
```

### Authorization

All reporting routes are protected by `role:manager|md` middleware.

For department-specific reports, extend with department authorization checks.

## Querying the Reporting Data

### Executive Overview

```php
$query = new ExecutiveOverviewQuery();
$overview = $query->getTodayOverview();

// Returns:
// [
//     'occupancy' => [...],
//     'arrivals_departures' => [...],
//     'department_status' => [...],
//     'exceptions' => [...],
//     'financial_pulse' => [...],
//     'backlog_alerts' => [...]
// ]
```

### Room Timeline

```php
$timeline = new RoomTimelineQuery($roomId);
$events = $timeline
    ->forDateRange($startDate, $endDate)
    ->withContext()
    ->get();
```

### Department Performance

```php
$dept = new DepartmentPerformanceQuery('kitchen');
$performance = $dept
    ->forDateRange($startDate, $endDate)
    ->getSLAPerformance();

// Returns SLA rates, response times, etc.
```

### Exceptions

```php
$exceptions = new ExceptionsQuery();
$criticals = $exceptions
    ->getCriticalOnly()
    ->getOpenOnly()
    ->orderBy('detected_at', 'desc')
    ->paginate(20);
```

## Metric Definitions

Metric definitions are stored in `reporting_metric_definitions` and can be versioned:

```php
$definition = ReportingMetricDefinition::getActiveByKey('occupancy_rate');

// Usage in calculations
$metric = new OccupancyRateCalculator($definition);
$rate = $metric->calculate($startDate, $endDate);
```

## Testing

### Assertions

```php
$this->assertDatabaseHas('reporting_room_daily_facts', [
    'room_id' => $room->id,
    'date' => today(),
    'occupied' => true,
]);

$this->assertDatabaseHas('reporting_exceptions', [
    'exception_type' => 'maintenance_overdue',
    'severity' => 'high',
    'status' => 'open',
]);
```

### Test Metrics

```php
public function test_occupancy_rate_calculation()
{
    $definition = ReportingMetricDefinition::getActiveByKey('occupancy_rate');
    $this->assertTrue($definition->is_tested);
}
```

## Performance Considerations

1. **Indexing**: All fact tables include strategic indexes on query columns
2. **Partitioning**: Consider partitioning by date for large deployments
3. **Archival**: Archive old reporting data after configured days
4. **Materialized Views**: For expensive queries, consider materialized views
5. **Caching**: Dashboard queries cache results for 5 minutes

## Next Steps

1. **Initialize Database**: Run migrations
   ```bash
   php artisan migrate
   php artisan db:seed --class=ReportingMetricSeeder
   ```

2. **Add Observers**: Attach projectors to existing models via observers

3. **Schedule Jobs**: Configure exception detection in scheduler

4. **Customize SLAs**: Update config/reporting.php with hotel's actual SLAs

5. **Build Dashboards**: Extend Vue components for specific use cases

6. **Monitor Performance**: Watch query performance in slow-query logs

## API Reference

### ReportingService Base Class

```php
class ReportingService
{
    public function filterByDateRange($startDate, $endDate)
    public function filterByDepartment($department)
    public function filterByRoom($roomId)
    public function filterByBooking($bookingId)
    public function filterByStatus($status)
    public function orderBy($column, $direction = 'asc')
    public function get()
    public function paginate($perPage = 15)
    public function count()
}
```

### ReportingException Methods

```php
ReportingException::createFromReference($type, $id, $exceptionType, $severity, $title, $description);
$exception->markForEscalation($userId, $reason);
$exception->acknowledge($userId);
$exception->resolve($notes);
```

## Support & Troubleshooting

### Dashboard not showing data
- Verify migrations ran: `php artisan migrate:status`
- Check if projectors are being called after model events
- Verify authorization: user must have `manager` or `md` role

### Exceptions not detected
- Ensure scheduler is running: `php artisan schedule:work`
- Check `config/reporting.php` SLA thresholds
- Verify tickets/orders meet exception criteria

### Slow queries
- Check indexes are created: `SHOW INDEX FROM reporting_*`
- Use `EXPLAIN` to analyze slow queries
- Consider aggregating older data separately

---

**Last Updated**: April 2026 | Version 1.0

# Reporting Architecture Blueprint

## Purpose

This document defines a production-grade reporting architecture for the hotel management system.

The reporting system must let leadership users such as `admin`, `manager`, `md`, and `superuser` understand what is happening across the hotel even when they are not physically present. It must answer:

- What is happening in the hotel right now?
- What happened in each room today and over time?
- What is happening in each operational department?
- What has been completed, delayed, escalated, refunded, cancelled, or left unattended?
- What is the financial and operational impact of those activities?

This architecture is designed for:

- operational visibility
- room-level oversight
- department-level accountability
- executive decision support
- auditability and post-incident review
- scalable report generation and exports

## Product Intent

The reporting system is not just a collection of export pages.

It is an oversight platform for absentee leadership. A manager or owner who is off-site should still be able to open the system and quickly understand:

- occupancy and guest movement
- room activity and unresolved issues
- department workload and bottlenecks
- staff actions and service completion quality
- billing, collections, and outstanding balances
- unusual activity requiring intervention

## Core Design Principles

1. One source of truth per metric.
2. Operational reporting and financial reporting must be separated but connected.
3. Every important hotel action should become reportable.
4. Room history must be reconstructable as a timeline.
5. Department performance must be measurable by queue, SLA, throughput, exceptions, and revenue or cost impact where applicable.
6. Reports must support drill-down from summary card to exact underlying records.
7. Leadership dashboards must show both current state and trend.
8. Every derived metric must be traceable back to raw records.
9. Large reports should be generated from optimized reporting tables, not expensive ad hoc transactional queries.
10. Reporting definitions must be explicit, versioned, and testable.

## Target User Roles

### Executive oversight roles

- `md`
- `superuser`
- `manager`
- selected `admin` users

These users need broad visibility across all departments, rooms, finances, service operations, and exceptions.

### Functional leadership roles

- accountant
- front desk lead
- housekeeping lead
- maintenance lead
- kitchen lead
- bar lead
- laundry lead
- inventory lead
- HR or operations admin

These users need department-specific reports with limited drill-down into related records.

## Reporting Outcomes

The new architecture should deliver these outcomes.

### 1. Hotel-wide oversight

Leadership should see:

- live hotel status
- room occupancy and room state
- guest activity and service load
- department queue pressure
- open issues and escalations
- revenue, collections, and outstanding balances
- key anomalies and risks

### 2. Room-level visibility

Every room should have a reportable operational history including:

- booking lifecycle
- check-in and check-out activity
- room status changes
- guest requests
- kitchen orders
- bar orders
- laundry requests and status changes
- housekeeping actions
- maintenance issues
- charges, payments, refunds, and adjustments
- incidents and notes

### 3. Department-level accountability

Each department should be measurable on:

- work received
- work completed
- work delayed
- backlog
- average response time
- average completion time
- cancellations
- refunds or reversals
- staff workload
- revenue or cost where relevant

### 4. Audit and investigation support

A leadership user should be able to answer:

- who handled this request
- when status changed
- what was charged
- what was paid
- why an issue stayed open
- which rooms had repeat service failures
- which department is missing SLAs

## Reporting Domain Model

The reporting platform should be organized into six domains.

### 1. Executive Reporting

Cross-hotel oversight and exceptions.

Includes:

- hotel overview dashboard
- department overview dashboard
- room activity monitor
- incident and escalation monitor
- cross-department trend reporting

### 2. Room Intelligence

Room-specific operational history and performance.

Includes:

- room timeline
- room revenue summary
- room issue history
- room service consumption
- room downtime and maintenance burden
- room housekeeping and readiness performance

### 3. Operations Reporting

Departmental operational performance.

Includes:

- front desk reports
- housekeeping reports
- maintenance reports
- kitchen reports
- bar reports
- laundry reports
- inventory movement and stock risk reports

### 4. Financial Reporting

Commercial and accounting visibility.

Includes:

- charges report
- collections report
- outstanding balances
- daily revenue
- revenue by department
- refunds and write-offs
- P&L
- balance sheet
- accounting period reports

### 5. Staff Performance Reporting

Operational contribution and workload.

Includes:

- staff activity counts
- assignments completed
- ticket handling
- service speed
- reassignment and escalation patterns
- suspension and attendance-linked operational impact if supported later

### 6. Audit and Exception Reporting

Control, security, compliance, and incident review.

Includes:

- privileged actions
- manual overrides
- payment corrections
- discount usage
- stock adjustments
- unresolved tickets
- repeated complaints
- suspected process breaches

## Canonical Reporting Objects

The architecture should standardize around a small set of reportable objects.

### Operational event

A normalized record representing something that happened.

Examples:

- booking confirmed
- guest checked in
- room status changed
- order created
- order prepared
- laundry picked up
- laundry delivered
- housekeeping completed
- maintenance issue reported
- maintenance issue resolved
- stock transferred
- charge posted
- payment received
- refund issued

Suggested fields:

- `id`
- `occurred_at`
- `event_type`
- `domain`
- `department`
- `room_id`
- `booking_id`
- `guest_id`
- `staff_id`
- `actor_user_id`
- `reference_type`
- `reference_id`
- `status_before`
- `status_after`
- `amount`
- `currency`
- `meta_json`

### Report fact

A denormalized record optimized for analytics and tabular reporting.

Examples:

- booking fact
- room-day fact
- department-day fact
- order fact
- laundry-order fact
- maintenance-ticket fact
- payment fact
- charge fact

### Snapshot

A stored summary of state at a point in time.

Examples:

- hotel daily snapshot
- room daily snapshot
- department daily snapshot
- staff daily snapshot

## Data Architecture

Use a two-layer reporting model.

### Layer 1: transactional source tables

Existing domain tables remain the system of record.

Examples likely already present in this codebase:

- bookings
- rooms
- room types
- orders
- laundry orders
- maintenance tickets
- inventory movements
- charges
- payments
- journal entries
- users
- departments
- audit logs

These tables should not be overloaded with reporting-only logic.

### Layer 2: reporting layer

Create a reporting layer for fast, consistent, testable metrics.

Recommended tables:

- `reporting_events`
- `reporting_room_timelines`
- `reporting_room_daily_facts`
- `reporting_department_daily_facts`
- `reporting_staff_daily_facts`
- `reporting_booking_facts`
- `reporting_order_facts`
- `reporting_laundry_facts`
- `reporting_maintenance_facts`
- `reporting_inventory_daily_facts`
- `reporting_charge_facts`
- `reporting_payment_facts`
- `reporting_exceptions`
- `reporting_metric_definitions`

## Recommended Reporting Tables

### `reporting_events`

Purpose:

- append-only normalized event stream for hotel operations
- supports room timeline, department timeline, executive activity feed, and audits

Indexes:

- `occurred_at`
- `department, occurred_at`
- `room_id, occurred_at`
- `booking_id, occurred_at`
- `reference_type, reference_id`

### `reporting_room_daily_facts`

Grain:

- one row per room per date

Suggested metrics:

- occupied flag
- occupied hours or nights
- guest count
- booking count touching date
- housekeeping completed flag
- maintenance issue count
- open maintenance count end of day
- kitchen order count
- bar order count
- laundry request count
- charges posted
- payments received
- refunds
- room revenue
- out-of-service flag

Purpose:

- room-level oversight
- room trend reporting
- room profitability and burden analysis

### `reporting_department_daily_facts`

Grain:

- one row per department per date

Suggested metrics:

- requests_received
- requests_completed
- requests_cancelled
- requests_escalated
- backlog_open
- avg_response_minutes
- avg_completion_minutes
- sla_breaches
- revenue
- refunds
- cost_of_consumption if available

Supported departments:

- frontdesk
- housekeeping
- maintenance
- kitchen
- bar
- laundry
- inventory
- finance

### `reporting_staff_daily_facts`

Grain:

- one row per staff member per date per department

Suggested metrics:

- assignments_received
- assignments_completed
- assignments_reassigned
- avg_completion_minutes
- open_work_end_of_day
- escalations
- refunds_or_reversals_influenced
- charges_posted
- payments_collected

### `reporting_booking_facts`

Grain:

- one row per booking

Suggested metrics:

- booking source
- booking status lifecycle
- room nights
- guest count
- room revenue
- ancillary revenue
- total charges
- total payments
- outstanding balance
- check-in delay flag
- checkout delay flag
- complaints count
- service requests count

### `reporting_order_facts`

Grain:

- one row per order

Supports:

- kitchen
- bar
- room service orders if unified later

Suggested metrics:

- service_area
- created_at
- accepted_at
- prepared_at
- delivered_at
- cancelled_at
- room_id
- booking_id
- staff_owner_id
- amount
- payment_status
- completion_minutes
- delay_minutes
- was_refunded

### `reporting_laundry_facts`

Grain:

- one row per laundry order

Suggested metrics:

- pickup_at
- processing_started_at
- ready_at
- delivered_at
- payment_status
- item_count
- total_amount
- completion_minutes
- breach flag

### `reporting_maintenance_facts`

Grain:

- one row per maintenance ticket

Suggested metrics:

- reported_at
- assigned_at
- in_progress_at
- resolved_at
- room_id
- category
- severity
- assigned_staff_id
- status
- response_minutes
- resolution_minutes
- reopen_count
- downtime_impact
- escalation_flag

### `reporting_charge_facts` and `reporting_payment_facts`

Purpose:

- clean financial operational reporting without mixing definitions

Rules:

- charges report what was posted
- payments report what was collected
- revenue recognition report should come from accounting logic, not booking creation alone

## Reporting Definitions

The architecture must formalize metric definitions.

Add a metric definition registry stored in code and optionally mirrored in `reporting_metric_definitions`.

Each metric should define:

- metric key
- business meaning
- source tables
- transformation rules
- exclusions
- time basis
- owner
- test coverage

Examples:

### `occupancy_rate`

- numerator: occupied room-nights or occupied rooms for selected period
- denominator: available room-nights or available rooms
- excludes out-of-service rooms when that behavior is approved
- owner: operations

### `collected_cash`

- source: successful payments
- time basis: payment timestamp
- excludes pending or failed payments
- owner: finance

### `recognized_revenue`

- source: journalized accounting entries or approved recognition rules
- not the same as booking total
- owner: finance

### `department_backlog`

- open work items not completed by end of selected period
- owner: department operations

## Canonical Hotel Dashboards

The architecture should support these primary screens.

### 1. Executive Overview Dashboard

Audience:

- `manager`
- `md`
- `superuser`
- executive admins

Primary cards:

- occupancy today
- arrivals today
- departures today
- active in-house guests
- open issues requiring attention
- unresolved maintenance
- laundry backlog
- kitchen pending orders
- bar pending orders
- outstanding balances
- cash collected today
- anomalies detected today

Main sections:

- live hotel pulse
- department scorecards
- room alerts
- financial pulse
- exception feed
- recent critical events

Required drill-downs:

- card click opens filtered report
- backlog card opens only open items
- anomaly card opens exception records

### 2. Room Intelligence Dashboard

Purpose:

- allow leadership to inspect any room as a mini business unit

For each room show:

- current status
- current guest or latest booking
- housekeeping readiness
- maintenance status
- service orders today
- charges and payments
- unresolved issues
- timeline of room activity
- revenue over time
- complaint frequency

Key tabs:

- summary
- timeline
- finance
- service history
- incidents

### 3. Department Command Dashboard

One per department:

- front desk
- housekeeping
- maintenance
- kitchen
- bar
- laundry
- inventory

For each department show:

- queue size
- open work
- overdue work
- completion rate
- average response time
- average turnaround time
- staff load
- escalations
- revenue or cost effect

### 4. Finance Oversight Dashboard

Show separate views for:

- posted charges
- collected payments
- outstanding balances
- refunds and reversals
- daily revenue
- departmental revenue
- accounting summaries

Important rule:

never combine booking-created totals, posted charges, received cash, and recognized revenue into one unlabeled metric.

### 5. Exceptions Dashboard

This is critical for absentee leadership.

Show:

- orders pending beyond SLA
- laundry orders stuck in one state too long
- maintenance tickets unresolved too long
- rooms with repeated issues
- discounts above threshold
- payments without matching charges
- charges without booking or room links
- repeated guest complaints
- inventory adjustments beyond threshold
- rooms marked available with unresolved maintenance

## Room Timeline Design

The room timeline is one of the most important outputs in this architecture.

For each room, leadership should see a chronological feed of:

- booking created
- booking confirmed
- guest checked in
- guest checked out
- room status changed
- charge posted
- payment received
- kitchen order placed and delivered
- bar order placed and delivered
- laundry pickup and delivery
- housekeeping task completed
- maintenance issue opened and resolved
- note added
- incident recorded

Timeline event requirements:

- exact timestamp
- actor
- department
- event label
- room reference
- booking reference where applicable
- amount where applicable
- status change details
- direct link to original record

## Department Reporting Requirements

### Front Desk

Need visibility into:

- arrivals and departures
- check-in and checkout delays
- walk-ins
- room assignment issues
- pending balances
- guest complaints
- unresolved guest requests

### Housekeeping

Need visibility into:

- rooms cleaned
- rooms awaiting cleaning
- room turnaround times
- missed cleanings
- repeat cleaning requests
- inventory consumption per room or shift

### Maintenance

Need visibility into:

- open tickets
- severity
- room impact
- response time
- resolution time
- repeated issues by room
- technician workload
- unresolved aging tickets

### Kitchen

Need visibility into:

- orders received
- pending orders
- prep time
- delivery time
- cancellations
- refunds
- top-selling items
- delayed rooms

### Bar

Need visibility into:

- orders received
- pending service
- average service time
- revenue by shift
- unpaid orders
- room-level consumption

### Laundry

Need visibility into:

- orders received
- pickup delays
- processing delays
- delivery delays
- unpaid orders
- item volume
- repeat issues
- image-backed dispute cases

### Inventory

Need visibility into:

- stock movement
- transfer volume
- abnormal adjustments
- low-stock risk
- department consumption
- shrinkage indicators

## Reporting API Architecture

Create a dedicated reporting module in the backend.

Suggested structure:

- `App\\Reporting\\Definitions`
- `App\\Reporting\\Queries`
- `App\\Reporting\\Projectors`
- `App\\Reporting\\Snapshots`
- `App\\Reporting\\Exports`
- `App\\Reporting\\Metrics`
- `App\\Reporting\\Exceptions`

Recommended concepts:

### Projectors

Transform transactional events into reporting facts and snapshots.

Examples:

- `BookingProjector`
- `RoomTimelineProjector`
- `DepartmentDailyProjector`
- `MaintenanceProjector`
- `LaundryProjector`
- `RevenueProjector`

### Query objects

Encapsulate report retrieval logic.

Examples:

- `ExecutiveOverviewQuery`
- `RoomTimelineQuery`
- `DepartmentPerformanceQuery`
- `MaintenanceAgingQuery`
- `ServiceDelayQuery`
- `RoomRevenueQuery`

### Metric calculators

Keep derived metric logic isolated and tested.

Examples:

- `OccupancyRateCalculator`
- `CollectionRateCalculator`
- `AverageFulfillmentTimeCalculator`
- `BacklogCalculator`

## Ingestion Strategy

Reporting data should be updated through a combination of synchronous writes and queued projections.

### Real-time updates

Use for:

- executive live dashboard
- room timeline
- active department queue counts
- open exceptions

Events should be emitted when core actions happen.

Examples:

- booking status changed
- order status changed
- laundry status changed
- maintenance status changed
- charge posted
- payment succeeded
- room status changed

### Near-real-time projections

Use queue jobs to update fact tables and daily aggregates.

Examples:

- update room daily facts
- update department daily facts
- recompute booking outstanding balance fact
- recompute room burden score

### Scheduled snapshots

Run scheduled commands for:

- daily hotel snapshot
- daily department snapshot
- daily room snapshot
- aging recomputation
- anomaly detection

## Performance Strategy

The system should not rely on expensive dashboard queries across raw tables for every page load.

Use:

- reporting fact tables
- append-only event storage
- nightly or hourly snapshots
- indexed filters
- pre-aggregated chart series
- export jobs for large datasets

For long exports:

- queue export generation
- store result file
- notify user when ready
- record export audit trail

## Exception Detection Layer

A strong reporting platform for absentee leadership needs automated exception detection.

Introduce `reporting_exceptions` with severity and ownership.

Suggested exception types:

- maintenance ticket overdue
- laundry order overdue
- kitchen order delayed
- bar order delayed
- room with repeated issues in 7 or 30 days
- high-value discount applied
- charge without room link
- payment mismatch
- room marked clean but maintenance unresolved
- room occupied but checkout not completed correctly
- unusual inventory adjustment

Suggested fields:

- `id`
- `exception_type`
- `severity`
- `department`
- `room_id`
- `booking_id`
- `reference_type`
- `reference_id`
- `detected_at`
- `status`
- `assigned_to_user_id`
- `resolved_at`
- `meta_json`

## UI Information Architecture

Recommended navigation:

- `Reports`
- `Reports / Executive`
- `Reports / Rooms`
- `Reports / Departments`
- `Reports / Finance`
- `Reports / Staff`
- `Reports / Exceptions`

Recommended report page structure:

1. summary cards
2. trend charts
3. exception highlights
4. filter bar
5. main report table
6. drill-down drawer or linked detail page

All summary cards representing actionable data must be clickable and open the filtered underlying records.

## Filters and Drill-Down Standards

Every report should support a consistent filter model where relevant.

Standard filters:

- date range
- hotel or property
- department
- room
- room type
- booking
- staff
- status
- severity
- payment status
- shift

Drill-down rules:

- executive card to filtered report
- department card to queue table
- room anomaly to room timeline
- revenue total to posted charge and payment details
- staff metric to handled record list

## Security and Access Control

Reporting access must follow least privilege.

Rules:

- executives can view all report domains
- department heads can view their own department plus related room records where justified
- accountants can view finance reports and relevant drill-downs
- sensitive reports should log access and exports

Sensitive data requiring careful treatment:

- payment details
- guest contact data
- staff disciplinary or suspension-related data
- adjustment and override logs

## Audit Requirements

The reporting system itself must be auditable.

Log:

- report viewed
- report exported
- filters used for export
- generated file metadata
- scheduled report recipients
- exception status updates

## Testing Strategy

Production-grade reporting must be tested at the definition level, not only the UI level.

### Required test layers

- projector tests
- metric calculator tests
- query tests
- authorization tests
- export tests
- anomaly detection tests

### Critical business-rule tests

- occupancy counts behave correctly on check-in and check-out boundaries
- unresolved maintenance affects room health correctly
- revenue definitions do not mix bookings, charges, payments, and journalized revenue
- overdue queues are detected correctly
- room timeline events appear in correct chronological order
- drill-down filters return the same records used for summary totals

## Rollout Strategy

Do not replace everything at once.

Recommended phased rollout:

### Phase 1: foundations

- define metric dictionary
- create reporting module structure
- create `reporting_events`
- create room, department, and finance fact tables
- implement shared filters and report response contracts

### Phase 2: executive oversight

- build executive dashboard
- build exceptions dashboard
- build room timeline and room intelligence screen

### Phase 3: department migration

- maintenance reports
- laundry reports
- kitchen reports
- bar reports
- housekeeping reports
- inventory reports
- front desk operational reports

### Phase 4: finance hardening

- split charges, collections, and recognized revenue clearly
- reconcile reporting with accounting sources
- upgrade exports and scheduled report delivery

### Phase 5: advanced reporting

- scheduled email reports
- saved filter presets
- anomaly notifications
- benchmark and target tracking

## Initial Recommended MVP Scope

If implementation must be staged carefully, the highest-value first slice is:

1. executive overview dashboard
2. exceptions dashboard
3. room timeline
4. maintenance reporting
5. laundry reporting
6. kitchen and bar performance reporting
7. outstanding balances and collections reporting

This scope best supports absentee leadership because it shows:

- what is happening now
- where service is breaking down
- which rooms need attention
- which departments are overloaded
- where money is at risk

## Proposed Ownership

Business ownership should be explicit.

- operations owner: occupancy, room activity, department SLA metrics
- finance owner: charges, payments, revenue recognition, balances
- executive owner: exceptions, hotel overview, cross-department KPIs
- engineering owner: reporting infrastructure, projectors, definitions, exports, permissions

## Non-Negotiable Rules

1. No dashboard card without drill-down.
2. No metric without a written definition.
3. No mixing of operational revenue and accounting revenue.
4. No room oversight strategy without a room timeline.
5. No department reporting without backlog and SLA views.
6. No large executive report from raw unindexed transactional joins if a fact table is appropriate.
7. No silent data discrepancy between summary cards and report tables.

## Final Recommendation

The new reporting architecture should be built as a hotel oversight platform centered on:

- executive visibility
- room intelligence
- department accountability
- financial clarity
- exceptions and intervention

The most important design choice is to model the hotel as a stream of operational events projected into reporting facts and room or department snapshots. That approach gives leadership a reliable way to understand what is happening across rooms, bookings, departments, staff activity, and finances without being physically present on-site.

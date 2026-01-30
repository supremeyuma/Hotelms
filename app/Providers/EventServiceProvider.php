<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Accounting Events
        \App\Events\Accounting\PaymentProcessed::class => [
            \App\Listeners\Accounting\UpdateAccountsReceivable::class,
            \App\Listeners\Accounting\LogPaymentActivity::class,
        ],

        \App\Events\Accounting\JournalEntryPosted::class => [
            \App\Listeners\Accounting\UpdateAccountBalances::class,
            \App\Listeners\Accounting\ValidateDoubleEntry::class,
        ],

        \App\Events\Accounting\AccountingPeriodClosed::class => [
            \App\Listeners\Accounting\LockPeriodTransactions::class,
            \App\Listeners\Accounting\GeneratePeriodReports::class,
        ],

        \App\Events\Accounting\ChargeAdded::class => [
            \App\Listeners\Accounting\UpdateGuestBalance::class,
            \App\Listeners\Accounting\CreateRevenueEntry::class,
        ],

        \App\Events\Accounting\RefundProcessed::class => [
            \App\Listeners\Accounting\ProcessRefundAccounting::class,
            \App\Listeners\Accounting\LogRefundActivity::class,
        ],

        \App\Events\Accounting\TaxCalculated::class => [
            \App\Listeners\Accounting\RecordTaxLiability::class,
            \App\Listeners\Accounting\UpdateTaxPayable::class,
        ],

        // Booking Lifecycle Events
        \App\Events\Booking\BookingCreated::class => [
            \App\Listeners\Booking\SendBookingConfirmation::class,
            \App\Listeners\Booking\CreateInitialReservation::class,
        ],

        \App\Events\Booking\BookingCheckedIn::class => [
            \App\Listeners\Booking\ActivateRoomCharges::class,
            \App\Listeners\Booking\StartNightlyRevenue::class,
        ],

        \App\Events\Booking\BookingCheckedOut::class => [
            \App\Listeners\Booking\FinalizeRoomCharges::class,
            \App\Listeners\Booking\UpdateRoomStatus::class,
            \App\Listeners\Booking\GenerateFinalInvoice::class,
        ],

        \App\Events\Booking\BookingCancelled::class => [
            \App\Listeners\Booking\ProcessCancellation::class,
            \App\Listeners\Booking\HandleDeposits::class,
        ],

        // Operational Events
        \App\Events\Operational\RoomStatusChanged::class => [
            \App\Listeners\Operational\NotifyHousekeeping::class,
            \App\Listeners\Operational\UpdateRoomInventory::class,
        ],

        \App\Events\Operational\AuditLogCreated::class => [
            \App\Listeners\Operational\SendSecurityAlerts::class,
            \App\Listeners\Operational\UpdateComplianceLogs::class,
        ],

        // Service Events
        \App\Events\Service\ServiceRequestCreated::class => [
            \App\Listeners\Service\AssignServiceStaff::class,
            \App\Listeners\Service\NotifyDepartment::class,
        ],

        \App\Events\Service\ServiceCompleted::class => [
            \App\Listeners\Service\UpdateServiceRevenue::class,
            \App\Listeners\Service\RequestGuestFeedback::class,
        ],

        // Payment Events
        \App\Events\Payment\PaymentFailed::class => [
            \App\Listeners\Payment\NotifyPaymentFailure::class,
            \App\Listeners\Payment\RetryPaymentProcessing::class,
        ],

        \App\Events\Payment\DepositReceived::class => [
            \App\Listeners\Payment\ApplyDepositToBooking::class,
            \App\Listeners\Payment\UpdateDepositLiability::class,
        ],

        // Notification Events
        \App\Events\Notification\EmailNotificationSent::class => [
            \App\Listeners\Notification\LogEmailDelivery::class,
        ],

        \App\Events\Notification\SMSNotificationSent::class => [
            \App\Listeners\Notification\LogSMSDelivery::class,
        ],
    ];

    /**
     * The subscribers to register.
     *
     * @var array
     */
    protected $subscribe = [
        \App\Subscribers\AccountingSubscriber::class,
        \App\Subscribers\BookingSubscriber::class,
        \App\Subscribers\ReportingSubscriber::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Event::listen('accounting.*', function ($eventName, array $data) {
            \Log::channel('accounting')->info("Accounting event fired: {$eventName}", $data);
        });

        Event::listen('booking.*', function ($eventName, array $data) {
            \Log::channel('bookings')->info("Booking event fired: {$eventName}", $data);
        });

        Event::listen('payment.*', function ($eventName, array $data) {
            \Log::channel('payments')->info("Payment event fired: {$eventName}", $data);
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
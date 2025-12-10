<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\Notification;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\DB;

/**
 * NotificationService
 *
 * Send internal notifications (DB and email optionally) to staff or managers.
 */
class NotificationService
{
    protected AuditLoggerService $audit;

    public function __construct(AuditLoggerService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Notify a specific staff user.
     *
     * @param int|null $staffId
     * @param string $title
     * @param array $payload
     * @return void
     */
    public function notifyStaff(?int $staffId, string $title, array $payload = []): void
    {
        if (!$staffId) return;
        $user = User::find($staffId);
        if (!$user) return;

        Notification::send($user, new GenericNotification($title, $payload));
        $this->audit->log('notification_sent', 'User', $staffId, ['title' => $title, 'payload' => $payload]);
    }

    /**
     * Notify by department (role) - will notify all users with the role.
     *
     * @param string $department
     * @param string $title
     * @param array $payload
     * @return void
     */
    public function notifyDepartment(string $department, string $title, array $payload = []): void
    {
        // map department to spatie role names; simple mapping used
        $roleMap = [
            'kitchen' => 'staff',
            'laundry' => 'staff',
            'housekeeping' => 'staff',
            'maintenance' => 'staff',
            'managers' => 'manager',
        ];

        $roleName = $roleMap[$department] ?? 'staff';

        $users = User::role($roleName)->get();

        foreach ($users as $user) {
            Notification::send($user, new GenericNotification($title, $payload));
        }

        $this->audit->log('department_notification', 'Department', 0, ['department' => $department, 'title' => $title]);
    }

    /**
     * Notify all managers
     */
    public function notifyManagers(string $title, array $payload = []): void
    {
        $users = User::role('manager')->get();
        foreach ($users as $u) {
            Notification::send($u, new GenericNotification($title, $payload));
        }
        $this->audit->log('manager_notification', 'Department', 0, ['title' => $title]);
    }
}

<?php

namespace App\Services;

use App\Models\Company;
use App\Notifications\BillingActivityNotification;
use Illuminate\Support\Facades\Notification;

class BillingActivityNotifier
{
    public function notifyOwners(
        Company $company,
        string $subject,
        string $headline,
        array $details = []
    ): void {
        $recipients = $company->users()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->whereNotNull('users.email')
            ->get()
            ->unique(fn ($user) => strtolower((string) $user->email))
            ->values();

        $actionUrl = route('client-portal.billing.index', $company);
        $notification = new BillingActivityNotification(
            subject: $subject,
            companyName: $company->name,
            headline: $headline,
            details: $details,
            actionUrl: $actionUrl,
        );

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
            return;
        }

        if (filled($company->email)) {
            Notification::route('mail', $company->email)
                ->notify(new BillingActivityNotification(
                    subject: $subject,
                    companyName: $company->name,
                    headline: $headline,
                    details: $details,
                    actionUrl: $actionUrl,
                ));
        }
    }
}

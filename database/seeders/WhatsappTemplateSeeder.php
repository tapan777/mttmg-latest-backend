<?php

namespace Database\Seeders;

use App\Models\WhatsappTemplate;
use Illuminate\Database\Seeder;

class WhatsappTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name'            => 'registration_successful',
                'display_name'    => 'Registration Successful',
                'description'     => 'Sent to member when they successfully register at the gym.',
                'variables_count' => 4,
                'variable_labels' => ['Payment Received (₹)', 'Admission + Charges (₹)', 'Member Name', 'Next Due Date'],
                'used_in'         => 'Member Creation',
            ],
            [
                'name'            => 'payment_received_confirmation',
                'display_name'    => 'Payment Received Confirmation',
                'description'     => 'Sent when any payment is received from a member.',
                'variables_count' => 3,
                'variable_labels' => ['Member Name', 'Amount Paid (₹)', 'Package Name'],
                'used_in'         => 'Member Creation, Payment, Trainer Payment',
            ],
            [
                'name'            => 'membership_expire_info',
                'display_name'    => 'Membership Expiry Info',
                'description'     => 'Sent 1 or 3 days before membership expires as a reminder.',
                'variables_count' => 3,
                'variable_labels' => ['Member Name', 'Package Name', 'Expiry Date'],
                'used_in'         => 'Cron – Expiry Reminder (1 day, 3 days before)',
            ],
            [
                'name'            => 'renewal_reminder',
                'display_name'    => 'Renewal Reminder',
                'description'     => 'Sent 1 or 3 days before expiry urging member to renew.',
                'variables_count' => 3,
                'variable_labels' => ['Member Name', 'Package Name', 'Expiry Date'],
                'used_in'         => 'Cron – Renewal Reminder (1 day, 3 days before)',
            ],
            [
                'name'            => 'expire_membership',
                'display_name'    => 'Membership Expired',
                'description'     => 'Sent on expiry day and 3 / 7 days after expiry.',
                'variables_count' => 3,
                'variable_labels' => ['Member Name', 'Package Name', 'Expiry Date'],
                'used_in'         => 'Cron – Post-Expiry (day 0, day 3, day 7)',
            ],
            [
                'name'            => 'due_reminder_1',
                'display_name'    => 'Due Payment Reminder',
                'description'     => 'Sent daily to members who have a pending due amount.',
                'variables_count' => 2,
                'variable_labels' => ['Member Name', 'Due Amount (₹)'],
                'used_in'         => 'Cron – Due Reminder',
            ],
            [
                'name'            => 'due_reminder_pt',
                'display_name'    => 'PT Due Reminder',
                'description'     => 'Sent before PT package expiry reminding about due payment.',
                'variables_count' => 3,
                'variable_labels' => ['Member Name', 'Trainer Name', 'Expiry Date'],
                'used_in'         => 'Cron – PT Expiry Reminder',
            ],
            [
                'name'            => 'pt_package_expire_reminder',
                'display_name'    => 'PT Package Expiry Reminder',
                'description'     => 'Sent when PT package is about to expire or has expired.',
                'variables_count' => 3,
                'variable_labels' => ['Member Name', 'Trainer Name', 'Expiry Date'],
                'used_in'         => 'Cron – PT Expiry Reminder',
            ],
            [
                'name'            => 'personal_trainer_package',
                'display_name'    => 'Personal Trainer Package',
                'description'     => 'Sent when a member purchases a personal trainer package.',
                'variables_count' => 3,
                'variable_labels' => ['Member Name', 'Trainer Name', 'Package Amount (₹)'],
                'used_in'         => 'Trainer Payment Creation',
            ],
            [
                'name'            => 'member_renewal_payment_received',
                'display_name'    => 'Membership Renewal Payment Received',
                'description'     => 'Sent when a member renews their membership and payment is received.',
                'variables_count' => 3,
                'variable_labels' => ['Member Name', 'Package Name', 'Amount Paid (₹)'],
                'used_in'         => 'Payment – Renewal',
            ],
            [
                'name'            => 'offerr_letter',
                'display_name'    => 'Offer Letter',
                'description'     => 'Sent to employee when an offer letter is generated on creation.',
                'variables_count' => 2,
                'variable_labels' => ['Employee Name', 'Designation'],
                'used_in'         => 'Employee Creation',
            ],
        ];

        foreach ($templates as $template) {
            WhatsappTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}

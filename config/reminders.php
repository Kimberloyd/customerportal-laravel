<?php

return [
    'enabled' => env('ORDER_REMINDERS_ENABLED', false),
    'timezone' => env('ORDER_REMINDERS_TIMEZONE', 'Asia/Manila'),
    'quiet_hours_start' => (int) env('ORDER_REMINDERS_QUIET_HOURS_START', 20),
    'quiet_hours_end' => (int) env('ORDER_REMINDERS_QUIET_HOURS_END', 8),
    'customer_sms_enabled' => env('ORDER_REMINDERS_CUSTOMER_SMS_ENABLED', false),
    // Staff (agent/office/admin) recipients of a reminder -- separate from
    // the customer-facing flag above so these can be turned on independently.
    'agent_sms_enabled' => env('ORDER_REMINDERS_AGENT_SMS_ENABLED', false),
    'agent_email_enabled' => env('ORDER_REMINDERS_AGENT_EMAIL_ENABLED', false),
    'agent_messenger_enabled' => env('ORDER_REMINDERS_AGENT_MESSENGER_ENABLED', false),
    'rollout_grace_hours' => (int) env('ORDER_REMINDERS_ROLLOUT_GRACE_HOURS', 24),
    'thresholds' => [
        'awaiting_fulfillment' => ['reminder' => 24, 'escalation' => 72],
        'stalled_partial' => ['reminder' => 48, 'escalation' => 96],
        'awaiting_customer_close' => ['reminder' => 24, 'repeat' => 72, 'escalation' => 168],
        'return_review' => ['reminder' => 24, 'escalation' => 48],
        'return_receipt' => ['reminder' => 72, 'escalation' => 168],
    ],
];

<?php

return [
    /*
     * Routine account deletion keeps the record recoverable for this many
     * days. Confirm the value with counsel for each deployment jurisdiction.
     */
    'retention_days' => (int) env('ACCOUNT_DELETION_RETENTION_DAYS', 30),

    /*
     * Administrative deadline used to track formal data-subject requests.
     * This is configurable because the applicable legal deadline can vary.
     */
    'erasure_deadline_days' => (int) env('DATA_ERASURE_DEADLINE_DAYS', 30),
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IEP Intake Retention Policies
    |--------------------------------------------------------------------------
    |
    | These values control how long IEP intake records are retained based
    | on their status. Draft intakes are deleted after a shorter period,
    | while submitted intakes are retained longer for compliance.
    |
    */

    'retention' => [
        'draft' => env('IEP_RETENTION_DRAFT_DAYS', 90),
        'submitted' => env('IEP_RETENTION_SUBMITTED_DAYS', 365),
    ],
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IEP Intake Retention Periods
    |--------------------------------------------------------------------------
    |
    | These values determine how long draft and submitted IEP intakes are
    | retained before automatic deletion. All values are in days.
    |
    | Orvis is NOT a system of record. These retention periods ensure
    | temporary data does not persist beyond its useful lifecycle.
    |
    */

    'retention' => [
        // How long draft intakes are retained (days)
        'draft' => env('IEP_DRAFT_RETENTION_DAYS', 90),

        // How long submitted intakes are retained (days)
        'submitted' => env('IEP_SUBMITTED_RETENTION_DAYS', 180),
    ],

];

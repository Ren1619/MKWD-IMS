<?php

return [
    'ppe' => [
        /*
         * This identifier is snapshotted on every PPE record. Changing a policy
         * requires a new version so historical classifications stay explainable.
         */
        'policy_version' => '2026-01',
        'effective_from' => '2026-01-01',
        'capitalization_threshold' => '50000.00',
        'default_residual_value_percentage' => '5.00',
        'depreciation_method' => 'straight_line',
        'depreciation_start_rule' => 'first_half_month',
        'first_half_cutoff_day' => 15,
        'default_useful_life_months' => 60,
    ],

    'semi_expendable' => [
        'policy_version' => '2026-01',
        'effective_from' => '2026-01-01',
        'low_value_threshold' => '5000.00',
        'ics_renewal_years' => 3,
        'loss_reporting_deadline_days' => 30,
        'renewal_due_soon_days' => 90,
        'expiration_due_soon_days' => 90,
    ],

    'property_access' => [
        'oversight_positions' => [
            'PPE Chairman',
            'Supply Division Manager',
            'HR Department Manager',
            'HR Manager',
        ],
    ],
];

<?php

return [

    /**
     * Known franchise/corporate brand signals — businesses where the decision-maker
     * is not at practice level, making the sales cycle prohibitively long.
     */
    'franchise_signals' => [
        'portman',
        'mydentist',
        'bupa dental',
        'dental care alliance',
        'hsone',
        'dentalnode',
        'multiple location',
        'national coverage',
        'corporate booking',
        'group entity',
        'part of',
    ],

    'match_fields' => [
        'qualification_flags',
        'business_name',
        'website_url',
        'qualification_summary',
    ],

    'weakness_threshold_high' => 60,
    'weakness_threshold_strong' => 25,
    'high_review_count' => 500,

];

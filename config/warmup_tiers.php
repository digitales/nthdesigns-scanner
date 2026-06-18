<?php

return [
    'solo' => [
        'max_outreach_mailboxes' => 1,
        'max_seed_mailboxes' => 3,
        'pool_participation_allowed' => false,
        'send_window_customisation_allowed' => false,
        'weekend_volume_control_allowed' => false,
    ],
    'agency' => [
        'max_outreach_mailboxes' => 3,
        'max_seed_mailboxes' => 10,
        'pool_participation_allowed' => true,
        'send_window_customisation_allowed' => true,
        'weekend_volume_control_allowed' => true,
    ],
    'white_label' => [
        'max_outreach_mailboxes' => 5,
        'max_seed_mailboxes' => 20,
        'pool_participation_allowed' => true,
        'send_window_customisation_allowed' => true,
        'weekend_volume_control_allowed' => true,
    ],
];

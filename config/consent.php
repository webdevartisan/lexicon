<?php

declare(strict_types=1);

return [
    'cookie_name' => 'app_consent',
    'cookie_ttl_days' => 180,

    // Bump this when consent categories or vendors change to force re-prompting.
    'version' => 1,

    // Designed to be extensible; new categories can be added without core logic changes.
    'categories' => [
        'necessary' => true,
        'preferences' => false,
        'analytics' => false,
        'marketing' => false,
    ],
];

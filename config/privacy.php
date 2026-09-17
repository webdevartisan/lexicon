<?php

declare(strict_types=1);

return [
    // The shared account that reviews and submissions move to when their author's account is erased.
    'deleted_user_handle' => 'deleted-user',

    // Sign-up asks people to confirm this age. 16 is the highest digital age of consent in the EU.
    'minimum_age' => 16,

    // Self-requested account deletion locks the account out immediately, but
    // AccountErasureService does not run for this many days, giving a first
    // offense nobody has reported yet a window to still be caught.
    'erasure_grace_period_days' => 30,

    // How long personal data is kept before privacy:prune removes it. Days.
    'retention_days' => [
        'activity_log' => 365,
        'mail_queue' => 30,
        'answered_invitations' => 7,
        // SubscriptionConfirmMail promises the address is gone within a week.
        'unconfirmed_subscriptions' => 7,
        // Who an erased account used to be, kept only long enough to answer a late
        // abuse report or a legal request. Admin-only; never shown publicly.
        'account_erasure_records' => 90,
    ],
];

<?php

declare(strict_types=1);

return [
    // Which of Framework\Validation\Validator's password presets ('basic', 'medium', 'strong')
    // real forms enforce. Locally this stays 'basic'; set PASSWORD_POLICY_PRESET=strong in production.
    'password_policy_preset' => env('PASSWORD_POLICY_PRESET', 'basic'),

    // bcrypt ignores everything past 72 bytes, so a much longer limit would only look safer.
    'password_max_length' => 64,

    // UK NCSC list of the 100,000 passwords seen most in breaches. Compared case-insensitively.
    'common_passwords_file' => ROOT_PATH.'/resources/security/common-passwords.txt',
];

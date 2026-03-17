<?php

declare(strict_types=1);

/**
 * Validation edge cases for comprehensive testing.
 * We test boundary conditions and attack vectors.
 */
dataset('xss_payloads', [
    'script tag' => '<script>alert("XSS")</script>',
    'img onerror' => '<img src=x onerror=alert(1)>',
    'svg onload' => '<svg onload=alert(1)>',
    'iframe' => '<iframe src="javascript:alert(1)">',
    'encoded' => '&#60;script&#62;alert(1)&#60;/script&#62;',
]);

dataset('sql_injection_attempts', [
    'union select' => "' UNION SELECT * FROM users--",
    'drop table' => "'; DROP TABLE users;--",
    'comment bypass' => "admin'--",
    'boolean bypass' => "' OR '1'='1",
    'time based' => "'; WAITFOR DELAY '00:00:05'--",
]);

dataset('unicode_edge_cases', [
    'emoji' => '👨‍💻 Test User 🚀',
    'rtl override' => "\u{202E}Reversed",
    'zero width' => "Test\u{200B}User",
    'combining chars' => 'é́́́́',
]);

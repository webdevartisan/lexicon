<?php

dataset('cache_key_patterns', [
    'blog routes' => ['*:GET:/blogs*', ['user123:GET:/blogs', 'user456:GET:/blogs/popular']],
    'locale prefix' => ['en:*', ['en:GET:/home', 'en:GET:/about']],
    'session data' => ['session:*:data', ['session:123:data', 'session:456:data']],
]);

dataset('invalid_cache_keys', [
    'empty string' => [''],
    'null character' => ["test\0key"],
    'directory traversal' => ['../../etc/passwd'],
]);

dataset('cache_content_sizes', [
    'empty' => [''],
    'small' => ['content'],
    'medium' => [str_repeat('x', 1000)],
    'large' => [str_repeat('x', 100000)],
]);

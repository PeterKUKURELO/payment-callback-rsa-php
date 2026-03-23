<?php

declare(strict_types=1);

$configuredPath = getenv('PUBLIC_KEY_PATH') ?: 'src/Config/public.pem';

if (!preg_match('/^(?:[A-Za-z]:\\\\|\\\\\\\\|\\/)/', $configuredPath)) {
    $configuredPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath);
}

return [
    'public_key_path' => $configuredPath,
];

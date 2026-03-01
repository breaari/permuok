<?php
require __DIR__ . '/vendor/autoload.php';

function loadEnvVars(string $envPath): array {
    $envVars = [];
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            $envVars[$name] = $value;
        }
    }
    return $envVars;
}

$GLOBALS['env'] = loadEnvVars(__DIR__ . '/.env');

function env(string $key, $default = null) {
    return $GLOBALS['env'][$key] ?? $default;
}

error_reporting(E_ALL);
ini_set(
    'display_errors',
    env('APP_DEBUG', 'false') === 'true' ? '1' : '0'
);

<?php

if (!function_exists('load_project_env')) {
    function load_project_env(): void {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $projectRoot = dirname(__DIR__);
        $envFile = $projectRoot . '/.env';
        if (!file_exists($envFile)) {
            $loaded = true;
            return;
        }

        $content = file_get_contents($envFile);
        if ($content === false) {
            $loaded = true;
            return;
        }

        if (substr($content, 0, 2) === "\xFF\xFE") {
            $content = iconv('UTF-16LE', 'UTF-8', $content);
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }

        $loaded = true;
    }
}

if (!function_exists('app_env')) {
    function app_env(): string {
        load_project_env();
        return strtolower(getenv('APP_ENV') ?: 'local');
    }
}

if (!function_exists('is_production')) {
    function is_production(): bool {
        return app_env() === 'production';
    }
}

if (!function_exists('configure_error_handling')) {
    function configure_error_handling(): void {
        if (is_production()) {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            return;
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('log_errors', '1');
    }
}

if (!function_exists('apply_cors_headers')) {
    function apply_cors_headers(array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']): void {
        load_project_env();

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowlist = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:8000,http://127.0.0.1:8000')));

        if ($origin !== '' && in_array($origin, $allowlist, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

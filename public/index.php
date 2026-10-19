<?php

declare(strict_types=1);

// Carregamento de Autoload: suporta composer vendor ou autoloader nativo PSR-4
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Autoloader nativo PSR-4 para App\* mapeado para src/*
    spl_autoload_register(function (string $class) {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/../src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

use App\Config\Database;
use App\Utils\Response;

// Carrega variáveis do arquivo .env
Database::loadEnv(__DIR__ . '/../.env');

// ===================================================================
// Headers de Segurança e Configurações de CORS
// ===================================================================
$allowedOrigin = Database::getEnv('CORS_ALLOWED_ORIGIN', 'http://localhost:3000');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Permite requisições vindas do frontend configurado ou localhost em dev
if ($origin === $allowedOrigin || str_starts_with($origin, 'http://localhost:')) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    header("Access-Control-Allow-Origin: {$allowedOrigin}");
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

// Trata requisições de preflight OPTIONS do navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ===================================================================
// Servir arquivos estáticos para Swagger UI / Documentação OpenAPI
// ===================================================================
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (str_starts_with($requestUri, '/docs/')) {
    $filePath = __DIR__ . $requestUri;
    if (file_exists($filePath) && is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html; charset=UTF-8',
            'yaml' => 'text/yaml; charset=UTF-8',
            'yml'  => 'text/yaml; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'css'  => 'text/css; charset=UTF-8',
            'js'   => 'application/javascript; charset=UTF-8',
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'text/plain; charset=UTF-8'));
        readfile($filePath);
        exit;
    }
}

// ===================================================================
// Tratamento Global de Exceções e Inicialização de Rotas
// ===================================================================
try {
    /** @var \App\Routing\Router $router */
    $router = require __DIR__ . '/../routes/api.php';
    $router->dispatch();
} catch (\Throwable $e) {
    // Log interno do erro do servidor
    error_log('[LOGMOV_API_ERROR] ' . $e->getMessage() . "\n" . $e->getTraceAsString());

    Response::error('Erro interno no servidor: ' . $e->getMessage(), 500);
}

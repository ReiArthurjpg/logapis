<?php

declare(strict_types=1);

use App\Routing\Router;
use App\Controllers\AuthController;
use App\Middlewares\AuthMiddleware;
use App\Utils\Response;

$router = new Router();

// Rota de Teste e Status da API
$router->get('/api', function () {
    Response::success('LOGMOV Auth API ativa e operacional.', [
        'version'   => '1.0.0',
        'endpoints' => [
            'login'           => 'POST /api/login',
            'me'              => 'GET /api/me (Bearer Token required)',
            'forgot_password' => 'POST /api/forgot-password',
            'reset_password'  => 'POST /api/reset-password',
            'docs'            => 'GET /docs/index.html'
        ]
    ]);
});

// Autenticação / Login
$router->post('/api/login', [AuthController::class, 'login']);

// Endpoint protegido para obter dados da sessão atual
$router->get('/api/me', [AuthController::class, 'me'], [
    fn() => AuthMiddleware::handle()
]);

// Recuperação de Senha (Reset Token com validade de 30 minutos)
$router->post('/api/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/reset-password', [AuthController::class, 'resetPassword']);

return $router;

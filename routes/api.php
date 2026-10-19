<?php

declare(strict_types=1);

use App\Routing\Router;
use App\Controllers\AuthController;
use App\Controllers\EmpresaController;
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

// Endpoint protegido para Logout / Invalidação de Token
$router->post('/api/logout', [AuthController::class, 'logout'], [
    fn() => AuthMiddleware::handle()
]);

// ===================================================================
// Rotas de Empresas Parceiras (Protegidas por JWT)
// ===================================================================
$router->get('/api/empresas/stats', [EmpresaController::class, 'stats'], [
    fn() => AuthMiddleware::handle()
]);

$router->get('/api/empresas', [EmpresaController::class, 'index'], [
    fn() => AuthMiddleware::handle()
]);

$router->get('/api/empresas/{id}', [EmpresaController::class, 'show'], [
    fn() => AuthMiddleware::handle()
]);

$router->post('/api/empresas', [EmpresaController::class, 'store'], [
    fn() => AuthMiddleware::handle()
]);

$router->put('/api/empresas/{id}', [EmpresaController::class, 'update'], [
    fn() => AuthMiddleware::handle()
]);

$router->patch('/api/empresas/{id}/status', [EmpresaController::class, 'updateStatus'], [
    fn() => AuthMiddleware::handle()
]);

$router->delete('/api/empresas/{id}', [EmpresaController::class, 'destroy'], [
    fn() => AuthMiddleware::handle()
]);

// Recuperação de Senha (Reset Token com validade de 30 minutos)
$router->post('/api/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/reset-password', [AuthController::class, 'resetPassword']);

return $router;

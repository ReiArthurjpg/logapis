<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Config\Database;
use App\Utils\JwtService;
use App\Utils\Response;
use Exception;

class AuthMiddleware
{
    /**
     * Valida o Header Authorization: Bearer <token>
     * Retorna os dados do usuário contidos no payload ou encerra a requisição com 401 Unauthorized.
     *
     * @param array $allowedRoles Lista de papéis permitidos (ex: ['admin', 'empresa']). Se vazio, permite qualquer papel autenticado.
     * @return array Payload do token autenticado
     */
    public static function handle(array $allowedRoles = []): array
    {
        $headers = self::getRequestHeaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            Response::error('Token de autorização não fornecido ou inválido.', 401);
        }

        $token = trim($matches[1]);
        $secret = Database::getEnv('JWT_SECRET', 'super_secret_logmov_key_2026_change_in_production!#@');

        try {
            $payload = JwtService::decode($token, $secret);

            // Validação de papéis de permissão
            if (!empty($allowedRoles)) {
                $userRole = $payload['role'] ?? null;
                if (!in_array($userRole, $allowedRoles, true)) {
                    Response::error('Acesso negado: permissões insuficientes para este recurso.', 403);
                }
            }

            return $payload;
        } catch (Exception $e) {
            Response::error('Sessão expirada ou token inválido: ' . $e->getMessage(), 401);
        }

        return [];
    }

    private static function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ($headers !== false) {
                return $headers;
            }
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            } elseif ($name === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($name === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }

        // Em alguns servidores Apache/FastCGI o Authorization vem em REDIRECT_HTTP_AUTHORIZATION
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $headers;
    }
}

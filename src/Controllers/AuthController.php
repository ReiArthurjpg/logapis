<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Utils\JwtService;
use App\Utils\Response;
use PDO;
use Exception;
use DateTime;

class AuthController
{
    private PDO $db;
    private string $jwtSecret;
    private int $jwtExpiration;
    private int $resetExpirationMinutes;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->jwtSecret = Database::getEnv('JWT_SECRET', 'super_secret_logmov_key_2026_change_in_production!#@');
        $this->jwtExpiration = (int) Database::getEnv('JWT_EXPIRATION_TIME', '28800'); // 8h
        $this->resetExpirationMinutes = (int) Database::getEnv('RESET_TOKEN_EXPIRATION_MINUTES', '30'); // 30min
    }

    /**
     * Endpoint: POST /api/login
     * Autenticação de usuários (Admin, Empresa, Motoboy)
     */
    public function login(): void
    {
        $input = $this->getJsonInput();

        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        // Validação de entrada
        if (empty($email) || empty($password)) {
            Response::error('E-mail e senha são obrigatórios.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Formato de e-mail inválido.', 400);
        }

        // Busca o usuário no banco de dados via PDO com Prepared Statements
        $stmt = $this->db->prepare(
            'SELECT id, nome, email, senha, tipo, status FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Verificação segura de credenciais
        if (!$user || !password_verify($password, $user['senha'])) {
            Response::error('E-mail ou senha incorretos.', 401);
        }

        // Verificação de status da conta
        if ($user['status'] !== 'ativo') {
            Response::error('Sua conta está desativada ou pendente de aprovação. Contate o suporte.', 403);
        }

        // Geração do Payload JWT
        $issuedAt = time();
        $expireAt = $issuedAt + $this->jwtExpiration;

        $payload = [
            'iat'   => $issuedAt,
            'exp'   => $expireAt,
            'id'    => (int) $user['id'],
            'name'  => $user['nome'],
            'email' => $user['email'],
            'role'  => $user['tipo'],
        ];

        $token = JwtService::encode($payload, $this->jwtSecret);

        // Resposta formatada conforme solicitado
        Response::success('Login realizado com sucesso', [
            'token' => $token,
            'user'  => [
                'id'    => (int) $user['id'],
                'name'  => $user['nome'],
                'email' => $user['email'],
                'role'  => $user['tipo'],
            ]
        ], 200);
    }

    /**
     * Endpoint: GET /api/me
     * Retorna os dados do usuário autenticado no token Bearer
     */
    public function me(array $authUser): void
    {
        $stmt = $this->db->prepare(
            'SELECT id, nome, email, tipo, status, criado_em, atualizado_em FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $authUser['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Usuário não encontrado.', 404);
        }

        Response::success('Dados do usuário autenticado', [
            'id'         => (int) $user['id'],
            'name'       => $user['nome'],
            'email'      => $user['email'],
            'role'       => $user['tipo'],
            'status'     => $user['status'],
            'created_at' => $user['criado_em'],
            'updated_at' => $user['atualizado_em'],
        ], 200);
    }

    /**
     * Endpoint: POST /api/logout
     * Invalida o token JWT atual adicionando seu hash à blacklist até a sua data de expiração
     */
    public function logout(array $authUser): void
    {
        $rawToken = $authUser['raw_token'] ?? null;
        $exp = $authUser['exp'] ?? (time() + 28800);

        if ($rawToken) {
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', (int) $exp);

            $stmt = $this->db->prepare(
                'INSERT INTO token_blacklist (token_hash, expires_at) VALUES (:token_hash, :expires_at) ON DUPLICATE KEY UPDATE expires_at = :expires_at_dup'
            );
            $stmt->execute([
                ':token_hash'      => $tokenHash,
                ':expires_at'      => $expiresAt,
                ':expires_at_dup'  => $expiresAt,
            ]);
        }

        Response::success('Logout realizado com sucesso. Sessão encerrada.', null, 200);
    }

    /**
     * Endpoint: POST /api/forgot-password
     * Gera um token de redefinição de senha com validade estrita de 30 minutos
     */
    public function forgotPassword(): void
    {
        $input = $this->getJsonInput();
        $email = trim((string) ($input['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Informe um e-mail válido.', 400);
        }

        $stmt = $this->db->prepare('SELECT id, nome, status FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Para evitar enumeração de usuários, responde mensagem neutra mesmo se o e-mail não existir
        if (!$user || $user['status'] !== 'ativo') {
            Response::success('Se o e-mail estiver cadastrado e ativo, as instruções para redefinição foram enviadas.');
            return;
        }

        // Gera token criptograficamente seguro e define expiração de 30 minutos
        $resetToken = bin2hex(random_bytes(32)); // 64 caracteres
        $expiresAt = (new DateTime())
            ->modify("+{$this->resetExpirationMinutes} minutes")
            ->format('Y-m-d H:i:s');

        // Salva token e timestamp no banco
        $updateStmt = $this->db->prepare(
            'UPDATE usuarios SET reset_token = :token, reset_expires_at = :expires_at WHERE id = :id'
        );
        $updateStmt->execute([
            ':token'      => $resetToken,
            ':expires_at' => $expiresAt,
            ':id'         => $user['id'],
        ]);

        // Em ambiente de produção, este token seria enviado por e-mail com link do frontend.
        // Retornamos em 'data' para testes rápidos locais da API.
        Response::success('Instruções para redefinição de senha geradas com sucesso.', [
            'reset_token' => $resetToken,
            'expires_in'  => "{$this->resetExpirationMinutes} minutos",
            'expires_at'  => $expiresAt,
        ], 200);
    }

    /**
     * Endpoint: POST /api/reset-password
     * Valida o reset_token e redefine a senha do usuário
     */
    public function resetPassword(): void
    {
        $input = $this->getJsonInput();

        $token = trim((string) ($input['token'] ?? ''));
        $newPassword = (string) ($input['password'] ?? '');

        if (empty($token) || empty($newPassword)) {
            Response::error('Token e nova senha são obrigatórios.', 400);
        }

        if (strlen($newPassword) < 6) {
            Response::error('A nova senha deve ter no mínimo 6 caracteres.', 400);
        }

        // Busca o usuário pelo token de reset ativo e não expirado
        $stmt = $this->db->prepare(
            'SELECT id, email, reset_expires_at FROM usuarios WHERE reset_token = :token LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Token de redefinição inválido ou já utilizado.', 400);
        }

        // Valida se o tempo expirou (30 minutos)
        $now = new DateTime();
        $expiresAt = new DateTime($user['reset_expires_at']);

        if ($now > $expiresAt) {
            // Limpa o token expirado
            $clearStmt = $this->db->prepare('UPDATE usuarios SET reset_token = NULL, reset_expires_at = NULL WHERE id = :id');
            $clearStmt->execute([':id' => $user['id']]);

            Response::error('O token de redefinição expirou. Solicite um novo link.', 400);
        }

        // Gera novo hash Bcrypt e invalida o token utilizado
        $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $updateStmt = $this->db->prepare(
            'UPDATE usuarios SET senha = :senha, reset_token = NULL, reset_expires_at = NULL WHERE id = :id'
        );
        $updateStmt->execute([
            ':senha' => $newPasswordHash,
            ':id'    => $user['id'],
        ]);

        Response::success('Senha redefinida com sucesso! Você já pode realizar o login com suas novas credenciais.', null, 200);
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return $_POST ?? [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

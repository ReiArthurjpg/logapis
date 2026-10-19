<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use PDO;

class MotoboyController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Endpoint: GET /api/motoboys/stats
     * Retorna contadores agregados: total, ativos, pendentes, recusados
     */
    public function stats(array $context): void
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Ativo' THEN 1 ELSE 0 END) AS ativos,
                    SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) AS pendentes,
                    SUM(CASE WHEN status = 'Recusado' THEN 1 ELSE 0 END) AS recusados
                FROM motoboys";

        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();

        Response::success('Estatísticas de motoboys obtidas com sucesso.', [
            'total'     => (int) ($result['total'] ?? 0),
            'ativos'    => (int) ($result['ativos'] ?? 0),
            'pendentes' => (int) ($result['pendentes'] ?? 0),
            'recusados' => (int) ($result['recusados'] ?? 0),
        ]);
    }

    /**
     * Endpoint: GET /api/motoboys
     * Suporta ?search=termo (nome, email, mei, placa) e ?status=Ativo|Pendente|Recusado
     */
    public function index(array $context): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $sql = 'SELECT id, nome, email, mei, whatsapp, cnh, placa, cidade_uf, status, motivo_recusa, criado_em, atualizado_em
                FROM motoboys WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (nome LIKE :search_nome OR email LIKE :search_email OR mei LIKE :search_mei OR placa LIKE :search_placa)';
            $searchTerm = "%{$search}%";
            $params[':search_nome']  = $searchTerm;
            $params[':search_email'] = $searchTerm;
            $params[':search_mei']   = $searchTerm;
            $params[':search_placa'] = $searchTerm;
        }

        if ($status !== '' && in_array($status, ['Ativo', 'Pendente', 'Recusado'], true)) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY criado_em DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $motoboys = $stmt->fetchAll();

        $data = array_map(function ($item) {
            return [
                'id'           => (int) $item['id'],
                'nome'         => $item['nome'],
                'email'        => $item['email'],
                'mei'          => $item['mei'],
                'whatsapp'     => $item['whatsapp'],
                'cnh'          => $item['cnh'],
                'placa'        => $item['placa'],
                'cidadeUf'     => $item['cidade_uf'],
                'status'       => $item['status'],
                'motivoRecusa' => $item['motivo_recusa'],
                'dataCriacao'  => date('d/m/Y', strtotime($item['criado_em'])),
                'criadoEm'     => $item['criado_em'],
                'atualizadoEm' => $item['atualizado_em'],
            ];
        }, $motoboys);

        Response::success('Lista de motoboys obtida com sucesso.', $data);
    }

    /**
     * Endpoint: GET /api/motoboys/{id}
     * Detalhes de um único motoboy
     */
    public function show(array $context, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $stmt = $this->db->prepare(
            'SELECT id, nome, email, mei, whatsapp, cnh, placa, cidade_uf, status, motivo_recusa, criado_em, atualizado_em
             FROM motoboys WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();

        if (!$item) {
            Response::error('Motoboy não encontrado.', 404);
        }

        Response::success('Dados do motoboy obtidos com sucesso.', [
            'id'           => (int) $item['id'],
            'nome'         => $item['nome'],
            'email'        => $item['email'],
            'mei'          => $item['mei'],
            'whatsapp'     => $item['whatsapp'],
            'cnh'          => $item['cnh'],
            'placa'        => $item['placa'],
            'cidadeUf'     => $item['cidade_uf'],
            'status'       => $item['status'],
            'motivoRecusa' => $item['motivo_recusa'],
            'dataCriacao'  => date('d/m/Y', strtotime($item['criado_em'])),
            'criadoEm'     => $item['criado_em'],
            'atualizadoEm' => $item['atualizado_em'],
        ]);
    }

    /**
     * Endpoint: POST /api/motoboys
     * Cadastro de novo motoboy parceiro
     */
    public function store(array $context): void
    {
        $input = $this->getJsonInput();

        $nome      = trim((string) ($input['nome'] ?? ''));
        $email     = trim((string) ($input['email'] ?? ''));
        $senha     = (string) ($input['senha'] ?? '');
        $mei       = trim((string) ($input['mei'] ?? ''));
        $whatsapp  = trim((string) ($input['whatsapp'] ?? ''));
        $cnh       = trim((string) ($input['cnh'] ?? ''));
        $placa     = strtoupper(trim((string) ($input['placa'] ?? '')));
        $cidadeUf  = trim((string) ($input['cidade_uf'] ?? ($input['cidadeUf'] ?? '')));
        $status    = trim((string) ($input['status'] ?? 'Pendente'));

        // Validações de campos obrigatórios
        if ($nome === '') {
            Response::error('O campo nome completo do motoboy é obrigatório.', 422);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Informe um e-mail de acesso válido.', 422);
        }

        if (strlen($senha) < 4) {
            Response::error('A senha deve ter no mínimo 4 caracteres.', 422);
        }

        if ($mei === '') {
            Response::error('O campo MEI é obrigatório.', 422);
        }

        if ($whatsapp === '') {
            Response::error('O WhatsApp com DDD é obrigatório.', 422);
        }

        if (!in_array($status, ['Ativo', 'Pendente', 'Recusado'], true)) {
            $status = 'Pendente';
        }

        // Verifica unicidade de e-mail
        $stmtCheck = $this->db->prepare('SELECT id FROM motoboys WHERE email = :email LIMIT 1');
        $stmtCheck->execute([':email' => $email]);
        if ($stmtCheck->fetch()) {
            Response::error('Já existe um entregador cadastrado com este e-mail.', 409);
        }

        // Verifica unicidade de MEI
        $stmtCheckMei = $this->db->prepare('SELECT id FROM motoboys WHERE mei = :mei LIMIT 1');
        $stmtCheckMei->execute([':mei' => $mei]);
        if ($stmtCheckMei->fetch()) {
            Response::error('Já existe um entregador cadastrado com este MEI.', 409);
        }

        $senhaHash = password_hash($senha, PASSWORD_BCRYPT);

        $sql = 'INSERT INTO motoboys (nome, email, senha, mei, whatsapp, cnh, placa, cidade_uf, status)
                VALUES (:nome, :email, :senha, :mei, :whatsapp, :cnh, :placa, :cidade_uf, :status)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nome'      => $nome,
            ':email'     => $email,
            ':senha'     => $senhaHash,
            ':mei'       => $mei,
            ':whatsapp'  => $whatsapp,
            ':cnh'       => $cnh ?: null,
            ':placa'     => $placa ?: null,
            ':cidade_uf' => $cidadeUf ?: null,
            ':status'    => $status,
        ]);

        $id = (int) $this->db->lastInsertId();

        Response::success('Motoboy parceiro cadastrado com sucesso.', [
            'id'           => $id,
            'nome'         => $nome,
            'email'        => $email,
            'mei'          => $mei,
            'whatsapp'     => $whatsapp,
            'cnh'          => $cnh,
            'placa'        => $placa,
            'cidadeUf'     => $cidadeUf,
            'status'       => $status,
            'motivoRecusa' => null,
            'dataCriacao'  => date('d/m/Y'),
        ], 201);
    }

    /**
     * Endpoint: PUT /api/motoboys/{id}
     * Atualização completa dos dados do motoboy
     */
    public function update(array $context, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $input = $this->getJsonInput();

        $nome      = trim((string) ($input['nome'] ?? ''));
        $email     = trim((string) ($input['email'] ?? ''));
        $senha     = (string) ($input['senha'] ?? '');
        $mei       = trim((string) ($input['mei'] ?? ''));
        $whatsapp  = trim((string) ($input['whatsapp'] ?? ''));
        $cnh       = trim((string) ($input['cnh'] ?? ''));
        $placa     = strtoupper(trim((string) ($input['placa'] ?? '')));
        $cidadeUf  = trim((string) ($input['cidade_uf'] ?? ($input['cidadeUf'] ?? '')));

        if ($nome === '') {
            Response::error('O campo nome é obrigatório.', 422);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Informe um e-mail válido.', 422);
        }

        if ($mei === '') {
            Response::error('O campo MEI é obrigatório.', 422);
        }

        if ($whatsapp === '') {
            Response::error('O campo WhatsApp é obrigatório.', 422);
        }

        // Verifica existência do motoboy
        $stmtCheck = $this->db->prepare('SELECT id, senha FROM motoboys WHERE id = :id');
        $stmtCheck->execute([':id' => $id]);
        $current = $stmtCheck->fetch();

        if (!$current) {
            Response::error('Motoboy não encontrado.', 404);
        }

        // Verifica duplicidade de e-mail em outro ID
        $stmtEmail = $this->db->prepare('SELECT id FROM motoboys WHERE email = :email AND id != :id LIMIT 1');
        $stmtEmail->execute([':email' => $email, ':id' => $id]);
        if ($stmtEmail->fetch()) {
            Response::error('Este e-mail já está sendo utilizado por outro entregador.', 409);
        }

        // Verifica duplicidade de MEI em outro ID
        $stmtMei = $this->db->prepare('SELECT id FROM motoboys WHERE mei = :mei AND id != :id LIMIT 1');
        $stmtMei->execute([':mei' => $mei, ':id' => $id]);
        if ($stmtMei->fetch()) {
            Response::error('Este MEI já está sendo utilizado por outro entregador.', 409);
        }

        // Atualização de senha opcional
        if (trim($senha) !== '') {
            if (strlen($senha) < 4) {
                Response::error('A senha deve ter no mínimo 4 caracteres.', 422);
            }
            $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
            $sql = 'UPDATE motoboys 
                    SET nome = :nome, email = :email, senha = :senha, mei = :mei, whatsapp = :whatsapp, 
                        cnh = :cnh, placa = :placa, cidade_uf = :cidade_uf 
                    WHERE id = :id';
            $updateParams = [
                ':nome'      => $nome,
                ':email'     => $email,
                ':senha'     => $senhaHash,
                ':mei'       => $mei,
                ':whatsapp'  => $whatsapp,
                ':cnh'       => $cnh ?: null,
                ':placa'     => $placa ?: null,
                ':cidade_uf' => $cidadeUf ?: null,
                ':id'        => $id,
            ];
        } else {
            $sql = 'UPDATE motoboys 
                    SET nome = :nome, email = :email, mei = :mei, whatsapp = :whatsapp, 
                        cnh = :cnh, placa = :placa, cidade_uf = :cidade_uf 
                    WHERE id = :id';
            $updateParams = [
                ':nome'      => $nome,
                ':email'     => $email,
                ':mei'       => $mei,
                ':whatsapp'  => $whatsapp,
                ':cnh'       => $cnh ?: null,
                ':placa'     => $placa ?: null,
                ':cidade_uf' => $cidadeUf ?: null,
                ':id'        => $id,
            ];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($updateParams);

        Response::success('Dados do motoboy atualizados com sucesso.', [
            'id'       => $id,
            'nome'     => $nome,
            'email'    => $email,
            'mei'      => $mei,
            'whatsapp' => $whatsapp,
            'cnh'      => $cnh,
            'placa'    => $placa,
            'cidadeUf' => $cidadeUf,
        ], 200);
    }

    /**
     * Endpoint: PATCH /api/motoboys/{id}/status
     * Altera o status para 'Ativo', 'Pendente' ou 'Recusado'
     * Se for 'Recusado', motivo_recusa é obrigatório.
     */
    public function updateStatus(array $context, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $input = $this->getJsonInput();

        $status = trim((string) ($input['status'] ?? ''));
        $motivoRecusa = trim((string) ($input['motivo_recusa'] ?? ($input['motivoRecusa'] ?? '')));

        if (!in_array($status, ['Ativo', 'Pendente', 'Recusado'], true)) {
            Response::error('Status inválido. Escolha entre: Ativo, Pendente ou Recusado.', 400);
        }

        if ($status === 'Recusado' && $motivoRecusa === '') {
            Response::error('Para recusar um cadastro, o motivo da recusa é obrigatório.', 422);
        }

        // Se for Ativo ou Pendente, limpa o motivo da recusa
        if ($status !== 'Recusado') {
            $motivoRecusa = null;
        }

        $stmt = $this->db->prepare('UPDATE motoboys SET status = :status, motivo_recusa = :motivo_recusa WHERE id = :id');
        $stmt->execute([
            ':status'        => $status,
            ':motivo_recusa' => $motivoRecusa,
            ':id'            => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            $check = $this->db->prepare('SELECT id FROM motoboys WHERE id = :id');
            $check->execute([':id' => $id]);
            if (!$check->fetch()) {
                Response::error('Motoboy não encontrado.', 404);
            }
        }

        Response::success("Status do motoboy alterado para '{$status}' com sucesso.", [
            'id'           => $id,
            'status'       => $status,
            'motivoRecusa' => $motivoRecusa,
        ]);
    }

    /**
     * Endpoint: DELETE /api/motoboys/{id}
     * Remove o motoboy parceiro
     */
    public function destroy(array $context, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $check = $this->db->prepare('SELECT id, nome FROM motoboys WHERE id = :id LIMIT 1');
        $check->execute([':id' => $id]);
        $motoboy = $check->fetch();

        if (!$motoboy) {
            Response::error('Motoboy não encontrado para exclusão.', 404);
        }

        $stmt = $this->db->prepare('DELETE FROM motoboys WHERE id = :id');
        $stmt->execute([':id' => $id]);

        Response::success('Motoboy parceiro removido com sucesso.', [
            'id'   => $id,
            'nome' => $motoboy['nome'],
        ]);
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

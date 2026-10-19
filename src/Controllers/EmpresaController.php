<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use PDO;
use Exception;

class EmpresaController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Endpoint: GET /api/empresas
     * Lista empresas com suporte a filtro ?search=termo e ?status=Aprovado|Pendente|Suspenso
     */
    public function index(): void
    {
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        $query = 'SELECT id, responsavel, empresa, cnpj, whatsapp, cidade_uf, endereco, status, criado_em, atualizado_em FROM empresas WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $query .= ' AND (empresa LIKE :search_empresa OR responsavel LIKE :search_resp OR cnpj LIKE :search_cnpj)';
            $searchWildcard = "%{$search}%";
            $params[':search_empresa'] = $searchWildcard;
            $params[':search_resp']    = $searchWildcard;
            $params[':search_cnpj']    = $searchWildcard;
        }

        if ($status !== '' && in_array($status, ['Aprovado', 'Pendente', 'Suspenso'], true)) {
            $query .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $query .= ' ORDER BY criado_em DESC';

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $empresas = $stmt->fetchAll();

        // Formatação dos registros para o frontend
        $data = array_map(function ($item) {
            return [
                'id'           => (int) $item['id'],
                'responsavel'  => $item['responsavel'],
                'empresa'      => $item['empresa'],
                'cnpj'         => $item['cnpj'],
                'whatsapp'     => $item['whatsapp'],
                'cidadeUf'     => $item['cidade_uf'],
                'endereco'     => $item['endereco'],
                'status'       => $item['status'],
                'dataCriacao'  => date('d/m/Y', strtotime($item['criado_em'])),
                'criado_em'    => $item['criado_em'],
                'atualizado_em'=> $item['atualizado_em'],
            ];
        }, $empresas);

        Response::success('Lista de empresas recuperada com sucesso.', $data, 200);
    }

    /**
     * Endpoint: GET /api/empresas/stats
     * Retorna contadores agregados para os cards de métricas
     */
    public function stats(): void
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Aprovado' THEN 1 ELSE 0 END) as aprovados,
                SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status = 'Suspenso' THEN 1 ELSE 0 END) as suspensos
            FROM empresas
        ");
        $stats = $stmt->fetch();

        Response::success('Métricas de empresas recuperadas com sucesso.', [
            'total'     => (int) ($stats['total'] ?? 0),
            'aprovados' => (int) ($stats['aprovados'] ?? 0),
            'pendentes' => (int) ($stats['pendentes'] ?? 0),
            'suspensos' => (int) ($stats['suspensos'] ?? 0),
        ], 200);
    }

    /**
     * Endpoint: GET /api/empresas/{id}
     * Retorna os detalhes de uma única empresa
     */
    public function show(array $context, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $stmt = $this->db->prepare('SELECT * FROM empresas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $empresa = $stmt->fetch();

        if (!$empresa) {
            Response::error('Empresa não encontrada.', 404);
        }

        Response::success('Detalhes da empresa', [
            'id'          => (int) $empresa['id'],
            'responsavel' => $empresa['responsavel'],
            'empresa'     => $empresa['empresa'],
            'cnpj'        => $empresa['cnpj'],
            'whatsapp'    => $empresa['whatsapp'],
            'cidadeUf'    => $empresa['cidade_uf'],
            'endereco'    => $empresa['endereco'],
            'status'      => $empresa['status'],
            'dataCriacao' => date('d/m/Y', strtotime($empresa['criado_em'])),
            'criado_em'   => $empresa['criado_em'],
            'atualizado_em'=> $empresa['atualizado_em'],
        ], 200);
    }

    /**
     * Endpoint: POST /api/empresas
     * Criação de nova empresa
     */
    public function store(): void
    {
        $input = $this->getJsonInput();

        $responsavel = trim((string) ($input['responsavel'] ?? ''));
        $empresa     = trim((string) ($input['empresa'] ?? ''));
        $cnpj        = trim((string) ($input['cnpj'] ?? ''));
        $whatsapp    = trim((string) ($input['whatsapp'] ?? ''));
        $cidadeUf    = trim((string) ($input['cidadeUf'] ?? $input['cidade_uf'] ?? ''));
        $endereco    = trim((string) ($input['endereco'] ?? ''));
        $status      = trim((string) ($input['status'] ?? 'Aprovado'));

        // Validações obrigatórias
        if (empty($responsavel) || empty($empresa) || empty($cnpj)) {
            Response::error('Os campos Responsável, Empresa e CNPJ são obrigatórios.', 400);
        }

        if (!in_array($status, ['Aprovado', 'Pendente', 'Suspenso'], true)) {
            $status = 'Aprovado';
        }

        // Verifica unicidade de CNPJ
        $checkStmt = $this->db->prepare('SELECT id FROM empresas WHERE cnpj = :cnpj LIMIT 1');
        $checkStmt->execute([':cnpj' => $cnpj]);
        if ($checkStmt->fetch()) {
            Response::error('Já existe uma empresa cadastrada com este CNPJ.', 409);
        }

        // Insere registro
        $stmt = $this->db->prepare('
            INSERT INTO empresas (responsavel, empresa, cnpj, whatsapp, cidade_uf, endereco, status)
            VALUES (:responsavel, :empresa, :cnpj, :whatsapp, :cidade_uf, :endereco, :status)
        ');
        $stmt->execute([
            ':responsavel' => $responsavel,
            ':empresa'     => $empresa,
            ':cnpj'        => $cnpj,
            ':whatsapp'    => $whatsapp ?: null,
            ':cidade_uf'   => $cidadeUf ?: null,
            ':endereco'    => $endereco ?: null,
            ':status'      => $status,
        ]);

        $newId = (int) $this->db->lastInsertId();

        Response::success('Empresa parceira cadastrada e aprovada com sucesso!', [
            'id'          => $newId,
            'responsavel' => $responsavel,
            'empresa'     => $empresa,
            'cnpj'        => $cnpj,
            'whatsapp'    => $whatsapp,
            'cidadeUf'    => $cidadeUf,
            'endereco'    => $endereco,
            'status'      => $status,
            'dataCriacao' => date('d/m/Y'),
        ], 201);
    }

    /**
     * Endpoint: PUT /api/empresas/{id}
     * Atualização completa ou parcial do cadastro da empresa
     */
    public function update(array $context, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        // Verifica existência
        $checkStmt = $this->db->prepare('SELECT * FROM empresas WHERE id = :id LIMIT 1');
        $checkStmt->execute([':id' => $id]);
        $existing = $checkStmt->fetch();

        if (!$existing) {
            Response::error('Empresa não encontrada para atualização.', 404);
        }

        $input = $this->getJsonInput();

        $responsavel = trim((string) ($input['responsavel'] ?? $existing['responsavel']));
        $empresa     = trim((string) ($input['empresa'] ?? $existing['empresa']));
        $cnpj        = trim((string) ($input['cnpj'] ?? $existing['cnpj']));
        $whatsapp    = isset($input['whatsapp']) ? trim((string) $input['whatsapp']) : $existing['whatsapp'];
        $cidadeUf    = isset($input['cidadeUf']) ? trim((string) $input['cidadeUf']) : (isset($input['cidade_uf']) ? trim((string) $input['cidade_uf']) : $existing['cidade_uf']);
        $endereco    = isset($input['endereco']) ? trim((string) $input['endereco']) : $existing['endereco'];
        $status      = trim((string) ($input['status'] ?? $existing['status']));

        if (!in_array($status, ['Aprovado', 'Pendente', 'Suspenso'], true)) {
            $status = $existing['status'];
        }

        // Valida duplicidade de CNPJ em outro ID
        if ($cnpj !== $existing['cnpj']) {
            $cnpjCheck = $this->db->prepare('SELECT id FROM empresas WHERE cnpj = :cnpj AND id != :id LIMIT 1');
            $cnpjCheck->execute([':cnpj' => $cnpj, ':id' => $id]);
            if ($cnpjCheck->fetch()) {
                Response::error('Já existe outra empresa cadastrada com este CNPJ.', 409);
            }
        }

        $stmt = $this->db->prepare('
            UPDATE empresas 
            SET responsavel = :responsavel, empresa = :empresa, cnpj = :cnpj, 
                whatsapp = :whatsapp, cidade_uf = :cidade_uf, endereco = :endereco, status = :status
            WHERE id = :id
        ');
        $stmt->execute([
            ':responsavel' => $responsavel,
            ':empresa'     => $empresa,
            ':cnpj'        => $cnpj,
            ':whatsapp'    => $whatsapp ?: null,
            ':cidade_uf'   => $cidadeUf ?: null,
            ':endereco'    => $endereco ?: null,
            ':status'      => $status,
            ':id'          => $id,
        ]);

        Response::success('Empresa atualizada com sucesso.', [
            'id'          => $id,
            'responsavel' => $responsavel,
            'empresa'     => $empresa,
            'cnpj'        => $cnpj,
            'whatsapp'    => $whatsapp,
            'cidadeUf'    => $cidadeUf,
            'endereco'    => $endereco,
            'status'      => $status,
        ], 200);
    }

    /**
     * Endpoint: PATCH /api/empresas/{id}/status
     * Altera apenas o status ('Aprovado', 'Pendente', 'Suspenso')
     */
    public function updateStatus(array $context, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $input = $this->getJsonInput();

        $status = trim((string) ($input['status'] ?? ''));

        if (!in_array($status, ['Aprovado', 'Pendente', 'Suspenso'], true)) {
            Response::error('Status inválido. Escolha entre: Aprovado, Pendente ou Suspenso.', 400);
        }

        $stmt = $this->db->prepare('UPDATE empresas SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

        if ($stmt->rowCount() === 0) {
            // Verifica se o ID existe
            $check = $this->db->prepare('SELECT id FROM empresas WHERE id = :id');
            $check->execute([':id' => $id]);
            if (!$check->fetch()) {
                Response::error('Empresa não encontrada.', 404);
            }
        }

        Response::success("Status da empresa atualizado para '{$status}' com sucesso.", [
            'id'     => $id,
            'status' => $status,
        ], 200);
    }

    /**
     * Endpoint: DELETE /api/empresas/{id}
     * Exclui o registro da empresa
     */
    public function destroy(array $context, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $stmt = $this->db->prepare('DELETE FROM empresas WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            Response::error('Empresa não encontrada para exclusão.', 404);
        }

        Response::success('Empresa removida com sucesso.', ['id' => $id], 200);
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

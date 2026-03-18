<?php
// ============================================================
// Finance - API de Projetos
// ============================================================

namespace Finance\Api;

use Finance\Lib\FinanceDB;
use Finance\Lib\Helpers;
use PDO;

$db = FinanceDB::connect();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Lista todos os projetos (Opcionalmente, pode filtrar por ID)
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $project = $stmt->fetch();
            
            if (!$project) {
                Helpers::json_response(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
            }
            Helpers::json_response(['success' => true, 'data' => $project]);
        } else {
            $stmt = $db->query("SELECT * FROM projects ORDER BY status ASC, created_at DESC");
            Helpers::json_response(['success' => true, 'data' => $stmt->fetchAll()]);
        }
        break;

    case 'POST':
        // Cria novo projeto
        $input = json_decode(file_get_contents('php://input'), true);
        
        $name    = Helpers::sanitize($input['name'] ?? '');
        $client  = Helpers::sanitize($input['client'] ?? '');
        $split_a = (int) ($input['split_a'] ?? 50);
        $split_b = (int) ($input['split_b'] ?? 50);
        $notes   = Helpers::sanitize($input['notes'] ?? '');

        if (!$name) {
            Helpers::json_response(['success' => false, 'message' => 'O nome do projeto é obrigatório.'], 400);
        }

        if ($split_a + $split_b !== 100) {
            Helpers::json_response(['success' => false, 'message' => 'A soma dos splits deve ser 100%.'], 400);
        }

        $stmt = $db->prepare("INSERT INTO projects (name, client, split_a, split_b, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $client, $split_a, $split_b, $notes]);
        
        Helpers::json_response([
            'success' => true, 
            'message' => 'Projeto criado com sucesso.',
            'id' => $db->lastInsertId()
        ], 201);
        break;

    case 'PUT':
        // Fechar projeto (Mudar status para closed)
        $input = json_decode(file_get_contents('php://input'), true);
        $id    = (int) ($input['id'] ?? 0);
        
        if (!$id) {
            Helpers::json_response(['success' => false, 'message' => 'ID não fornecido.'], 400);
        }

        $stmt = $db->prepare("UPDATE projects SET status = 'closed', closed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);

        Helpers::json_response(['success' => true, 'message' => 'Projeto fechado com sucesso.']);
        break;

    default:
        Helpers::json_response(['success' => false, 'message' => 'Método inválido.'], 405);
}

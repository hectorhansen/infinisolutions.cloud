<?php
// ============================================================
// Finance - API de Categorias
// ============================================================

namespace Finance\Api;

use Finance\Lib\FinanceDB;
use Finance\Lib\Helpers;
use PDO;

$db = FinanceDB::connect();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Lista todas categorias
        $stmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
        Helpers::json_response(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        $name  = Helpers::sanitize($input['name'] ?? '');

        if (!$name) {
            Helpers::json_response(['success' => false, 'message' => 'O nome da categoria é obrigatório.'], 400);
        }

        $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);

        Helpers::json_response(['success' => true, 'message' => 'Categoria criada.', 'id' => $db->lastInsertId()], 201);
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            Helpers::json_response(['success' => false, 'message' => 'ID não informado.'], 400);
        }

        // Valida se está em uso
        $check = $db->prepare("SELECT COUNT(*) FROM entries WHERE category_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            Helpers::json_response(['success' => false, 'message' => 'Categoria em uso por lançamentos. Não pode ser excluída.'], 400);
        }

        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        Helpers::json_response(['success' => true, 'message' => 'Categoria removida.']);
        break;

    default:
        Helpers::json_response(['success' => false, 'message' => 'Método inválido.'], 405);
}

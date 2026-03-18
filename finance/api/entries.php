<?php
// ============================================================
// Finance - API de Lançamentos Fixos
// ============================================================

namespace Finance\Api;

use Finance\Lib\FinanceDB;
use Finance\Lib\Helpers;
use PDO;

$db = FinanceDB::connect();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Criar um novo lançamento (Receita ou Despesa)
        $input = json_decode(file_get_contents('php://input'), true);

        $project_id  = (int) ($input['project_id'] ?? 0);
        $type        = $input['type'] ?? '';
        $description = Helpers::sanitize($input['description'] ?? '');
        $amount      = Helpers::brl_to_float($input['amount'] ?? 0);
        $paid_by     = (int) ($input['paid_by'] ?? 0);
        $category_id = isset($input['category_id']) && $input['category_id'] ? (int) $input['category_id'] : null;
        $entry_date  = Helpers::sanitize($input['entry_date'] ?? date('Y-m-d'));
        $notes       = Helpers::sanitize($input['notes'] ?? '');

        // Validações
        if (!$project_id || !$description || $amount <= 0 || !$paid_by) {
            Helpers::json_response(['success' => false, 'message' => 'Campos obrigatórios (Projeto, Descrição, Valor e Pagador).'], 400);
        }

        if (!in_array($type, ['income', 'expense'])) {
            Helpers::json_response(['success' => false, 'message' => 'O tipo deve ser income ou expense.'], 400);
        }

        // Se for receita, não tem categoria. 
        if ($type === 'income') {
            $category_id = null;
        } else if ($type === 'expense' && !$category_id) {
            // Em caso de despesa, exigir categoria pode ser interessante, mas por flexibilidade
            // deixaremos NULL como viável ou obrigamos a existir. Obrigaremos no front.
        }

        $stmt = $db->prepare("
            INSERT INTO entries (project_id, type, description, amount, paid_by, category_id, entry_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$project_id, $type, $description, $amount, $paid_by, $category_id, $entry_date, $notes]);
        
        Helpers::json_response(['success' => true, 'message' => 'Lançamento registrado com sucesso.']);
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            Helpers::json_response(['success' => false, 'message' => 'ID não informado.'], 400);
        }
        $stmt = $db->prepare("DELETE FROM entries WHERE id = ?");
        $stmt->execute([$id]);

        Helpers::json_response(['success' => true, 'message' => 'Lançamento excluído com sucesso.']);
        break;

    default:
        // A listagem de lançamentos será feita pelo reports.php que já agrega 
        // tudo mastigado pela FinanceLogic, portanto não é estritamente necessário um GET aqui,
        // mas vamos abrir caso o fetch individual seja pedido.
        $pid = $_GET['project_id'] ?? null;
        if ($pid) {
            $stmt = $db->prepare("SELECT * FROM entries WHERE project_id = ? ORDER BY entry_date DESC");
            $stmt->execute([$pid]);
            Helpers::json_response(['success' => true, 'data' => $stmt->fetchAll()]);
        } else {
            Helpers::json_response(['success' => false, 'message' => 'Especifique o project_id.'], 400);
        }
}

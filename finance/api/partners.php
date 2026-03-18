<?php
// ============================================================
// Finance - API de Parceiros/Origens
// ============================================================

namespace Finance\Api;

use Finance\Lib\FinanceDB;
use Finance\Lib\Helpers;

$db = FinanceDB::connect();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Lista todos parceiros 
        $stmt = $db->query("SELECT * FROM partners ORDER BY id ASC");
        Helpers::json_response(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        Helpers::json_response(['success' => false, 'message' => 'Método inválido.'], 405);
}

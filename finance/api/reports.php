<?php
// ============================================================
// Finance - API de Relatório de Fechamento de Projetos
// ============================================================

namespace Finance\Api;

require_once __DIR__ . '/../lib/Finance.php';
require_once __DIR__ . '/../lib/Reports.php';

use Finance\Lib\Reports;
use Finance\Lib\Helpers;
use Exception;

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Helpers::json_response(['success' => false, 'message' => 'Método inválido.'], 405);
}

$projectId = (int) ($_GET['project_id'] ?? 0);

if (!$projectId) {
    Helpers::json_response(['success' => false, 'message' => 'ID do projeto não fornecido (project_id).'], 400);
}

try {
    $reports = new Reports();
    $summary = $reports->getFormattedProjectReport($projectId);

    Helpers::json_response([
        'success' => true,
        'data'    => $summary
    ]);

} catch (Exception $e) {
    Helpers::json_response(['success' => false, 'message' => $e->getMessage()], 404);
}

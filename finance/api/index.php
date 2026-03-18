<?php
// ============================================================
// Finance - API Roteador Principal
// Acessível via: /finance/api/index.php?action=XXXX
// ============================================================

namespace Finance\Api;

require_once __DIR__ . '/../lib/Helpers.php';
require_once __DIR__ . '/../lib/Auth.php';

use Finance\Lib\Helpers;
use Finance\Lib\Auth;

header('Content-Type: application/json; charset=utf-8');

// Configura o CORS (se acessado via fetch no mesmo domínio, não é estritamente necessário, 
// mas ajuda se a porta diferir em local dev).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$action = $_GET['action'] ?? '';

// Endpoints que não exigem auth
$publicActions = ['login', 'check_auth'];

if (!in_array($action, $publicActions, true)) {
    Auth::require_auth(); // Encerra execução com 401 se não logado
}

// Roteamento
switch ($action) {
    case 'login':
    case 'logout':
    case 'me':
    case 'check_auth':
        require __DIR__ . '/auth.php';
        break;

    case 'projects':
        require __DIR__ . '/projects.php';
        break;

    case 'entries':
        require __DIR__ . '/entries.php';
        break;

    case 'categories':
        require __DIR__ . '/categories.php';
        break;

    case 'reports':
        require __DIR__ . '/reports.php';
        break;
        
    case 'partners':
        require __DIR__ . '/partners.php';
        break;

    default:
        Helpers::json_response(['success' => false, 'message' => 'Endpoint não encontrado.'], 404);
}

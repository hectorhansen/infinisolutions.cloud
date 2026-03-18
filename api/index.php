<?php
// Handler global: garante que qualquer erro/exception retorne JSON válido
// evitando o erro "Unexpected end of JSON input" no cliente
set_exception_handler(function (Throwable $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error'   => 'Erro interno do servidor.',
        'detail'  => $e->getMessage(),  // Remover em produção se quiser ocultar detalhes
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    // Converte erros PHP em exceptions para serem capturados acima
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once '../config.php';
require_once '../db.php';
require_once '../helpers.php';
require_once '../lib/Auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Mapeador de rotas estilo Switch-Case para simplicidade (evita libs robustas de routng na hospedagem)
switch ($action) {
    
    // --- AUTHENTICATION ---
    case 'login':
        if ($method !== 'POST') sendJson(['error' => 'Method not allowed'], 405);
        require 'auth.php'; // Vai carregar a lógica ou chamar a função respectiva
        break;
        
    case 'logout':
        if ($method !== 'POST') sendJson(['error' => 'Method not allowed'], 405);
        Auth::requireSession();
        Auth::revokeCurrentSession();
        sendJson(['ok' => true]);
        break;
        
    case 'me':
        if ($method !== 'GET') sendJson(['error' => 'Method not allowed'], 405);
        $opId = Auth::requireSession();
        $pdo = DB::connect();
        $me = $pdo->query("SELECT id, name, email, role, avatar_url FROM operators WHERE id = " . $pdo->quote($opId))
                  ->fetch();
        sendJson($me);
        break;

    // --- SUB-ROTEAMENTO: Delegação para arquivos modulares ---
    // (Apenas repassa a requisição para o arquivo que contem a lógica)
    
    case 'operators':
        require 'operators.php';
        break;
        
    case 'conversations':
        require 'conversations.php';
        break;

    case 'messages':
        require 'messages.php';
        break;
        
    case 'events': // Polling (long polling em fase futura, atualmente polling simples 3s)
        require 'events.php';
        break;

    case 'metrics':
        require 'metrics.php';
        break;

    default:
        sendJson(['error' => 'Invalid or missing API action'], 404);
        break;
}

<?php
// ============================================================
// InfiniCloud - API de Autenticação
// POST ?action=login  → Autenticar usuário
// POST ?action=logout → Encerrar sessão
// GET  ?action=check  → Verificar sessão ativa
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if (session_status() === PHP_SESSION_NONE) {
    session_name(IC_SESSION_NAME);
    session_start();
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ic_json(['error' => 'Método não permitido.'], 405);
        }

        $body  = json_decode(file_get_contents('php://input'), true);
        $email = trim($body['email'] ?? '');
        $pass  = trim($body['password'] ?? '');

        if (!$email || !$pass) {
            ic_json(['error' => 'E-mail e senha são obrigatórios.'], 422);
        }

        $db   = ICDB::connect();
        $stmt = $db->prepare('SELECT id, name, email, password FROM ic_users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password'])) {
            ic_json(['error' => 'Credenciais inválidas.'], 401);
        }

        // Armazena dados do usuário na sessão (sem a senha)
        $_SESSION['ic_user'] = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ];

        ic_json(['ok' => true, 'user' => $_SESSION['ic_user']]);
        break;

    // ----------------------------------------------------------
    case 'logout':
        $_SESSION = [];
        session_destroy();
        ic_json(['ok' => true]);
        break;

    // ----------------------------------------------------------
    case 'check':
        $user = ic_auth(false);
        if ($user) {
            ic_json(['authenticated' => true, 'user' => $user]);
        } else {
            ic_json(['authenticated' => false], 401);
        }
        break;

    // ----------------------------------------------------------
    default:
        ic_json(['error' => 'Ação inválida.'], 400);
}

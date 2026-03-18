<?php
// ============================================================
// Finance - API de Autenticação
// ============================================================

namespace Finance\Api;

use Finance\Lib\Auth;
use Finance\Lib\Helpers;

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::json_response(['success' => false, 'message' => 'Método inválido.'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $user  = trim($input['username'] ?? '');
        $pass  = trim($input['password'] ?? '');

        if (!$user || !$pass) {
            Helpers::json_response(['success' => false, 'message' => 'Informe usuário e senha.'], 400);
        }

        if (Auth::login($user, $pass)) {
            Helpers::json_response(['success' => true, 'message' => 'Login efetuado com sucesso.', 'user' => Auth::user()]);
        } else {
            Helpers::json_response(['success' => false, 'message' => 'Credenciais inválidas.'], 401);
        }
        break;

    case 'logout':
        Auth::logout();
        Helpers::json_response(['success' => true, 'message' => 'Sessão encerrada.']);
        break;

    case 'check_auth':
    case 'me':
        $user = Auth::user();
        if ($user) {
            Helpers::json_response(['success' => true, 'authenticated' => true, 'user' => $user]);
        } else {
            Helpers::json_response(['success' => true, 'authenticated' => false]);
        }
        break;
}

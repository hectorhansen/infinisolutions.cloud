<?php
/**
 * Trata POST /api/index.php?action=login
 */
$payload = getJsonPayload();
$email = $payload['email'] ?? '';
$password = $payload['password'] ?? '';

if (!$email || !$password) {
    sendJson(['error' => 'Missing email or password'], 400);
}

try {
    $pdo = DB::connect();
    $stmt = $pdo->prepare("SELECT id, password_hash, is_active FROM operators WHERE email = ?");
    $stmt->execute([$email]);
    $operator = $stmt->fetch();

    if (!$operator || !password_verify($password, $operator['password_hash'])) {
        sendJson(['error' => 'Credenciais inválidas. Tente novamente.'], 401);
    }

    if (!$operator['is_active']) {
        sendJson(['error' => 'Sua conta de operador está desativada.'], 403);
    }

    // Sucesso: Gera sessão
    $token = Auth::createSession($operator['id']);

    sendJson([
        'token' => $token,
        'operator_id' => $operator['id']
    ]);
    
} catch (PDOException $e) {
    sendJson(['error' => 'Erro de banco de dados: ' . $e->getMessage()], 500);
}

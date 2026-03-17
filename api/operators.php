<?php
/**
 * Trata POST/GET /api/index.php?action=operators
 */

// Apenas admin pode criar/editar operadores. Supervisor pode listar, etc.
$method = $_SERVER['REQUEST_METHOD'];
$pdo = DB::connect();

if ($method === 'GET') {
    Auth::requireSession(['admin', 'supervisor']);
    
    // Lista operadores
    $stmt = $pdo->query("
        SELECT id, name, email, role, status, max_concurrent, is_active, last_seen_at, created_at
        FROM operators 
        ORDER BY name ASC
    ");
    sendJson($stmt->fetchAll());
}

if ($method === 'POST') {
    Auth::requireSession(['admin']);
    $payload = getJsonPayload();
    
    $name     = sanitizeString($payload['name'] ?? '');
    $email    = sanitizeString($payload['email'] ?? '');
    $password = $payload['password'] ?? '';
    $role     = $payload['role'] ?? 'agent';
    $maxConc  = (int)($payload['max_concurrent'] ?? MAX_CONCURRENT_DEFAULT);
    
    if (!$name || !$email || !$password) {
        sendJson(['error' => 'Missing required fields (name, email, password)'], 400);
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO operators (name, email, password_hash, role, max_concurrent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $hash, $role, $maxConc]);
        
        sendJson(['id' => $pdo->lastInsertId(), 'msg' => 'Operator created successfully']);
    } catch (PDOException $e) {
        // Erro 1062 é duplicate entry
        if ($e->errorInfo[1] === 1062) {
            sendJson(['error' => 'Email already exists'], 409);
        }
        sendJson(['error' => 'Database error'], 500);
    }
}

if ($method === 'PUT') {
    Auth::requireSession(['admin']); // Para edição completa
    $payload = getJsonPayload();
    $id = sanitizeString($payload['id'] ?? '');
    
    if (!$id) sendJson(['error' => 'Missing operator ID'], 400);
    
    // ... Aqui poderia ter lógicas p/ atualizar a senha só se enviada, role e is_active
}

sendJson(['error' => 'Method not allowed for this route'], 405);

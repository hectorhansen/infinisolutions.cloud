<?php
require_once 'config.php';
require_once 'db.php';
require_once 'lib/Hmac.php';
require_once 'lib/Queue.php';

// GET: verificação do webhook pela Meta
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? '';

    if ($mode === 'subscribe' && $token === WA_WEBHOOK_VERIFY_TOKEN) {
        http_response_code(200);
        echo $challenge;
    } else {
        http_response_code(403);
    }
    exit;
}

// POST: evento recebido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawBody  = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

    // Validar assinatura HMAC obrigatória
    if (!Hmac::validate($rawBody, $signature)) {
        http_response_code(401);
        exit;
    }

    $payload = json_decode($rawBody, true);

    // Salvar evento bruto
    $pdo = DB::connect();
    $eventType = $payload['entry'][0]['changes'][0]['field'] ?? 'unknown';
    $waMessageId = $payload['entry'][0]['changes'][0]['value']['messages'][0]['id'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO webhook_events (event_type, wa_message_id, phone_number_id, payload)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $eventType,
        $waMessageId,
        $payload['entry'][0]['id'] ?? null,
        $rawBody
    ]);

    // Enfileirar para processamento assíncrono
    Queue::push('process_webhook', ['webhook_event_id' => $pdo->lastInsertId()]);

    // Responder 200 imediatamente (a Meta exige resposta < 20s)
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

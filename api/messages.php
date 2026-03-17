<?php
/**
 * Trata POST/GET /api/index.php?action=messages
 */
$operatorId = Auth::requireSession();
$pdo = DB::connect();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $convId = sanitizeString($_GET['conversation_id'] ?? '');
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    if (!$convId) sendJson(['error' => 'Missing conversation_id'], 400);

    // Zerar contador de mensagens nao lidas nesta conversa para esse operador
    $pdo->prepare("UPDATE conversations SET unread_count = 0 WHERE id = ? AND operator_id = ?")
        ->execute([$convId, $operatorId]);

    $stmt = $pdo->prepare("
        SELECT m.id, m.wa_message_id, m.direction, m.type, m.body, m.context_message_id, 
               m.status, m.created_at, m.payload,
               (SELECT storage_url FROM message_media WHERE message_id = m.id LIMIT 1) as media_url
        FROM messages m
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $convId);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($messages as &$m) {
        if ($m['payload']) $m['payload'] = json_decode($m['payload'], true);
    }
    
    sendJson($messages);
}

if ($method === 'POST') {
    require_once '../lib/Queue.php';
    
    $payload = getJsonPayload();
    $convId = sanitizeString($payload['conversation_id'] ?? '');
    $text = sanitizeString($payload['text'] ?? '');
    $type = sanitizeString($payload['type'] ?? 'text'); // text, image, document
    
    if (!$convId) sendJson(['error' => 'Missing conversation_id'], 400);
    
    // Obter telefone destino
    $stmt = $pdo->prepare("
        SELECT c.phone 
        FROM conversations cv 
        JOIN contacts c ON c.id = cv.contact_id 
        WHERE cv.id = ? AND cv.operator_id = ?
    ");
    $stmt->execute([$convId, $operatorId]);
    $phone = $stmt->fetchColumn();
    
    if (!$phone) sendJson(['error' => 'Conversation not found or access denied'], 403);
    
    // Apenas insere na Queue (assíncrono para ser robusto)
    // O cron passará pegando e enviando para Meta via CURL
    
    $jobPayload = [
        'conversation_id' => $convId,
        'operator_id'     => $operatorId,
        'phone'           => $phone,
        'type'            => $type
    ];
    
    if ($type === 'text') {
        $jobPayload['text'] = $text;
        
        $msgId = (string) bin2hex(random_bytes(16)); // ID temp
        
        // Criar registro da mensagem "pendente" para UI já mostrar na tela (optimistic UI)
        $pdo->prepare("
            INSERT INTO messages (id, conversation_id, direction, type, body, status, operator_id) 
            VALUES (?, ?, 'outbound', 'text', ?, 'pending', ?)
        ")->execute([$msgId, $convId, $text, $operatorId]);
        
        $jobPayload['local_message_id'] = $msgId;
    }
    
    // Adiciona job fila (send_message)
    Queue::push('send_message', $jobPayload);
    
    // Atualiza listagem pra jogar p/ topo
    $pdo->prepare("UPDATE conversations SET last_message_preview = ?, last_message_at = NOW() WHERE id = ?")
        ->execute([mb_substr($text, 0, 50), $convId]);
    
    sendJson(['ok' => true, 'local_id' => $msgId ?? null]);
}

<?php
/**
 * Trata rotas de polling: /api/index.php?action=events
 */
$operatorId = Auth::requireSession();
$pdo = DB::connect();

// Atualiza o last_seen para manter online (ping/pong)
$pdo->prepare("UPDATE operators SET last_seen_at = NOW(), status = 'online' WHERE id = ?")
    ->execute([$operatorId]);

// Busca eventos não lidos
$stmt = $pdo->prepare("
    SELECT id, event_type, conversation_id, message_id, payload
    FROM polling_cache
    WHERE operator_id = ? AND seen = 0
    ORDER BY created_at ASC
    LIMIT 50
");
$stmt->execute([$operatorId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marcar como vistos atômico se achou algo
if ($events) {
    // Usando bind com in() cludindo IDs seguros para delete:
    $ids = array_column($events, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $updateStmt = $pdo->prepare("UPDATE polling_cache SET seen = 1 WHERE id IN ($placeholders)");
    $updateStmt->execute($ids);
}

// Formatar JSON payload p array associativo
foreach ($events as &$e) {
    if ($e['payload']) {
        $e['payload'] = json_decode($e['payload'], true);
    }
}

sendJson(['events' => $events]);

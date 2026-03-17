<?php
/**
 * Trata POST/GET /api/index.php?action=conversations
 */
$operatorId = Auth::requireSession();
$pdo = DB::connect();
$method = $_SERVER['REQUEST_METHOD'];

// Lista de conversas do operador ou todas (para admins)
if ($method === 'GET') {
    $filter = sanitizeString($_GET['filter'] ?? 'mine'); // mine | unassigned | all
    
    // Obter meus próprios dados
    $stmtMe = $pdo->prepare("SELECT role FROM operators WHERE id = ?");
    $stmtMe->execute([$operatorId]);
    $myRole = $stmtMe->fetchColumn();
    
    $where = [];
    $params = [];
    
    if ($filter === 'mine') {
        $where[] = "operator_id = ?";
        $params[] = $operatorId;
    } elseif ($filter === 'unassigned') {
        $where[] = "operator_id IS NULL";
    } elseif ($filter === 'all' && in_array($myRole, ['admin', 'supervisor'])) {
        // Sem filtro, retorna tudo
    } else {
        // Fallback pro default seguro
        $where[] = "operator_id = ?";
        $params[] = $operatorId;
    }
    
    // Filtro adicional por status opcional (?status=open,waiting)
    if (!empty($_GET['status'])) {
        $statusList = explode(',', sanitizeString($_GET['status']));
        $inQuery = implode(',', array_fill(0, count($statusList), '?'));
        $where[] = "status IN ($inQuery)";
        foreach($statusList as $s) $params[] = $s;
    } else {
        // Por padrão oculta resolvidas/expiradas
        $where[] = "status NOT IN ('resolved', 'expired')";
    }
    
    $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    
    $query = "
        SELECT 
            id, contact_id, operator_id, status, unread_count, 
            last_message_preview, last_message_at,
            (SELECT name FROM contacts WHERE id = contact_id) as contact_name,
            (SELECT phone FROM contacts WHERE id = contact_id) as contact_phone
        FROM conversations
        $whereClause
        ORDER BY last_message_at DESC
        LIMIT 100
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    sendJson($stmt->fetchAll());
}

if ($method === 'POST') {
    // Ação: Reatribuir / Enviar para fila
    $payload = getJsonPayload();
    $subAction = $payload['do'] ?? ''; // reassign | resolve | transfer
    $convId = sanitizeString($payload['conversation_id'] ?? '');
    
    if (!$convId) sendJson(['error' => 'No conversation ID'], 400);
    
    if ($subAction === 'reassign') {
        Auth::requireSession(['admin', 'supervisor']); // apenas gestores
        $newOpId = sanitizeString($payload['operator_id'] ?? '');
        
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE conversations SET operator_id = ?, assigned_at = NOW() WHERE id = ?")
            ->execute([$newOpId, $convId]);
        
        $pdo->prepare("INSERT INTO assignment_log (conversation_id, to_operator_id, reason, assigned_by) VALUES (?, ?, 'manual', ?)")
            ->execute([$convId, $newOpId, $operatorId]);
        
        // Notificar via Polling
        if ($newOpId) {
            $pdo->prepare("INSERT INTO polling_cache (operator_id, event_type, conversation_id) VALUES (?, 'assigned', ?)")
                ->execute([$newOpId, $convId]);
        }
            
        $pdo->commit();
        sendJson(['ok' => true]);
    }
    
    if ($subAction === 'resolve') {
        // Marcar como resolvida
        $pdo->prepare("
            UPDATE conversations 
            SET status = 'resolved', resolved_at = NOW(), resolved_by = ? 
            WHERE id = ? AND operator_id = ?
        ")->execute([$operatorId, $convId, $operatorId]);
        
        sendJson(['ok' => true]);
    }
}

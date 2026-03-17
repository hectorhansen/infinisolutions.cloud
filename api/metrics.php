<?php
/**
 * Trata GET /api/index.php?action=metrics
 */
Auth::requireSession(['admin', 'supervisor']);
$pdo = DB::connect();

try {
    // Busca os dados da view operator_metrics_24h
    $stmt = $pdo->query("SELECT * FROM operator_metrics_24h ORDER BY total_conversations DESC");
    $operatorMetrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Métricas gerais da fila atual
    $stmtQueue = $pdo->query("
        SELECT 
            COUNT(*) as waiting_count,
            COALESCE(MAX(TIMESTAMPDIFF(MINUTE, created_at, NOW())), 0) as max_wait_time_minutes
        FROM conversations 
        WHERE status = 'waiting'
    ");
    $queueMetrics = $stmtQueue->fetch(PDO::FETCH_ASSOC);

    sendJson([
        'queue' => $queueMetrics,
        'operators' => $operatorMetrics
    ]);
} catch (Exception $e) {
    sendJson(['error' => 'Failed to fetch metrics'], 500);
}

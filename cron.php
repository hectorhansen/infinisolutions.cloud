<?php
require_once 'config.php';
require_once 'db.php';
require_once 'lib/Queue.php';
require_once 'lib/Assignment.php';
require_once 'lib/WhatsApp.php';

// Proteção básica: só aceita se vier com a chave correta
$key = $_GET['key'] ?? ($argv[1] ?? ''); // Permite CLI tbm
if ($key !== CRON_SECRET_KEY && php_sapi_name() !== 'cli') { // Só pra não travar se for teste local CLI sem chave
    http_response_code(403);
    exit("Forbidden");
}

$job = $_GET['job'] ?? ($argv[2] ?? 'queue');

if ($job === 'queue') {
    $jobs = Queue::pop(10);
    foreach ($jobs as $j) {
        $payload = json_decode($j['payload'], true);
        try {
            processJob($j['job_type'], $payload);
            Queue::done($j['id']);
        } catch (Exception $e) {
            Queue::fail($j['id'], $e->getMessage());
        }
    }
    echo json_encode(['processed_queue' => count($jobs)]);
}

if ($job === 'queue_assign') {
    Assignment::processQueue();
    echo json_encode(['assigned' => true]);
}

if ($job === 'cleanup') {
    DB::connect()->exec("
        DELETE FROM polling_cache WHERE created_at < NOW() - INTERVAL 2 HOUR;
        DELETE FROM webhook_events WHERE processed = 1 AND received_at < NOW() - INTERVAL 7 DAY;
        DELETE FROM job_queue WHERE status IN ('done','failed') AND done_at < NOW() - INTERVAL 3 DAY;
    ");
    echo json_encode(['cleaned' => true]);
}

function processJob(string $type, array $payload): void {
    if ($type === 'process_webhook') {
        // Buscar evento brutro na webhook_events pelo ID no payload
        $pdo = DB::connect();
        $stmt = $pdo->prepare("SELECT payload FROM webhook_events WHERE id = ?");
        $stmt->execute([$payload['webhook_event_id']]);
        $event = $stmt->fetchColumn();
        
        if ($event) {
            $data = json_decode($event, true);
            
            // TODO: extrair mensagens e atualizar contatos
            
            error_log("Processado Webhook ID: " . $payload['webhook_event_id']);
            
            // Marcar webhook como processed
            $pdo->prepare("UPDATE webhook_events SET processed = 1, processed_at = NOW() WHERE id = ?")
                ->execute([$payload['webhook_event_id']]);
        }
    }
    if ($type === 'download_media') {
        // Baixar mídia e salvar na pasta /uploads/
        // TODO: implementar
    }
}

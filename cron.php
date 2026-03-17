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
    $pdo = DB::connect();
    
    if ($type === 'process_webhook') {
        $stmt = $pdo->prepare("SELECT payload FROM webhook_events WHERE id = ?");
        $stmt->execute([$payload['webhook_event_id']]);
        $eventJson = $stmt->fetchColumn();
        
        if ($eventJson) {
            $data = json_decode($eventJson, true);
            
            // Loop na estrutura da Meta API
            foreach ($data['entry'] as $entry) {
                foreach ($entry['changes'] as $change) {
                    $val = $change['value'] ?? [];
                    
                    // 1. Processar Status de Entrega (Read, Delivered, Failed)
                    if (!empty($val['statuses'])) {
                        foreach ($val['statuses'] as $status) {
                            $waId = $status['id'];
                            $state = $status['status']; // sent, delivered, read, failed
                            
                            $pdo->prepare("UPDATE messages SET status = ?, status_updated_at = NOW() WHERE wa_message_id = ?")
                                ->execute([$state, $waId]);
                                
                            // Notificar UI via Polling Cache para dar os "check check" na tela
                            $stmt = $pdo->prepare("SELECT conversation_id, operator_id, id FROM messages WHERE wa_message_id = ?");
                            $stmt->execute([$waId]);
                            $msgInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($msgInfo && $msgInfo['operator_id']) {
                                $pdo->prepare("INSERT INTO polling_cache (operator_id, event_type, conversation_id, message_id, payload) VALUES (?, 'status_update', ?, ?, ?)")
                                    ->execute([$msgInfo['operator_id'], $msgInfo['conversation_id'], $msgInfo['id'], json_encode(['status' => $state])]);
                            }
                        }
                    }
                    
                    // 2. Processar Mensagens Recebidas
                    if (!empty($val['messages']) && !empty($val['contacts'])) {
                        $contact = $val['contacts'][0];
                        $msg     = $val['messages'][0];
                        
                        $phone = $contact['wa_id'];
                        $name = $contact['profile']['name'] ?? 'Contato Desconhecido';
                        $waMsgId = $msg['id'];
                        $msgType = $msg['type'];
                        
                        // Upsert Contato
                        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE phone = ?");
                        $stmt->execute([$phone]);
                        $contactId = $stmt->fetchColumn();
                        
                        if (!$contactId) {
                            $contactId = (string) bin2hex(random_bytes(16));
                            $pdo->prepare("INSERT INTO contacts (id, phone, name, wa_id) VALUES (?, ?, ?, ?)")
                                ->execute([$contactId, $phone, $name, $phone]);
                        } else {
                            $pdo->prepare("UPDATE contacts SET name = ? WHERE id = ? AND name IS NULL")
                                ->execute([$name, $contactId]);
                        }
                        
                        // Upsert Conversa
                        $stmt = $pdo->prepare("SELECT id, operator_id, status FROM conversations WHERE contact_id = ? AND status != 'resolved' AND status != 'expired' LIMIT 1");
                        $stmt->execute([$contactId]);
                        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$conv) {
                            $convId = (string) bin2hex(random_bytes(16));
                            $pdo->prepare("INSERT INTO conversations (id, contact_id, status) VALUES (?, ?, 'waiting')")
                                ->execute([$convId, $contactId]);
                                
                            // Já tenta atribuir se for conversa nova (se não tiver ngm vai p/ fila)
                            Assignment::assign($convId);
                        } else {
                            $convId = $conv['id'];
                            if ($conv['status'] === 'pending') {
                                $pdo->prepare("UPDATE conversations SET status = 'open' WHERE id = ?")->execute([$convId]);
                            }
                        }
                        
                        // Extrair corpo da msg baseado no tipo
                        $body = '';
                        $mediaId = null;
                        if ($msgType === 'text') $body = $msg['text']['body'];
                        elseif ($msgType === 'image') { $body = $msg['image']['caption'] ?? ''; $mediaId = $msg['image']['id']; }
                        elseif ($msgType === 'document') { $body = $msg['document']['filename'] ?? ''; $mediaId = $msg['document']['id']; }
                        elseif ($msgType === 'audio') { $body = 'Áudio'; $mediaId = $msg['audio']['id']; }
                        
                        // Inserir Mensagem
                        $msgIdObj = (string) bin2hex(random_bytes(16));
                        
                        try {
                            $pdo->prepare("
                                INSERT INTO messages (id, conversation_id, wa_message_id, direction, type, body, status, payload, sent_at) 
                                VALUES (?, ?, ?, 'inbound', ?, ?, 'delivered', ?, FROM_UNIXTIME(?))
                            ")->execute([$msgIdObj, $convId, $waMsgId, $msgType, $body, json_encode($msg), $msg['timestamp']]);
                            
                            // Atualizar ultima mensagem da conversa e count de unreads
                            $pdo->prepare("UPDATE conversations SET last_message_preview = ?, last_message_at = NOW(), unread_count = unread_count + 1 WHERE id = ?")
                                ->execute([mb_substr($body ?: $msgType, 0, 50), $convId]);
                                
                            // Notificar Operador que chegou msg nova pro front atualizar
                            $stmt = $pdo->prepare("SELECT operator_id FROM conversations WHERE id = ?");
                            $stmt->execute([$convId]);
                            $opId = $stmt->fetchColumn();
                            
                            if ($opId) {
                                $pdo->prepare("INSERT INTO polling_cache (operator_id, event_type, conversation_id, message_id, payload) VALUES (?, 'new_message', ?, ?, ?)")
                                    ->execute([$opId, $convId, $msgIdObj, json_encode(['message' => [
                                        'id' => $msgIdObj,
                                        'conversation_id' => $convId,
                                        'direction' => 'inbound',
                                        'type' => $msgType,
                                        'body' => $body,
                                        'status' => 'delivered',
                                        'created_at' => date('Y-m-d H:i:s')
                                    ]])]);
                            }
                            
                            // Agendar download de mídia se existir!
                            if ($mediaId) {
                                Queue::push('download_media', ['message_id' => $msgIdObj, 'media_id' => $mediaId, 'media_type' => $msgType]);
                            }
                            
                        } catch (PDOException $e) {
                            // Ignora duplicate entrys (wamid unico) - retransmissões do Mëta
                        }
                    }
                }
            }
            
            $pdo->prepare("UPDATE webhook_events SET processed = 1, processed_at = NOW() WHERE id = ?")
                ->execute([$payload['webhook_event_id']]);
        }
    }
    
    if ($type === 'send_message') {
        // Enviar msg pela API WA
        $phone = $payload['phone'];
        $msgType = $payload['type'];
        
        $res = null;
        if ($msgType === 'text') {
            $res = WhatsApp::sendText($phone, $payload['text']);
        }
        
        if ($res && $res['code'] == 200 && isset($res['body']['messages'][0]['id'])) {
            $wamid = $res['body']['messages'][0]['id'];
            // Atualiza msg local pendente com o WAMID real
            if (!empty($payload['local_message_id'])) {
                $pdo->prepare("UPDATE messages SET wa_message_id = ?, status = 'sent' WHERE id = ?")
                    ->execute([$wamid, $payload['local_message_id']]);
            }
        } else {
            // Falha
            if (!empty($payload['local_message_id'])) {
                $pdo->prepare("UPDATE messages SET status = 'failed', error_message = ? WHERE id = ?")
                    ->execute([json_encode($res), $payload['local_message_id']]);
            }
            throw new Exception("Falha ao enviar WA API: " . json_encode($res));
        }
    }
    
    if ($type === 'download_media') {
        // Lógica de Media
        $binary = WhatsApp::downloadMedia($payload['media_id']);
        if ($binary) {
            $ext = $payload['media_type'] === 'image' ? 'jpeg' : ($payload['media_type'] === 'audio' ? 'ogg' : 'bin');
            $filename = uniqid() . '.' . $ext;
            $filepath = __DIR__ . '/uploads/' . $filename;
            file_put_contents($filepath, $binary);
            
            // Gravar path absoluto logico
            $url = '/uploads/' . $filename;
            
            $pdo->prepare("INSERT INTO message_media (message_id, wa_media_id, media_type, storage_url) VALUES (?, ?, ?, ?)")
                ->execute([$payload['message_id'], $payload['media_id'], $payload['media_type'], $url]);
        } else {
            throw new Exception("Falha de download de midia");
        }
    }
}

# Plano de Implementação — WhatsApp Business Manager
### Stack: PHP 8.x puro + MySQL + HTML/JS (sem custos adicionais)

---

## Sumário

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Estrutura de Pastas](#2-estrutura-de-pastas)
3. [Esquema do Banco de Dados (MySQL)](#3-esquema-do-banco-de-dados-mysql)
4. [Fase 1 — Webhook Gateway](#4-fase-1--webhook-gateway)
5. [Fase 2 — Operadores e Atribuição](#5-fase-2--operadores-e-atribuição)
6. [Fase 3 — Envio de Mensagens e Funcionalidades Nativas](#6-fase-3--envio-de-mensagens-e-funcionalidades-nativas)
7. [Fase 4 — Dashboard Web](#7-fase-4--dashboard-web)
8. [Fase 5 — Supervisão e Gestão](#8-fase-5--supervisão-e-gestão)
9. [Cron Jobs (substituto dos workers)](#9-cron-jobs-substituto-dos-workers)
10. [Variáveis de Configuração](#10-variáveis-de-configuração)
11. [Estimativa de Esforço](#11-estimativa-de-esforço)
12. [Limitações e Compensações](#12-limitações-e-compensações)

---

## 1. Visão Geral da Arquitetura

```
┌──────────────────────────────────────────────────┐
│           WhatsApp Cloud API (Meta)              │
└────────────────────┬─────────────────────────────┘
                     │ HTTPS (webhooks + REST)
                     ▼
┌──────────────────────────────────────────────────┐
│           Hospedagem PHP compartilhada           │
│                                                  │
│  webhook.php ──► processa e salva no MySQL       │
│  api.php     ──► endpoints internos (AJAX)       │
│  cron.php    ──► worker chamado pelo cron job    │
│  send.php    ──► envia mensagens via Cloud API   │
│                                                  │
│  /public/    ──► dashboard HTML + JS             │
└────────────────────┬─────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────┐
│                   MySQL                          │
│  (todas as tabelas: mensagens, operadores,       │
│   conversas, fila de jobs, cache de polling)     │
└──────────────────────────────────────────────────┘
```

### Fluxo de uma mensagem recebida

```
1. Cliente envia mensagem no WhatsApp
2. Meta chama POST /webhook.php
3. webhook.php valida HMAC e salva em webhook_events
4. webhook.php salva em job_queue (status = pending)
5. Cron job chama /cron.php a cada minuto
6. cron.php processa job_queue:
   - Cria/busca contact e conversation
   - Salva message
   - Atribui operador aleatório (se nova conversa)
7. Dashboard JS faz polling em /api.php?action=inbox a cada 3s
8. Operador vê a mensagem e responde
9. send.php chama Cloud API e salva mensagem outbound
```

---

## 2. Estrutura de Pastas

```
/wabmanager/
│
├── config.php                  # Configurações globais (DB, tokens)
├── db.php                      # Conexão PDO com MySQL
├── helpers.php                 # Funções utilitárias
│
├── webhook.php                 # Endpoint público (Meta aponta aqui)
├── cron.php                    # Worker chamado pelo cron job
│
├── api/
│   ├── index.php               # Roteador de endpoints AJAX
│   ├── auth.php                # Login / logout / sessão
│   ├── conversations.php       # Listar, buscar, reatribuir
│   ├── messages.php            # Histórico, envio, polling
│   ├── operators.php           # CRUD de operadores
│   ├── quick_replies.php       # Respostas rápidas
│   ├── templates.php           # Templates HSM
│   └── tags.php                # Etiquetas
│
├── lib/
│   ├── WhatsApp.php            # Cliente da Cloud API (curl)
│   ├── Hmac.php                # Validação de assinatura
│   ├── Assignment.php          # Motor de atribuição aleatória
│   ├── Queue.php               # Gerenciador da job_queue
│   └── Auth.php                # Autenticação de sessão
│
└── public/
    ├── index.html              # Login
    ├── dashboard.html          # Interface principal
    ├── js/
    │   ├── app.js              # Inicialização
    │   ├── inbox.js            # Lista de conversas + polling
    │   ├── chat.js             # Painel de chat + envio
    │   └── admin.js            # Área de gestão
    └── css/
        └── style.css
```

---

## 3. Esquema do Banco de Dados (MySQL)

> Diferenças do esquema PostgreSQL original:
> - `UUID` substituído por `CHAR(36)` com `DEFAULT (UUID())`
> - `TIMESTAMPTZ` substituído por `DATETIME`
> - `JSONB` substituído por `JSON`
> - `SERIAL` substituído por `AUTO_INCREMENT`
> - Adicionada tabela `job_queue` (substitui BullMQ/Redis)
> - Adicionada tabela `polling_cache` (substitui WebSocket)

---

### Tabela: `operators`

```sql
CREATE TABLE operators (
  id             CHAR(36)      NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  name           VARCHAR(120)  NOT NULL,
  email          VARCHAR(255)  NOT NULL UNIQUE,
  password_hash  TEXT          NOT NULL,
  role           ENUM('admin','supervisor','agent') NOT NULL DEFAULT 'agent',
  status         ENUM('online','away','offline')    NOT NULL DEFAULT 'offline',
  avatar_url     TEXT,
  max_concurrent INT           NOT NULL DEFAULT 10,
  is_active      TINYINT(1)    NOT NULL DEFAULT 1,
  last_seen_at   DATETIME,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_email  (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `contacts`

```sql
CREATE TABLE contacts (
  id              CHAR(36)     NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  phone           VARCHAR(30)  NOT NULL UNIQUE COMMENT 'Formato E.164: +5511999999999',
  name            VARCHAR(255),
  profile_pic_url TEXT,
  wa_id           VARCHAR(50),
  blocked         TINYINT(1)   NOT NULL DEFAULT 0,
  notes           TEXT,
  metadata        JSON,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_phone (phone),
  INDEX idx_wa_id (wa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `conversations`

```sql
CREATE TABLE conversations (
  id                   CHAR(36)    NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  contact_id           CHAR(36)    NOT NULL,
  operator_id          CHAR(36)    NULL COMMENT 'NULL = aguardando atribuição',
  status               ENUM('waiting','open','pending','resolved','expired')
                                   NOT NULL DEFAULT 'waiting',
  unread_count         INT         NOT NULL DEFAULT 0,
  last_message_at      DATETIME,
  last_message_preview VARCHAR(255),
  resolved_at          DATETIME,
  resolved_by          CHAR(36)    NULL,
  queue_position       INT         NULL,
  assigned_at          DATETIME,
  created_at           DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (contact_id)   REFERENCES contacts(id),
  FOREIGN KEY (operator_id)  REFERENCES operators(id),
  INDEX idx_contact   (contact_id),
  INDEX idx_operator  (operator_id),
  INDEX idx_status    (status),
  INDEX idx_last_msg  (last_message_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `messages`

```sql
CREATE TABLE messages (
  id                  CHAR(36)    NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  conversation_id     CHAR(36)    NOT NULL,
  wa_message_id       VARCHAR(255) UNIQUE COMMENT 'wamid retornado pela Cloud API',
  direction           ENUM('inbound','outbound') NOT NULL,
  type                ENUM(
                        'text','image','video','audio','document',
                        'sticker','location','contacts','reaction',
                        'interactive','template','unsupported'
                      ) NOT NULL DEFAULT 'text',
  body                TEXT        COMMENT 'Conteúdo para type=text',
  context_message_id  VARCHAR(255) COMMENT 'wa_message_id da msg citada',
  reaction_emoji      VARCHAR(10),
  reaction_target_id  VARCHAR(255),
  latitude            DECIMAL(10,7),
  longitude           DECIMAL(10,7),
  location_name       VARCHAR(255),
  location_address    TEXT,
  payload             JSON        COMMENT 'Objeto completo para tipos complexos',
  status              ENUM('pending','sent','delivered','read','failed')
                                  NOT NULL DEFAULT 'pending',
  status_updated_at   DATETIME,
  error_code          VARCHAR(20),
  error_message       TEXT,
  operator_id         CHAR(36)    NULL,
  sent_at             DATETIME    COMMENT 'Timestamp original da API do WhatsApp',
  created_at          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id),
  INDEX idx_conversation (conversation_id, created_at DESC),
  INDEX idx_wa_id        (wa_message_id),
  INDEX idx_status       (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `message_media`

```sql
CREATE TABLE message_media (
  id             CHAR(36)    NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  message_id     CHAR(36)    NOT NULL,
  wa_media_id    VARCHAR(255),
  media_type     ENUM('image','video','audio','document','sticker') NOT NULL,
  mime_type      VARCHAR(100),
  file_name      VARCHAR(255),
  file_size      BIGINT      COMMENT 'bytes',
  storage_url    TEXT        COMMENT 'URL no storage após download',
  thumbnail_url  TEXT,
  duration_secs  INT,
  width          INT,
  height         INT,
  caption        TEXT,
  downloaded_at  DATETIME,
  created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `assignment_log`

```sql
CREATE TABLE assignment_log (
  id               CHAR(36)   NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  conversation_id  CHAR(36)   NOT NULL,
  from_operator_id CHAR(36)   NULL COMMENT 'NULL = atribuição inicial da fila',
  to_operator_id   CHAR(36)   NULL,
  reason           ENUM('auto_random','manual','transfer','requeue','offline')
                              NOT NULL DEFAULT 'auto_random',
  note             TEXT,
  assigned_by      CHAR(36)   NULL COMMENT 'NULL = sistema automático',
  created_at       DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id),
  INDEX idx_conversation (conversation_id),
  INDEX idx_operator     (to_operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `job_queue` ⭐ (substitui BullMQ/Redis)

```sql
CREATE TABLE job_queue (
  id           CHAR(36)    NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  job_type     VARCHAR(50) NOT NULL
               COMMENT 'process_webhook | send_message | download_media',
  payload      JSON        NOT NULL,
  status       ENUM('pending','processing','done','failed')
                           NOT NULL DEFAULT 'pending',
  attempts     INT         NOT NULL DEFAULT 0,
  max_attempts INT         NOT NULL DEFAULT 5,
  error        TEXT,
  scheduled_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at   DATETIME,
  done_at      DATETIME,
  created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status    (status, scheduled_at),
  INDEX idx_pending   (status) -- usado pelo cron para buscar pendentes
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `polling_cache` ⭐ (substitui WebSocket)

```sql
CREATE TABLE polling_cache (
  id              CHAR(36)    NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  operator_id     CHAR(36)    NOT NULL,
  event_type      VARCHAR(50) NOT NULL
                  COMMENT 'new_message | status_update | new_conversation | assigned',
  conversation_id CHAR(36)    NULL,
  message_id      CHAR(36)    NULL,
  payload         JSON,
  seen            TINYINT(1)  NOT NULL DEFAULT 0,
  created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_operator_unseen (operator_id, seen, created_at),
  INDEX idx_cleanup         (created_at)  -- para deletar eventos antigos
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> O dashboard JS faz polling em `/api/index.php?action=events` a cada 3 segundos.
> O servidor retorna eventos não vistos e os marca como `seen = 1`.
> Eventos com mais de 1 hora são deletados pelo cron de limpeza.

---

### Tabela: `quick_replies`

```sql
CREATE TABLE quick_replies (
  id          CHAR(36)     NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  title       VARCHAR(120) NOT NULL,
  shortcut    VARCHAR(50)  NOT NULL UNIQUE COMMENT 'Palavra-chave após /',
  content     TEXT         NOT NULL,
  category    VARCHAR(80),
  is_global   TINYINT(1)   NOT NULL DEFAULT 1,
  operator_id CHAR(36)     NULL,
  created_by  CHAR(36)     NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_shortcut (shortcut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `templates`

```sql
CREATE TABLE templates (
  id              CHAR(36)    NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  wa_template_id  VARCHAR(255) UNIQUE,
  name            VARCHAR(255) NOT NULL,
  language        VARCHAR(10)  NOT NULL DEFAULT 'pt_BR',
  category        ENUM('MARKETING','UTILITY','AUTHENTICATION') NOT NULL,
  status          ENUM('APPROVED','PENDING','REJECTED','DISABLED') NOT NULL DEFAULT 'PENDING',
  header_type     ENUM('TEXT','IMAGE','VIDEO','DOCUMENT') NULL,
  header_content  TEXT,
  body            TEXT         NOT NULL,
  footer          TEXT,
  buttons         JSON,
  variables_count INT          NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `tags` e relacionamentos

```sql
CREATE TABLE tags (
  id         CHAR(36)    NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  name       VARCHAR(80) NOT NULL UNIQUE,
  color      VARCHAR(7)  NOT NULL DEFAULT '#6B7280',
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE conversation_tags (
  conversation_id CHAR(36)  NOT NULL,
  tag_id          CHAR(36)  NOT NULL,
  tagged_by       CHAR(36)  NULL,
  tagged_at       DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, tag_id),
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id)          REFERENCES tags(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contact_tags (
  contact_id CHAR(36)  NOT NULL,
  tag_id     CHAR(36)  NOT NULL,
  tagged_at  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (contact_id, tag_id),
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id)     REFERENCES tags(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `conversation_notes`

```sql
CREATE TABLE conversation_notes (
  id              CHAR(36)  NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  conversation_id CHAR(36)  NOT NULL,
  operator_id     CHAR(36)  NOT NULL,
  content         TEXT      NOT NULL,
  created_at      DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id),
  INDEX idx_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `webhook_events`

```sql
CREATE TABLE webhook_events (
  id               CHAR(36)    NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  event_type       VARCHAR(80) NOT NULL,
  wa_message_id    VARCHAR(255),
  phone_number_id  VARCHAR(50),
  payload          JSON        NOT NULL,
  processed        TINYINT(1)  NOT NULL DEFAULT 0,
  processed_at     DATETIME,
  error            TEXT,
  received_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_unprocessed (processed, received_at),
  INDEX idx_wa_msg      (wa_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Tabela: `operator_sessions`

```sql
CREATE TABLE operator_sessions (
  id           CHAR(36)    NOT NULL DEFAULT (UUID()) PRIMARY KEY,
  operator_id  CHAR(36)    NOT NULL,
  token        VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token de sessão (hash SHA256)',
  user_agent   TEXT,
  ip_address   VARCHAR(45),
  expires_at   DATETIME    NOT NULL,
  created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at   DATETIME    NULL,
  FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE,
  INDEX idx_token    (token),
  INDEX idx_operator (operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Views MySQL

```sql
-- Inbox por operador
CREATE OR REPLACE VIEW operator_inbox AS
SELECT
  c.id                   AS conversation_id,
  c.status,
  c.unread_count,
  c.last_message_at,
  c.last_message_preview,
  c.operator_id,
  ct.name                AS contact_name,
  ct.phone               AS contact_phone,
  ct.profile_pic_url
FROM conversations c
JOIN contacts ct ON ct.id = c.contact_id
WHERE c.status IN ('open','pending','waiting');

-- Métricas 24h por operador
CREATE OR REPLACE VIEW operator_metrics_24h AS
SELECT
  o.id                                                          AS operator_id,
  o.name,
  COUNT(c.id)                                                   AS total_conversations,
  SUM(c.status = 'open')                                        AS open_conversations,
  SUM(c.status = 'resolved')                                    AS resolved_conversations,
  ROUND(AVG(TIMESTAMPDIFF(MINUTE, c.created_at, c.assigned_at))) AS avg_wait_minutes,
  ROUND(AVG(TIMESTAMPDIFF(MINUTE, c.assigned_at, c.resolved_at))) AS avg_resolution_minutes
FROM operators o
LEFT JOIN conversations c
  ON c.operator_id = o.id
  AND c.created_at >= NOW() - INTERVAL 24 HOUR
GROUP BY o.id, o.name;
```

---

## 4. Fase 1 — Webhook Gateway

**Duração estimada:** 2 semanas

### 4.1 `webhook.php` — ponto de entrada da Meta

```php
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
```

### 4.2 `lib/Hmac.php`

```php
<?php
class Hmac {
    public static function validate(string $rawBody, string $signature): bool {
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, WA_APP_SECRET);
        return hash_equals($expected, $signature);
    }
}
```

### 4.3 `lib/Queue.php`

```php
<?php
class Queue {
    public static function push(string $jobType, array $payload): void {
        $pdo = DB::connect();
        $stmt = $pdo->prepare("
            INSERT INTO job_queue (job_type, payload, status)
            VALUES (?, ?, 'pending')
        ");
        $stmt->execute([$jobType, json_encode($payload)]);
    }

    public static function pop(int $limit = 10): array {
        $pdo = DB::connect();
        // Marcar como 'processing' e retornar atomicamente
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT * FROM job_queue
            WHERE status = 'pending'
              AND attempts < max_attempts
              AND scheduled_at <= NOW()
            ORDER BY created_at ASC
            LIMIT ?
            FOR UPDATE SKIP LOCKED
        ");
        $stmt->execute([$limit]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($jobs as $job) {
            $pdo->prepare("
                UPDATE job_queue
                SET status = 'processing', started_at = NOW(), attempts = attempts + 1
                WHERE id = ?
            ")->execute([$job['id']]);
        }
        $pdo->commit();
        return $jobs;
    }

    public static function done(string $jobId): void {
        DB::connect()->prepare("
            UPDATE job_queue SET status = 'done', done_at = NOW() WHERE id = ?
        ")->execute([$jobId]);
    }

    public static function fail(string $jobId, string $error): void {
        $pdo = DB::connect();
        // Se excedeu tentativas, marca como failed; senão volta para pending com delay
        $pdo->prepare("
            UPDATE job_queue
            SET status = IF(attempts >= max_attempts, 'failed', 'pending'),
                error = ?,
                scheduled_at = DATE_ADD(NOW(), INTERVAL (attempts * 30) SECOND)
            WHERE id = ?
        ")->execute([$error, $jobId]);
    }
}
```

---

## 5. Fase 2 — Operadores e Atribuição

**Duração estimada:** 2 semanas

### 5.1 `lib/Assignment.php`

```php
<?php
class Assignment {

    /**
     * Atribui uma conversa a um operador aleatório disponível.
     * Retorna o operator_id ou null se nenhum disponível (vai para fila).
     */
    public static function assign(string $conversationId): ?string {
        $pdo = DB::connect();

        // Buscar operadores elegíveis (online + abaixo do limite)
        $stmt = $pdo->prepare("
            SELECT o.id
            FROM operators o
            WHERE o.is_active = 1
              AND o.status = 'online'
              AND (
                SELECT COUNT(*) FROM conversations c
                WHERE c.operator_id = o.id
                  AND c.status IN ('open','pending')
              ) < o.max_concurrent
            ORDER BY RAND()
            LIMIT 1
        ");
        $stmt->execute();
        $operator = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$operator) {
            // Sem operadores disponíveis — coloca na fila
            self::enqueue($conversationId);
            return null;
        }

        $operatorId = $operator['id'];

        // Atribuição em transação para evitar race condition
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE conversations
                SET operator_id = ?, status = 'open', assigned_at = NOW()
                WHERE id = ?
            ")->execute([$operatorId, $conversationId]);

            $pdo->prepare("
                INSERT INTO assignment_log (conversation_id, to_operator_id, reason)
                VALUES (?, ?, 'auto_random')
            ")->execute([$conversationId, $operatorId]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Notificar operador via polling_cache
        self::notifyOperator($operatorId, $conversationId);

        return $operatorId;
    }

    private static function enqueue(string $conversationId): void {
        $pdo = DB::connect();
        // Calcular próxima posição na fila
        $pos = $pdo->query("
            SELECT COALESCE(MAX(queue_position), 0) + 1 FROM conversations WHERE status = 'waiting'
        ")->fetchColumn();

        $pdo->prepare("
            UPDATE conversations
            SET status = 'waiting', queue_position = ?
            WHERE id = ?
        ")->execute([$pos, $conversationId]);
    }

    private static function notifyOperator(string $operatorId, string $conversationId): void {
        $pdo = DB::connect();
        $pdo->prepare("
            INSERT INTO polling_cache (operator_id, event_type, conversation_id)
            VALUES (?, 'new_conversation', ?)
        ")->execute([$operatorId, $conversationId]);
    }

    /**
     * Tenta reatribuir conversas em fila (chamado pelo cron).
     */
    public static function processQueue(): void {
        $pdo = DB::connect();
        $waiting = $pdo->query("
            SELECT id FROM conversations
            WHERE status = 'waiting'
            ORDER BY queue_position ASC
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($waiting as $convId) {
            $result = self::assign($convId);
            if (!$result) break; // Sem operadores disponíveis, parar
        }
    }
}
```

---

## 6. Fase 3 — Envio de Mensagens e Funcionalidades Nativas

**Duração estimada:** 3 semanas

### 6.1 `lib/WhatsApp.php` — cliente da Cloud API

```php
<?php
class WhatsApp {

    private static function request(array $body): array {
        $url = 'https://graph.facebook.com/v19.0/' . WA_PHONE_NUMBER_ID . '/messages';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . WA_ACCESS_TOKEN,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $httpCode, 'body' => json_decode($response, true)];
    }

    public static function sendText(string $to, string $text, ?string $contextId = null): array {
        $body = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $text],
        ];
        if ($contextId) $body['context'] = ['message_id' => $contextId];
        return self::request($body);
    }

    public static function sendImage(string $to, string $mediaId, ?string $caption = null): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'image',
            'image' => array_filter(['id' => $mediaId, 'caption' => $caption]),
        ]);
    }

    public static function sendDocument(string $to, string $mediaId, string $filename): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'document',
            'document' => ['id' => $mediaId, 'filename' => $filename],
        ]);
    }

    public static function sendAudio(string $to, string $mediaId): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'audio',
            'audio' => ['id' => $mediaId],
        ]);
    }

    public static function sendVideo(string $to, string $mediaId, ?string $caption = null): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'video',
            'video' => array_filter(['id' => $mediaId, 'caption' => $caption]),
        ]);
    }

    public static function sendLocation(string $to, float $lat, float $lng, string $name = '', string $address = ''): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'location',
            'location' => ['latitude' => $lat, 'longitude' => $lng, 'name' => $name, 'address' => $address],
        ]);
    }

    public static function sendReaction(string $to, string $targetMessageId, string $emoji): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'reaction',
            'reaction' => ['message_id' => $targetMessageId, 'emoji' => $emoji],
        ]);
    }

    public static function sendButtons(string $to, string $bodyText, array $buttons): array {
        // $buttons = [['id' => 'btn1', 'title' => 'Sim'], ...]  — máx 3
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => [
                    'buttons' => array_map(fn($b) => [
                        'type'  => 'reply',
                        'reply' => ['id' => $b['id'], 'title' => $b['title']],
                    ], $buttons),
                ],
            ],
        ]);
    }

    public static function sendTemplate(string $to, string $templateName, string $language, array $components = []): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'template',
            'template' => [
                'name'       => $templateName,
                'language'   => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Download de mídia recebida (retorna binário)
     */
    public static function downloadMedia(string $mediaId): ?string {
        // 1. Buscar URL da mídia
        $url = 'https://graph.facebook.com/v19.0/' . $mediaId;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . WA_ACCESS_TOKEN],
        ]);
        $resp    = json_decode(curl_exec($ch), true);
        $mediaUrl = $resp['url'] ?? null;
        curl_close($ch);

        if (!$mediaUrl) return null;

        // 2. Baixar o arquivo
        $ch = curl_init($mediaUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . WA_ACCESS_TOKEN],
        ]);
        $binary = curl_exec($ch);
        curl_close($ch);

        return $binary ?: null;
    }
}
```

---

## 7. Fase 4 — Dashboard Web

**Duração estimada:** 3 semanas

### 7.1 Polling de eventos (substitui WebSocket)

O frontend JS faz uma requisição a cada 3 segundos para `/api/index.php?action=events`. O servidor retorna os eventos novos da `polling_cache` para aquele operador.

```javascript
// js/inbox.js
const POLL_INTERVAL = 3000; // 3 segundos

async function pollEvents() {
  try {
    const res  = await fetch('/api/index.php?action=events');
    const data = await res.json();

    data.events.forEach(event => {
      if (event.type === 'new_message') {
        appendMessage(event.conversation_id, event.payload);
        updateInboxPreview(event.conversation_id);
      }
      if (event.type === 'new_conversation') {
        prependConversation(event.conversation_id);
      }
      if (event.type === 'status_update') {
        updateMessageStatus(event.message_id, event.payload.status);
      }
    });
  } catch (e) {
    console.warn('Polling falhou:', e);
  }
}

setInterval(pollEvents, POLL_INTERVAL);
pollEvents(); // chamada imediata ao carregar
```

### 7.2 Endpoint de polling no backend

```php
// api/index.php — action=events
case 'events':
    $operatorId = Auth::requireSession();
    $pdo = DB::connect();

    $stmt = $pdo->prepare("
        SELECT id, event_type, conversation_id, message_id, payload
        FROM polling_cache
        WHERE operator_id = ? AND seen = 0
        ORDER BY created_at ASC
        LIMIT 50
    ");
    $stmt->execute([$operatorId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marcar como vistos
    if ($events) {
        $ids = implode(',', array_map(fn($e) => $pdo->quote($e['id']), $events));
        $pdo->exec("UPDATE polling_cache SET seen = 1 WHERE id IN ($ids)");
    }

    foreach ($events as &$e) {
        $e['payload'] = json_decode($e['payload'], true);
    }

    echo json_encode(['events' => $events]);
    break;
```

### 7.3 Estrutura do dashboard HTML

```
Login (public/index.html)
  └── POST → /api/index.php?action=login
      └── Redireciona para public/dashboard.html

Dashboard (public/dashboard.html)
  ├── Coluna esquerda: lista de conversas (polling a cada 3s)
  ├── Coluna central: chat ativo
  │   ├── Histórico (paginado por cursor)
  │   ├── Campo de mensagem (texto, anexo, emoji, atalho /)
  │   └── Indicador de status (pending/sent/delivered/read/failed)
  └── Coluna direita: painel do contato
      ├── Dados do contato
      ├── Tags
      ├── Notas internas
      └── Histórico de conversas anteriores
```

---

## 8. Fase 5 — Supervisão e Gestão

**Duração estimada:** 2 semanas

### 8.1 Painel do supervisor

Acessível para roles `admin` e `supervisor`. Mostra:

- Fila de espera com tempo aguardando
- Conversas abertas por operador
- Botão de reatribuição manual
- Métricas da view `operator_metrics_24h`
- Exportação CSV de conversas por período

### 8.2 Reatribuição manual

```php
// api/conversations.php — action=reassign
$supervisorId   = Auth::requireRole(['admin','supervisor']);
$conversationId = $_POST['conversation_id'];
$toOperatorId   = $_POST['operator_id'];

$pdo = DB::connect();
$conv = $pdo->prepare("SELECT * FROM conversations WHERE id = ?")->execute([$conversationId])->fetch();

$pdo->beginTransaction();
$pdo->prepare("
    UPDATE conversations
    SET operator_id = ?, assigned_at = NOW()
    WHERE id = ?
")->execute([$toOperatorId, $conversationId]);

$pdo->prepare("
    INSERT INTO assignment_log
      (conversation_id, from_operator_id, to_operator_id, reason, note, assigned_by)
    VALUES (?, ?, ?, 'manual', ?, ?)
")->execute([
    $conversationId,
    $conv['operator_id'],
    $toOperatorId,
    $_POST['note'] ?? null,
    $supervisorId
]);

// Notificar novo operador via polling_cache
$pdo->prepare("
    INSERT INTO polling_cache (operator_id, event_type, conversation_id)
    VALUES (?, 'assigned', ?)
")->execute([$toOperatorId, $conversationId]);

$pdo->commit();
echo json_encode(['ok' => true]);
```

---

## 9. Cron Jobs (substituto dos workers)

Configure no painel de hospedagem (cPanel > Cron Jobs) ou via SSH:

```bash
# A cada minuto — processar fila de jobs pendentes
* * * * * curl -s "https://seudominio.com/wabmanager/cron.php?key=SEU_CRON_SECRET&job=queue"

# A cada minuto — tentar reatribuir conversas em fila de espera
* * * * * curl -s "https://seudominio.com/wabmanager/cron.php?key=SEU_CRON_SECRET&job=queue_assign"

# A cada hora — limpar eventos de polling antigos
0 * * * * curl -s "https://seudominio.com/wabmanager/cron.php?key=SEU_CRON_SECRET&job=cleanup"
```

### `cron.php`

```php
<?php
require_once 'config.php';
require_once 'db.php';
require_once 'lib/Queue.php';
require_once 'lib/Assignment.php';
require_once 'lib/WhatsApp.php';

// Proteção básica: só aceita se vier com a chave correta
if (($_GET['key'] ?? '') !== CRON_SECRET_KEY) {
    http_response_code(403);
    exit;
}

$job = $_GET['job'] ?? 'queue';

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
}

if ($job === 'queue_assign') {
    Assignment::processQueue();
}

if ($job === 'cleanup') {
    DB::connect()->exec("
        DELETE FROM polling_cache WHERE created_at < NOW() - INTERVAL 2 HOUR;
        DELETE FROM webhook_events WHERE processed = 1 AND received_at < NOW() - INTERVAL 7 DAY;
        DELETE FROM job_queue WHERE status IN ('done','failed') AND done_at < NOW() - INTERVAL 3 DAY;
    ");
}

function processJob(string $type, array $payload): void {
    if ($type === 'process_webhook') {
        // Buscar evento, processar mensagem/status/contacts
        // ... lógica completa de processamento
    }
    if ($type === 'download_media') {
        // Baixar mídia e salvar na pasta /uploads/
        // ... lógica de download
    }
}
```

---

## 10. Variáveis de Configuração

### `config.php`

```php
<?php
// WhatsApp Cloud API
define('WA_PHONE_NUMBER_ID',    getenv('WA_PHONE_NUMBER_ID')    ?: 'seu_phone_id');
define('WA_ACCESS_TOKEN',       getenv('WA_ACCESS_TOKEN')       ?: 'seu_token');
define('WA_APP_SECRET',         getenv('WA_APP_SECRET')         ?: 'seu_app_secret');
define('WA_WEBHOOK_VERIFY_TOKEN', getenv('WA_WEBHOOK_VERIFY_TOKEN') ?: 'meu_token_secreto');

// Banco de dados
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'wabmanager');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Segurança
define('CRON_SECRET_KEY', getenv('CRON_SECRET_KEY') ?: 'chave_cron_aqui');
define('SESSION_SECRET',  getenv('SESSION_SECRET')  ?: 'chave_sessao_aqui');

// Storage local (mídia recebida)
define('MEDIA_PATH',    __DIR__ . '/uploads/');
define('MEDIA_URL',     'https://seudominio.com/wabmanager/uploads/');

// Configurações gerais
define('POLL_CLEANUP_HOURS',  2);   // Limpar eventos de polling após N horas
define('MAX_CONCURRENT_DEFAULT', 10); // Conversas simultâneas por operador
```

### `db.php`

```php
<?php
class DB {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (!self::$instance) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }
}
```

---

## 11. Estimativa de Esforço

| Fase | Descrição | Duração |
|------|-----------|---------|
| 1 | Webhook Gateway (PHP) | 2 semanas |
| 2 | Operadores e atribuição | 2 semanas |
| 3 | Envio e tipos de mensagens | 3 semanas |
| 4 | Dashboard HTML/JS | 3 semanas |
| 5 | Supervisão e gestão | 2 semanas |
| **Total** | | **~12 semanas** |

---

## 12. Limitações e Compensações

| Limitação | Causa | Compensação |
|-----------|-------|-------------|
| Sem tempo real instantâneo | Sem WebSocket em PHP compartilhado | Polling a cada 3s (delay máximo 3s) |
| Sem workers permanentes | Sem processos background | Cron job a cada minuto |
| Mensagem pode demorar ~1min para ser processada | Cron não roda em tempo real | `webhook.php` já salva o evento bruto; o delay é só no processamento interno |
| Sem Redis | Hospedagem compartilhada | `job_queue` e `polling_cache` no MySQL |
| Armazenamento de mídia limitado | Espaço em disco da hospedagem | Salvar apenas miniatura; link direto para URL da Meta (válida por 5 dias) |
| `SKIP LOCKED` pode não existir em MySQL < 8.0 | Versão antiga | Usar `SELECT ... FOR UPDATE` com verificação manual de status |

> **Nota sobre a mídia:** a URL de mídia retornada pela Cloud API expira em 5 dias.
> Se precisar de persistência maior, baixe o arquivo com `WhatsApp::downloadMedia()`
> e salve em `/uploads/` na própria hospedagem.

---

*Documento gerado em: 2026-03-17*
*Versão: 2.0 — Adaptado para PHP puro + MySQL (sem custos adicionais)*

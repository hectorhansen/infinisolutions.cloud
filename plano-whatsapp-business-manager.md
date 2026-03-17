# Plano de Implementação — WhatsApp Business Manager

Sistema de gerenciamento de conversas via WhatsApp Business Cloud API (Meta), com múltiplos operadores, atribuição aleatória automática e suporte a todas as funcionalidades nativas da plataforma.

---

## Sumário

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Esquema do Banco de Dados](#2-esquema-do-banco-de-dados)
3. [Fase 1 — Integração com a API Oficial](#3-fase-1--integração-com-a-api-oficial)
4. [Fase 2 — Operadores e Motor de Atribuição](#4-fase-2--operadores-e-motor-de-atribuição)
5. [Fase 3 — Interface do Operador e Funcionalidades Nativas](#5-fase-3--interface-do-operador-e-funcionalidades-nativas)
6. [Fase 4 — Supervisão e Gestão](#6-fase-4--supervisão-e-gestão)
7. [Fase 5 — Infraestrutura e Produção](#7-fase-5--infraestrutura-e-produção)
8. [Estimativa de Esforço](#8-estimativa-de-esforço)
9. [Riscos e Mitigações](#9-riscos-e-mitigações)

---

## 1. Visão Geral da Arquitetura

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENTE (usuário WA)                     │
└──────────────────────────────┬──────────────────────────────────┘
                               │ HTTPS
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│              WhatsApp Cloud API (Meta for Developers)            │
└──────────┬───────────────────────────────────────┬──────────────┘
           │ Webhooks (eventos)                    │ REST (envio)
           ▼                                       ▼
┌─────────────────────┐              ┌─────────────────────────────┐
│   Webhook Gateway   │              │       Core API Server       │
│  (verificação +     │──────────►   │    Node.js / NestJS         │
│   validação HMAC)   │              │                             │
└─────────────────────┘              └──────┬──────────────────────┘
                                            │
              ┌─────────────────────────────┼──────────────────────┐
              │                             │                      │
              ▼                             ▼                      ▼
┌─────────────────────┐  ┌──────────────────────┐  ┌─────────────────────┐
│  Assignment Engine  │  │  Conversation Manager │  │  Notification Svc   │
│  (atribuição rnd.)  │  │  (estado e histórico) │  │  (push / e-mail)    │
└──────────┬──────────┘  └──────────┬───────────┘  └──────────┬──────────┘
           │                        │                          │
           ▼                        ▼                          ▼
┌──────────────────┐   ┌────────────────────┐   ┌─────────────────────────┐
│   PostgreSQL     │   │    Redis Cache     │   │   S3 / Object Store     │
│  (dados princ.)  │   │  (sessões, filas)  │   │   (mídia e arquivos)    │
└──────────────────┘   └────────────────────┘   └─────────────────────────┘
           ▲                        ▲
           │     WebSocket          │
           └────────────────────────┘
                        │
                        ▼
        ┌───────────────────────────────┐
        │      Dashboard Web (React)    │
        │   Interface dos operadores    │
        └───────────────────────────────┘
```

### Stack Tecnológico

| Camada | Tecnologia |
|--------|-----------|
| Backend | Node.js + NestJS |
| Frontend | React + Vite + TailwindCSS |
| Banco de dados | PostgreSQL 15+ |
| Cache e filas | Redis + BullMQ |
| Tempo real | Socket.IO (WebSocket) |
| Armazenamento de mídia | AWS S3 ou Cloudflare R2 |
| Autenticação | JWT (access + refresh token) |
| Infraestrutura | Docker + Railway / AWS ECS |

---

## 2. Esquema do Banco de Dados

### Diagrama de Entidade-Relacionamento

```
operators ──────────────────────────┐
    │ 1                             │
    │                               │
    │ N                             │
conversations ──────── N ── contacts
    │ 1                             │ 1
    │                               │
    │ N                             │ N
messages                        contact_tags
    │ 1
    │
    │ N
message_media

operators ──── N ── quick_replies
operators ──── N ── conversation_notes (via conversation_id)
conversations ── N ── conversation_notes
conversations ── N ── conversation_tags
templates ──── N ── template_variables
```

---

### Tabela: `operators`

Representa os usuários do sistema (agentes, supervisores e administradores).

```sql
CREATE TABLE operators (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name             VARCHAR(120)  NOT NULL,
  email            VARCHAR(255)  NOT NULL UNIQUE,
  password_hash    TEXT          NOT NULL,
  role             VARCHAR(20)   NOT NULL DEFAULT 'agent'
                     CHECK (role IN ('admin', 'supervisor', 'agent')),
  status           VARCHAR(20)   NOT NULL DEFAULT 'offline'
                     CHECK (status IN ('online', 'away', 'offline')),
  avatar_url       TEXT,
  max_concurrent   INT           NOT NULL DEFAULT 10,
  -- Quantas conversas simultâneas este operador pode ter
  is_active        BOOLEAN       NOT NULL DEFAULT TRUE,
  last_seen_at     TIMESTAMPTZ,
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_operators_status ON operators (status) WHERE is_active = TRUE;
CREATE INDEX idx_operators_email  ON operators (email);
```

**Campos relevantes:**
- `role`: `admin` vê tudo; `supervisor` pode reatribuir; `agent` vê só suas conversas
- `max_concurrent`: limite de conversas ativas por operador (usado no filtro de elegibilidade da atribuição)
- `status`: apenas operadores `online` recebem novas conversas

---

### Tabela: `contacts`

Representa os usuários finais do WhatsApp.

```sql
CREATE TABLE contacts (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  phone            VARCHAR(30)   NOT NULL UNIQUE,
  -- Formato E.164: +5511999999999
  name             VARCHAR(255),
  -- Nome do perfil do WhatsApp (atualizado automaticamente)
  profile_pic_url  TEXT,
  wa_id            VARCHAR(50),
  -- ID interno retornado pela Cloud API
  blocked          BOOLEAN       NOT NULL DEFAULT FALSE,
  notes            TEXT,
  -- Anotações internas sobre o contato
  metadata         JSONB         NOT NULL DEFAULT '{}',
  -- Dados customizados: CRM ID, segmento, etc.
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_contacts_phone ON contacts (phone);
CREATE INDEX idx_contacts_wa_id ON contacts (wa_id);
```

---

### Tabela: `conversations`

Representa um atendimento. Um contato pode ter múltiplas conversas ao longo do tempo.

```sql
CREATE TABLE conversations (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contact_id       UUID          NOT NULL REFERENCES contacts (id),
  operator_id      UUID          REFERENCES operators (id),
  -- NULL = na fila, aguardando operador disponível
  status           VARCHAR(20)   NOT NULL DEFAULT 'waiting'
                     CHECK (status IN (
                       'waiting',   -- aguardando operador
                       'open',      -- em atendimento
                       'pending',   -- aguardando resposta do cliente
                       'resolved',  -- encerrada pelo operador
                       'expired'    -- janela de 24h expirada sem resolução
                     )),
  channel          VARCHAR(20)   NOT NULL DEFAULT 'whatsapp',
  unread_count     INT           NOT NULL DEFAULT 0,
  -- Mensagens não lidas pelo operador
  last_message_at  TIMESTAMPTZ,
  last_message_preview TEXT,
  -- Cache da última mensagem para exibição na lista
  resolved_at      TIMESTAMPTZ,
  resolved_by      UUID          REFERENCES operators (id),
  queue_position   INT,
  -- Posição na fila de espera (NULL se atribuída)
  assigned_at      TIMESTAMPTZ,
  -- Momento da atribuição ao operador atual
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_conv_contact    ON conversations (contact_id);
CREATE INDEX idx_conv_operator   ON conversations (operator_id);
CREATE INDEX idx_conv_status     ON conversations (status);
CREATE INDEX idx_conv_last_msg   ON conversations (last_message_at DESC);
CREATE INDEX idx_conv_waiting    ON conversations (queue_position)
  WHERE status = 'waiting';
```

---

### Tabela: `messages`

Cada mensagem trocada entre o contato e o operador.

```sql
CREATE TABLE messages (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  conversation_id  UUID          NOT NULL REFERENCES conversations (id),
  wa_message_id    VARCHAR(255)  UNIQUE,
  -- ID retornado pela Cloud API (wamid.xxx)
  direction        VARCHAR(10)   NOT NULL
                     CHECK (direction IN ('inbound', 'outbound')),
  type             VARCHAR(30)   NOT NULL
                     CHECK (type IN (
                       'text', 'image', 'video', 'audio', 'document',
                       'sticker', 'location', 'contacts', 'reaction',
                       'interactive', 'template', 'unsupported'
                     )),

  -- Conteúdo de texto (para type = 'text')
  body             TEXT,

  -- Referência a mensagem citada (reply)
  context_message_id  VARCHAR(255),
  -- wa_message_id da mensagem respondida

  -- Para reactions
  reaction_emoji   VARCHAR(10),
  reaction_target_id VARCHAR(255),
  -- wa_message_id da mensagem que recebeu a reação

  -- Para location
  latitude         DECIMAL(10, 7),
  longitude        DECIMAL(10, 7),
  location_name    VARCHAR(255),
  location_address TEXT,

  -- Conteúdo estruturado (buttons, lists, templates)
  payload          JSONB,
  -- Armazena o objeto completo da mensagem interativa, template, contatos etc.

  -- Status de entrega (outbound)
  status           VARCHAR(20)   NOT NULL DEFAULT 'pending'
                     CHECK (status IN (
                       'pending', 'sent', 'delivered', 'read', 'failed'
                     )),
  status_updated_at TIMESTAMPTZ,
  error_code       VARCHAR(20),
  error_message    TEXT,

  -- Metadados
  operator_id      UUID          REFERENCES operators (id),
  -- NULL para mensagens inbound
  sent_at          TIMESTAMPTZ,
  -- Timestamp da API do WhatsApp
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_msg_conversation  ON messages (conversation_id, created_at DESC);
CREATE INDEX idx_msg_wa_id         ON messages (wa_message_id);
CREATE INDEX idx_msg_context       ON messages (context_message_id);
CREATE INDEX idx_msg_status        ON messages (status) WHERE direction = 'outbound';
```

---

### Tabela: `message_media`

Armazenam referências de mídia vinculadas a mensagens.

```sql
CREATE TABLE message_media (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  message_id       UUID          NOT NULL REFERENCES messages (id) ON DELETE CASCADE,
  wa_media_id      VARCHAR(255),
  -- ID de mídia retornado pela Cloud API
  media_type       VARCHAR(20)   NOT NULL
                     CHECK (media_type IN (
                       'image', 'video', 'audio', 'document', 'sticker'
                     )),
  mime_type        VARCHAR(100),
  file_name        VARCHAR(255),
  file_size        BIGINT,
  -- bytes
  storage_url      TEXT,
  -- URL no S3/R2 após download e re-upload
  thumbnail_url    TEXT,
  duration_secs    INT,
  -- para áudio e vídeo
  width            INT,
  height           INT,
  -- para imagem e vídeo
  caption          TEXT,
  downloaded_at    TIMESTAMPTZ,
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_media_message ON message_media (message_id);
```

---

### Tabela: `assignment_log`

Histórico completo de atribuições e transferências de conversas.

```sql
CREATE TABLE assignment_log (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  conversation_id  UUID          NOT NULL REFERENCES conversations (id),
  from_operator_id UUID          REFERENCES operators (id),
  -- NULL = atribuição inicial (da fila)
  to_operator_id   UUID          REFERENCES operators (id),
  reason           VARCHAR(50)   NOT NULL DEFAULT 'auto_random'
                     CHECK (reason IN (
                       'auto_random',   -- atribuição automática ao entrar
                       'manual',        -- supervisor reatribuiu
                       'transfer',      -- operador transferiu
                       'requeue',       -- devolvido à fila
                       'offline'        -- operador ficou offline
                     )),
  note             TEXT,
  -- Nota interna na transferência
  assigned_by      UUID          REFERENCES operators (id),
  -- NULL = sistema automático
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_assign_conversation ON assignment_log (conversation_id);
CREATE INDEX idx_assign_operator     ON assignment_log (to_operator_id);
```

---

### Tabela: `conversation_notes`

Notas internas visíveis apenas para operadores (não enviadas ao cliente).

```sql
CREATE TABLE conversation_notes (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  conversation_id  UUID          NOT NULL REFERENCES conversations (id),
  operator_id      UUID          NOT NULL REFERENCES operators (id),
  content          TEXT          NOT NULL,
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_notes_conversation ON conversation_notes (conversation_id);
```

---

### Tabela: `quick_replies`

Respostas rápidas pré-cadastradas acessadas pelo operador via atalho `/`.

```sql
CREATE TABLE quick_replies (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  title            VARCHAR(120)  NOT NULL,
  -- Nome exibido na lista de atalhos
  shortcut         VARCHAR(50)   NOT NULL UNIQUE,
  -- Palavra-chave após "/" (ex: "saudacao")
  content          TEXT          NOT NULL,
  -- Texto da resposta (aceita variáveis: {{nome}}, {{protocolo}})
  category         VARCHAR(80),
  is_global        BOOLEAN       NOT NULL DEFAULT TRUE,
  -- FALSE = exclusiva do operador abaixo
  operator_id      UUID          REFERENCES operators (id),
  -- NULL = disponível para todos
  created_by       UUID          NOT NULL REFERENCES operators (id),
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_qr_shortcut  ON quick_replies (shortcut);
CREATE INDEX idx_qr_operator  ON quick_replies (operator_id);
```

---

### Tabela: `templates`

Templates HSM aprovados pela Meta (necessários para iniciar conversas fora da janela de 24h).

```sql
CREATE TABLE templates (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  wa_template_id   VARCHAR(255)  UNIQUE,
  -- ID retornado pela API da Meta
  name             VARCHAR(255)  NOT NULL,
  language         VARCHAR(10)   NOT NULL DEFAULT 'pt_BR',
  category         VARCHAR(50)   NOT NULL
                     CHECK (category IN (
                       'MARKETING', 'UTILITY', 'AUTHENTICATION'
                     )),
  status           VARCHAR(30)   NOT NULL DEFAULT 'PENDING'
                     CHECK (status IN (
                       'APPROVED', 'PENDING', 'REJECTED', 'DISABLED'
                     )),
  header_type      VARCHAR(20)
                     CHECK (header_type IN ('TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT')),
  header_content   TEXT,
  body             TEXT          NOT NULL,
  footer           TEXT,
  buttons          JSONB,
  -- Array de botões (QUICK_REPLY ou CALL_TO_ACTION)
  variables_count  INT           NOT NULL DEFAULT 0,
  -- Quantidade de variáveis {{1}}, {{2}}... no body
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_templates_status ON templates (status);
CREATE INDEX idx_templates_name   ON templates (name);
```

---

### Tabela: `tags`

Sistema de etiquetas para organizar conversas e contatos.

```sql
CREATE TABLE tags (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name             VARCHAR(80)   NOT NULL UNIQUE,
  color            VARCHAR(7)    NOT NULL DEFAULT '#6B7280',
  -- Hex color
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE TABLE conversation_tags (
  conversation_id  UUID NOT NULL REFERENCES conversations (id) ON DELETE CASCADE,
  tag_id           UUID NOT NULL REFERENCES tags (id) ON DELETE CASCADE,
  tagged_by        UUID REFERENCES operators (id),
  tagged_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (conversation_id, tag_id)
);

CREATE TABLE contact_tags (
  contact_id       UUID NOT NULL REFERENCES contacts (id) ON DELETE CASCADE,
  tag_id           UUID NOT NULL REFERENCES tags (id) ON DELETE CASCADE,
  tagged_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (contact_id, tag_id)
);
```

---

### Tabela: `operator_sessions`

Controle de tokens de autenticação.

```sql
CREATE TABLE operator_sessions (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  operator_id      UUID          NOT NULL REFERENCES operators (id) ON DELETE CASCADE,
  refresh_token    TEXT          NOT NULL UNIQUE,
  user_agent       TEXT,
  ip_address       INET,
  expires_at       TIMESTAMPTZ   NOT NULL,
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  revoked_at       TIMESTAMPTZ
);

CREATE INDEX idx_sessions_operator ON operator_sessions (operator_id);
CREATE INDEX idx_sessions_token    ON operator_sessions (refresh_token);
```

---

### Tabela: `webhook_events`

Log de todos os eventos recebidos da Cloud API (útil para debugging e reprocessamento).

```sql
CREATE TABLE webhook_events (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  event_type       VARCHAR(80)   NOT NULL,
  -- Ex: "messages", "statuses", "contacts"
  wa_message_id    VARCHAR(255),
  phone_number_id  VARCHAR(50),
  payload          JSONB         NOT NULL,
  processed        BOOLEAN       NOT NULL DEFAULT FALSE,
  processed_at     TIMESTAMPTZ,
  error            TEXT,
  received_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_webhook_processed ON webhook_events (processed, received_at)
  WHERE processed = FALSE;
CREATE INDEX idx_webhook_wa_msg    ON webhook_events (wa_message_id);
```

---

### Views úteis

```sql
-- Conversas com contagem de mensagens não lidas por operador
CREATE VIEW operator_inbox AS
SELECT
  c.id              AS conversation_id,
  c.status,
  c.unread_count,
  c.last_message_at,
  c.last_message_preview,
  c.operator_id,
  ct.name           AS contact_name,
  ct.phone          AS contact_phone,
  ct.profile_pic_url
FROM conversations c
JOIN contacts ct ON ct.id = c.contact_id
WHERE c.status IN ('open', 'pending', 'waiting');

-- Métricas por operador (últimas 24h)
CREATE VIEW operator_metrics_24h AS
SELECT
  o.id              AS operator_id,
  o.name,
  COUNT(c.id)                                           AS total_conversations,
  COUNT(c.id) FILTER (WHERE c.status = 'open')         AS open_conversations,
  COUNT(c.id) FILTER (WHERE c.status = 'resolved')     AS resolved_conversations,
  ROUND(AVG(
    EXTRACT(EPOCH FROM (c.assigned_at - c.created_at)) / 60
  ))::INT                                               AS avg_wait_minutes,
  ROUND(AVG(
    EXTRACT(EPOCH FROM (c.resolved_at - c.assigned_at)) / 60
  ))::INT                                               AS avg_resolution_minutes
FROM operators o
LEFT JOIN conversations c
  ON c.operator_id = o.id
  AND c.created_at >= NOW() - INTERVAL '24 hours'
GROUP BY o.id, o.name;
```

---

## 3. Fase 1 — Integração com a API Oficial

**Duração estimada:** 3 semanas  
**Pré-requisito:** Conta verificada no Meta Business Suite

### 3.1 Configuração na Meta

1. Criar App no [Meta for Developers](https://developers.facebook.com) do tipo **Business**
2. Adicionar o produto **WhatsApp Business**
3. Verificar o número de telefone (não pode ser o mesmo número no WhatsApp pessoal)
4. Gerar **System User Access Token** permanente (não expira como o token temporário)
5. Registrar o endpoint de webhook com token de verificação personalizado

### 3.2 Webhook Gateway

```
POST /webhook
  → Validar assinatura HMAC-SHA256 (X-Hub-Signature-256)
  → Inserir evento na tabela webhook_events
  → Enfileirar no Redis (BullMQ) para processamento assíncrono
  → Responder 200 OK imediatamente

GET /webhook
  → Verificar hub.mode === 'subscribe'
  → Verificar hub.verify_token === WEBHOOK_VERIFY_TOKEN
  → Responder com hub.challenge
```

### 3.3 Processamento de eventos (worker)

```
Para cada evento na fila:
  1. Identificar tipo (message | status | contacts)
  2. Para "message":
     a. Buscar ou criar contact pelo número
     b. Buscar conversa aberta ou criar nova
     c. Se nova conversa → acionar Assignment Engine
     d. Persistir mensagem na tabela messages
     e. Baixar mídia (se houver) e fazer upload no S3
     f. Emitir evento WebSocket para o operador
  3. Para "status":
     a. Atualizar messages.status (sent/delivered/read/failed)
     b. Emitir atualização de status via WebSocket
  4. Para "contacts":
     a. Atualizar contacts.name e profile_pic_url
  5. Marcar webhook_events.processed = TRUE
```

---

## 4. Fase 2 — Operadores e Motor de Atribuição

**Duração estimada:** 2 semanas

### 4.1 Cadastro de operadores

Endpoints:

```
POST   /operators          → Criar operador (admin)
GET    /operators          → Listar operadores (admin/supervisor)
GET    /operators/:id      → Detalhe
PATCH  /operators/:id      → Atualizar dados
DELETE /operators/:id      → Desativar (soft delete via is_active)
PATCH  /operators/:id/status → Mudar status (online/away/offline)
```

### 4.2 Lógica de atribuição aleatória

```typescript
async function assignConversation(conversationId: string): Promise<string | null> {
  // 1. Buscar operadores elegíveis
  const eligible = await db.query(`
    SELECT o.id
    FROM operators o
    WHERE o.is_active = TRUE
      AND o.status = 'online'
      AND (
        SELECT COUNT(*) FROM conversations c
        WHERE c.operator_id = o.id
          AND c.status IN ('open', 'pending')
      ) < o.max_concurrent
    ORDER BY random()   -- seleção aleatória no nível do banco
    LIMIT 1
  `);

  if (eligible.rows.length === 0) {
    // Nenhum operador disponível → colocar na fila de espera
    await enqueue(conversationId);
    return null;
  }

  const operatorId = eligible.rows[0].id;

  // 2. Atribuir a conversa
  await db.transaction(async (trx) => {
    await trx.query(
      `UPDATE conversations
       SET operator_id = $1, status = 'open', assigned_at = NOW()
       WHERE id = $2`,
      [operatorId, conversationId]
    );

    await trx.query(
      `INSERT INTO assignment_log
         (conversation_id, to_operator_id, reason)
       VALUES ($1, $2, 'auto_random')`,
      [conversationId, operatorId]
    );
  });

  // 3. Notificar operador via WebSocket
  io.to(operatorId).emit('conversation:assigned', { conversationId });

  return operatorId;
}
```

### 4.3 Fila de espera

Quando não há operadores disponíveis, a conversa recebe `status = 'waiting'` e `queue_position` é calculado como o próximo número da fila. Um worker monitora o Redis a cada 10 segundos e tenta reatribuir conversas em espera sempre que um operador ficar online ou liberar vagas.

---

## 5. Fase 3 — Interface do Operador e Funcionalidades Nativas

**Duração estimada:** 4 semanas

### 5.1 Tipos de mensagem suportados

| Tipo | Envio | Recebimento | Notas |
|------|-------|-------------|-------|
| Texto simples | ✅ | ✅ | Formatação: *negrito*, _itálico_, ~riscado~, ```mono``` |
| Imagem | ✅ | ✅ | JPG, PNG, WebP — até 5MB |
| Vídeo | ✅ | ✅ | MP4 — até 16MB |
| Áudio | ✅ | ✅ | MP3, OGG, AAC; player embutido |
| Documento | ✅ | ✅ | PDF, XLS, DOC etc. |
| Sticker | ❌ | ✅ | Recebe; envio não suportado pela API |
| Localização | ✅ | ✅ | Mapa interativo no chat |
| Contato (vCard) | ✅ | ✅ | |
| Reação | ✅ | ✅ | Emoji sobre mensagem específica |
| Resposta citada | ✅ | ✅ | Context com message_id |
| Botões interativos | ✅ | ✅ | Até 3 botões QUICK_REPLY |
| Lista interativa | ✅ | ✅ | Até 10 itens em seções |
| CTA com URL | ✅ | ✅ | Botão com link externo |
| Template HSM | ✅ | — | Para iniciar fora da janela de 24h |

### 5.2 Interface do chat

```
┌─────────────────────┬───────────────────────────┬──────────────────┐
│   Lista de chats    │     Painel de conversa     │  Painel lateral  │
│                     │                            │                  │
│ [ Buscar... ]       │  João Silva ▸ #123         │  Contato         │
│                     │  ────────────────────      │  João Silva      │
│ ● João Silva        │                            │  +55 11 9xxxx    │
│   Oi, preciso de... │  [11:32] João              │                  │
│   há 2 min          │  Oi, preciso de ajuda      │  Tags            │
│                     │  com meu pedido            │  [suporte] [vip] │
│ ● Maria Costa       │                            │                  │
│   Ok, vou verificar │  [11:33] Você              │  Notas internas  │
│   há 5 min          │  Claro! Qual é o número    │  + Adicionar     │
│                     │  do pedido?                │                  │
│ ○ Carlos (fila)     │                            │  Histórico       │
│   Aguardando...     │  ────────────────────      │  3 conversas     │
│                     │  [ Escreva uma mensagem ]  │  anteriores      │
│                     │  [📎] [😊] [⚡] [Enviar]  │                  │
└─────────────────────┴───────────────────────────┴──────────────────┘
```

### 5.3 Atalhos de resposta rápida

O operador digita `/` no campo de mensagem e vê a lista de respostas cadastradas filtradas em tempo real. Selecionando uma, o texto é preenchido no campo (com variáveis destacadas para substituição manual antes do envio).

### 5.4 Status de mensagens

Todas as mensagens outbound exibem indicadores de status em tempo real via WebSocket:

- `⏳` Pendente (enfileirada localmente)
- `✓` Enviada (confirmada pela API)
- `✓✓` Entregue (webhook `delivered`)
- `✓✓` (azul) Lida (webhook `read`)
- `✗` Falhou (exibe código de erro)

---

## 6. Fase 4 — Supervisão e Gestão

**Duração estimada:** 2 semanas

### 6.1 Reatribuição manual

Supervisores e admins podem reatribuir qualquer conversa aberta para outro operador disponível. O operador receptor recebe notificação push e WebSocket. O histórico completo da conversa segue junto. Um campo de nota opcional é exibido no painel de informações para o novo operador.

### 6.2 Monitoramento em tempo real

```
Dashboard do supervisor:
- Contador de conversas por status (waiting/open/pending/resolved)
- Conversas por operador (com tempo de atendimento atual)
- Tempo médio de primeira resposta (últimas 1h / 24h / 7d)
- Fila de espera com posição e tempo aguardando
- Gráfico de volume por hora (últimas 24h)
```

### 6.3 Mensagens automáticas configuráveis

| Gatilho | Exemplo de mensagem |
|---------|---------------------|
| Nova conversa recebida | *"Olá! Em breve um de nossos agentes irá atendê-lo."* |
| Fora do horário de atendimento | *"Nosso atendimento funciona das 9h às 18h."* |
| Na fila de espera | *"Você é o #{{posicao}} na fila. Aguarde!"* |
| Conversa encerrada | *"Seu atendimento foi concluído. Obrigado!"* |

### 6.4 Relatórios exportáveis

- Exportação em CSV/XLSX de conversas por período
- Relatório de desempenho por operador
- Volume de mensagens por dia/semana/mês
- Tempo médio de resolução por categoria/tag

---

## 7. Fase 5 — Infraestrutura e Produção

**Duração estimada:** 2 semanas

### 7.1 Autenticação

```
POST /auth/login          → Retorna access_token (15min) + refresh_token (30d)
POST /auth/refresh        → Troca refresh_token por novo par de tokens
POST /auth/logout         → Revoga refresh_token (revoked_at = NOW())
```

Todos os endpoints protegidos exigem `Authorization: Bearer <access_token>`.

### 7.2 Segurança do webhook

```typescript
// Validação obrigatória em todo evento recebido
function validateWebhookSignature(rawBody: Buffer, signature: string): boolean {
  const expected = crypto
    .createHmac('sha256', process.env.WHATSAPP_APP_SECRET)
    .update(rawBody)
    .digest('hex');
  return crypto.timingSafeEqual(
    Buffer.from(`sha256=${expected}`),
    Buffer.from(signature)
  );
}
```

### 7.3 Rate limiting e retry

A Cloud API limita envios por número de telefone conforme o tier:

| Tier | Limite | Como subir |
|------|--------|------------|
| 1 | 1.000 conversas únicas/dia | Verificar empresa na Meta |
| 2 | 10.000 conversas/dia | Volume de mensagens ≥ 1.000 em 30d |
| 3 | 100.000 conversas/dia | Volume de mensagens ≥ 10.000 em 30d |
| 4 | Ilimitado | Volume ≥ 100.000 em 30d |

Implemente retry com backoff exponencial para erros `429` e `500`:

```
Tentativa 1: imediato
Tentativa 2: 2s
Tentativa 3: 4s
Tentativa 4: 8s
Tentativa 5: 16s → falha permanente, status = 'failed'
```

### 7.4 Infraestrutura recomendada

| Componente | Opção principal | Alternativa |
|------------|-----------------|-------------|
| Backend | Railway | AWS ECS + Fargate |
| PostgreSQL | Supabase | AWS RDS |
| Redis | Upstash Redis | AWS ElastiCache |
| Mídia (S3) | Cloudflare R2 | AWS S3 |
| Frontend | Vercel | Netlify |
| Monitoramento | Sentry + Datadog | New Relic |
| CI/CD | GitHub Actions | GitLab CI |

### 7.5 Variáveis de ambiente obrigatórias

```env
# WhatsApp Cloud API
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_APP_SECRET=
WHATSAPP_WEBHOOK_VERIFY_TOKEN=

# Banco de dados
DATABASE_URL=postgresql://user:pass@host:5432/dbname

# Redis
REDIS_URL=redis://user:pass@host:6379

# JWT
JWT_SECRET=
JWT_REFRESH_SECRET=

# Storage
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_S3_BUCKET=
AWS_REGION=

# Aplicação
APP_URL=https://app.seudominio.com
WEBHOOK_URL=https://api.seudominio.com/webhook
NODE_ENV=production
```

---

## 8. Estimativa de Esforço

| Fase | Descrição | Duração | Complexidade |
|------|-----------|---------|--------------|
| 1 | Integração com a Cloud API | 3 semanas | 🔴 Alta |
| 2 | Operadores e atribuição | 2 semanas | 🟡 Média |
| 3 | Interface e funcionalidades nativas | 4 semanas | 🔴 Alta |
| 4 | Supervisão e gestão | 2 semanas | 🟡 Média |
| 5 | Infraestrutura e produção | 2 semanas | 🟡 Média |
| **Total** | | **~13 semanas** | |

---

## 9. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Aprovação do número na Meta demorar | Alta | Alto | Iniciar processo na semana 1, em paralelo ao dev |
| Mudanças na política da API (Meta) | Média | Alto | Monitorar changelog; encapsular chamadas da API em serviço isolado |
| Volume de mensagens exceder tier atual | Média | Médio | Implementar fila e rate limiter desde o início |
| Operador offline com conversas abertas | Baixa | Médio | Worker de redistribuição automática ao detectar status offline |
| Perda de webhook event (timeout) | Baixa | Alto | Log em `webhook_events` + worker de reprocessamento com idempotência via `wa_message_id` |
| Janela de 24h expirar no meio do atendimento | Média | Médio | Alerta para o operador 1h antes; template de continuação pré-aprovado |

---

*Documento gerado em: 2026-03-17*  
*Versão: 1.0*

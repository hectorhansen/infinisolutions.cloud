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
  INDEX idx_pending   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  INDEX idx_cleanup         (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

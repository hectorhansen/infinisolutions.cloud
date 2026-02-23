-- =============================================================
--  NUCLEOFIX CHAT — Schema MySQL
--  Importar via phpMyAdmin: Banco → Importar → Selecionar arquivo
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0; 

-- ---------------------------------------------------------------
-- 1. USUÁRIOS (agentes e administradores)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(120)                                  NOT NULL,
  `email`      VARCHAR(180)                                  NOT NULL UNIQUE,
  `password`   VARCHAR(255)                                  NOT NULL,
  `role`       ENUM('admin','agent')                         NOT NULL DEFAULT 'agent',
  `status`     ENUM('online','offline','away')               NOT NULL DEFAULT 'offline',
  `avatar`     VARCHAR(500)                                  NULL,
  `created_at` DATETIME                                      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME                                      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin padrão (senha: nucleofix@2025 — troque após o primeiro login)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES
  ('Administrador', 'admin@nucleofix.cloud',
   '$2b$12$LKJhFKGkdHQKzABMnY6Y8OFPb5GcQQwOc7oSYGpRtJzKJhMJiJiNK', 'admin', 'offline');

-- ---------------------------------------------------------------
-- 2. CONTATOS (clientes/leads do WhatsApp)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contacts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `phone`       VARCHAR(30)   NOT NULL UNIQUE COMMENT 'Número no formato E.164 ex: 5511999999999',
  `name`        VARCHAR(150)  NULL COMMENT 'Nome salvo quando disponível',
  `avatar`      VARCHAR(500)  NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 3. CONVERSAS
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversations` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `contact_id`   INT UNSIGNED NOT NULL,
  `agent_id`     INT UNSIGNED NULL COMMENT 'NULL = aguardando atribuição',
  `status`       ENUM('waiting','open','closed','archived') NOT NULL DEFAULT 'waiting',
  `unread_count` SMALLINT UNSIGNED                          NOT NULL DEFAULT 0,
  `last_message` TEXT                                       NULL,
  `last_msg_at`  DATETIME                                   NULL,
  `created_at`   DATETIME                                   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME                                   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`agent_id`)   REFERENCES `users`(`id`)    ON DELETE SET NULL,
  INDEX `idx_agent_status` (`agent_id`, `status`),
  INDEX `idx_status`       (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 4. MENSAGENS
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT UNSIGNED                                          NOT NULL,
  `sender_type`     ENUM('contact','agent','system')                      NOT NULL,
  `sender_id`       INT UNSIGNED                                          NULL COMMENT 'US ID se agent, NULL se contact/system',
  `type`            ENUM('text','image','video','audio','document','sticker','location','template') NOT NULL DEFAULT 'text',
  `body`            TEXT                                                  NULL COMMENT 'Texto ou legenda',
  `media_url`       VARCHAR(1000)                                         NULL COMMENT 'URL pública ou path local',
  `media_path`      VARCHAR(1000)                                         NULL COMMENT 'Path físico no servidor',
  `media_mime`      VARCHAR(100)                                          NULL,
  `media_size`      INT UNSIGNED                                          NULL COMMENT 'Bytes',
  `media_duration`  SMALLINT UNSIGNED                                     NULL COMMENT 'Segundos (áudio/vídeo)',
  `wa_message_id`   VARCHAR(100)                                          NULL UNIQUE COMMENT 'ID da mensagem na API do WhatsApp',
  `status`          ENUM('pending','sent','delivered','read','failed')    NOT NULL DEFAULT 'sent',
  `created_at`      DATETIME                                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  INDEX `idx_conversation` (`conversation_id`),
  INDEX `idx_wa_id`        (`wa_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 5. ETIQUETAS (TAGS)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(80)  NOT NULL UNIQUE,
  `color`      VARCHAR(7)   NOT NULL DEFAULT '#10B981' COMMENT 'Hex color',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tags` (`name`, `color`) VALUES
  ('Aguardando Pagamento', '#F59E0B'),
  ('Suporte',              '#3B82F6'),
  ('Lead Quente',          '#EF4444'),
  ('Negócio Fechado',      '#10B981'),
  ('Sem Interesse',        '#6B7280');

-- ---------------------------------------------------------------
-- 6. CONVERSA × TAG (M:N)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversation_tags` (
  `conversation_id` INT UNSIGNED NOT NULL,
  `tag_id`          INT UNSIGNED NOT NULL,
  `assigned_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversation_id`, `tag_id`),
  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`)          REFERENCES `tags`(`id`)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 7. RESPOSTAS RÁPIDAS
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quick_replies` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shortcut`   VARCHAR(50)  NOT NULL UNIQUE COMMENT 'Ex: ola, obrigado, pagamento',
  `title`      VARCHAR(120) NOT NULL COMMENT 'Título exibido na lista',
  `body`       TEXT         NOT NULL COMMENT 'Texto completo da mensagem',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `quick_replies` (`shortcut`, `title`, `body`, `created_by`) VALUES
  ('ola',       'Saudação inicial',     'Olá! Tudo bem? Sou da equipe Nucleofix. Como posso te ajudar hoje? 😊', NULL),
  ('obrigado',  'Agradecimento',        'Obrigado pelo contato! Fico à disposição para qualquer dúvida. 👍', NULL),
  ('aguarda',   'Aguardar',             'Por favor, aguarde um momento enquanto verifico isso para você! ⏳', NULL),
  ('horario',   'Horário de atendimento','Nosso horário de atendimento é de segunda a sexta, das 08h às 18h. 🕗', NULL);

-- ---------------------------------------------------------------
-- 8. CONFIGURAÇÕES DO SISTEMA
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`      TEXT         NULL,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key`, `value`) VALUES
  ('whatsapp_phone_number_id', ''),
  ('whatsapp_token',           ''),
  ('whatsapp_verify_token',    'nucleofix_verify_2025'),
  ('whatsapp_phone_display',   ''),
  ('system_name',              'Nucleofix Chat');

-- ---------------------------------------------------------------
-- 9. TOKENS DE REFRESH (sessões JWT)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `token`      VARCHAR(512) NOT NULL UNIQUE,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_token` (`token`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

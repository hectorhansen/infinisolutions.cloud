-- ============================================================
-- InfiniCloud - Schema do Banco de Dados
-- Schema: u752688765_infinicloud
-- Criado em: 2026-03-18
-- ============================================================

CREATE DATABASE IF NOT EXISTS `u752688765_infinicloud`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `u752688765_infinicloud`;

-- ------------------------------------------------------------
-- Tabela: ic_users
-- Usuários autorizados a fazer upload de arquivos.
-- Gerenciados manualmente (sistema corporativo interno).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ic_users` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120)     NOT NULL,
    `email`      VARCHAR(180)     NOT NULL UNIQUE,
    `password`   VARCHAR(255)     NOT NULL COMMENT 'Hash bcrypt via password_hash()',
    `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: ic_files
-- Metadados de cada arquivo enviado ao sistema.
-- O arquivo físico fica em /storage/{user_id}/{stored_name}
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ic_files` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED     NOT NULL,
    `original_name` VARCHAR(255)     NOT NULL COMMENT 'Nome original do arquivo (para exibição)',
    `stored_name`   VARCHAR(255)     NOT NULL COMMENT 'Nome físico salvo no servidor (com hash para evitar colisões)',
    `mime_type`     VARCHAR(120)     NOT NULL,
    `size_bytes`    BIGINT UNSIGNED  NOT NULL DEFAULT 0,
    `deleted_at`    DATETIME         NULL DEFAULT NULL COMMENT 'Soft-delete: preenchido após expiração ou deleção manual',
    `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_files_user` FOREIGN KEY (`user_id`) REFERENCES `ic_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: ic_share_links
-- Links de compartilhamento com hash único e data de expiração.
-- URL pública: share.infinisolutions.cloud/{hash}
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ic_share_links` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `file_id`    INT UNSIGNED     NOT NULL,
    `user_id`    INT UNSIGNED     NOT NULL COMMENT 'Quem gerou o link',
    `hash`       CHAR(40)         NOT NULL COMMENT 'Hash único SHA1 do link',
    `expires_at` DATETIME         NOT NULL COMMENT 'Data/hora de expiração do link',
    `is_active`  TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '0 = revogado manualmente',
    `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_hash` (`hash`),
    INDEX `idx_file_id` (`file_id`),
    INDEX `idx_expires_at` (`expires_at`),
    CONSTRAINT `fk_links_file` FOREIGN KEY (`file_id`) REFERENCES `ic_files` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_links_user` FOREIGN KEY (`user_id`) REFERENCES `ic_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed: Usuário administrador padrão.
-- Senha: Admin@2026 (ALTERAR APÓS PRIMEIRO ACESSO!)
-- hash = password_hash('Admin@2026', PASSWORD_BCRYPT)
-- ------------------------------------------------------------
INSERT INTO `ic_users` (`name`, `email`, `password`) VALUES
(
    'Administrador',
    'admin@infinisolutions.cloud',
    '$2y$12$QkMIJrSDl3RiEGj6wBXoO.cJv7F5sYKJ6KuuvSSXFGVWIlr5Gn5v.'
);

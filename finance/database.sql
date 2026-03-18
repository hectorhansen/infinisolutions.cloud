-- ============================================================
-- Finance - Schema do Banco de Dados
-- Schema: u752688765_finance
-- ============================================================

CREATE DATABASE IF NOT EXISTS `u752688765_finance`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `u752688765_finance`;

-- ------------------------------------------------------------
-- Usuários (Acesso ao painel local)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_users` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(100)     NOT NULL UNIQUE,
    `password_hash` VARCHAR(255)     NOT NULL,
    `created_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Sócios / Origens de Pagamento
-- type: 'pj' (Conta/Cartão empresa) | 'pf' (Dinheiro pessoal do sócio)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `partners` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)     NOT NULL,
    `type`       ENUM('pj','pf')  NOT NULL,
    `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Projetos
-- split_a / split_b: Porcentagem de lucro de cada sócio (Sócio A = ID 1, Sócio B = ID 2)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150)     NOT NULL,
    `client`     VARCHAR(150)     NULL DEFAULT NULL,
    `status`     ENUM('open','closed') NOT NULL DEFAULT 'open',
    `split_a`    TINYINT UNSIGNED NOT NULL DEFAULT 50,
    `split_b`    TINYINT UNSIGNED NOT NULL DEFAULT 50,
    `notes`      TEXT             NULL,
    `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at`  TIMESTAMP        NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Categorias (para classificação de despesas)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)     NOT NULL,
    `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Lançamentos Financeiros (Receitas e Despesas)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `entries` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `project_id`  INT UNSIGNED     NOT NULL,
    `type`        ENUM('income','expense') NOT NULL,
    `description` VARCHAR(255)     NOT NULL,
    `amount`      DECIMAL(12,2)    NOT NULL,
    `paid_by`     INT UNSIGNED     NOT NULL COMMENT 'ID da origem (partner)',
    `category_id` INT UNSIGNED     NULL COMMENT 'Apenas para despesas',
    `entry_date`  DATE             NOT NULL,
    `notes`       TEXT             NULL,
    `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_project` (`project_id`),
    CONSTRAINT `fk_entry_project`  FOREIGN KEY (`project_id`)  REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_entry_partner`  FOREIGN KEY (`paid_by`)     REFERENCES `partners` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_entry_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED INICIAL DE DADOS
-- ============================================================

-- Usuário Admin (Senha padrão: Admin123!)
-- Hash bcrypt = $2y$12$RF/rL2l/Ypuhz3lpvArnSuDbIQ2aQ1vJIlxYMPeEM3IU.0qHUdNnO
INSERT INTO `finance_users` (`username`, `password_hash`) VALUES
('admin@infinisolutions.cloud', '$2y$12$RF/rL2l/Ypuhz3lpvArnSuDbIQ2aQ1vJIlxYMPeEM3IU.0qHUdNnO');

-- Parceiros/Origens default do sistema
INSERT INTO `partners` (`id`, `name`, `type`) VALUES
(1, 'Sócio A', 'pf'),
(2, 'Sócio B', 'pf'),
(3, 'Conta PJ', 'pj'),
(4, 'Cartão PJ', 'pj');

-- Categorias de despesa mais comuns
INSERT INTO `categories` (`name`) VALUES
('Ferramentas / Software'),
('Hospedagem / Servidor'),
('Freelancer / Terceiros'),
('Marketing / Anúncios'),
('Deslocamento / Alimentação'),
('Impostos / Taxas'),
('Outros');

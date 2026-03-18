-- ============================================
-- GRANFINO - Sistema de Gestão de Qualidade
-- schema.sql
-- ============================================

CREATE DATABASE IF NOT EXISTS granfino CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE granfino;

-- Atendentes
CREATE TABLE IF NOT EXISTS atendentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Chamadas
CREATE TABLE IF NOT EXISTS chamadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_chamada INT NOT NULL AUTO_INCREMENT,
    atendente_id INT,

    -- Perfil consumidor
    nome_consumidor VARCHAR(150),
    telefone VARCHAR(30),
    endereco VARCHAR(200),
    bairro VARCHAR(100),
    estado CHAR(2),
    municipio VARCHAR(100),
    ponto_referencia VARCHAR(200),

    -- Motivo
    motivo VARCHAR(100),
    descricao_geral TEXT,
    observacoes_gerais TEXT,
    horario_preferencial VARCHAR(50),

    -- Status
    status ENUM('aberta','em_andamento','fechada') DEFAULT 'aberta',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (atendente_id) REFERENCES atendentes(id) ON DELETE SET NULL
) AUTO_INCREMENT = 1;

-- Produtos da chamada
CREATE TABLE IF NOT EXISTS chamada_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chamada_id INT NOT NULL,
    produto VARCHAR(150),
    quantidade VARCHAR(100),
    lote VARCHAR(50),
    fabricacao DATE,
    validade DATE,
    local_compra VARCHAR(150),
    FOREIGN KEY (chamada_id) REFERENCES chamadas(id) ON DELETE CASCADE
);

-- Usuário admin padrão (senha: granfino2024)
INSERT IGNORE INTO atendentes (nome, email, senha) VALUES
('Administrador', 'admin@granfino.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

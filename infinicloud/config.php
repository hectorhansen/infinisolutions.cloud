<?php
// ============================================================
// InfiniCloud - Configurações do Módulo
// ============================================================

// Banco de dados independente do módulo InfiniCloud
// ATENÇÃO: Crie este schema no painel Hostinger antes do deploy
define('IC_DB_HOST', getenv('IC_DB_HOST') ?: 'localhost');
define('IC_DB_NAME', getenv('IC_DB_NAME') ?: 'u752688765_infinicloud');
define('IC_DB_USER', getenv('IC_DB_USER') ?: 'u752688765_infinicloud');
define('IC_DB_PASS', getenv('IC_DB_PASS') ?: 'lGVnoV?b?0A=');

// Diretório físico onde os arquivos são armazenados
define('IC_STORAGE_PATH', __DIR__ . '/storage/');

// URL base do subdomínio (sem barra no final)
define('IC_BASE_URL', 'https://share.infinisolutions.cloud');

// Chave secreta para proteger o cron de limpeza
define('IC_CRON_KEY', getenv('IC_CRON_KEY') ?: 'infinicloud_cron_2026!');

// Sessão
define('IC_SESSION_NAME', 'infinicloud_session');

// Tipos MIME permitidos para upload
define('IC_ALLOWED_MIME_TYPES', [
    // Imagens
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    // Documentos
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    // Arquivos compactados
    'application/zip',
    'application/x-zip-compressed',
    'application/x-rar-compressed',
    'application/x-7z-compressed',
    'application/gzip',
    // Texto
    'text/plain', 'text/csv',
    // Outros comuns
    'application/json', 'application/xml',
    'video/mp4', 'video/webm',
    'audio/mpeg', 'audio/wav',
]);

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

<?php
// ============================================================
// InfiniCloud - API de Download (Rota Pública)
// GET ?hash=XXX → Valida hash e faz streaming do arquivo
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

$hash = trim($_GET['hash'] ?? '');

if (!$hash || !preg_match('/^[a-f0-9]{40}$/', $hash)) {
    http_response_code(400);
    die('Link inválido.');
}

$db = ICDB::connect();

// Busca o link validando: hash existe + ativo + não expirado + arquivo não excluído
$stmt = $db->prepare('
    SELECT
        l.id,
        l.expires_at,
        f.original_name,
        f.stored_name,
        f.mime_type,
        f.size_bytes,
        f.user_id
    FROM ic_share_links l
    JOIN ic_files f ON f.id = l.file_id
    WHERE l.hash = ?
      AND l.is_active = 1
      AND l.expires_at >= NOW()
      AND f.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute([$hash]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(410);
    die('Link expirado ou não encontrado.');
}

// Caminho físico do arquivo
$filePath = IC_STORAGE_PATH . $record['user_id'] . '/' . $record['stored_name'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('Arquivo não encontrado no servidor.');
}

// Headers de download
$filename = rawurlencode($record['original_name']);
header('Content-Description: File Transfer');
header('Content-Type: ' . $record['mime_type']);
header('Content-Disposition: attachment; filename="' . $record['original_name'] . '"; filename*=UTF-8\'\'' . $filename);
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $record['size_bytes']);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Limpa buffers de saída para não corromper o binário
if (ob_get_level()) {
    ob_end_clean();
}

// Streaming do arquivo (eficiente para arquivos grandes)
readfile($filePath);
exit;

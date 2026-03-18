<?php
// ============================================================
// InfiniCloud - Cron Job de Limpeza
// Exclui físicamente arquivos cujos links expiraram.
// Agendar no cPanel: 0 3 * * * (todo dia às 03:00)
// Proteção: ?key=IC_CRON_KEY
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Proteção por chave
$key = trim($_GET['key'] ?? '');
if ($key !== IC_CRON_KEY) {
    http_response_code(403);
    die('Acesso negado.');
}

$db      = ICDB::connect();
$deleted = 0;
$errors  = [];

// Encontra todos os arquivos cujos links já expiraram ou foram revogados
// e que ainda não passaram pelo soft-delete
$stmt = $db->prepare('
    SELECT DISTINCT
        f.id        AS file_id,
        f.user_id,
        f.stored_name
    FROM ic_files f
    WHERE f.deleted_at IS NULL
      AND NOT EXISTS (
          -- Arquivo tem ao menos 1 link ativo e não expirado?
          SELECT 1 FROM ic_share_links l
          WHERE l.file_id = f.id
            AND l.is_active = 1
            AND l.expires_at >= NOW()
      )
      AND EXISTS (
          -- Arquivo tem ao menos 1 link (já expirado ou revogado)
          SELECT 1 FROM ic_share_links l
          WHERE l.file_id = f.id
      )
');
$stmt->execute();
$filesToDelete = $stmt->fetchAll();

foreach ($filesToDelete as $file) {
    $physicalPath = IC_STORAGE_PATH . $file['user_id'] . '/' . $file['stored_name'];

    // Remove arquivo físico
    if (file_exists($physicalPath)) {
        if (!unlink($physicalPath)) {
            $errors[] = "Falha ao remover: {$physicalPath}";
            continue;
        }
    }

    // Soft-delete no banco
    $db->prepare('UPDATE ic_files SET deleted_at = NOW() WHERE id = ?')->execute([$file['file_id']]);
    $db->prepare('UPDATE ic_share_links SET is_active = 0 WHERE file_id = ?')->execute([$file['file_id']]);

    $deleted++;
}

// Diretórios de usuários vazios (limpeza opcional)
$userDirs = glob(IC_STORAGE_PATH . '*/');
foreach ($userDirs as $dir) {
    if (is_dir($dir) && count(glob($dir . '*')) === 0) {
        @rmdir($dir);
    }
}

// Resposta
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'      => true,
    'deleted' => $deleted,
    'errors'  => $errors,
    'ran_at'  => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

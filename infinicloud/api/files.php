<?php
// ============================================================
// InfiniCloud - API de Listagem e Exclusão de Arquivos
// GET    → Lista arquivos do usuário logado
// DELETE ?id=X → Exclui arquivo (físico + banco)
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$user = ic_auth();
$db   = ICDB::connect();

// ----------------------------------------------------------
// GET: Lista todos os arquivos do usuário (não excluídos)
// ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('
        SELECT
            f.id,
            f.original_name,
            f.mime_type,
            f.size_bytes,
            f.created_at,
            COUNT(l.id)                    AS total_links,
            SUM(l.is_active = 1 AND l.expires_at > NOW()) AS active_links
        FROM ic_files f
        LEFT JOIN ic_share_links l ON l.file_id = f.id
        WHERE f.user_id = ? AND f.deleted_at IS NULL
        GROUP BY f.id
        ORDER BY f.created_at DESC
    ');
    $stmt->execute([$user['id']]);
    $files = $stmt->fetchAll();

    // Enriquece dados para o frontend
    foreach ($files as &$file) {
        $file['size_human']   = ic_format_size((int)$file['size_bytes']);
        $file['icon']         = ic_file_icon($file['mime_type']);
        $file['total_links']  = (int)$file['total_links'];
        $file['active_links'] = (int)$file['active_links'];
    }

    ic_json(['ok' => true, 'files' => $files]);
}

// ----------------------------------------------------------
// DELETE: Exclui arquivo do usuário
// ----------------------------------------------------------
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $fileId = (int)($_GET['id'] ?? 0);
    if (!$fileId) {
        ic_json(['error' => 'ID inválido.'], 422);
    }

    // Busca o arquivo garantindo que pertence ao usuário
    $stmt = $db->prepare('SELECT id, stored_name FROM ic_files WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$fileId, $user['id']]);
    $file = $stmt->fetch();

    if (!$file) {
        ic_json(['error' => 'Arquivo não encontrado.'], 404);
    }

    // Exclui arquivo físico
    $physicalPath = IC_STORAGE_PATH . $user['id'] . '/' . $file['stored_name'];
    if (file_exists($physicalPath)) {
        unlink($physicalPath);
    }

    // Soft-delete no banco e desativa todos os links
    $db->prepare('UPDATE ic_files SET deleted_at = NOW() WHERE id = ?')->execute([$fileId]);
    $db->prepare('UPDATE ic_share_links SET is_active = 0 WHERE file_id = ?')->execute([$fileId]);

    ic_json(['ok' => true]);
}

else {
    ic_json(['error' => 'Método não permitido.'], 405);
}

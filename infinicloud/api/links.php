<?php
// ============================================================
// InfiniCloud - API de Links de Compartilhamento
// POST   → Gera novo link para um arquivo
// GET    ?file_id=X → Lista links de um arquivo
// DELETE ?id=X     → Revoga link
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$user = ic_auth();
$db   = ICDB::connect();

// ----------------------------------------------------------
// POST: Cria novo link de compartilhamento
// Body: { file_id: int, expires_at: "YYYY-MM-DD" }
// ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true);
    $fileId    = (int)($body['file_id'] ?? 0);
    $expiresAt = trim($body['expires_at'] ?? '');

    if (!$fileId || !$expiresAt) {
        ic_json(['error' => 'file_id e expires_at são obrigatórios.'], 422);
    }

    // Valida data de expiração
    $expireDate = DateTime::createFromFormat('Y-m-d', $expiresAt);
    if (!$expireDate || $expireDate <= new DateTime('today')) {
        ic_json(['error' => 'A data de expiração deve ser uma data futura (YYYY-MM-DD).'], 422);
    }

    // Garante que o arquivo pertence ao usuário e não foi excluído
    $stmt = $db->prepare('SELECT id FROM ic_files WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$fileId, $user['id']]);
    if (!$stmt->fetch()) {
        ic_json(['error' => 'Arquivo não encontrado.'], 404);
    }

    // Gera hash único e garante colisão zero
    do {
        $hash = ic_generate_hash($fileId, $user['id']);
        $check = $db->prepare('SELECT id FROM ic_share_links WHERE hash = ? LIMIT 1');
        $check->execute([$hash]);
    } while ($check->fetch());

    // Persiste o link
    $expiresDatetime = $expireDate->format('Y-m-d') . ' 23:59:59';
    $stmt = $db->prepare('
        INSERT INTO ic_share_links (file_id, user_id, hash, expires_at)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$fileId, $user['id'], $hash, $expiresDatetime]);

    $linkId  = (int)$db->lastInsertId();
    $shareUrl = IC_BASE_URL . '/' . $hash;

    ic_json([
        'ok'   => true,
        'link' => [
            'id'         => $linkId,
            'hash'       => $hash,
            'url'        => $shareUrl,
            'expires_at' => $expiresDatetime,
        ],
    ], 201);
}

// ----------------------------------------------------------
// GET: Lista links de um arquivo
// ----------------------------------------------------------
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fileId = (int)($_GET['file_id'] ?? 0);
    if (!$fileId) {
        ic_json(['error' => 'file_id é obrigatório.'], 422);
    }

    $stmt = $db->prepare('
        SELECT l.id, l.hash, l.expires_at, l.is_active, l.created_at,
               CONCAT(?, l.hash) AS url,
               (l.is_active = 1 AND l.expires_at > NOW()) AS is_valid
        FROM ic_share_links l
        JOIN ic_files f ON f.id = l.file_id
        WHERE l.file_id = ? AND f.user_id = ?
        ORDER BY l.created_at DESC
    ');
    $stmt->execute([IC_BASE_URL . '/', $fileId, $user['id']]);
    $links = $stmt->fetchAll();

    foreach ($links as &$link) {
        $link['is_valid']   = (bool)(int)$link['is_valid'];
        $link['is_active']  = (bool)(int)$link['is_active'];
    }

    ic_json(['ok' => true, 'links' => $links]);
}

// ----------------------------------------------------------
// DELETE: Revoga link
// ----------------------------------------------------------
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $linkId = (int)($_GET['id'] ?? 0);
    if (!$linkId) {
        ic_json(['error' => 'ID inválido.'], 422);
    }

    // Garante que o link pertence ao usuário
    $stmt = $db->prepare('
        UPDATE ic_share_links l
        JOIN ic_files f ON f.id = l.file_id
        SET l.is_active = 0
        WHERE l.id = ? AND f.user_id = ?
    ');
    $stmt->execute([$linkId, $user['id']]);

    if ($stmt->rowCount() === 0) {
        ic_json(['error' => 'Link não encontrado.'], 404);
    }

    ic_json(['ok' => true]);
}

else {
    ic_json(['error' => 'Método não permitido.'], 405);
}

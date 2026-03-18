<?php
// ============================================================
// InfiniCloud - API de Upload de Arquivos
// POST multipart/form-data → Recebe e armazena arquivo
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

// Exige sessão autenticada
$user = ic_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ic_json(['error' => 'Método não permitido.'], 405);
}

if (empty($_FILES['file'])) {
    ic_json(['error' => 'Nenhum arquivo enviado.'], 422);
}

$file  = $_FILES['file'];
$error = $file['error'];

// Mapeia erros de upload do PHP
if ($error !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulário.',
        UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente.',
        UPLOAD_ERR_CANT_WRITE => 'Erro ao salvar o arquivo.',
    ];
    ic_json(['error' => $errors[$error] ?? 'Erro desconhecido no upload.'], 500);
}

// Valida MIME type real (não confiar no $_FILES['type'])
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$allowed = IC_ALLOWED_MIME_TYPES;
if (!in_array($mimeType, $allowed, true)) {
    ic_json(['error' => "Tipo de arquivo não permitido: {$mimeType}"], 422);
}

// Gera nome único para evitar colisões no disco
$ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext        = preg_replace('/[^a-zA-Z0-9]/', '', $ext); // sanitiza extensão
$storedName = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');

// Cria diretório do usuário se não existir
$userDir = IC_STORAGE_PATH . $user['id'] . '/';
if (!is_dir($userDir)) {
    mkdir($userDir, 0755, true);
}

$destination = $userDir . $storedName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    ic_json(['error' => 'Falha ao mover o arquivo. Verifique permissões da pasta storage/.'], 500);
}

// Persiste metadados no banco
$db   = ICDB::connect();
$stmt = $db->prepare('
    INSERT INTO ic_files (user_id, original_name, stored_name, mime_type, size_bytes)
    VALUES (?, ?, ?, ?, ?)
');
$stmt->execute([
    $user['id'],
    $file['name'],
    $storedName,
    $mimeType,
    $file['size'],
]);

$fileId = (int) $db->lastInsertId();

ic_json([
    'ok'   => true,
    'file' => [
        'id'            => $fileId,
        'original_name' => $file['name'],
        'mime_type'     => $mimeType,
        'size_bytes'    => $file['size'],
        'size_human'    => ic_format_size($file['size']),
        'icon'          => ic_file_icon($mimeType),
    ],
], 201);

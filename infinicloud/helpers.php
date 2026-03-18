<?php
// ============================================================
// InfiniCloud - Funções Auxiliares
// ============================================================

/**
 * Responde em JSON e encerra a execução.
 */
function ic_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verifica se existe uma sessão autenticada válida.
 * Encerra com 401 caso não autenticado (quando $die = true).
 */
function ic_auth(bool $die = true): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(IC_SESSION_NAME);
        session_start();
    }

    if (!empty($_SESSION['ic_user'])) {
        return $_SESSION['ic_user'];
    }

    if ($die) {
        ic_json(['error' => 'Não autorizado. Faça login para continuar.'], 401);
    }
    return null;
}

/**
 * Gera um hash SHA1 único de 40 caracteres para o link.
 */
function ic_generate_hash(int $file_id, int $user_id): string {
    return sha1($file_id . $user_id . microtime(true) . random_bytes(16));
}

/**
 * Formata tamanho em bytes para string legível (KB, MB, GB).
 */
function ic_format_size(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

/**
 * Retorna o ícone FontAwesome correspondente ao MIME type.
 */
function ic_file_icon(string $mime): string {
    if (str_starts_with($mime, 'image/'))       return 'fa-file-image';
    if ($mime === 'application/pdf')             return 'fa-file-pdf';
    if (str_contains($mime, 'word'))             return 'fa-file-word';
    if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return 'fa-file-excel';
    if (str_contains($mime, 'powerpoint') || str_contains($mime, 'presentation')) return 'fa-file-powerpoint';
    if (str_contains($mime, 'zip') || str_contains($mime, 'rar') || str_contains($mime, '7z') || str_contains($mime, 'gzip')) return 'fa-file-zipper';
    if (str_starts_with($mime, 'video/'))        return 'fa-file-video';
    if (str_starts_with($mime, 'audio/'))        return 'fa-file-audio';
    if (str_starts_with($mime, 'text/'))         return 'fa-file-lines';
    return 'fa-file';
}

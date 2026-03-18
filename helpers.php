<?php
// helpers.php - Funções utilitárias que podem ser usadas em toda aplicação

function sendJson(array $data, int $statusCode = 200): void {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonPayload(): array {
    $raw = file_get_contents('php://input');
    return $raw ? json_decode($raw, true) : [];
}

function sanitizeString(string $val): ?string {
    $trim = trim($val);
    return $trim === '' ? null : htmlspecialchars($trim);
}

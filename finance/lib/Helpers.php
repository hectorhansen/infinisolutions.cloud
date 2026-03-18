<?php
// ============================================================
// Finance - Helpers
// ============================================================

namespace Finance\Lib;

class Helpers {
    
    /**
     * Responde em JSON e encerra a execução.
     */
    public static function json_response(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Sanitiza strings recebidas no payload.
     */
    public static function sanitize(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Converte BRL formatado (ex: 1.234,56) para float (1234.56).
     */
    public static function brl_to_float($value): float {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $value = str_replace('.', '', $value); // Remove milhares
        $value = str_replace(',', '.', $value); // Vírgula para ponto
        return (float) $value;
    }
}

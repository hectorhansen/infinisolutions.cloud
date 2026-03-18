<?php
// ============================================================
// Finance - Banco de Dados Isolado e Autenticação Nativa
// ============================================================

namespace Finance\Lib;

use PDO;
use Exception;

class FinanceDB {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (!self::$instance) {
            $host = getenv('FINANCE_DB_HOST') ?: 'localhost';
            $name = getenv('FINANCE_DB_NAME') ?: 'u752688765_finance';
            $user = getenv('FINANCE_DB_USER') ?: 'u752688765_finance';
            $pass = getenv('FINANCE_DB_PASS') ?: 'Finance2026#'; // Ajuste conforme seu painel

            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }
}

class Auth {
    private const SESSION_NAME = 'finance_session';

    public static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(self::SESSION_NAME);
            session_start();
        }
    }

    public static function require_auth(): array {
        self::initSession();
        if (empty($_SESSION['finance_user'])) {
            Helpers::json_response(['success' => false, 'message' => 'Não autorizado.'], 401);
        }
        return $_SESSION['finance_user'];
    }

    public static function user(): ?array {
        self::initSession();
        return $_SESSION['finance_user'] ?? null;
    }

    public static function login(string $username, string $password): bool {
        self::initSession();
        try {
            $db = FinanceDB::connect();
            $stmt = $db->prepare("SELECT id, username, password_hash FROM finance_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['finance_user'] = [
                    'id'       => $user['id'],
                    'username' => $user['username']
                ];
                return true;
            }
        } catch (Exception $e) {
            error_log('Login Error: ' . $e->getMessage());
        }
        return false;
    }

    public static function logout(): void {
        self::initSession();
        $_SESSION = [];
        session_destroy();
    }
}

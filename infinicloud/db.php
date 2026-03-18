<?php
// ============================================================
// InfiniCloud - Singleton PDO
// ============================================================
class ICDB {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (!self::$instance) {
            $dsn = 'mysql:host=' . IC_DB_HOST . ';dbname=' . IC_DB_NAME . ';charset=utf8mb4';
            self::$instance = new PDO($dsn, IC_DB_USER, IC_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }
}

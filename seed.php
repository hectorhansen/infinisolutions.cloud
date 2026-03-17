<?php
/**
 * Script utilitário para criar o primeiro administrador do sistema
 * Execute apenas uma vez via terminal (php seed.php) ou acessando via browser
 * ATENÇÃO: Apague este arquivo após o uso por segurança.
 */

require_once 'config.php';
require_once 'db.php';

try {
    $pdo = DB::connect();

    // Verifica se já existe algum admin
    $stmt = $pdo->query("SELECT id FROM operators WHERE role = 'admin' LIMIT 1");
    if ($stmt->fetch()) {
        die("Já existe um administrador no sistema. Abortando seed por segurança.\n");
    }

    $name = 'Admin Master';
    $email = 'admin@infinisolutions.cloud';
    $password = 'Admin123!'; // Troque imediatamente após logar
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'admin';
    $maxConc = 50;

    $stmt = $pdo->prepare("
        INSERT INTO operators (name, email, password_hash, role, max_concurrent, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$name, $email, $hash, $role, $maxConc]);

    echo "✅ Administrador criado com sucesso!\n";
    echo "====================================\n";
    echo "E-mail: $email\n";
    echo "Senha:  $password\n";
    echo "====================================\n";
    echo "Acesse /public/index.html para fazer login.\n";
    echo "⚠️ Mude sua senha no painel e apague este script (seed.php) do servidor.\n";

} catch (Exception $e) {
    die("❌ Erro ao criar admin: " . $e->getMessage() . "\n");
}

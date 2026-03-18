<?php
require_once __DIR__ . '/lib/Auth.php';
use Finance\Lib\FinanceDB;

try {
    $db = FinanceDB::connect();
    $username = 'admin@infinisolutions.cloud';
    $new_password = 'Admin123!';
    
    // Gera o hash nativamente no próprio servidor Hostinger para evitar problemas de charset do SQL
    $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Verifica se o usuário existe, se sim atualiza, se não insere
    $check = $db->prepare("SELECT id FROM finance_users WHERE username = ?");
    $check->execute([$username]);
    
    if ($check->rowCount() > 0) {
        $stmt = $db->prepare("UPDATE finance_users SET password_hash = ? WHERE username = ?");
        $stmt->execute([$hash, $username]);
        echo "<h2>Senha do $username atualizada com sucesso!</h2>";
    } else {
        $stmt = $db->prepare("INSERT INTO finance_users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        echo "<h2>Usuário $username criado com sucesso com a nova senha!</h2>";
    }
    
    echo "<p>Sua nova senha é: <strong>$new_password</strong></p>";
    echo "<p>Agora você pode acessar o <a href='https://finance.infinisolutions.cloud/'>login</a>.</p>";
    echo "<p style='color:red;'>⚠️ ATENÇÃO: Por segurança, exclua o arquivo <code>reset_password.php</code> após testar o login.</p>";
    
} catch (Exception $e) {
    echo "<h2>Erro ao atualizar a senha:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}

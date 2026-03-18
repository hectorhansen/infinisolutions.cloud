<?php
// ============================================================
// InfiniCloud - Reset de Senha do Admin
// Acesse via: share.infinisolutions.cloud/reset_admin.php
// ATENÇÃO: APAGUE ESTE ARQUIVO APÓS USAR!
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$newPassword = 'Admin@2026';
$hash        = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $db = ICDB::connect();

    // Verifica se o usuário já existe
    $check = $db->query("SELECT COUNT(*) FROM ic_users")->fetchColumn();

    if ((int)$check === 0) {
        // Cria o admin do zero se a tabela estiver vazia
        $stmt = $db->prepare("INSERT INTO ic_users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute(['Administrador', 'admin@infinisolutions.cloud', $hash]);
        $action = 'Usuário admin criado';
    } else {
        // Atualiza o hash do admin existente
        $stmt = $db->prepare("UPDATE ic_users SET password = ? WHERE email = ?");
        $stmt->execute([$hash, 'admin@infinisolutions.cloud']);
        $action = 'Senha atualizada';
    }

    echo "<pre style='font-family:monospace;background:#0d0f17;color:#10b981;padding:24px;border-radius:8px;'>";
    echo "✅ {$action} com sucesso!\n\n";
    echo "E-mail : admin@infinisolutions.cloud\n";
    echo "Senha  : {$newPassword}\n\n";
    echo "Hash gerado:\n{$hash}\n\n";
    echo "⚠️  APAGUE ESTE ARQUIVO IMEDIATAMENTE!\n";
    echo "    rm reset_admin.php\n";
    echo "</pre>";

} catch (Exception $e) {
    echo "<pre style='color:#ef4444;'>Erro: " . $e->getMessage() . "</pre>";
}

<?php
require_once __DIR__ . '/lib/Auth.php';
use Finance\Lib\FinanceDB;

try {
    $db = FinanceDB::connect();
    
    // Atualiza os parceiros de forma definitiva e direta no cloud
    $db->exec("UPDATE partners SET name = 'André' WHERE id = 1");
    $db->exec("UPDATE partners SET name = 'Hector' WHERE id = 2");
    
    echo "<h2>Nomes atualizados com sucesso no Banco de Dados Live! Sócio A agora é André e Sócio B agora é Hector.</h2>";
    echo "<p><a href='https://finance.infinisolutions.cloud/'>Voltar ao sistema</a></p>";
    echo "<br><p style='color:red;'>⚠️ ATENÇÃO: Por segurança, exclua o arquivo <code>update_names.php</code> após testar.</p>";

} catch (Exception $e) {
    echo "<h2>Erro ao atualizar:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}

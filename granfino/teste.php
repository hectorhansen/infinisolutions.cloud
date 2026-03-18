<?php
require 'config.php';
try {
    $pdo = db();
    echo 'Conexão OK!';
} catch (Exception $e) {
    echo 'Erro: ' . $e->getMessage();
}
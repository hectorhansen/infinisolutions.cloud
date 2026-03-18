<?php
require 'config.php';
require 'db.php';
try {
    DB::connect();
    echo "DB_OK\n";
} catch (Exception $e) {
    echo "DB_ERROR: " . $e->getMessage() . "\n";
}

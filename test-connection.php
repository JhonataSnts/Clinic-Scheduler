<?php

use App\Core\Config;
use App\Core\Database;

require_once __DIR__ . '/vendor/autoload.php';

$config = new Config(__DIR__ . '/config');
$database = new Database($config);
$pdo = $database->connection();

echo "Conexão com o banco de dados estabelecida com sucesso!" . PHP_EOL;
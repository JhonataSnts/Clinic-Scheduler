<?php

require __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\Response;

$router = new Router();

$router->get('/', function () {
    return new Response('Home');
});

echo "Rota GET registrada com sucesso!" . PHP_EOL;
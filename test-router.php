<?php

require __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\Response;
use App\Core\Request;

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/customers';

$request = new Request();
$router = new Router();

$router->get('/', function () {
    return new Response('Home');
});

$router->post('/customers', function () {
    return new Response('Cliente cadastrado');
});


$response = $router->dispatch($request);

$response->send();


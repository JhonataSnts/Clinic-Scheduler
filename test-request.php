<?php

use App\Core\Request;

require __DIR__ . '/vendor/autoload.php';

$request = new Request();

echo $request->method() . PHP_EOL;
echo $request->path() . PHP_EOL;
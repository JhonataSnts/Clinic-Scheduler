<?php

namespace App\Core;

class Router
{   
    // Guarda as rotas registradas.
    private array $routes = [];

    // Registra uma rota GET.
    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }
}

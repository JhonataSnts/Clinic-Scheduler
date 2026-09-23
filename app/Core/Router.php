<?php

namespace App\Core;

class Router
{   
    // Guarda as rotas registradas.
    private array $routes = [];

    // Registra uma rota GET.
    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    // Armazena uma rota usando o metodo HTTP informado.
    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }

    //Registrar uma rota POST.
    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    // Dispara a rota correspondente à requisição.
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        if (isset($this->routes[$method][$path])) {
            $handler = $this->routes[$method][$path];
            return $handler();
        }

        return new Response('Rota não encontrada', 404);
    }
}

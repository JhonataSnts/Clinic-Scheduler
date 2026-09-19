<?php

namespace App\Core;

class Request
{
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }

        return $_GET[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }

        return $_POST[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query(), $this->input());
    }
}

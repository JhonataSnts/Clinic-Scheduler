<?php

namespace App\Core;

class Config
{
    private array $items = [];

    public function __construct(private string $configPath)
    {
        $this->load();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->items;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }

            $value = $value[$part];
        }

        return $value;
    }

    private function load(): void
    {
        $files = glob($this->configPath . '/*.php');

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $this->items[$name] = require $file;
        }
    }
}

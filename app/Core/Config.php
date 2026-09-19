<?php

namespace App\Core;

class Config
{
    // Guarda todas as configuracoes carregadas da pasta config/.
    private array $items = [];

    // Recebe o caminho da pasta config/ e carrega os arquivos automaticamente.
    public function __construct(private string $configPath)
    {
        $this->load();
    }

    // Busca um valor de configuracao usando notacao com ponto.
    // Exemplo: database.host acessa $items['database']['host'].
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

    // Carrega todos os arquivos .php da pasta config/.
    // Exemplo: app.php vira a chave 'app', database.php vira a chave 'database'.
    private function load(): void
    {
        $files = glob($this->configPath . '/*.php');

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $this->items[$name] = require $file;
        }
    }
}

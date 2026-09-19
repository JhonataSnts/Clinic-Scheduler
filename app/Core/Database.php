<?php

namespace App\Core;

use PDO;

class Database
{
    public function __construct(private Config $config)
    {
    }

    public function connection(): PDO
    {
        //ler os dados da config
        $host = $this->config->get('database.host');
        $database = $this->config->get('database.database');
        $charset = $this->config->get('database.charset');

        //montar o dsn
        $dsn = "mysql:host=$host;dbname=$database;charset=$charset";

        //ler usuário, senha e opções
        $username = $this->config->get('database.username');
        $password = $this->config->get('database.password');
        $options = $this->config->get('database.options', []);

        return new PDO($dsn, $username, $password, $options);
    }
}
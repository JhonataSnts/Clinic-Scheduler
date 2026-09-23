<?php

namespace App\Core;

class Session
{
    public function __construct(private Config $config)
    {
        
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name = $this->config->get('app.session.name', 'clinic_scheduler_session');
        session_name($name);
        session_start();
    }
}

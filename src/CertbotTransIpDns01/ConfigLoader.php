<?php

namespace RoyBongers\CertbotTransIpDns01;

use RuntimeException;

class ConfigLoader
{
    private $config = [];

    private $requires = [
        'login',
        'private_key',
    ];

    public function __construct()
    {
        if (file_exists(APP_ROOT . '/config/transip.php')) {
            $this->config = include(APP_ROOT . '/config/transip.php');
        }

        foreach ($this->requires as $required) {
            if ($this->get($required) === null) {
                throw new RuntimeException(sprintf("Config option '%s' not found", $required));
            }
        }
    }

    public function get(string $key, $default = null): ?string
    {
        return $this->config[$key] ?? getenv(strtoupper($key)) ?? $default;
    }
}
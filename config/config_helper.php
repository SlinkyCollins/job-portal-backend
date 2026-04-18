<?php

if (!function_exists('appConfig')) {
    function appConfig(string $key, $default = null)
    {
        static $config = null;

        if ($config === null) {
            $config = require __DIR__ . '/app.php';
        }

        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

<?php

$dotenv = Dotenv\Dotenv::createImmutable(
    dirname(__DIR__),
    '.env.testing'
);

$dotenv->load();

require_once __DIR__ . '/Integration/BaseTestCase.php';
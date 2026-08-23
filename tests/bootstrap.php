<?php

$dotenv = Dotenv\Dotenv::createImmutable(
    dirname(__DIR__),
    '.env.testing'
);

$dotenv->load();
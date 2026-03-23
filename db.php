<?php
$env = parse_ini_file(__DIR__ . '/.env');
$db = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME'),
    getenv('DB_PORT')
)

?>
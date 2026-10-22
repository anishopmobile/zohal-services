<?php
// Development router: never serve files outside public/ or hidden files.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$resolved = is_string($path) ? realpath(__DIR__.$path) : false;
if ($resolved && str_starts_with($resolved, __DIR__.'/assets/') && is_file($resolved)) return false;
require __DIR__.'/index.php';

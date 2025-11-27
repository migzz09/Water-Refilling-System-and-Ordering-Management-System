<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dotenv\Dotenv;


$projectRoot = dirname(__DIR__, 2); // This should resolve to C:/xampp/htdocs/WRSOMS
$dotenv = Dotenv::createImmutable($projectRoot);
$dotenv->load();

define('PAYMONGO_SECRET_KEY', $_ENV['PAYMONGO_SECRET_KEY'] ?? null);
define('PAYMONGO_PUBLIC_KEY', $_ENV['PAYMONGO_PUBLIC_KEY'] ?? null);
define('SUCCESS_URL', $_ENV['SUCCESS_URL'] ?? null);
define('FAILED_URL', $_ENV['FAILED_URL'] ?? null);
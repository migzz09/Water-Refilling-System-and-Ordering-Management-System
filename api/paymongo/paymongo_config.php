<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

define('PAYMONGO_SECRET_KEY', getenv('PAYMONGO_SECRET_KEY'));
define('PAYMONGO_PUBLIC_KEY', getenv('PAYMONGO_PUBLIC_KEY'));
define('SUCCESS_URL', getenv('SUCCESS_URL'));
define('FAILED_URL', getenv('FAILED_URL'));
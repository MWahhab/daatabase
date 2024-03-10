<?php

use database\Database;

putenv("DB_HOST=localhost");
putenv("DB_NAME=*");
putenv("DB_USER=*");
putenv("DB_PASS=*");

require_once ("Database.php");
require_once ("C:\\xampp\htdocs\weatherForecast\ForecastController.php");

try {
    $connection = new Database();
} catch(Exception $e) {
    die($e->getMessage());
}

$controller = new \weatherForecast\ForecastController($connection);